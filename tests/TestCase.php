<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Storage;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    /**
     * No test may reach the stored files of the real application: test
     * records reuse the ids of real labels and releases, so code that
     * cleans up after them would remove real masters.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Real masters live on both: no test may ever reach the server's disk or the bucket.
        Storage::fake('local');
        Storage::fake('s3');
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
