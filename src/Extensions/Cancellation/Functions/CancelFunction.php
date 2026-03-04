<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Forrst\Extensions\Cancellation\Functions;

use Cline\Forrst\Attributes\Descriptor;
use Cline\Forrst\Exceptions\CancellationTokenMissingException;
use Cline\Forrst\Exceptions\CancellationTokenNotFoundException;
use Cline\Forrst\Extensions\Cancellation\CancellationExtension;
use Cline\Forrst\Extensions\Cancellation\Descriptors\CancelDescriptor;
use Cline\Forrst\Functions\AbstractFunction;

use function is_string;

/**
 * Request cancellation function.
 *
 * Implements forrst.cancel for cancelling in-flight synchronous requests that
 * have a cancellation token.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @see https://docs.cline.sh/forrst/extensions/cancellation
 */
#[Descriptor(CancelDescriptor::class)]
final class CancelFunction extends AbstractFunction
{
    /**
     * Create a new cancel function instance.
     *
     * @param CancellationExtension $extension Cancellation extension instance
     */
    public function __construct(
        private readonly CancellationExtension $extension,
    ) {}

    /**
     * Execute the cancel function.
     *
     * @throws CancellationTokenMissingException  If the token argument is missing or empty
     * @throws CancellationTokenNotFoundException If the token is unknown or expired
     *
     * @return array{cancelled: bool, token: string} Cancellation result
     */
    public function __invoke(): array
    {
        $token = $this->requestObject->getArgument('token');

        if (!is_string($token) || $token === '') {
            throw CancellationTokenMissingException::create();
        }

        $cancelled = $this->extension->cancel($token);

        if (!$cancelled) {
            throw CancellationTokenNotFoundException::forToken($token);
        }

        return [
            'cancelled' => true,
            'token' => $token,
        ];
    }
}
