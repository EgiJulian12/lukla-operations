<?php

namespace App\Filament\Resources\QcRecords\Pages;

use App\Enums\QcResult;
use App\Exceptions\InvalidQcInspection;
use App\Filament\Resources\QcRecords\QcRecordResource;
use App\Models\Assembly;
use App\Models\DefectCategory;
use App\Services\Quality\QcService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * Mencatat hasil inspeksi lewat QcService (FR-014).
 *
 * Bila hasilnya bukan lolos tetapi kategori cacat belum dipilih, service
 * melempar InvalidQcInspection dan halaman ini mengubahnya menjadi galat
 * validasi pada field kategori cacat.
 */
class CreateQcRecord extends CreateRecord
{
    protected static string $resource = QcRecordResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        $result = $data['result'] instanceof QcResult
            ? $data['result']
            : QcResult::from($data['result']);

        try {
            return app(QcService::class)->recordInspection(
                assembly: Assembly::findOrFail($data['assembly_id']),
                result: $result,
                quantity: (int) $data['quantity'],
                defectCategory: isset($data['defect_category_id'])
                    ? DefectCategory::find($data['defect_category_id'])
                    : null,
                notes: $data['notes'] ?? null,
            );
        } catch (InvalidQcInspection $exception) {
            throw ValidationException::withMessages([
                'data.defect_category_id' => $exception->getMessage(),
            ]);
        }
    }
}
