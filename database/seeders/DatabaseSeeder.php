<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    // No WithoutModelEvents: HasSlug generates slugs on the model `creating` event.

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            PlaceSeeder::class,
            DemoSeeder::class,
        ]);
    }
}
