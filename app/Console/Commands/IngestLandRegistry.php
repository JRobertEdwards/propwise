<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class IngestLandRegistry extends Command
{
    protected $signature = 'ingest:land-registry {--full : Import the complete dataset since 1995 instead of the monthly update}';
    protected $description = 'Download and import Land Registry Price Paid data';

    public function handle(): int
    {
        $full = $this->option('full');
        $url = $full
            ? 'https://price-paid-data.publicdata.landregistry.gov.uk/pp-complete.csv'
            : 'https://price-paid-data.publicdata.landregistry.gov.uk/pp-monthly-update-new-version.csv';

        $label = $full ? 'complete dataset' : 'monthly update';
        Log::info("IngestLandRegistry: starting {$label}");
        $this->info("Downloading Land Registry {$label}...");

        $dir = storage_path('app/ingestion/land-registry');
        @mkdir($dir, 0775, true);
        $path = "{$dir}/pp.csv";

        $response = Http::sink($path)->timeout(7200)->get($url);

        if (! $response->successful()) {
            Log::error('IngestLandRegistry: download failed', ['status' => $response->status()]);
            $this->error("Download failed (HTTP {$response->status()})");
            return self::FAILURE;
        }

        $this->info('Running ETL...');

        $result = Process::path(base_path('etl'))
            ->timeout(7200)
            ->run('python3 import_land_registry.py ' . escapeshellarg($path));

        @unlink($path);

        if (! $result->successful()) {
            Log::error('IngestLandRegistry: ETL failed', ['stderr' => $result->errorOutput()]);
            $this->error('ETL failed: ' . $result->errorOutput());
            return self::FAILURE;
        }

        Log::info('IngestLandRegistry: complete');
        $this->info('Done.');
        return self::SUCCESS;
    }
}
