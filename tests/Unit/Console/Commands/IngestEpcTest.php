<?php

namespace Tests\Unit\Console\Commands;

use Illuminate\Support\Facades\Process;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class IngestEpcTest extends TestCase
{
    #[Test]
    public function fails_when_no_file_option_provided(): void
    {
        $this->artisan('ingest:epc')->assertFailed();

        Process::assertNothingRan();
    }

    #[Test]
    public function fails_when_file_does_not_exist(): void
    {
        $this->artisan('ingest:epc', ['--file' => '/nonexistent/epc.csv'])->assertFailed();

        Process::assertNothingRan();
    }

    #[Test]
    public function runs_import_and_match_scripts_on_valid_file(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'epc_test_');
        Process::fake(['*' => Process::result('', '', 0)]);

        $this->artisan('ingest:epc', ['--file' => $file])->assertSuccessful();

        Process::assertRan(fn ($proc) => str_contains($proc->command, 'import_epc.py'));
        Process::assertRan(fn ($proc) => str_contains($proc->command, 'match_epc.py'));

        @unlink($file);
    }

    #[Test]
    public function fails_when_import_epc_exits_non_zero(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'epc_test_');
        Process::fake(['*import_epc*' => Process::result('', 'error', 1)]);

        $this->artisan('ingest:epc', ['--file' => $file])->assertFailed();

        @unlink($file);
    }

    #[Test]
    public function fails_when_match_epc_exits_non_zero(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'epc_test_');
        Process::fake([
            '*import_epc*' => Process::result('', '', 0),
            '*match_epc*' => Process::result('', 'match failed', 1),
        ]);

        $this->artisan('ingest:epc', ['--file' => $file])->assertFailed();

        @unlink($file);
    }
}
