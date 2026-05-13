<?php

namespace Tests\Unit\Services;

use App\Models\Postcode;
use App\Repositories\Contracts\PostcodeRepositoryInterface;
use App\Services\PostcodeLookupService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PostcodeLookupServiceTest extends TestCase
{
    private function makeService(PostcodeRepositoryInterface $repository): PostcodeLookupService
    {
        return new PostcodeLookupService($repository);
    }

    #[Test]
    public function returns_postcode_for_known_postcode(): void
    {
        $postcode = new Postcode(['postcode' => 'SW1A1AA', 'latitude' => 51.5, 'longitude' => -0.1]);
        $repo = $this->createMock(PostcodeRepositoryInterface::class);
        $repo->expects($this->once())->method('findByPostcode')->with('SW1A1AA')->willReturn($postcode);

        $result = $this->makeService($repo)->lookup('SW1A1AA');

        $this->assertSame($postcode, $result);
    }

    #[Test]
    public function normalises_postcode_with_space(): void
    {
        $repo = $this->createMock(PostcodeRepositoryInterface::class);
        $repo->expects($this->once())->method('findByPostcode')->with('SW1A1AA');

        $this->makeService($repo)->lookup('SW1A 1AA');
    }

    #[Test]
    public function normalises_lowercase_postcode(): void
    {
        $repo = $this->createMock(PostcodeRepositoryInterface::class);
        $repo->expects($this->once())->method('findByPostcode')->with('SW1A1AA');

        $this->makeService($repo)->lookup('sw1a1aa');
    }

    #[Test]
    public function returns_null_for_unknown_postcode(): void
    {
        $repo = $this->createMock(PostcodeRepositoryInterface::class);
        $repo->method('findByPostcode')->willReturn(null);

        $result = $this->makeService($repo)->lookup('ZZ99ZZZ');

        $this->assertNull($result);
    }
}
