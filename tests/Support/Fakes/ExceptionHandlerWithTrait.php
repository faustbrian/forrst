<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Support\Fakes;

use Cline\Forrst\Exceptions\Concerns\RendersThrowable;

/**
 * Test class for testing the RendersThrowable trait.
 *
 * This class simulates Laravel's Exceptions configuration class
 * that would normally mix in the RendersThrowable trait.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class ExceptionHandlerWithTrait
{
    use RendersThrowable;

    /**
     * Callback for capturing renderable calls.
     */
    private $renderableCallback;

    /**
     * Set a callback to capture renderable registrations.
     */
    public function setRenderableCallback(callable $callback): void
    {
        $this->renderableCallback = $callback;
    }

    /**
     * Expose the renderableThrowable method publicly for testing.
     */
    public function callRenderableThrowable(): void
    {
        $this->renderableThrowable();
    }

    /**
     * Mock implementation of the renderable method.
     *
     * This method simulates Laravel's Exceptions::renderable() method
     * that would normally register exception rendering callbacks.
     */
    protected function renderable(callable $closure): void
    {
        if (!$this->renderableCallback) {
            return;
        }

        ($this->renderableCallback)($closure);
    }
}
