<?php

namespace Tests\Unit\Console\Commands;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class IngestPostcodesTest extends TestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();
        @unlink(storage_path('app/ingestion/postcodes/codepoint.zip'));
        @unlink(storage_path('app/ingestion/postcodes/combined.csv'));
    }

    #[Test]
    public function fails_when_os_api_key_not_configured(): void
    {
        config(['services.os.api_key' => null]);

        $this->artisan('ingest:postcodes')->assertFailed();

        Http::assertNothingSent();
    }

    #[Test]
    public function fetches_download_url_from_os_api(): void
    {
        config(['services.os.api_key' => 'test-key']);

        Http::fake([
            'api.os.uk/downloads/*' => Http::response([
                ['format' => 'CSV', 'area' => 'GB', 'url' => 'https://cdn.os.uk/codepoint.zip'],
            ], 200),
            'cdn.os.uk/*' => Http::response('', 200),
        ]);
        Process::fake(['*' => Process::result('', '', 0)]);

        $this->artisan('ingest:postcodes')->assertSuccessful();

        Http::assertSent(fn ($req) => str_contains($req->url(), 'api.os.uk/downloads'));
    }

    #[Test]
    public function fails_when_os_api_returns_error(): void
    {
        config(['services.os.api_key' => 'test-key']);

        Http::fake(['api.os.uk/*' => Http::response('', 401)]);

        $this->artisan('ingest:postcodes')->assertFailed();

        Process::assertNothingRan();
    }

    #[Test]
    public function fails_when_no_csv_gb_entry_in_api_response(): void
    {
        config(['services.os.api_key' => 'test-key']);

        Http::fake([
            'api.os.uk/*' => Http::response([
                ['format' => 'GML', 'area' => 'GB', 'url' => 'https://cdn.os.uk/codepoint.gml.zip'],
            ], 200),
        ]);

        $this->artisan('ingest:postcodes')->assertFailed();
    }

    #[Test]
    public function runs_import_postcodes_script(): void
    {
        config(['services.os.api_key' => 'test-key']);

        Http::fake([
            'api.os.uk/*' => Http::response([
                ['format' => 'CSV', 'area' => 'GB', 'url' => 'https://cdn.os.uk/codepoint.zip'],
            ], 200),
            'cdn.os.uk/*' => Http::response('', 200),
        ]);
        Process::fake(['*' => Process::result('', '', 0)]);

        $this->artisan('ingest:postcodes')->assertSuccessful();

        Process::assertRan(fn ($proc) => str_contains($proc->command, 'import_postcodes.py'));
    }
}
