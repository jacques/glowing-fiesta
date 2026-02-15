<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ApiKeySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Only seed in non-production environments
        if (app()->environment('production')) {
            $this->command->warn('Skipping API key seeding in production environment.');
            return;
        }

        \DB::table('api_keys')->insert([
            'name' => 'Development Key',
            'key' => hash('sha256', 'dev-key-' . now()->timestamp),
            'rate_limit' => 10000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->command->info('Sample API key created for development.');
    }
}
