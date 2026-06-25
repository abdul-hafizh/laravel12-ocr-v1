<?php

namespace App\Helpers;

use App\Models\MasterCabang;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class UserAccessHelper
{
    public static function isAdminOrFinance(User $user): bool
    {
        $user->loadMissing('role');

        $slug = strtolower(trim((string) $user->role?->slug));

        return in_array($slug, ['admin', 'finance']);
    }

    public static function cabangIds(User $user): Collection
    {
        if (self::isAdminOrFinance($user)) {
            return MasterCabang::where('is_active', true)->pluck('id');
        }

        return DB::table('master_cabang_user')
            ->join('master_cabangs', 'master_cabang_user.master_cabang_id', '=', 'master_cabangs.id')
            ->where('master_cabang_user.user_id', $user->id)
            ->where('master_cabangs.is_active', true)
            ->pluck('master_cabangs.id');
    }

    public static function applyCabangFilter($query, User $user, string $column = 'master_cabang_id')
    {
        if (!self::isAdminOrFinance($user)) {
            $query->whereIn($column, self::cabangIds($user));
        }

        return $query;
    }
}