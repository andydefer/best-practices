<?php

declare(strict_types=1);

namespace AndyDefer\BestPractices\Tests\Fixtures\Actions;

use AndyDefer\BestPractices\Actions\AbstractAction;

final class TestAction extends AbstractAction
{
    public function run(...$parameters): mixed
    {
        // This is a test fixture that doesn't need to do anything
        return null;
    }
}
