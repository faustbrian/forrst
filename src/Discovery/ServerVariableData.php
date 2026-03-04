<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Forrst\Discovery;

use Cline\Forrst\Exceptions\EmptyArrayException;
use Cline\Forrst\Exceptions\EmptyFieldException;
use Cline\Forrst\Exceptions\InvalidFieldTypeException;
use Cline\Forrst\Exceptions\InvalidFieldValueException;
use Spatie\LaravelData\Data;

use function implode;
use function in_array;
use function is_string;
use function mb_trim;
use function sprintf;

/**
 * Server URL template variable for the Forrst discovery document.
 *
 * Represents a variable used in server URL templates. Variables allow
 * server URLs to be parameterized, enabling dynamic host names, ports,
 * base paths, or protocol schemes. Each variable has a default value
 * and optionally an enumeration of allowed values.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @see https://docs.cline.sh/specs/forrst/discovery#server-variable-object
 * @see https://docs.cline.sh/forrst/
 */
final class ServerVariableData extends Data
{
    /**
     * Create a new server variable instance.
     *
     * @param string                  $default     Default value for the variable used when no value is
     *                                             explicitly provided. This value MUST be one of the enum
     *                                             values if an enumeration is specified. Required for all
     *                                             server variables to ensure valid URL construction.
     * @param null|array<int, string> $enum        Enumeration of allowed values for the variable. When specified,
     *                                             variable substitutions MUST use one of these values. Used to
     *                                             restrict variables to known-safe values like environments
     *                                             ('production', 'staging') or API versions ('v1', 'v2').
     * @param null|string             $description Human-readable description explaining the variable's purpose,
     *                                             constraints, and usage. Should clarify what the variable
     *                                             controls and provide examples of valid values when helpful.
     */
    public function __construct(
        public readonly string $default,
        public readonly ?array $enum = null,
        public readonly ?string $description = null,
    ) {
        // Validate default is not empty
        if (mb_trim($this->default) === '') {
            throw EmptyFieldException::forField('default');
        }

        // Validate enum list if provided
        if ($this->enum === null) {
            return;
        }

        if ($this->enum === []) {
            throw EmptyArrayException::forField('enum');
        }

        // Enum must contain only strings
        foreach ($this->enum as $index => $value) {
            if (!is_string($value)) {
                throw InvalidFieldTypeException::forField(
                    sprintf('enum[%d]', $index),
                    'string',
                    $value,
                );
            }
        }

        // Default MUST be in enum list
        if (!in_array($this->default, $this->enum, true)) {
            throw InvalidFieldValueException::forField(
                'default',
                sprintf("Default value '%s' must be one of the enum values: ", $this->default).
                implode(', ', $this->enum),
            );
        }
    }
}
