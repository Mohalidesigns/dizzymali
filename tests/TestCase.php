<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Inertia pages render through app.blade.php, which calls @vite. The
        // suite must not depend on a dev server being up or on a production
        // build existing, so the tag is stubbed out for every test.
        $this->withoutVite();
    }
}
