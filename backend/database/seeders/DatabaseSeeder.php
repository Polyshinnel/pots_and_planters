<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(PageSeoSeeder::class);
        $this->call(CategorySeeder::class);
    }
}
