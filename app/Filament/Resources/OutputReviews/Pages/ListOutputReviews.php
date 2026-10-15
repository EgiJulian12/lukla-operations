<?php

namespace App\Filament\Resources\OutputReviews\Pages;

use App\Filament\Resources\OutputReviews\OutputReviewResource;
use Filament\Resources\Pages\ListRecords;

/**
 * Daftar order yang outputnya menunggu diperiksa Kepala Produksi.
 */
class ListOutputReviews extends ListRecords
{
    protected static string $resource = OutputReviewResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
