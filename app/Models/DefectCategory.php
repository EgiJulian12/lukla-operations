<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Kategori cacat untuk hasil QC selain lolos (FR-014).
 * Data referensi global — tidak di-scope per klien.
 */
class DefectCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function qcRecords(): HasMany
    {
        return $this->hasMany(QcRecord::class);
    }
}
