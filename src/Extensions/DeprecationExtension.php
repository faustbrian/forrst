<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Forrst\Extensions;

use Carbon\CarbonImmutable;
use Cline\Forrst\Data\ExtensionData;
use Cline\Forrst\Data\ResponseData;
use Cline\Forrst\Events\FunctionExecuted;
use Override;

use function array_filter;
use function in_array;
use function is_array;
use function is_string;
use function sprintf;

/**
 * Deprecation extension handler.
 *
 * Warns clients about deprecated functions, versions, or features.
 * Servers proactively inform clients of upcoming changes.
 *
 * Request options:
 * - acknowledge: array of deprecation URIs to suppress
 *
 * Response data:
 * - warnings: array of deprecation warnings
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @see https://docs.cline.sh/specs/forrst/extensions/deprecation
 */
final class DeprecationExtension extends AbstractExtension
{
    /**
     * Deprecation types.
     */
    public const string TYPE_FUNCTION = 'function';

    public const string TYPE_VERSION = 'version';

    public const string TYPE_ARGUMENT = 'argument';

    public const string TYPE_FIELD = 'field';

    /**
     * Maximum number of warnings to include in response.
     */
    private const int MAX_WARNINGS = 10;

    /**
     * Registered deprecation warnings.
     *
     * @var array<string, array<string, mixed>>
     */
    private array $warnings = [];

    #[Override()]
    public function getUrn(): string
    {
        return ExtensionUrn::Deprecation->value;
    }

    #[Override()]
    public function isErrorFatal(): bool
    {
        return false;
    }

    #[Override()]
    public function getSubscribedEvents(): array
    {
        return [
            FunctionExecuted::class => [
                'priority' => 210,
                'method' => 'onFunctionExecuted',
            ],
        ];
    }

    /**
     * Add deprecation warnings to response after execution.
     *
     * Filters warnings to only those applicable to the executed function/version
     * and not acknowledged by the client. Enriches response with warning metadata
     * including sunset dates, replacements, and migration documentation links.
     * Also prunes expired warnings.
     *
     * @param FunctionExecuted $event Function execution event with response
     */
    public function onFunctionExecuted(FunctionExecuted $event): void
    {
        // Prune expired warnings before processing
        $this->pruneExpiredWarnings();

        $acknowledgedUrns = $this->getAcknowledgedUrns($event->extension->options);
        $applicableWarnings = $this->getApplicableWarnings(
            $event->request->call->function,
            $event->request->call->version,
            $acknowledgedUrns,
        );

        if ($applicableWarnings === []) {
            return;
        }

        $extensions = $event->getResponse()->extensions ?? [];
        $extensions[] = ExtensionData::response(ExtensionUrn::Deprecation->value, [
            'warnings' => $applicableWarnings,
        ]);

        $event->setResponse(
            new ResponseData(
                protocol: $event->getResponse()->protocol,
                id: $event->getResponse()->id,
                result: $event->getResponse()->result,
                errors: $event->getResponse()->errors,
                extensions: $extensions,
                meta: $event->getResponse()->meta,
            ),
        );
    }

    /**
     * Register a deprecation warning.
     *
     * Creates a structured deprecation warning with optional sunset date (when feature
     * will be removed), replacement suggestion, and migration documentation URL. Warnings
     * are matched to requests based on type and target.
     *
     * @param string                    $urn           Unique identifier for this warning (e.g., "deprecation:user.get")
     * @param string                    $type          Deprecation type (function, version, argument, field)
     * @param string                    $target        What is deprecated (function name, version string, etc.)
     * @param string                    $message       Human-readable explanation of the deprecation
     * @param null|string               $sunsetDate    ISO 8601 date when removal occurs
     * @param null|array<string, mixed> $replacement   Suggested alternative (function/version structure)
     * @param null|string               $documentation URL to migration guide or documentation
     *
     * @return self Fluent interface for chaining registrations
     */
    public function registerWarning(
        string $urn,
        string $type,
        string $target,
        string $message,
        ?string $sunsetDate = null,
        ?array $replacement = null,
        ?string $documentation = null,
    ): self {
        $warning = [
            'urn' => $urn,
            'type' => $type,
            'target' => $target,
            'message' => $message,
        ];

        if ($sunsetDate !== null) {
            $warning['sunset_date'] = $sunsetDate;
        }

        if ($replacement !== null) {
            $warning['replacement'] = $replacement;
        }

        if ($documentation !== null) {
            $warning['documentation'] = $documentation;
        }

        $this->warnings[$urn] = $warning;

        return $this;
    }

    /**
     * Register a function deprecation.
     *
     * Convenience method for deprecating entire functions. Automatically generates
     * URN and builds replacement structure from function/version parameters.
     *
     * @param string      $function       Deprecated function name
     * @param string      $message        Human-readable deprecation explanation
     * @param null|string $sunsetDate     ISO 8601 date when function will be removed
     * @param null|string $replacementFn  Replacement function name to suggest
     * @param null|string $replacementVer Replacement function version to suggest
     *
     * @return self Fluent interface for chaining registrations
     */
    public function deprecateFunction(
        string $function,
        string $message,
        ?string $sunsetDate = null,
        ?string $replacementFn = null,
        ?string $replacementVer = null,
    ): self {
        $replacement = null;

        if ($replacementFn !== null) {
            $replacement = ['function' => $replacementFn];

            if ($replacementVer !== null) {
                $replacement['version'] = $replacementVer;
            }
        }

        return $this->registerWarning(
            urn: 'deprecation:'.$function,
            type: self::TYPE_FUNCTION,
            target: $function,
            message: $message,
            sunsetDate: $sunsetDate,
            replacement: $replacement,
        );
    }

    /**
     * Register a version deprecation.
     *
     * Convenience method for deprecating specific versions of a function. Automatically
     * generates URN and target string in "function@version" format.
     *
     * @param string      $function       Function name
     * @param string      $version        Deprecated version string
     * @param string      $message        Human-readable deprecation explanation
     * @param null|string $sunsetDate     ISO 8601 date when version will be removed
     * @param null|string $replacementVer Replacement version to suggest (same function)
     *
     * @return self Fluent interface for chaining registrations
     */
    public function deprecateVersion(
        string $function,
        string $version,
        string $message,
        ?string $sunsetDate = null,
        ?string $replacementVer = null,
    ): self {
        $replacement = null;

        if ($replacementVer !== null) {
            $replacement = ['function' => $function, 'version' => $replacementVer];
        }

        return $this->registerWarning(
            urn: sprintf('deprecation:%s:v%s', $function, $version),
            type: self::TYPE_VERSION,
            target: sprintf('%s@%s', $function, $version),
            message: $message,
            sunsetDate: $sunsetDate,
            replacement: $replacement,
        );
    }

    /**
     * Get acknowledged URNs from extension options.
     *
     * Clients can suppress specific warnings they've already handled by providing
     * URNs in the acknowledge array. This prevents duplicate warnings. Validates
     * that the acknowledge value is an array of strings.
     *
     * @param null|array<string, mixed> $options Extension options from request
     *
     * @return array<int, string> List of warning URNs to suppress
     */
    private function getAcknowledgedUrns(?array $options): array
    {
        $acknowledge = $options['acknowledge'] ?? [];

        // Validate that it's an array
        if (!is_array($acknowledge)) {
            return [];
        }

        // Filter to only strings
        return array_filter($acknowledge, is_string(...));
    }

    /**
     * Get applicable warnings for a function/version.
     *
     * Filters registered warnings to only those that match the executed function
     * and version, excluding any warnings the client has acknowledged. Limits
     * the number of warnings to prevent response bloat.
     *
     * @param string             $function         Function name
     * @param null|string        $version          Function version
     * @param array<int, string> $acknowledgedUrns URNs to suppress
     *
     * @return array<int, array<string, mixed>> Warnings to include in response
     */
    private function getApplicableWarnings(string $function, ?string $version, array $acknowledgedUrns): array
    {
        $applicable = [];
        $count = 0;

        foreach ($this->warnings as $urn => $warning) {
            // Stop if we've reached the maximum number of warnings
            if ($count >= self::MAX_WARNINGS) {
                break;
            }

            // Skip acknowledged warnings
            if (in_array($urn, $acknowledgedUrns, true)) {
                continue;
            }

            // Check if warning applies to this function/version
            if (!$this->warningApplies($warning, $function, $version)) {
                continue;
            }

            $applicable[] = $warning;
            ++$count;
        }

        return $applicable;
    }

    /**
     * Check if a warning applies to a function/version.
     *
     * Matches warnings to requests based on type and target. Function-level warnings
     * apply to all versions, while version-level warnings only match specific versions.
     *
     * @param array<string, mixed> $warning  Warning data structure
     * @param string               $function Function name being executed
     * @param null|string          $version  Version being executed
     *
     * @return bool True if warning should be included in response
     */
    private function warningApplies(array $warning, string $function, ?string $version): bool
    {
        $target = $warning['target'];

        // Function deprecation
        if ($warning['type'] === self::TYPE_FUNCTION && $target === $function) {
            return true;
        }

        // Version deprecation
        return $warning['type'] === self::TYPE_VERSION && $target === sprintf('%s@%s', $function, $version);
    }

    /**
     * Prune expired warnings.
     *
     * Removes warnings for features that have already been removed (past their
     * sunset date). This prevents memory leaks in long-running processes and
     * removes warnings for features that no longer exist.
     */
    private function pruneExpiredWarnings(): void
    {
        CarbonImmutable::now();

        foreach ($this->warnings as $urn => $warning) {
            if (!isset($warning['sunset_date'])) {
                continue;
            }

            $sunsetDate = CarbonImmutable::parse($warning['sunset_date']);

            // Remove warnings for features already removed (past sunset)
            if (!$sunsetDate->isPast()) {
                continue;
            }

            unset($this->warnings[$urn]);
        }
    }
}
