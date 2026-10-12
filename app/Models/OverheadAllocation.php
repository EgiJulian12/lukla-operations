<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Alokasi overhead per unit produk per bulan (FR-018).
 *
 * overhead_per_unit = total overhead bulan tersebut / total seluruh unit
 * yang diproduksi di bulan itu. Hasilnya disimpan agar laporan bulan lalu
 * tetap konsisten meski data produksi sesudahnya berubah.
 */
class OverheadAllocation extends Model
{
    use HasFactory;

    protected $fillable = [
        'period_month',
        'total_overhead',
        'total_units',
        'overhead_per_unit',
    ];

    protected function casts(): array
    {
        return [
            'period_month' => 'date',
            'total_overhead' => 'decimal:2',
            'total_units' => 'integer',
            'overhead_per_unit' => 'decimal:4',
        ];
    }

    /**
     * Menghitung overhead per unit dari total overhead dan total unit (FR-018).
     * Mengembalikan 0 bila belum ada unit yang diproduksi, bukan membagi nol.
     */
    public function calculatePerUnit(): string
    {
        $units = (int) $this->total_units;

        if ($units <= 0) {
            return '0.0000';
        }

        return number_format((float) $this->total_overhead / $units, 4, '.', '');
    }
}
