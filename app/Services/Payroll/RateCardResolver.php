<?php

namespace App\Services\Payroll;

use App\Enums\RateType;
use App\Models\RateCard;
use DateTimeInterface;

/**
 * Pencari tarif yang berlaku (FR-010).
 *
 * Satu kombinasi klien, artikel, dan komponen dapat punya beberapa rate card
 * dengan rentang tanggal berbeda. Pencarian memilih tarif yang paling spesifik
 * pada tanggal yang diminta, dengan urutan: komponen, lalu artikel, lalu tarif
 * dasar klien. Tarif yang lebih spesifik selalu menang agar kustomisasi per
 * komponen tidak tertimpa tarif umum.
 */
class RateCardResolver
{
    public function resolve(
        int $clientId,
        ?int $articleId,
        ?int $componentId,
        RateType $type,
        DateTimeInterface $date,
    ): ?RateCard {
        $base = RateCard::query()
            ->withoutGlobalScope('client')
            ->where('client_id', $clientId)
            ->where('rate_type', $type->value)
            ->effectiveOn($date);

        $candidates = array_filter([
            $componentId !== null
                ? (clone $base)->where('component_id', $componentId)
                : null,
            $articleId !== null
                ? (clone $base)->where('article_id', $articleId)->whereNull('component_id')
                : null,
            (clone $base)->whereNull('article_id')->whereNull('component_id'),
        ]);

        foreach ($candidates as $query) {
            $card = $query->orderByDesc('effective_from')->first();

            if ($card !== null) {
                return $card;
            }
        }

        return null;
    }

    public function rateFor(
        int $clientId,
        ?int $articleId,
        ?int $componentId,
        RateType $type,
        DateTimeInterface $date,
    ): float {
        return (float) ($this->resolve($clientId, $articleId, $componentId, $type, $date)?->rate ?? 0);
    }
}
