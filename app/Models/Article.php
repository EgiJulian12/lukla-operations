<?php

namespace App\Models;

use App\Concerns\BelongsToClient;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Artikel (jenis produk) milik satu klien.
 */
class Article extends Model
{
    use BelongsToClient;
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'client_id',
        'code',
        'name',
        'description',
    ];

    public function components(): HasMany
    {
        return $this->hasMany(Component::class)->orderBy('sequence');
    }

    public function samples(): HasMany
    {
        return $this->hasMany(Sample::class);
    }
}
