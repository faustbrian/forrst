<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Forrst\Data;

use Cline\Forrst\Exceptions\InvalidEnumValueException;
use Cline\Forrst\Exceptions\InvalidFieldTypeException;
use Cline\Forrst\Exceptions\InvalidFieldValueException;
use Cline\Forrst\Exceptions\MissingRequiredFieldException;

use function array_filter;
use function in_array;
use function is_int;

/**
 * Health status value object.
 *
 * Provides type-safe representation of health check results with validation
 * and convenience methods for status inspection. Ensures consistent health
 * status structure across all health checkers.
 *
 * @author Brian Faust <brian@cline.sh>
 * @psalm-immutable
 */
final readonly class HealthStatus
{
    /**
     * Create a new health status instance.
     *
     * @param 'degraded'|'healthy'|'unhealthy'     $status    Health status value
     * @param null|array{value: int, unit: string} $latency   Optional latency metrics
     * @param null|string                          $message   Optional diagnostic message
     * @param null|string                          $lastCheck Optional last check timestamp
     *
     * @throws InvalidEnumValueException     If latency unit is invalid
     * @throws InvalidEnumValueException     If status value is invalid
     * @throws InvalidFieldTypeException     If latency value is not an integer
     * @throws InvalidFieldValueException    If latency value is negative
     * @throws MissingRequiredFieldException If required latency fields are missing
     */
    public function __construct(
        public string $status,
        public ?array $latency = null,
        public ?string $message = null,
        public ?string $lastCheck = null,
    ) {
        if (!in_array($status, ['healthy', 'degraded', 'unhealthy'], true)) {
            throw InvalidEnumValueException::forField('status', ['healthy', 'degraded', 'unhealthy']);
        }

        if ($latency === null) {
            return;
        }

        if (!isset($latency['value'], $latency['unit'])) {
            throw MissingRequiredFieldException::forField('latency.value and latency.unit');
        }

        if (!is_int($latency['value'])) {
            throw InvalidFieldTypeException::forField('latency.value', 'integer', $latency['value']);
        }

        if ($latency['value'] < 0) {
            throw InvalidFieldValueException::forField('latency.value', 'must be non-negative');
        }

        if (!in_array($latency['unit'], ['ms', 'us', 's'], true)) {
            throw InvalidEnumValueException::forField('latency.unit', ['ms', 'us', 's']);
        }
    }

    /**
     * Convert health status to array representation.
     *
     * @return array{status: string, latency?: array{value: int, unit: string}, message?: string, last_check?: string}
     */
    public function toArray(): array
    {
        return array_filter([
            'status' => $this->status,
            'latency' => $this->latency,
            'message' => $this->message,
            'last_check' => $this->lastCheck,
        ], fn (string|array|null $value): bool => $value !== null);
    }

    /**
     * Check if component is healthy.
     */
    public function isHealthy(): bool
    {
        return $this->status === 'healthy';
    }

    /**
     * Check if component is degraded.
     */
    public function isDegraded(): bool
    {
        return $this->status === 'degraded';
    }

    /**
     * Check if component is unhealthy.
     */
    public function isUnhealthy(): bool
    {
        return $this->status === 'unhealthy';
    }
}
