<?php

namespace Database\Seeders;

use App\Models\Place;
use Illuminate\Database\Seeder;

class PlaceSeeder extends Seeder
{
    public function run(): void
    {
        // [name, country, region, elevation_m|null, difficulty, category]
        $rows = [
            // Mostly hikes — the heart of Peeps
            ['Mount Rinjani', 'Indonesia', 'Lombok', 3726, 'hard', 'hike'],
            ['Mount Batur', 'Indonesia', 'Bali', 1717, 'easy', 'hike'],
            ['Mount Fuji', 'Japan', 'Shizuoka', 3776, 'challenging', 'hike'],
            ['Mount Kinabalu', 'Malaysia', 'Sabah', 4095, 'hard', 'hike'],
            ['Mount Pulag', 'Philippines', 'Benguet', 2926, 'challenging', 'hike'],
            ['Mount Merbabu', 'Indonesia', 'Java', 3145, 'challenging', 'hike'],
            // ...but joiners is trips of every kind
            ['Ijen Crater', 'Indonesia', 'Java', 2799, 'challenging', 'hike'],
            ['Nusa Penida', 'Indonesia', 'Bali', null, 'easy', 'island'],
            ['El Nido', 'Philippines', 'Palawan', null, 'easy', 'island'],
            ['Siargao', 'Philippines', 'Surigao', null, 'challenging', 'surf'],
            ['Kyoto', 'Japan', 'Kansai', null, 'easy', 'city'],
            ['Coron', 'Philippines', 'Palawan', null, 'easy', 'beach'],
        ];

        foreach ($rows as [$name, $country, $region, $elevation, $difficulty, $category]) {
            Place::firstOrCreate(
                ['name' => $name],
                compact('country', 'region', 'difficulty', 'category') + ['elevation_m' => $elevation],
            );
        }
    }
}
