<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

class OrderDesignDownloadController extends Controller
{
    /**
     * Unduh berkas desain order secara aman dengan otorisasi berbasis peran/klien.
     */
    public function download(Request $request, int|string $order): BinaryFileResponse|StreamedResponse
    {
        $order = Order::withoutGlobalScope('client')->findOrFail($order);

        Gate::authorize('view', $order);

        $images = $order->design_images ?? [];

        if (empty($images)) {
            abort(404, 'Order ini tidak memiliki lampiran dokumen desain.');
        }

        // Jika klien meminta spesifik 1 index file (misal ?index=0)
        if ($request->has('index')) {
            $index = (int) $request->input('index');
            if (! isset($images[$index])) {
                abort(404, 'Berkas desain pada indeks tersebut tidak ditemukan.');
            }

            return $this->downloadSingleFile($images[$index]);
        }

        // Jika hanya ada 1 berkas desain, langsung unduh berkas tersebut
        if (count($images) === 1) {
            return $this->downloadSingleFile($images[0]);
        }

        // Jika ada lebih dari 1 berkas desain, buat arsip ZIP
        return $this->downloadZip($order, $images);
    }

    /**
     * Unduh satu berkas desain.
     */
    private function downloadSingleFile(string $filePath): StreamedResponse
    {
        [$disk, $path] = $this->resolveDiskAndPath($filePath);

        if ($disk === null) {
            abort(404, 'Berkas desain tidak ditemukan di penyimpanan.');
        }

        return Storage::disk($disk)->download($path, basename($path));
    }

    /**
     * Unduh banyak berkas desain dalam format ZIP.
     *
     * @param  array<int, string>  $images
     */
    private function downloadZip(Order $order, array $images): BinaryFileResponse
    {
        $zipFilename = 'desain-'.($order->order_number ?: 'order-'.$order->id).'.zip';
        $tempPath = tempnam(sys_get_temp_dir(), 'order_designs_');

        $zip = new ZipArchive;
        if ($zip->open($tempPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            abort(500, 'Gagal membuat arsip unduhan desain.');
        }

        $addedCount = 0;
        foreach ($images as $imagePath) {
            [$disk, $path] = $this->resolveDiskAndPath($imagePath);
            if ($disk !== null) {
                try {
                    $content = Storage::disk($disk)->get($path);
                    if ($content !== false && $content !== null) {
                        $zip->addFromString(basename($path), $content);
                        $addedCount++;
                    }
                } catch (\Throwable) {
                    continue;
                }
            }
        }

        $zip->close();

        if ($addedCount === 0) {
            if (file_exists($tempPath)) {
                unlink($tempPath);
            }

            abort(404, 'Berkas lampiran desain tidak ditemukan di penyimpanan.');
        }

        return response()->download($tempPath, $zipFilename)->deleteFileAfterSend(true);
    }

    /**
     * Menemukan disk dan path tempat berkas berada.
     *
     * @return array{0: ?string, 1: ?string}
     */
    private function resolveDiskAndPath(string $filePath): array
    {
        $disksToCheck = array_unique(array_filter([
            config('filesystems.default', 'local'),
            'local',
            'public',
            's3',
        ]));

        foreach ($disksToCheck as $disk) {
            try {
                if (Storage::disk($disk)->exists($filePath)) {
                    return [$disk, $filePath];
                }
            } catch (\Throwable) {
                continue;
            }
        }

        return [null, null];
    }
}
