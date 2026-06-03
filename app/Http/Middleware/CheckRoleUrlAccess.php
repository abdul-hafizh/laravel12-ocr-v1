<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CheckRoleUrlAccess
{
    public function handle(Request $request, Closure $next)
    {
        $user = auth()->user();

        if (!$user || !$user->role_id) {
            abort(403, 'Anda tidak memiliki akses.');
        }

        $path = '/' . ltrim($request->path(), '/');

        $allowed = DB::table('role_url_permissions')
            ->where('role_id', $user->role_id)
            ->where('is_active', 1)
            ->where(function ($q) use ($path) {
                $q->where('url', $path)
                    ->orWhereRaw('? LIKE CONCAT(url, \'%\')', [$path]);
            })
            ->exists();

        if (!$allowed) {
            abort(403, 'Anda tidak memiliki akses ke halaman ini.');
        }

        return $next($request);
    }
}