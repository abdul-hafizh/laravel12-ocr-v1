<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MasterHargaTinta extends Model
{
    protected $table = 'master_harga_tintas';

    protected $fillable = [
        'nama_tinta',
        'status_kepemilikan',
        'harga',
        'keterangan',
        'is_active',
    ];

    protected $casts = [
        'harga' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    /**
     * Harga tinta aktif untuk mesin CEA. Prioritas: baris yang status
     * kepemilikannya sama dgn mesin (milik/sewa), lalu baris umum (NULL).
     * Kalau ada lebih dari satu, ambil yang terakhir diperbarui.
     */
    public static function hargaAktifUntuk(?string $statusKepemilikan): ?self
    {
        $status = strtolower(trim((string) $statusKepemilikan));

        return static::query()
            ->where('is_active', true)
            ->where(function ($q) use ($status) {
                $q->whereNull('status_kepemilikan');

                if ($status !== '') {
                    $q->orWhereRaw('LOWER(status_kepemilikan) = ?', [$status]);
                }
            })
            ->orderByRaw('CASE WHEN status_kepemilikan IS NULL THEN 1 ELSE 0 END')
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->first();
    }
}
