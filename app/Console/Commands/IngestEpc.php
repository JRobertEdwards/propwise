<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class IngestEpc extends Command
{
    protected $signature = 'ingest:epc {--file= : Path to a pre-downloaded EPC bulk CSV file}';
    protected $description = 'Import EPC certificate data and run address matching';

    public function handle(): int
    {
        $file = $this->option('file');

        if (! $file) {
            $this->error('Specify a CSV with --file=<path>.');
            $this->line('Download the EPC bulk data from https://epc.opendatacommunities.org/ and re-run.');
            return self::FAILURE;
        }

        if (! file_exists($file)) {
            $this->error("File not found: {$file}");
            return self::FAILURE;
        }

        Log::info('IngestEpc: starting import', ['file' => $file]);
        $this->info('Importing EPC certificates...');

        $dbHost = config('database.connections.pgsql.host');
        $importResult = Process::path(base_path('etl'))
            ->timeout(7200)
            ->run('ETL_DB_HOST=' . escapeshellarg($dbHost) . ' python3 import_epc.py ' . escapeshellarg($file));

        if (! $importResult->successful()) {
            Log::error('IngestEpc: import_epc.py failed', ['stderr' => $importResult->errorOutput()]);
            $this->error('import_epc.py failed: ' . $importResult->errorOutput());
            return self::FAILURE;
        }

        $this->info('Running EPC matching...');

        $matchResult = Process::path(base_path('etl'))
            ->timeout(3600)
            ->run('ETL_DB_HOST=' . escapeshellarg($dbHost) . ' python3 match_epc.py');

        if (! $matchResult->successful()) {
            Log::error('IngestEpc: match_epc.py failed', ['stderr' => $matchResult->errorOutput()]);
            $this->error('match_epc.py failed: ' . $matchResult->errorOutput());
            return self::FAILURE;
        }

        Log::info('IngestEpc: complete');
        $this->info('Done.');
        return self::SUCCESS;
    }
}
