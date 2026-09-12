<?php

declare(strict_types=1);

use App\Enums\CustomerStage;
use App\Enums\OrderStatus;

it('walks the happy path from draft to closed', function () {
    $path = [
        OrderStatus::Draft, OrderStatus::Submitted, OrderStatus::QuoteAccepted,
        OrderStatus::PaymentPending, OrderStatus::Paid, OrderStatus::FabricSourced,
        OrderStatus::Cutting, OrderStatus::Sewing, OrderStatus::QualityCheck,
        OrderStatus::Ready, OrderStatus::Shipped, OrderStatus::Delivered, OrderStatus::Closed,
    ];

    for ($i = 0; $i < count($path) - 1; $i++) {
        expect($path[$i]->canTransitionTo($path[$i + 1]))
            ->toBeTrue("{$path[$i]->value} should reach {$path[$i + 1]->value}");
    }
});

it('refuses to skip the queue', function () {
    expect(OrderStatus::Draft->canTransitionTo(OrderStatus::Paid))->toBeFalse()
        ->and(OrderStatus::Submitted->canTransitionTo(OrderStatus::Sewing))->toBeFalse()
        ->and(OrderStatus::Paid->canTransitionTo(OrderStatus::Shipped))->toBeFalse();
});

it('refuses to go backwards', function () {
    expect(OrderStatus::Sewing->canTransitionTo(OrderStatus::Cutting))->toBeFalse()
        ->and(OrderStatus::Delivered->canTransitionTo(OrderStatus::Shipped))->toBeFalse();
});

it('allows quality check to send a garment back to the machine', function () {
    expect(OrderStatus::QualityCheck->canTransitionTo(OrderStatus::Sewing))->toBeTrue();
});

it('treats terminal states as terminal', function () {
    foreach ([OrderStatus::Closed, OrderStatus::Cancelled, OrderStatus::Refunded] as $status) {
        expect($status->isTerminal())->toBeTrue()
            ->and($status->allowedTransitions())->toBe([]);
    }
});

it('cannot cancel an order that is already being cut', function () {
    expect(OrderStatus::Cutting->canTransitionTo(OrderStatus::Cancelled))->toBeFalse()
        ->and(OrderStatus::Sewing->canTransitionTo(OrderStatus::Cancelled))->toBeFalse();
});

it('locks the order the moment it leaves draft', function () {
    // Submission is when measurements, prices and the FX rate are snapshotted.
    // A later catalogue change must not reach back into a committed order, so
    // everything past draft is frozen.
    expect(OrderStatus::Draft->isLocked())->toBeFalse();

    foreach (OrderStatus::cases() as $status) {
        if ($status === OrderStatus::Draft) {
            continue;
        }

        expect($status->isLocked())->toBeTrue("{$status->value} should be locked");
    }
});

it('collapses thirteen internal states into five customer stages', function () {
    expect(OrderStatus::Paid->customerStage())->toBe(CustomerStage::Received)
        ->and(OrderStatus::Cutting->customerStage())->toBe(CustomerStage::FabricAndCutting)
        ->and(OrderStatus::Sewing->customerStage())->toBe(CustomerStage::Sewing)
        ->and(OrderStatus::Ready->customerStage())->toBe(CustomerStage::QualityCheck)
        ->and(OrderStatus::Shipped->customerStage())->toBe(CustomerStage::OnItsWay);
});

it('gives every stage a position on the five-step timeline', function () {
    expect(CustomerStage::Received->position())->toBe(1)
        ->and(CustomerStage::OnItsWay->position())->toBe(5)
        ->and(CustomerStage::timeline())->toHaveCount(5);
});

it('knows which states mean money has been taken', function () {
    expect(OrderStatus::Draft->isPaid())->toBeFalse()
        ->and(OrderStatus::PaymentPending->isPaid())->toBeFalse()
        ->and(OrderStatus::Cancelled->isPaid())->toBeFalse()
        ->and(OrderStatus::Paid->isPaid())->toBeTrue()
        ->and(OrderStatus::Delivered->isPaid())->toBeTrue();
});

it('can put work in progress on hold and bring it back', function () {
    // Everything still in the workshop can be paused. A delivered garment
    // cannot — it is out of our hands and only a refund makes sense there.
    foreach (OrderStatus::workshopStages() as $stage) {
        $expected = $stage !== OrderStatus::Delivered;

        expect($stage->canTransitionTo(OrderStatus::OnHold))
            ->toBe($expected, "{$stage->value} hold behaviour");
    }

    expect(OrderStatus::Delivered->allowedTransitions())
        ->toBe([OrderStatus::Closed, OrderStatus::Refunded]);

    expect(OrderStatus::OnHold->canTransitionTo(OrderStatus::Sewing))->toBeTrue();
});
