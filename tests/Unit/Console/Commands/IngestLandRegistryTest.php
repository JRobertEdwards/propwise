<?php

namespace Tests\Unit\Console\Commands;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class IngestLandRegistryTest extends TestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();
        @unlink(storage_path('app/ingestion/land-registry/pp.csv'));
    }

    #[Test]
    public function downloads_monthly_update_url_by_default(): void
    {
        Http::fake(['*' => Http::response('', 200)]);
        Process::fake(['*' => Process::result('', '', 0)]);

        $this->artisan('ingest:land-registry')->assertSuccessful();

        Http::assertSent(
            fn ($req) => str_contains($req->url(), 'pp-monthly-update-new-version.csv')
        );
    }

    #[Test]
    public function downloads_complete_dataset_with_full_flag(): void
    {
        Http::fake(['*' => Http::response('', 200)]);
        Process::fake(['*' => Process::result('', '', 0)]);

        $this->artisan('ingest:land-registry', ['--full' => true])->assertSuccessful();

        Http::assertSent(fn ($req) => str_contains($req->url(), 'pp-complete.csv'));
    }

    #[Test]
    public function runs_import_land_registry_script(): void
    {
        Http::fake(['*' => Http::response('', 200)]);
        Process::fake(['*' => Process::result('', '', 0)]);

        $this->artisan('ingest:land-registry')->assertSuccessful();

        Process::assertRan(fn ($proc) => str_contains($proc->command, 'import_land_registry.py'));
    }

    #[Test]
    public function fails_when_download_returns_non_200(): void
    {
        Http::fake(['*' => Http::response('', 503)]);

        $this->artisan('ingest:land-registry')->assertFailed();

        Process::assertNothingRan();
    }

    #[Test]
    public function fails_when_etl_exits_non_zero(): void
    {
        Http::fake(['*' => Http::response('', 200)]);
        Process::fake(['*' => Process::result('', 'connection refused', 1)]);

        $this->artisan('ingest:land-registry')->assertFailed();
    }
}
