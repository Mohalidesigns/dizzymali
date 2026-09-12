<?php

declare(strict_types=1);

namespace App\Domain\Messaging;

/**
 * A WhatsApp message, expressed in terms of a template key rather than a
 * provider's template name.
 *
 * The codebase never knows what Meta approved the template as — that mapping
 * lives in config. When approval comes through, or if we move to a different
 * provider entirely, nothing that builds a message has to change.
 */
final readonly class WhatsAppMessage
{
    /**
     * @param  list<string>  $parameters  Ordered body variables for the template.
     */
    public function __construct(
        public string $to,
        public string $templateKey,
        public array $parameters = [],
        public ?string $locale = null,
        /** Plain-text equivalent, used by the log driver and as a session-message fallback. */
        public string $preview = '',
    ) {}

    /** E.164 without the leading +, which is what every provider actually wants. */
    public function normalisedTo(): string
    {
        $digits = preg_replace('/\D+/', '', $this->to) ?? '';

        // A Nigerian number typed as 08012345678 is +234 801 234 5678.
        if (str_starts_with($digits, '0') && strlen($digits) === 11) {
            return '234'.substr($digits, 1);
        }

        return $digits;
    }
}
