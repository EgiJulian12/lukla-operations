<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Komponen = bagian artikel yang dijahit terpisah lalu dirakit.
 * Time study (FR-002) dan output 30 menit (FR-011) dicatat per komponen.
 *
 * Komponen tidak membawa client_id sendiri — kepemilikannya diturunkan dari
 * artikel induknya, yang sudah di-scope per klien.
 */
class Component extends Model
{
    use HasFactory;

    protected $fillable = [
        'article_id',
        'code',
        'name',
        'sequence',
    ];

    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
        ];
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    public function timeStudies(): HasMany
    {
        return $this->hasMany(TimeStudy::class);
    }

    public function outputEntries(): HasMany
    {
        return $this->hasMany(OutputEntry::class);
    }

    public function rateCards(): HasMany
    {
        return $this->hasMany(RateCard::class);
    }
}
