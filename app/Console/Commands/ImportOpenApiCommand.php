<?php

namespace App\Console\Commands;

use App\Services\Paystack\OpenApi\EndpointImporter;
use Illuminate\Console\Command;
use Throwable;

class ImportOpenApiCommand extends Command
{
    protected $signature = 'paystack:import-openapi
        {path? : OpenAPI document (YAML or JSON). Defaults to the bundled Paystack spec}
        {--force : Overwrite endpoints an admin has edited and reset enabled flags}
        {--dry-run : Show what would be imported without writing}';

    protected $description = 'Import Paystack endpoint definitions from an OpenAPI specification into the registry';

    public function handle(EndpointImporter $importer): int
    {
        $path = $this->argument('path') ?? config('paystack.openapi.spec_path');

        try {
            if ($this->option('dry-run')) {
                $built = $importer->build($path);
                $this->table(
                    ['Key', 'Method', 'Path', 'Category', 'Params', 'Features', 'Enabled'],
                    collect($built['endpoints'])->map(fn ($e) => [
                        $e['key'], $e['method'], $e['path'], $e['category'], count($e['parameters']),
                        implode(',', $e['features']), $e['enabled'] ? 'yes' : 'no',
                    ])->all()
                );
                $this->info(count($built['endpoints']).' endpoints in '.count($built['categories']).' categories (dry run, nothing written).');

                return self::SUCCESS;
            }

            $stats = $importer->import($path, (bool) $this->option('force'));
        } catch (Throwable $e) {
            $this->error('Import failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Imported: %d created, %d updated, %d locally modified preserved, %d categories.',
            $stats['created'], $stats['updated'], $stats['preserved'], $stats['categories']
        ));

        return self::SUCCESS;
    }
}
