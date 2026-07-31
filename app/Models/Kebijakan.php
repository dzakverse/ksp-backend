<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Kebijakan extends Model
{
    protected $fillable = [
        'plafon_maksimal',
        'suku_bunga_persen',
        'simpanan_wajib_nominal',
        'catatan_terakhir',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'plafon_maksimal' => 'decimal:2',
            'suku_bunga_persen' => 'decimal:2',
            'simpanan_wajib_nominal' => 'decimal:2',
        ];
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Selalu ada tepat 1 baris kebijakan aktif. Dibuat otomatis dengan nilai
     * default kalau belum pernah di-set oleh Ketua.
     */
    public static function current(): self
    {
        return static::firstOrCreate([]);
    }
}
