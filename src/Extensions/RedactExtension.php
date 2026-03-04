<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Forrst\Extensions;

use Cline\Forrst\Data\ErrorData;
use Cline\Forrst\Data\ExtensionData;
use Cline\Forrst\Data\ResponseData;
use Cline\Forrst\Enums\ErrorCode;
use Cline\Forrst\Events\FunctionExecuted;
use Cline\Forrst\Events\RequestValidated;
use Override;

use function array_filter;
use function array_merge;
use function array_unique;
use function assert;
use function count;
use function explode;
use function implode;
use function in_array;
use function is_array;
use function is_int;
use function is_string;
use function mb_strlen;
use function mb_substr;
use function mb_trim;
use function preg_replace;
use function preg_split;
use function sprintf;
use function str_contains;
use function str_repeat;

/**
 * Redaction mode enum.
 *
 * Defines the redaction strategy for sensitive data in responses.
 * @author Brian Faust <brian@cline.sh>
 */
enum RedactionMode: string
{
    /** Replace entire value with mask (***) */
    case Full = 'full';

    /** Show partial value (e.g., last 4 digits of card, first letter of email) */
    case Partial = 'partial';

    /** No redaction, return raw values (requires elevated permissions) */
    case None = 'none';
}

/**
 * Redact extension handler.
 *
 * Controls sensitive data handling in responses and logs to ensure compliance with
 * security and privacy requirements (PCI-DSS, GDPR, HIPAA). Supports full masking,
 * partial redaction, and authorized unredacted access. Automatically redacts common
 * sensitive fields (passwords, tokens, card numbers, PII) with customizable patterns.
 *
 * Request options:
 * - mode: Redaction mode ('full', 'partial', 'none')
 * - fields: Specific fields to redact (overrides defaults if provided)
 * - purpose: Explanation for why unredacted data is needed (logged for audit trail)
 *
 * Response data:
 * - mode: Redaction mode that was applied to this response
 * - redacted_fields: Array of field paths that were redacted
 * - policy: Name of the redaction policy applied (default, authorized_access, etc.)
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @see https://docs.cline.sh/forrst/extensions/redact
 */
final class RedactExtension extends AbstractExtension
{
    /**
     * Replacement strings for redaction.
     */
    public const string FULL_MASK = '***';

    /**
     * Default fields to redact by category.
     */
    public const array DEFAULT_REDACTED_FIELDS = [
        'authentication' => ['password', 'secret', 'token', 'api_key'],
        'payment' => ['card_number', 'cvv', 'account_number'],
        'pii' => ['ssn', 'tax_id', 'passport_number'],
        'contact' => ['email', 'phone'],
    ];

    /**
     * Redaction patterns for partial mode.
     *
     * @var array<string, callable>
     */
    private array $partialPatterns;

    /**
     * Resolved redaction state for current request.
     *
     * @var array{mode: RedactionMode, fields: array<int, string>, policy: string, redacted_fields: array<int, string>}
     */
    private array $state;

    /**
     * Create a new extension instance.
     *
     * @param array<int, string> $unredactedScopes OAuth/permission scopes required for unredacted data
     *                                             access. Used to authorize mode='none' requests and
     *                                             control access to sensitive data.
     * @param string             $defaultPolicy    Default redaction policy name used for audit logging
     *                                             and policy tracking in compliance reports.
     */
    public function __construct(
        /**
         * Scopes required for unredacted access.
         *
         * @var array<int, string>
         */
        private readonly array $unredactedScopes = ['pii:read:unredacted'],
        private readonly string $defaultPolicy = 'default',
    ) {
        $this->initializePartialPatterns();
        $this->resetState();
    }

    /**
     * {@inheritDoc}
     */
    #[Override()]
    public function getUrn(): string
    {
        return ExtensionUrn::Redact->value;
    }

    /**
     * {@inheritDoc}
     */
    #[Override()]
    public function getSubscribedEvents(): array
    {
        return [
            RequestValidated::class => [
                'priority' => 40,
                'method' => 'onRequestValidated',
            ],
            FunctionExecuted::class => [
                'priority' => 50, // Run before locale to redact before formatting
                'method' => 'onFunctionExecuted',
            ],
        ];
    }

    /**
     * Validate redaction options and check authorization on request validation.
     *
     * Processes redaction mode from request options, enforces authorization for
     * unredacted access, and prepares the redaction state for response processing.
     *
     * @param RequestValidated $event Event containing validated request data
     */
    public function onRequestValidated(RequestValidated $event): void
    {
        $extension = $event->request->getExtension(ExtensionUrn::Redact->value);

        if (!$extension instanceof ExtensionData) {
            return;
        }

        $options = $extension->options ?? [];
        $modeValue = $options['mode'] ?? RedactionMode::Full->value;
        assert(is_string($modeValue) || is_int($modeValue));
        $mode = RedactionMode::tryFrom($modeValue) ?? RedactionMode::Full;

        // Check authorization for unredacted access
        // In a real implementation, check request context for scopes
        // For now, we'll provide a hook point
        if ($mode === RedactionMode::None && !$this->isUnredactedAccessAllowed()) {
            $event->setResponse(ResponseData::error(
                new ErrorData(
                    code: ErrorCode::Forbidden,
                    message: 'Unredacted access requires elevated permissions',
                    details: [
                        'required_scope' => $this->unredactedScopes[0] ?? 'pii:read:unredacted',
                    ],
                ),
                $event->request->id,
                extensions: [
                    ExtensionData::response(ExtensionUrn::Redact->value, [
                        'mode' => RedactionMode::Full->value,
                        'policy' => 'access_denied',
                    ]),
                ],
            ));
            $event->stopPropagation();

            return;
        }

        // Store resolved state
        /** @var array<int, string> $fields */
        $fields = $options['fields'] ?? $this->getDefaultFieldsList();
        $this->state = [
            'mode' => $mode,
            'fields' => $fields,
            'policy' => $this->defaultPolicy,
            'redacted_fields' => [],
        ];

        // Log purpose if provided (for audit)
        if (!isset($options['purpose']) || $mode !== RedactionMode::None) {
            return;
        }

        $this->state['policy'] = 'authorized_access';
    }

    /**
     * Apply redaction to response and add metadata after execution.
     *
     * Recursively processes the response result to redact sensitive fields based
     * on the configured mode and field list. Enriches the response with redaction
     * metadata indicating which fields were masked.
     *
     * @param FunctionExecuted $event Event containing request and response data
     */
    public function onFunctionExecuted(FunctionExecuted $event): void
    {
        $extension = $event->request->getExtension(ExtensionUrn::Redact->value);

        if (!$extension instanceof ExtensionData) {
            return;
        }

        // Apply redaction to result if mode is not none
        $result = $event->getResponse()->result;
        $redactedFields = [];

        if ($this->state['mode'] !== RedactionMode::None && $result !== null) {
            [$result, $redactedFields] = $this->redactValue($result, $this->state['fields']);
        }

        // Build extension response
        $extensionData = array_filter([
            'mode' => $this->state['mode']->value,
            'redacted_fields' => $redactedFields ?: null,
            'policy' => $this->state['policy'],
        ], fn ($v): bool => $v !== null);

        $extensions = $event->getResponse()->extensions ?? [];
        $extensions[] = ExtensionData::response(ExtensionUrn::Redact->value, $extensionData);

        $event->setResponse(
            new ResponseData(
                protocol: $event->getResponse()->protocol,
                id: $event->getResponse()->id,
                result: $result,
                errors: $event->getResponse()->errors,
                extensions: $extensions,
                meta: $event->getResponse()->meta,
            ),
        );
    }

    /**
     * Get the current redaction mode.
     */
    public function getMode(): RedactionMode
    {
        return $this->state['mode'];
    }

    /**
     * Get fields that will be redacted.
     *
     * @return array<int, string>
     */
    public function getRedactionFields(): array
    {
        return $this->state['fields'];
    }

    /**
     * Redact a single value based on field name.
     *
     * @param  string $fieldName Field name
     * @param  mixed  $value     Original value
     * @return mixed  Redacted value
     */
    public function redactField(string $fieldName, mixed $value): mixed
    {
        if (!is_string($value)) {
            return self::FULL_MASK;
        }

        if ($this->state['mode'] === RedactionMode::Full) {
            return self::FULL_MASK;
        }

        // Partial redaction
        $pattern = $this->partialPatterns[$fieldName] ?? null;

        if ($pattern !== null) {
            return $pattern($value);
        }

        // Default partial: mask most characters
        $length = mb_strlen($value);

        if ($length <= 4) {
            return self::FULL_MASK;
        }

        return str_repeat('*', $length - 4).mb_substr($value, -4);
    }

    /**
     * Check if a field should be redacted.
     */
    public function shouldRedact(string $fieldName): bool
    {
        if ($this->state['mode'] === RedactionMode::None) {
            return false;
        }

        return in_array($fieldName, $this->state['fields'], true);
    }

    /**
     * Check if unredacted access is allowed.
     *
     * Override this in subclasses to implement actual authorization checks.
     */
    private function isUnredactedAccessAllowed(): bool
    {
        // Default: deny unredacted access
        // Real implementations should check request context for scopes
        return false;
    }

    /**
     * Redact sensitive fields in a value recursively.
     *
     * @param  mixed                                  $value        Value to redact
     * @param  array<int, string>                     $targetFields Fields to redact
     * @param  string                                 $path         Current path for nested tracking
     * @return array{0: mixed, 1: array<int, string>} [redacted value, list of redacted field paths]
     */
    private function redactValue(mixed $value, array $targetFields, string $path = ''): array
    {
        if (!is_array($value)) {
            return [$value, []];
        }

        $redactedFields = [];
        $result = [];

        foreach ($value as $key => $val) {
            $currentPath = $path === '' ? (string) $key : sprintf('%s.%s', $path, $key);

            if (in_array($key, $targetFields, true)) {
                $result[$key] = $this->redactField($key, $val);
                $redactedFields[] = $currentPath;
            } elseif (is_array($val)) {
                [$result[$key], $nestedRedacted] = $this->redactValue($val, $targetFields, $currentPath);
                $redactedFields = array_merge($redactedFields, $nestedRedacted);
            } else {
                $result[$key] = $val;
            }
        }

        return [$result, $redactedFields];
    }

    /**
     * Get flat list of default fields to redact.
     *
     * @return array<int, string>
     */
    private function getDefaultFieldsList(): array
    {
        $fields = [];

        foreach (self::DEFAULT_REDACTED_FIELDS as $categoryFields) {
            $fields = array_merge($fields, $categoryFields);
        }

        return array_unique($fields);
    }

    /**
     * Initialize partial redaction patterns.
     */
    private function initializePartialPatterns(): void
    {
        $this->partialPatterns = [
            'email' => $this->partialEmail(...),
            'phone' => $this->partialPhone(...),
            'card_number' => $this->partialCardNumber(...),
            'ssn' => $this->partialSsn(...),
            'name' => $this->partialName(...),
            'cardholder' => $this->partialName(...),
        ];
    }

    /**
     * Partial redaction for email addresses.
     */
    private function partialEmail(string $email): string
    {
        $parts = explode('@', $email);

        if (count($parts) !== 2) {
            return self::FULL_MASK;
        }

        $local = $parts[0];
        $domain = $parts[1];

        $maskedLocal = $local !== '' ? mb_substr($local, 0, 1).'***' : '***';
        $maskedDomain = '***.'.(str_contains($domain, '.') ? explode('.', $domain)[count(explode('.', $domain)) - 1] : 'com');

        return sprintf('%s@%s', $maskedLocal, $maskedDomain);
    }

    /**
     * Partial redaction for phone numbers.
     */
    private function partialPhone(string $phone): string
    {
        // Remove non-digits
        $digits = preg_replace('/\D/', '', $phone);

        if ($digits === null || mb_strlen($digits) < 4) {
            return self::FULL_MASK;
        }

        return '***-***-'.mb_substr($digits, -4);
    }

    /**
     * Partial redaction for card numbers.
     */
    private function partialCardNumber(string $cardNumber): string
    {
        // Remove non-digits
        $digits = preg_replace('/\D/', '', $cardNumber);

        if ($digits === null || mb_strlen($digits) < 4) {
            return self::FULL_MASK;
        }

        return '****-****-****-'.mb_substr($digits, -4);
    }

    /**
     * Partial redaction for SSN.
     */
    private function partialSsn(string $ssn): string
    {
        // Remove non-digits
        $digits = preg_replace('/\D/', '', $ssn);

        if ($digits === null || mb_strlen($digits) < 4) {
            return self::FULL_MASK;
        }

        return '***-**-'.mb_substr($digits, -4);
    }

    /**
     * Partial redaction for names.
     */
    private function partialName(string $name): string
    {
        $parts = preg_split('/\s+/', mb_trim($name));

        if ($parts === false || $parts === []) {
            return self::FULL_MASK;
        }

        $masked = [];

        foreach ($parts as $part) {
            if (mb_strlen($part) <= 0) {
                continue;
            }

            $masked[] = mb_substr($part, 0, 1).'***';
        }

        return implode(' ', $masked);
    }

    /**
     * Reset state between requests.
     */
    private function resetState(): void
    {
        $this->state = [
            'mode' => RedactionMode::Full,
            'fields' => $this->getDefaultFieldsList(),
            'policy' => $this->defaultPolicy,
            'redacted_fields' => [],
        ];
    }
}
