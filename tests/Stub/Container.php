<?php

declare(strict_types=1);

namespace Harbor\MonologManager\Tests\Stub;

use Psr\Container\ContainerInterface;

use function array_key_exists;

class Container implements ContainerInterface
{
    private array $entries;

    public function __construct(array $entries = [])
    {
        $this->entries = $entries;
    }

    /**
     * @inheritDoc
     */
    public function get($id)
    {
        return $this->entries[$id] ?? null;
    }

    /**
     * @inheritDoc
     */
    public function has($id): bool
    {
        return array_key_exists($id, $this->entries);
    }
}
