<?php

namespace App\Filament\Resources\InventoryItems\Pages;

use App\Filament\Resources\InventoryItems\InventoryItemResource;
use App\Models\InventoryItem;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditInventoryItem extends EditRecord
{
    protected static string $resource = InventoryItemResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        if ($this->record instanceof InventoryItem && $this->record->isReceivedByProduction()) {
            Notification::make()
                ->title('Barang Sudah Keluar Gudang')
                ->body('Barang ini telah resmi diterima oleh bagian produksi sehingga tidak dapat diubah lagi.')
                ->danger()
                ->send();

            $this->redirect($this->getResource()::getUrl('index'));
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
