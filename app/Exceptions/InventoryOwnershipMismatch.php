<?php

namespace App\Exceptions;

use App\Models\Client;
use App\Models\InventoryItem;
use App\Models\Order;
use RuntimeException;

/**
 * Bahan milik satu klien/pesanan dicoba dikeluarkan untuk pesanan lain (FR-007).
 *
 * Dibuat sebagai exception domain — bukan dibiarkan menjadi galat foreign key —
 * supaya lapisan UI dapat menampilkan peringatan eksplisit ketidakcocokan
 * pemilik SEBELUM transaksi dicoba, sesuai NFR-002.
 */
class InventoryOwnershipMismatch extends RuntimeException
{
    public function __construct(
        public readonly InventoryItem $item,
        public readonly int $targetClientId,
        public readonly int $targetOrderId,
    ) {
        parent::__construct(sprintf(
            'Bahan #%d milik klien #%d untuk pesanan #%d tidak dapat dikeluarkan untuk klien #%d pesanan #%d — pemilik tidak cocok.',
            $item->getKey(),
            $item->client_id,
            $item->order_id,
            $targetClientId,
            $targetOrderId,
        ));
    }

    /**
     * @return array{client_id: int, order_id: int}
     */
    public function itemOwnership(): array
    {
        return [
            'client_id' => (int) $this->item->client_id,
            'order_id' => (int) $this->item->order_id,
        ];
    }

    public static function for(InventoryItem $item, Client|int $targetClient, Order|int $targetOrder): self
    {
        return new self(
            $item,
            $targetClient instanceof Client ? (int) $targetClient->getKey() : (int) $targetClient,
            $targetOrder instanceof Order ? (int) $targetOrder->getKey() : (int) $targetOrder,
        );
    }
}
