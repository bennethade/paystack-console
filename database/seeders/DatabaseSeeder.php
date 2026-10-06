<?php

namespace Database\Seeders;

use App\Models\Setting;
use App\Support\Settings;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            PaystackReferenceSeeder::class,
            EndpointRegistrySeeder::class,
            AdminUserSeeder::class,
        ]);

        // Persist default settings/feature flags so they appear in the admin area.
        $settings = app(Settings::class);
        foreach (Settings::DEFINITIONS as $key => $definition) {
            if (! Setting::where('key', $key)->exists()) {
                $settings->set($key, $definition['default']);
            }
        }
    }
}
