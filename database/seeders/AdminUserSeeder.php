<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Створює адміністратора з ADMIN_EMAIL / ADMIN_PASSWORD (.env), якщо його ще немає.
 * Запускається при кожному розгортанні (migrate --seed), але наявного користувача
 * не змінює — пароль, змінений в адмінці, не перезапишеться.
 */
class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = config('admin.email');
        $password = config('admin.password');

        if (blank($email) || blank($password)) {
            $this->command?->warn('ADMIN_EMAIL / ADMIN_PASSWORD не задані — адміністратора не створено.');

            return;
        }

        $user = User::firstOrCreate(
            ['email' => $email],
            ['name' => config('admin.name'), 'password' => $password],
        );

        $this->command?->info($user->wasRecentlyCreated
            ? "Адміністратора {$email} створено."
            : "Адміністратор {$email} уже існує — без змін.");
    }
}
