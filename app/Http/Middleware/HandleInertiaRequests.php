<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        return [
            ...parent::share($request),

            'auth' => [
                'user' => $request->user(),
            ],

            'allowedUrls' => function () use ($request) {

                $user = $request->user();

                if (!$user || !$user->role_id) {
                    return [];
                }

                return DB::table('role_url_permissions')
                    ->where('role_id', $user->role_id)
                    ->where('is_active', 1)
                    ->pluck('url')
                    ->toArray();
            },
        ];
    }
}