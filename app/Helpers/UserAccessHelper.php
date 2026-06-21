<?php

namespace App\Helpers;

use App\Models\MasterCabang;
use App\Models\User;
use Illuminate\Support\Collection;

class UserAccessHelper
{
    public static function isAdminOrFinance(User $user): bool
    {
        $user->loadMissing('role');

        return in_array($user->role?->slug, ['admin', 'finance']);
    }

    public static function cabangIds(User $user): Collection
    {
        if (self::isAdminOrFinance($user)) {
            return MasterCabang::where('is_active', true)->pluck('id');
        }

        return MasterCabang::where('is_active', true)
            ->where('pic_user_id', $user->id)
            ->pluck('id');
    }

    public static function applyCabangFilter($query, User $user, string $column = 'master_cabang_id')
    {
        if (!self::isAdminOrFinance($user)) {
            $query->whereIn($column, self::cabangIds($user));
        }

        return $query;
    }
}