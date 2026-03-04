<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Support\Fakes\Functions;

use Cline\Forrst\Contracts\StreamableFunction;
use Cline\Forrst\Data\RequestObjectData;
use Cline\Forrst\Functions\AbstractFunction;
use Cline\Forrst\Streaming\StreamChunk;
use Generator;

use function array_sum;
use function count;

/**
 * A streamable function for testing SSE streaming functionality.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class StreamingSum extends AbstractFunction implements StreamableFunction
{
    /**
     * Non-streaming fallback.
     */
    public function handle(RequestObjectData $requestObject): array
    {
        $numbers = $requestObject->getArguments();
        $sum = array_sum($numbers);

        return ['sum' => $sum];
    }

    /**
     * Stream the sum calculation with progress updates.
     *
     * @return Generator<int, StreamChunk>
     */
    public function stream(): Generator
    {
        $numbers = $this->requestObject->getArguments();
        $total = count($numbers);
        $runningSum = 0;

        foreach ($numbers as $index => $number) {
            $runningSum += $number;

            // Emit progress
            yield StreamChunk::progress($index + 1, $total, 'Processing number '.$number);

            // Emit partial result
            yield StreamChunk::data(['partial_sum' => $runningSum]);
        }

        // Emit final result
        yield StreamChunk::done(['sum' => $runningSum]);
    }
}
