<?php

declare(strict_types=1);

namespace Harbor\MonologManager\Tests\Stub;

use Monolog\Handler\NullHandler;

class HandlerFactory
{
    public function __invoke(): NullHandler
    {
        return new NullHandler();
    }
}
