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

/*
 * Two unit tests reach for framework facades — Http::fake for the gateways and
 * the log channel for the WhatsApp driver — so they need the container booted.
 * They still touch no database, which is the line that matters.
 */
pest()->extend(TestCase::class)->in('Unit/PaymentGatewayTest.php', 'Unit/WhatsAppMessageTest.php');
