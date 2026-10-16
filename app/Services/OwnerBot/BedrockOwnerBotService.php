<?php

namespace App\Services\OwnerBot;

use Aws\BedrockRuntime\BedrockRuntimeClient;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Bedrock-powered conversational AI agent with Database Tool Use
 * tailored specifically for the Owner Executive role.
 */
class BedrockOwnerBotService
{
    private const SYSTEM_PROMPT = <<<'PROMPT'
Anda adalah "Lukla Owner Executive AI Assistant", asisten kecerdasan buatan berbasis Amazon Bedrock untuk Pemilik / Owner dari Lukla Operations Engine (platform manufaktur garmen).

SUMBER DATA & PENGETAHUAN ANDA:
1. Database Operasional Pabrik: data real-time stok barang/material, lokasi meja potong/gudang, status pengerjaan, tagihan & invoice klien, dan progres order massal.
2. RAG Knowledge Base (Dokumen S3 & SRS/SOP Pabrik): dokumen kebutuhan bisnis, SOP pabrik, arsitektur sistem, definisi model maklon (seperti CMT, Semi-CMT, Full Package), problem statements, dan aturan operasional.

TUGAS UTAMA ANDA:
1. Menjawab pertanyaan Owner mengenai operasional pabrik, keuangan, dan inventaris tanpa perlu membuka dashboard web.
2. Melacak status barang/material (sedang di cutting, di gudang, atau proses lainnya).
3. Memberikan informasi tagihan dan hutang klien (berapa yang harus dibayar klien, invoice jatuh tempo, riwayat lunas).
4. Menjawab pertanyaan konseptual, definisi (misalnya "apa itu CMT"), SOP, dan problem statement Lukla menggunakan tool `search_knowledge_base`.

PEDOMAN FORMAT JAWABAN TELEGRAM:
- Jawablah dalam bahasa Indonesia yang profesional, ramah, dan ringkas.
- Gunakan format teks yang nyaman dibaca di Telegram: gunakan bullet (•), baris kosong antar paragraf, dan cetak tebal / penomoran untuk angka rupiah dan status penting.
- PENTING: Jawab langsung ke intinya, JANGAN pernah menampilkan tag internal seperti <thinking>...</thinking> kepada pengguna.
- Gunakan tools yang tersedia untuk mengambil data akurat dari database atau dokumen RAG sebelum membuat kesimpulan.
PROMPT;

    private BedrockRuntimeClient $bedrock;

    private string $modelId;

    private int $maxTokens;

    private float $temperature;

    public function __construct(
        private readonly OwnerDataService $dataService
    ) {
        $cfg = config('services.bedrock');
        $ownerCfg = config('services.owner_bot');

        $this->modelId = $ownerCfg['model_id'] ?? $cfg['model_id'] ?? 'amazon.nova-lite-v1:0';
        $this->maxTokens = (int) ($ownerCfg['max_tokens'] ?? 1500);
        $this->temperature = (float) ($ownerCfg['temperature'] ?? 0.1);

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

    /**
     * Process message from Owner Telegram Bot.
     *
     * @return array{
     *     reply: string,
     *     intent: string,
     *     tools_used: list<string>,
     *     data: array<string, mixed>|null,
     *     source: 'bedrock'|'deterministic_fallback'
     * }
     */
    public function handleMessage(string $message, ?string $chatId = null): array
    {
        $cleanMessage = trim($message);

        try {
            return $this->invokeBedrockWithTools($cleanMessage);
        } catch (Throwable $e) {
            Log::warning('BedrockOwnerBotService: Bedrock call failed or unavailable, using deterministic fallback.', [
                'error' => $e->getMessage(),
                'query' => $cleanMessage,
            ]);

            return $this->fallbackProcess($cleanMessage);
        }
    }

    /**
     * Run Bedrock Converse API with tool definitions.
     *
     * @return array{
     *     reply: string,
     *     intent: string,
     *     tools_used: list<string>,
     *     data: array<string, mixed>|null,
     *     source: 'bedrock'
     * }
     */
    private function invokeBedrockWithTools(string $query): array
    {
        $tools = $this->getToolDefinitions();
        $messages = [
            [
                'role' => 'user',
                'content' => [['text' => $query]],
            ],
        ];

        $toolsUsed = [];
        $collectedData = [];
        $primaryIntent = 'general_inquiry';

        // Maximum 3 conversational iterations for tool calling
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
                    'tools' => $tools,
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

            // Add assistant response to messages history
            $messages[] = $messageOutput;

            if ($stopReason === 'tool_use') {
                $toolResults = [];

                foreach ($messageOutput['content'] as $contentBlock) {
                    if (isset($contentBlock['toolUse'])) {
                        $toolUse = $contentBlock['toolUse'];
                        $toolName = $toolUse['name'];
                        $toolInput = (array) ($toolUse['input'] ?? []);
                        $toolUseId = $toolUse['toolUseId'];

                        $toolsUsed[] = $toolName;
                        $primaryIntent = $toolName;

                        $toolData = $this->executeTool($toolName, $toolInput);
                        $collectedData[$toolName] = $toolData;

                        $toolResults[] = [
                            'toolResult' => [
                                'toolUseId' => $toolUseId,
                                'content' => [
                                    ['json' => $toolData],
                                ],
                                'status' => 'success',
                            ],
                        ];
                    }
                }

                // Feed tool results back to Bedrock
                $messages[] = [
                    'role' => 'user',
                    'content' => $toolResults,
                ];

                continue;
            }

            // End turn reached
            $replyText = '';
            foreach ($messageOutput['content'] ?? [] as $contentBlock) {
                if (isset($contentBlock['text'])) {
                    $replyText .= $contentBlock['text'];
                }
            }

            // Strip internal <thinking> tags produced by reasoning models
            $cleanReply = preg_replace('/<thinking>.*?<\/thinking>/is', '', $replyText);
            $cleanReply = preg_replace('/<thinking>.*$/is', '', (string) $cleanReply);
            $cleanReply = trim((string) $cleanReply);

            if ($cleanReply === '') {
                $cleanReply = trim($replyText);
            }

            return [
                'reply' => $cleanReply,
                'intent' => $primaryIntent,
                'tools_used' => array_values(array_unique($toolsUsed)),
                'data' => ! empty($collectedData) ? $collectedData : null,
                'source' => 'bedrock',
            ];

        }

        return [
            'reply' => 'Maaf, proses pemanggilan data memakan waktu terlalu lama. Silakan coba pertanyaan yang lebih spesifik.',
            'intent' => $primaryIntent,
            'tools_used' => $toolsUsed,
            'data' => $collectedData,
            'source' => 'bedrock',
        ];
    }

    /**
     * Fallback mechanism that uses NLP pattern matching to query database
     * directly and construct an executive reply when Bedrock is unreachable.
     *
     * @return array{
     *     reply: string,
     *     intent: string,
     *     tools_used: list<string>,
     *     data: array<string, mixed>|null,
     *     source: 'deterministic_fallback'
     * }
     */
    public function fallbackProcess(string $query): array
    {
        $lower = strtolower($query);

        // 1. Check for Inventory Item Tracking intent
        // Examples: "id barang 5 sedang ngapain", "lacak barang 12", "status TAG-CLI1-ORD001-00001", "barang 3"
        if (preg_match('/(?:barang|material|item|lacak|tag)\s*(?:id|nomor|kode)?\s*[:#]?\s*(tag-[a-z0-9-]+|\d+|[a-z0-9_-]+)/i', $query, $matches)) {
            $identifier = $matches[1];
            $data = $this->dataService->trackInventoryItem($identifier);

            if (! $data['found']) {
                return [
                    'reply' => "⚠️ {$data['message']}\n\nPastikan ID barang atau Tag ID sudah benar.",
                    'intent' => 'track_inventory_item',
                    'tools_used' => ['track_inventory_item'],
                    'data' => $data,
                    'source' => 'deterministic_fallback',
                ];
            }

            $reply = "📦 *Status & Pelacakan Barang #{$data['item_id']}*\n";
            $reply .= "• *Material*: {$data['material_name']} ({$data['material_code']})\n";
            $reply .= "• *Tag ID*: `{$data['tag_id']}`\n";
            $reply .= "• *Jumlah*: {$data['quantity']} {$data['uom']}\n";
            $reply .= "• *Kondisi*: {$data['state_label']}\n";
            $reply .= "• *Lokasi*: {$data['location']}\n";
            $reply .= "• *Klien*: {$data['client_name']}\n";
            $reply .= "• *Order*: {$data['order']['order_number']} ({$data['order']['style_or_article']})\n\n";
            $reply .= "📍 *Sedang Ngapain Sekarang:*\n";
            $reply .= "{$data['current_activity']}";

            if ($data['dispatch_info']) {
                $reply .= "\n• Status Pengantaran: *{$data['dispatch_info']['status_label']}* (No: {$data['dispatch_info']['dispatch_number']})";
            }

            return [
                'reply' => $reply,
                'intent' => 'track_inventory_item',
                'tools_used' => ['track_inventory_item'],
                'data' => $data,
                'source' => 'deterministic_fallback',
            ];
        }

        // 2. Check for Client Billing intent
        // Examples: "id klien 2 harus bayar berapa", "tagihan klien zara", "hutang klien 3", "klien 1 bayar"
        if (preg_match('/(?:klien|client|customer|tagihan|hutang|bayar)\s*(?:id|kode|nama)?\s*[:#]?\s*([a-z0-9_\s-]+?)(?:\s+(?:harus|perlu|belum|sudah)?\s*(?:bayar|lunas|tagihan|berapa)|$)/i', $query, $matches)
            || preg_match('/(?:harus bayar berapa|berapa tagihan|cek tagihan)\s*(?:klien)?\s*[:#]?\s*([a-z0-9_\s-]+)/i', $query, $matches)) {
            $identifier = trim($matches[1]);
            // Clean common filler words
            $identifier = preg_replace('/^(id|kode|klien|client)\s+/i', '', $identifier);

            $data = $this->dataService->getClientBilling($identifier);

            if (! $data['found']) {
                return [
                    'reply' => "⚠️ {$data['message']}\n\nSilakan cek ID, nama, atau kode klien yang dimaksud.",
                    'intent' => 'get_client_billing',
                    'tools_used' => ['get_client_billing'],
                    'data' => $data,
                    'source' => 'deterministic_fallback',
                ];
            }

            $reply = "💰 *Rincian Tagihan Klien: {$data['client_name']} ({$data['client_code']})*\n\n";
            $reply .= "• *Total Harus Dibayar*: *{$data['total_harus_dibayar_formatted']}*\n";
            $reply .= "• *Total Sudah Dibayar*: {$data['total_sudah_dibayar_formatted']}\n";
            $reply .= "• *Invoice Belum Lunas*: {$data['jumlah_invoice_belum_lunas']} invoice\n";

            if ($data['jumlah_invoice_overdue'] > 0) {
                $reply .= "• ⚠️ *Invoice Jatuh Tempo (Overdue)*: {$data['jumlah_invoice_overdue']} invoice\n";
            }

            if (! empty($data['unpaid_invoices'])) {
                $reply .= "\n📋 *Daftar Invoice Belum Lunas:*\n";
                foreach ($data['unpaid_invoices'] as $idx => $inv) {
                    $num = $idx + 1;
                    $overdueIcon = $inv['is_overdue'] ? '🔴' : '🟡';
                    $reply .= "{$num}. {$overdueIcon} *{$inv['invoice_number']}* — {$inv['amount_formatted']}\n";
                    $reply .= "   • Order: {$inv['order_number']} ({$inv['style_name']})\n";
                    $reply .= "   • Jatuh Tempo: {$inv['due_at']} ({$inv['overdue_status']})\n";
                }
            } else {
                $reply .= "\n✅ Klien ini tidak memiliki tunggakan / tagihan yang belum lunas.";
            }

            return [
                'reply' => $reply,
                'intent' => 'get_client_billing',
                'tools_used' => ['get_client_billing'],
                'data' => $data,
                'source' => 'deterministic_fallback',
            ];
        }

        // 3. Check for Order Status intent
        if (preg_match('/(?:order|pesanan)\s*(?:id|nomor)?\s*[:#]?\s*([a-z0-9_-]+)/i', $query, $matches)) {
            $identifier = trim($matches[1]);
            $data = $this->dataService->getOrderStatus($identifier);

            if (! $data['found']) {
                return [
                    'reply' => "⚠️ {$data['message']}",
                    'intent' => 'get_order_status',
                    'tools_used' => ['get_order_status'],
                    'data' => $data,
                    'source' => 'deterministic_fallback',
                ];
            }

            $reply = "📑 *Status Pesanan {$data['order_number']}*\n\n";
            $reply .= "• *Klien*: {$data['client_name']} ({$data['client_code']})\n";
            $reply .= "• *Artikel / Style*: {$data['article_name']}\n";
            $reply .= "• *Status Produksi*: *{$data['status_label']}*\n";
            $reply .= "• *Target Jumlah*: {$data['total_quantity']} pcs\n";
            $reply .= "• *Target Selesai*: {$data['target_delivery_date']}\n";
            $reply .= "• *Tagihan Belum Lunas*: {$data['total_unpaid_formatted']}\n";

            return [
                'reply' => $reply,
                'intent' => 'get_order_status',
                'tools_used' => ['get_order_status'],
                'data' => $data,
                'source' => 'deterministic_fallback',
            ];
        }

        // 4. Check for Summary / Ringkasan / Overview intent
        if (preg_match('/(ringkasan|summary|overview|status pabrik|laporan owner|keuangan|piutang)/i', $lower)) {
            $data = $this->dataService->getOwnerExecutiveOverview();

            $reply = "📊 *Ringkasan Eksekutif Pabrik (Owner Overview)*\n\n";
            $reply .= "🏭 *Produksi:*\n";
            $reply .= "• Order Aktif: *{$data['orders']['active_orders']}*\n";
            $reply .= "• Sedang Dijahit / In Production: {$data['orders']['in_production']}\n";
            $reply .= "• Dalam Pemeriksaan QC: {$data['orders']['in_qc']}\n";
            $reply .= "• Selesai Bulan Ini: {$data['orders']['completed_this_month']}\n\n";

            $reply .= "💵 *Keuangan & Piutang:*\n";
            $reply .= "• Total Piutang Belum Lunas: *{$data['finance']['total_piutang_formatted']}*\n";
            $reply .= "• Total Piutang Overdue: *{$data['finance']['total_overdue_formatted']}* ({$data['finance']['overdue_invoice_count']} invoice)\n\n";

            $reply .= "📦 *Inventaris & Gudang:*\n";
            $reply .= "• Total Item Stok: {$data['inventory']['total_items']}\n";
            $reply .= "• Bahan Sedang di Meja Cutting: *{$data['inventory']['materials_currently_in_cutting']}* item\n";

            if (! empty($data['finance']['top_debtor_clients'])) {
                $reply .= "\n👥 *Top Klien dengan Tagihan Terbuka:*\n";
                foreach ($data['finance']['top_debtor_clients'] as $c) {
                    $reply .= "• {$c['client_name']}: {$c['total_hutang_formatted']} ({$c['invoice_count']} inv)\n";
                }
            }

            return [
                'reply' => $reply,
                'intent' => 'get_owner_overview',
                'tools_used' => ['get_owner_overview'],
                'data' => $data,
                'source' => 'deterministic_fallback',
            ];
        }

        // 5. Check for Conceptual / Knowledge Base / RAG / SOP intent
        if (preg_match('/(cmt|semi-cmt|full package|problem statement|apa itu|definisi|maklon|skema|srs|sop|alur produksi)/i', $lower)) {
            $data = $this->dataService->searchKnowledgeBase($query);

            if ($data['found'] && ! empty($data['documents'])) {
                $reply = "📚 *Informasi Dokumen RAG & SOP Lukla:*\n\n";
                foreach ($data['documents'] as $doc) {
                    $reply .= '• '.trim($doc['excerpt'])."\n\n";
                }

                return [
                    'reply' => trim($reply),
                    'intent' => 'search_knowledge_base',
                    'tools_used' => ['search_knowledge_base'],
                    'data' => $data,
                    'source' => 'deterministic_fallback',
                ];
            }
        }

        // Default greeting / help menu
        return [
            'reply' => "👋 Halo Pak Owner! Saya adalah Asisten AI Operasional Lukla.\n\nAnda dapat menanyakan hal berikut tanpa membuka web:\n"
                ."• *Lacak Barang*: _\"ID barang 5 sedang ngapain?\"_ atau _\"Lacak TAG-CLI1-ORD001-00001\"_\n"
                ."• *Cek Tagihan Klien*: _\"ID klien 2 harus bayar berapa?\"_ atau _\"Tagihan klien PT Maju\"_\n"
                ."• *Status Pesanan*: _\"Status order ORD-001\"_\n"
                ."• *Dokumen & SOP*: _\"Apa itu CMT?\"_ atau _\"Problem statement di Lukla\"_\n"
                ."• *Ringkasan Pabrik*: _\"Tampilkan ringkasan keuangan dan produksi\"_\n\n"
                .'Ada yang bisa saya bantu cek saat ini?',
            'intent' => 'help',
            'tools_used' => [],
            'data' => null,
            'source' => 'deterministic_fallback',
        ];
    }

    /**
     * Dispatch tool call to OwnerDataService.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function executeTool(string $toolName, array $input): array
    {
        return match ($toolName) {
            'track_inventory_item' => $this->dataService->trackInventoryItem($input['identifier'] ?? ''),
            'get_client_billing' => $this->dataService->getClientBilling($input['identifier'] ?? ''),
            'get_order_status' => $this->dataService->getOrderStatus($input['identifier'] ?? ''),
            'get_owner_overview' => $this->dataService->getOwnerExecutiveOverview(),
            'search_knowledge_base' => $this->dataService->searchKnowledgeBase($input['query'] ?? ''),
            default => ['error' => "Unknown tool: {$toolName}"],
        };
    }

    /**
     * Tool specifications for Amazon Bedrock Converse API.
     *
     * @return list<array<string, mixed>>
     */
    private function getToolDefinitions(): array
    {
        return [
            [
                'toolSpec' => [
                    'name' => 'search_knowledge_base',
                    'description' => 'Cari informasi di dokumen RAG, SRS, SOP, Problem Statement, dan konsep bisnis manufaktur Lukla (seperti CMT, Semi-CMT, Full Package, maklon, alur produksi, rate card, dan aturan bisnis). Gunakan tool ini jika pertanyaan berkaitan dengan konsep, definisi, aturan bisnis, SOP, atau problem statement pabrik.',
                    'inputSchema' => [
                        'json' => [
                            'type' => 'object',
                            'properties' => [
                                'query' => [
                                    'type' => 'string',
                                    'description' => 'Topik atau kata kunci yang ingin dicari di dokumen RAG (contoh: "CMT", "problem statement", "skema maklon")',
                                ],
                            ],
                            'required' => ['query'],
                        ],
                    ],
                ],
            ],
            [
                'toolSpec' => [
                    'name' => 'track_inventory_item',

                    'description' => 'Lacak status fisik, lokasi, dan aktivitas pengerjaan saat ini (apakah sedang di cutting, di gudang, selesai, dll) dari barang atau material berdasarkan ID item atau Tag ID fisik.',
                    'inputSchema' => [
                        'json' => [
                            'type' => 'object',
                            'properties' => [
                                'identifier' => [
                                    'type' => 'string',
                                    'description' => 'ID angka inventaris atau Tag ID (misal: "5" atau "TAG-CLI1-ORD001-00005") atau nama/batch material',
                                ],
                            ],
                            'required' => ['identifier'],
                        ],
                    ],
                ],
            ],
            [
                'toolSpec' => [
                    'name' => 'get_client_billing',
                    'description' => 'Dapatkan rincian tagihan keuangan klien, berapa rupiah yang harus dibayar klien saat ini, daftar invoice belum lunas, dan status keterlambatan (overdue).',
                    'inputSchema' => [
                        'json' => [
                            'type' => 'object',
                            'properties' => [
                                'identifier' => [
                                    'type' => 'string',
                                    'description' => 'ID angka klien, kode klien, atau nama klien (misal: "2" atau "CLI1" atau "Zara")',
                                ],
                            ],
                            'required' => ['identifier'],
                        ],
                    ],
                ],
            ],
            [
                'toolSpec' => [
                    'name' => 'get_order_status',
                    'description' => 'Dapatkan status pesanan produksi massal (order), progres, artikel pakaian, dan rincian kuantitas berdasarkan ID order atau nomor order.',
                    'inputSchema' => [
                        'json' => [
                            'type' => 'object',
                            'properties' => [
                                'identifier' => [
                                    'type' => 'string',
                                    'description' => 'ID angka pesanan atau nomor order (misal: "1" atau "ORD-001")',
                                ],
                            ],
                            'required' => ['identifier'],
                        ],
                    ],
                ],
            ],
            [
                'toolSpec' => [
                    'name' => 'get_owner_overview',
                    'description' => 'Dapatkan ringkasan eksekutif seluruh pabrik untuk Owner: total piutang belum bayar, order aktif di produksi/QC, dan status inventaris saat ini.',
                    'inputSchema' => [
                        'json' => [
                            'type' => 'object',
                            'properties' => [
                                'scope' => [
                                    'type' => 'string',
                                    'description' => 'Fokus data: "all", "finance", "production", "inventory"',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}
