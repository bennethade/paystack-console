<?php

namespace Database\Seeders;

use App\Services\Paystack\OpenApi\EndpointImporter;
use Illuminate\Database\Seeder;

/**
 * Populates categories and endpoints from Paystack's official OpenAPI
 * specification plus the documented overlay (config/paystack-endpoints.php).
 */
class EndpointRegistrySeeder extends Seeder
{
    public function run(EndpointImporter $importer): void
    {
        $stats = $importer->import(config('paystack.openapi.spec_path'));

        $this->command?->info(sprintf(
            'Endpoint registry: %d created, %d updated, %d preserved, %d categories.',
            $stats['created'], $stats['updated'], $stats['preserved'], $stats['categories']
        ));
    }
}
