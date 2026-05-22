<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use ZipArchive;

class IngestPostcodes extends Command
{
    protected $signature = 'ingest:postcodes';
    protected $description = 'Download and import OS Code Point Open postcode data (one-time server setup)';

    public function handle(): int
    {
        $apiKey = config('services.os.api_key');

        if (! $apiKey) {
            $this->error('OS_API_KEY is not configured.');
            return self::FAILURE;
        }

        Log::info('IngestPostcodes: fetching download metadata from OS API');
        $this->info('Fetching download URL from OS Data Hub...');

        $meta = Http::timeout(30)
            ->get('https://api.os.uk/downloads/v1/products/CodePointOpen/downloads', ['key' => $apiKey]);

        if (! $meta->successful()) {
            Log::error('IngestPostcodes: OS API failed', ['status' => $meta->status()]);
            $this->error("OS API request failed (HTTP {$meta->status()})");
            return self::FAILURE;
        }

        $entry = collect($meta->json())->first(
            fn ($d) => ($d['format'] ?? '') === 'CSV' && ($d['area'] ?? '') === 'GB'
        );

        if (! $entry || empty($entry['url'])) {
            $this->error('No CSV/GB download entry found in OS API response.');
            return self::FAILURE;
        }

        $dir = storage_path('app/ingestion/postcodes');
        @mkdir($dir, 0775, true);
        $zipPath = "{$dir}/codepoint.zip";
        $combinedPath = "{$dir}/combined.csv";

        $this->info('Downloading Code Point Open zip...');

        $response = Http::sink($zipPath)
            ->timeout(3600)
            ->get($entry['url'], ['key' => $apiKey]);

        if (! $response->successful()) {
            Log::error('IngestPostcodes: zip download failed', ['status' => $response->status()]);
            $this->error("Zip download failed (HTTP {$response->status()})");
            @unlink($zipPath);
            return self::FAILURE;
        }

        $this->info('Combining CSV files from zip...');

        $combined = fopen($combinedPath, 'w');
        $zip = new ZipArchive();

        if ($zip->open($zipPath) !== true) {
            $size = file_exists($zipPath) ? filesize($zipPath) : 0;
            $preview = $size > 0 ? substr(file_get_contents($zipPath), 0, 500) : '(empty)';
            $this->error("Failed to open zip (size: {$size} bytes). Content preview:");
            $this->line($preview);
            @unlink($zipPath);
            return self::FAILURE;
        }

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (preg_match('/Data\/CSV\/.+\.csv$/i', $name)) {
                fwrite($combined, $zip->getFromIndex($i));
            }
        }

        $zip->close();
        fclose($combined);
        @unlink($zipPath);

        $this->info('Running ETL...');

        $dbHost = config('database.connections.pgsql.host');
        $result = Process::path(base_path('etl'))
            ->timeout(3600)
            ->run('ETL_DB_HOST=' . escapeshellarg($dbHost) . ' python3 import_postcodes.py ' . escapeshellarg($combinedPath));

        @unlink($combinedPath);

        if (! $result->successful()) {
            Log::error('IngestPostcodes: ETL failed', ['stderr' => $result->errorOutput()]);
            $this->error('ETL failed: ' . $result->errorOutput());
            return self::FAILURE;
        }

        Log::info('IngestPostcodes: complete');
        $this->info('Done.');
        return self::SUCCESS;
    }
}
