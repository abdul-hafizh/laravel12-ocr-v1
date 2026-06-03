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

            'flash' => [
                'message' => fn() => $request->session()->get('message'),
                'success' => fn() => $request->session()->get('success'),
                'error'   => fn() => $request->session()->get('error'),
            ],

            'auth' => [
                'user' => function () use ($request) {

                    $user = $request->user();

                    if (!$user) {
                        return null;
                    }

                    $user->load('role');

                    return [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'phone' => $user->phone,
                        'role_id' => $user->role_id,

                        'role' => $user->role
                            ? [
                                'id' => $user->role->id,
                                'name' => $user->role->name,
                                'slug' => $user->role->slug,
                            ]
                            : null,
                    ];
                },

                'permissions' => function () use ($request) {

                    $user = $request->user();

                    if (!$user || !$user->role_id) {
                        return [];
                    }

                    return DB::table('role_action_permissions')
                        ->where('role_id', $user->role_id)
                        ->where('is_active', 1)
                        ->pluck('action_key')
                        ->toArray();
                },
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
