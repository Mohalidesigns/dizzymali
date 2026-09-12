<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
 * Feature tests get the framework and a clean database per test.
 *
 * Unit tests get neither. The pricing engine, the yardage calculator and the
 * Money value object are pure by design, and keeping their tests framework-free
 * is what proves it.
 */
pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Feature');
