<?php

namespace App\Http\Controllers;

use App\Models\TelegramUser;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class TelegramUserController extends Controller
{
    public function index(Request $request)
    {
        $query = TelegramUser::with('user');

        if ($request->filled('search')) {
            $search = trim($request->search);

            $query->where(function ($q) use ($search) {
                $q->where('telegram_chat_id', 'like', "%{$search}%")
                    ->orWhere('telegram_user_id', 'like', "%{$search}%")
                    ->orWhere('telegram_username', 'like', "%{$search}%")
                    ->orWhere('telegram_first_name', 'like', "%{$search}%")
                    ->orWhere('telegram_last_name', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($userQuery) use ($search) {
                        $userQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%");
                    });
            });
        }

        $telegramUsers = $query
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();

        $users = User::query()
            ->where('is_active', true)
            ->where('is_delete', false)
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'email',
                'phone',
                'employee_id',
            ]);

        return Inertia::render('TelegramUser/Index', [
            'telegramUsers' => $telegramUsers,
            'users' => $users,
            'filters' => [
                'search' => $request->search,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id' => [
                'nullable',
                'exists:users,id',
                'unique:telegram_users,user_id',
            ],
            'telegram_chat_id' => [
                'required',
                'integer',
                'unique:telegram_users,telegram_chat_id',
            ],
            'telegram_user_id' => [
                'required',
                'integer',
                'unique:telegram_users,telegram_user_id',
            ],
            'telegram_username' => [
                'nullable',
                'string',
                'max:100',
            ],
            'telegram_first_name' => [
                'nullable',
                'string',
                'max:100',
            ],
            'telegram_last_name' => [
                'nullable',
                'string',
                'max:100',
            ],
            'is_active' => [
                'boolean',
            ],
        ]);

        $validated['is_active'] = $request->boolean('is_active');

        TelegramUser::create($validated);

        return redirect()
            ->route('telegram-users.index')
            ->with('message', [
                'text' => 'Data Telegram User berhasil disimpan!',
                'type' => 'success',
            ]);
    }

    public function update(Request $request, TelegramUser $telegramUser)
    {
        $validated = $request->validate([
            'user_id' => [
                'nullable',
                'exists:users,id',
                Rule::unique('telegram_users', 'user_id')->ignore($telegramUser->id),
            ],
            'telegram_chat_id' => [
                'required',
                'integer',
                Rule::unique('telegram_users', 'telegram_chat_id')->ignore($telegramUser->id),
            ],
            'telegram_user_id' => [
                'required',
                'integer',
                Rule::unique('telegram_users', 'telegram_user_id')->ignore($telegramUser->id),
            ],
            'telegram_username' => [
                'nullable',
                'string',
                'max:100',
            ],
            'telegram_first_name' => [
                'nullable',
                'string',
                'max:100',
            ],
            'telegram_last_name' => [
                'nullable',
                'string',
                'max:100',
            ],
            'is_active' => [
                'boolean',
            ],
        ]);

        $validated['is_active'] = $request->boolean('is_active');

        $telegramUser->update($validated);

        return redirect()
            ->route('telegram-users.index')
            ->with('message', [
                'text' => 'Data Telegram User berhasil diperbaharui!',
                'type' => 'success',
            ]);
    }

    public function destroy(TelegramUser $telegramUser)
    {
        $telegramUser->delete();

        return redirect()
            ->route('telegram-users.index')
            ->with('message', [
                'text' => 'Data Telegram User berhasil dihapus!',
                'type' => 'success',
            ]);
    }
}