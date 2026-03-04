<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Forrst\Exceptions;

/**
 * Base exception for version resolution failures.
 *
 * Represents Forrst error code VERSION_NOT_FOUND for requests to function versions
 * that are not registered. Provides a common type for catching all version-related
 * errors while allowing granular handling of specific version resolution failures.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @see https://docs.cline.sh/forrst/versioning
 * @see https://docs.cline.sh/forrst/errors
 */
abstract class VersionNotFoundException extends AbstractRequestException
{
    // Abstract base - no factory methods
}
