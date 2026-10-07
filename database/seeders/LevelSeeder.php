<?php

namespace Database\Seeders;

use App\Models\Level;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class LevelSeeder extends Seeder
{
    public const LEVELS = ['Beginner', 'Junior', 'Intermediate', 'Mid-level', 'Senior', 'Lead/Expert'];

    public function run(): void
    {
        foreach (self::LEVELS as $index => $name) {
            Level::updateOrCreate(
                ['slug' => Str::slug(str_replace('/', ' ', $name))],
                ['name' => $name, 'sort_order' => $index + 1],
            );
        }
    }
}
