<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use App\Models\BusinessSetting;
use App\Models\Service;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        BusinessSetting::current();
        foreach ([['Wash, dry & fold', 'kg', 6500, 24, 'Your everyday laundry, freshly washed and neatly folded.'], ['Wash & dry', 'kg', 5000, 24, 'A simple wash and dry for everyday fabrics.'], ['Dry cleaning', 'piece', 18000, 48, 'Specialist care for delicate garments.'], ['Iron & press', 'piece', 3500, 24, 'A crisp finish, ready to wear.'], ['Comforter / blanket', 'piece', 25000, 48, 'Freshness for your larger linens.'], ['Express surcharge', 'load', 10000, 6, 'Add to an express order for priority processing.']] as [$name,$unit,$price,$hours,$description]) {
            Service::firstOrCreate(['name' => $name], ['unit' => $unit, 'price_cents' => $price, 'turnaround_hours' => $hours, 'description' => $description, 'active' => true]);
        }
    }
}
