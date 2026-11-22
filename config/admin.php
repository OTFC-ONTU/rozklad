<?php

// Перший адміністратор (database/seeders/AdminUserSeeder.php).
// Через config, а не env() у сидері: при закешованому конфігу env() повертає null.
return [
    'name' => env('ADMIN_NAME', 'Адміністратор'),
    'email' => env('ADMIN_EMAIL'),
    'password' => env('ADMIN_PASSWORD'),
];
