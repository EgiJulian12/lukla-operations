<?php

namespace App\Services\ClientBot;

use App\Helpers\MarkdownToText;
use App\Models\User;
use Aws\BedrockRuntime\BedrockRuntimeClient;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Agent AI Bedrock untuk client bot: memilih tool data klien sendiri
 * lewat Converse tool-use, menjawab pertanyaan operasional tanpa perintah.
 */
class BedrockClientBotService
{
    private const SYSTEM_PROMPT = <<<'PROMPT'
Anda adalah "Asisten AI Lukla", asisten operasional untuk KLIEN maklon dari Lukla Operations (platform manufaktur garmen). Klien bertanya lewat Telegram tentang aktivitas mereka sendiri.

SUMBER DATA:
1. Database operasional milik klien yang bertanya: order, status produksi, catatan output jahit per interval, stok/bahan, dan rate card. SEMUA data hanya boleh diambil lewat tools yang tersedia; tools otomatis membatasi data pada klien yang bertanya.
2. Dokumen SOP dan proses produksi via tool search_knowledge_base — untuk pertanyaan konsep seperti "apa itu CMT", "bagaimana alur sampel", atau pengertian jenis jasa.

ATURAN:
- Untuk pertanyaan data (status, jumlah, stok, harga) WAJIB memanggil tool yang sesuai dulu; jangan menebak atau mengarang angka. Jika hasil tool found = false, katakan datanya belum ada — jangan mengarang.
- Jawab dalam bahasa yang dipakai klien; default bahasa Indonesia, profesional dan ramah.
- Ringkas: satu jawaban tidak lebih dari ~150 kata. Gunakan bullet (•) untuk daftar.
- Format polos untuk Telegram: TANPA markdown (**, __, tabel, header). Jawaban akan dipostproses dari markdown, tapi tetap tulis sederhana.
- Sebutkan nomor order yang relevan (contoh: ORD-2025-001) agar klien bisa pakai /status <nomor>.

TOOL YANG TERSEDIA:
- get_orders: daftar order terakhir klien (opsional filter status).
- get_order_status: detail satu order berdasarkan nomor atau ID.
- get_inventory_items: stok/bahan klien beserta kondisi dan lokasi.
- get_output_entries: catatan produksi terkini (hasil jahit per interval).
- get_rate_cards: rate card yang sudah disetujui (harga per jasa).
- search_knowledge_base: pertanyaan konseptual / proses / SOP.
PROMPT;

    private BedrockRuntimeClient $bedrock;

    private string $modelId;

    private int $maxTokens;

    private float $temperature;

    public function __construct(
        private readonly ClientDataService $dataService,
    ) {
        $cfg = config('services.bedrock');
        $clientCfg = config('services.client_bot');

        $this->modelId = $clientCfg['model_id'] ?? $cfg['model_id'] ?? 'amazon.nova-lite-v1:0';
        $this->maxTokens = (int) ($clientCfg['max_tokens'] ?? 1500);
        $this->temperature = (float) ($clientCfg['temperature'] ?? 0.2);

        $this->bedrock = new BedrockRuntimeClient([
            'version' => 'latest',
            'region' => $cfg['bedrock_region'] ?? 'us-east-1',
            'credentials' => [
                'key' => $cfg['key'] ?? '',
                'secret' => $cfg['secret'] ?? '',
            ],
            'http' => [
                'verify' => false,
                'timeout' => 15,
            ],
        ]);
    }

    public function handleMessage(User $user, string $message): string
    {
        $cleanMessage = trim($message);

        try {
            return $this->invokeBedrockWithTools($user, $cleanMessage);
        } catch (Throwable $e) {
            Log::warning('BedrockClientBotService: Bedrock tidak tersedia, memakai fallback.', [
                'error' => $e->getMessage(),
                'client_id' => $user->client_id,
            ]);

            return $this->fallbackProcess();
        }
    }

    private function invokeBedrockWithTools(User $user, string $query): string
    {
        $messages = [
            [
                'role' => 'user',
                'content' => [['text' => "(Pertanyaan dari klien {$user->name})\n\n".$query]],
            ],
        ];

        for ($i = 0; $i < 3; $i++) {
            $response = $this->bedrock->converse([
                'modelId' => $this->modelId,
                'messages' => $messages,
                'system' => [
                    ['text' => self::SYSTEM_PROMPT],
                ],
                'inferenceConfig' => [
                    'maxTokens' => $this->maxTokens,
                    'temperature' => $this->temperature,
                ],
                'toolConfig' => [
                    'tools' => $this->getToolDefinitions(),
                ],
            ]);

            $stopReason = $response['stopReason'] ?? 'end_turn';
            $messageOutput = $response['output']['message'] ?? [];

            // Input tool dari SDK tiba sebagai array PHP; array kosong
            // diserialisasi ulang menjadi JSON list, bukan object, dan
            // Converse menolak feedback toolUse-nya. Ubah ke stdClass agar
            // tetap menjadi JSON object.
            $content = [];
            foreach ($messageOutput['content'] ?? [] as $block) {
                if (isset($block['toolUse']) && ($block['toolUse']['input'] ?? []) === []) {
                    $block['toolUse']['input'] = new \stdClass;
                }
                $content[] = $block;
            }
            $messageOutput['content'] = $content;

            $messages[] = $messageOutput;

            if ($stopReason === 'tool_use') {
                $toolResults = [];

                foreach ($messageOutput['content'] as $contentBlock) {
                    if (! isset($contentBlock['toolUse'])) {
                        continue;
                    }

                    $toolUse = $contentBlock['toolUse'];
                    $toolResults[] = [
                        'toolResult' => [
                            'toolUseId' => $toolUse['toolUseId'],
                            'content' => [
                                ['json' => $this->executeTool($user, $toolUse['name'], (array) ($toolUse['input'] ?? []))],
                            ],
                            'status' => 'success',
                        ],
                    ];
                }

                $messages[] = ['role' => 'user', 'content' => $toolResults];

                continue;
            }

            $replyText = '';
            foreach ($messageOutput['content'] ?? [] as $contentBlock) {
                if (isset($contentBlock['text'])) {
                    $replyText .= $contentBlock['text'];
                }
            }

            $replyText = trim($replyText);

            if ($replyText === '') {
                break;
            }

            return $this->cleanReply($replyText);
        }

        return 'Maaf, pengambilan data memakan waktu terlalu lama. Coba pertanyaan yang lebih spesifik, atau pakai /status <nomor order>.';
    }

    private function executeTool(User $user, string $toolName, array $input): array
    {
        $clientId = (int) $user->client_id;

        return match ($toolName) {
            'get_orders' => $this->dataService->getOrders($clientId, $input),
            'get_order_status' => $this->dataService->getOrderStatus($clientId, (string) ($input['identifier'] ?? '')),
            'get_inventory_items' => $this->dataService->getInventoryItems($clientId, $input),
            'get_output_entries' => $this->dataService->getOutputEntries($clientId, $input),
            'get_rate_cards' => $this->dataService->getRateCards($clientId, $input),
            'search_knowledge_base' => $this->dataService->searchKnowledgeBase((string) ($input['query'] ?? '')),
            default => ['error' => "Tool tidak dikenal: {$toolName}"],
        };
    }

    private function fallbackProcess(): string
    {
        return "Maaf, asisten AI sedang gangguan. Silakan coba lagi beberapa saat, atau pakai perintah:\n\n"
            ."/orders — ringkasan order Anda\n"
            .'/status <nomor> — detail satu order';
    }

    /**
     * Jawaban model dari Nova kadang masih membawa markdown; haluskan
     * untuk teks Telegram polos dan buang tag reasoning internal.
     */
    private function cleanReply(string $reply): string
    {
        $reply = preg_replace('/<thinking>.*?<\/thinking>/is', '', $reply);
        $reply = preg_replace('/<thinking>.*$/is', '', (string) $reply);

        return trim(MarkdownToText::convert((string) $reply));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function getToolDefinitions(): array
    {
        return [
            [
                'toolSpec' => [
                    'name' => 'get_orders',
                    'description' => 'Daftar order milik klien yang bertanya, terbaru dulu (maksimal 10). Untuk pertanyaan umum tentang "order saya", "pesanan saya".',
                    'inputSchema' => [
                        'json' => [
                            'type' => 'object',
                            'properties' => [
                                'status' => [
                                    'type' => 'string',
                                    'description' => 'Filter status order, misal: in_production, completed, cancelled. Kosongkan jika tidak relevan.',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'toolSpec' => [
                    'name' => 'get_order_status',
                    'description' => 'Detail satu order: status, progres produksi, jumlah, dan target selesai. Untuk pertanyaan seperti "status order saya" atau "order ORD-2025-001 udah di mana".',
                    'inputSchema' => [
                        'json' => [
                            'type' => 'object',
                            'properties' => [
                                'identifier' => [
                                    'type' => 'string',
                                    'description' => 'Nomor order (contoh: ORD-2025-001) atau ID angka.',
                                ],
                            ],
                            'required' => ['identifier'],
                        ],
                    ],
                ],
            ],
            [
                'toolSpec' => [
                    'name' => 'get_inventory_items',
                    'description' => 'Stok/bahan milik klien: nama material, batch, kondisi, jumlah, dan lokasi. Untuk pertanyaan seperti "stock kain saya" atau "bahan saya di mana".',
                    'inputSchema' => [
                        'json' => [
                            'type' => 'object',
                            'properties' => [
                                'state' => [
                                    'type' => 'string',
                                    'description' => 'Filter kondisi stok (raw, wip, finished). Kosongkan untuk semua.',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'toolSpec' => [
                    'name' => 'get_output_entries',
                    'description' => 'Catatan hasil produksi (output jahit per interval) milik klien: komponen, jumlah, garis produksi. Untuk pertanyaan seperti "progres jahit saya" atau "produksi saya dibanding apa".',
                    'inputSchema' => [
                        'json' => [
                            'type' => 'object',
                            'properties' => [
                                'order_number' => [
                                    'type' => 'string',
                                    'description' => 'Nomor order untuk filter output, misal ORD-2025-001.',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'toolSpec' => [
                    'name' => 'get_rate_cards',
                    'description' => 'Rate card klien yang sudah disetujui: harga per jasa (sewing, cutting, dll). Untuk pertanyaan seperti "biaya jahitnya berapa" atau "rate untuk artikel ini".',
                    'inputSchema' => [
                        'json' => [
                            'type' => 'object',
                            'properties' => [
                                'rate_type' => [
                                    'type' => 'string',
                                    'description' => 'Jenis tarif, misal: sewing, cutting, finishing. Kosongkan untuk semua.',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'toolSpec' => [
                    'name' => 'search_knowledge_base',
                    'description' => 'Cari di dokumen SOP dan aturan bisnis Lukla — definisi skema maklon (CMT, Semi-CMT, Full Package), alur sampel, proses QC. Untuk pertanyaan konseptual yang bukan data milik klien.',
                    'inputSchema' => [
                        'json' => [
                            'type' => 'object',
                            'properties' => [
                                'query' => [
                                    'type' => 'string',
                                    'description' => 'Topik atau kata kunci yang ingin dicari, misal: "apa itu CMT", "alur sample".',
                                ],
                            ],
                            'required' => ['query'],
                        ],
                    ],
                ],
            ],
        ];
    }
}
