<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Forrst\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Override;

use function is_numeric;
use function is_string;

/**
 * Validation rule for Forrst request identifiers.
 *
 * Validates that request ID values conform to the Forrst protocol specification,
 * which allows identifiers to be a string, number, or null. This rule ensures
 * proper request/response correlation in Forrst communication. Null identifiers
 * are permitted for notification requests that don't expect responses.
 *
 * @author Brian Faust <brian@cline.sh>
 * @see https://docs.cline.sh/forrst/protocol
 */
final class Identifier implements ValidationRule
{
    /**
     * Validate that the given value is a valid Forrst identifier.
     *
     * Accepts null, numeric, or string values as per the Forrst protocol specification.
     * Null values are allowed for notification requests that don't require responses.
     * Numeric and string values are used for standard request/response correlation.
     *
     * @param string  $attribute The name of the attribute being validated
     * @param mixed   $value     The value to validate (should be null, numeric, or string)
     * @param Closure $fail      Closure to invoke with error message if validation fails
     */
    #[Override()]
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null) {
            return;
        }

        if (is_numeric($value)) {
            return;
        }

        if (is_string($value)) {
            return;
        }

        $fail('The :attribute must be an integer, string or null.');
    }
}
