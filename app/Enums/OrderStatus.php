<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The internal order pipeline. Thirteen states in the workshop; the customer is
 * shown five. Internal granularity stays internal — a customer does not need to
 * know the difference between "fabric sourced" and "cutting", only that their
 * garment is being made.
 */
enum OrderStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case QuoteAccepted = 'quote_accepted';
    case PaymentPending = 'payment_pending';
    case Paid = 'paid';
    case FabricSourced = 'fabric_sourced';
    case Cutting = 'cutting';
    case Sewing = 'sewing';
    case QualityCheck = 'quality_check';
    case Ready = 'ready';
    case Shipped = 'shipped';
    case Delivered = 'delivered';
    case Closed = 'closed';
    case OnHold = 'on_hold';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Submitted => 'Submitted',
            self::QuoteAccepted => 'Quote accepted',
            self::PaymentPending => 'Awaiting payment',
            self::Paid => 'Paid',
            self::FabricSourced => 'Fabric sourced',
            self::Cutting => 'Cutting',
            self::Sewing => 'Sewing',
            self::QualityCheck => 'Quality check',
            self::Ready => 'Ready',
            self::Shipped => 'Shipped',
            self::Delivered => 'Delivered',
            self::Closed => 'Closed',
            self::OnHold => 'On hold',
            self::Cancelled => 'Cancelled',
            self::Refunded => 'Refunded',
        };
    }

    /** The five stages a customer sees on their timeline. */
    public function customerStage(): CustomerStage
    {
        return match ($this) {
            self::Draft, self::Submitted, self::QuoteAccepted,
            self::PaymentPending, self::Paid => CustomerStage::Received,
            self::FabricSourced, self::Cutting => CustomerStage::FabricAndCutting,
            self::Sewing => CustomerStage::Sewing,
            self::QualityCheck, self::Ready => CustomerStage::QualityCheck,
            self::Shipped, self::Delivered, self::Closed => CustomerStage::OnItsWay,
            self::OnHold => CustomerStage::OnHold,
            self::Cancelled, self::Refunded => CustomerStage::Cancelled,
        };
    }

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Submitted, self::Cancelled],
            self::Submitted => [self::QuoteAccepted, self::OnHold, self::Cancelled],
            self::QuoteAccepted => [self::PaymentPending, self::OnHold, self::Cancelled],
            self::PaymentPending => [self::Paid, self::OnHold, self::Cancelled],
            self::Paid => [self::FabricSourced, self::OnHold, self::Refunded],
            self::FabricSourced => [self::Cutting, self::OnHold, self::Refunded],
            self::Cutting => [self::Sewing, self::OnHold],
            self::Sewing => [self::QualityCheck, self::OnHold],
            self::QualityCheck => [self::Ready, self::Sewing, self::OnHold],
            self::Ready => [self::Shipped, self::OnHold],
            self::Shipped => [self::Delivered, self::OnHold],
            self::Delivered => [self::Closed, self::Refunded],
            self::Closed => [],
            self::OnHold => [
                self::Submitted, self::QuoteAccepted, self::PaymentPending, self::Paid,
                self::FabricSourced, self::Cutting, self::Sewing, self::QualityCheck,
                self::Ready, self::Shipped, self::Cancelled, self::Refunded,
            ],
            self::Cancelled => [],
            self::Refunded => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    /**
     * Once an order leaves draft it is frozen.
     *
     * Submission is the moment the measurements, the item prices and the FX
     * rate are snapshotted onto the order. A later catalogue change — a new
     * per-yard price, a sewing increase — must not reach back and reprice a
     * garment somebody has already committed to. The customer stops being able
     * to edit at the same instant, for the same reason.
     */
    public function isLocked(): bool
    {
        return $this !== self::Draft;
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Closed, self::Cancelled, self::Refunded], true);
    }

    public function isPaid(): bool
    {
        return ! in_array($this, [
            self::Draft, self::Submitted, self::QuoteAccepted,
            self::PaymentPending, self::Cancelled,
        ], true);
    }

    /** @return list<self> */
    public static function workshopStages(): array
    {
        return [
            self::Paid, self::FabricSourced, self::Cutting, self::Sewing,
            self::QualityCheck, self::Ready, self::Shipped, self::Delivered,
        ];
    }
}
