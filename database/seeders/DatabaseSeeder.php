<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Безпечно для production: викликається при кожному розгортанні (migrate --seed --force).
     * Не використовуйте тут фабрики — у production немає dev-залежностей (Faker).
     */
    public function run(): void
    {
        $this->call(AdminUserSeeder::class);
    }
}
