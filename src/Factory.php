<?php

declare(strict_types=1);

namespace Harbor\MonologManager;

use InvalidArgumentException;
use Monolog\Formatter\FormatterInterface;
use Monolog\Handler\HandlerInterface;
use Monolog\Logger;
use Monolog\Processor\ProcessorInterface;
use MonologFactory\Config\HandlerConfig;
use MonologFactory\Config\LoggerConfig;
use MonologFactory\LoggerFactory;
use Psr\Container\ContainerInterface;

use function array_map;
use function class_exists;
use function is_array;
use function is_callable;
use function is_string;
use function method_exists;
use function sprintf;

class Factory
{
    protected ?ContainerInterface $container;
    private LoggerFactory $loggerFactory;

    public function __construct(?ContainerInterface $container = null)
    {
        $this->loggerFactory = new LoggerFactory();
        $this->container = $container;
    }

    public function useContainer(?ContainerInterface $container): void
    {
        $this->container = $container;
    }

    public function hasContainer(): bool
    {
        return $this->container instanceof ContainerInterface;
    }

    public function create(string $name, array $config): Logger
    {
        if ($this->container) {
            $config = $this->prepareConfigUsingContainer($config);
        }

        return $this->loggerFactory->create($name, $config);
    }

    protected function prepareConfigUsingContainer(array $config): array
    {
        if (is_array($config[LoggerConfig::HANDLERS])) {
            $config[LoggerConfig::HANDLERS] = $this->prepareHandlers($config[LoggerConfig::HANDLERS]);
        }

        if (is_array($config[LoggerConfig::PROCESSORS])) {
            $config[LoggerConfig::PROCESSORS] = $this->prepareProcessors($config[LoggerConfig::PROCESSORS]);
        }

        return $config;
    }

    private function prepareHandlers(array $handlers): array
    {
        return array_map(fn ($handler) => $this->resolveHandler($handler), $handlers);
    }

    private function prepareProcessors(array $processors): array
    {
        return array_map(fn ($processor) => $this->resolveProcessor($processor), $processors);
    }

    /**
     * @param mixed $serviceOrFactory
     *
     * @return mixed
     */
    private function resolveFromContainer($serviceOrFactory)
    {
        if (is_string($serviceOrFactory) && $this->container->has($serviceOrFactory)) {
            return $this->container->get($serviceOrFactory);
        }

        $callable = $this->getCallable($serviceOrFactory);
        if ($callable) {
            return $callable($this->container);
        }

        throw new InvalidArgumentException(sprintf('Could not resolve "%s"', $serviceOrFactory));
    }

    /**
     * @param string|callable $serviceOrFactory
     */
    private function getCallable($serviceOrFactory): ?callable
    {
        if (is_callable($serviceOrFactory)) {
            return $serviceOrFactory;
        }

        if (class_exists($serviceOrFactory) && method_exists($serviceOrFactory, '__invoke')) {
            return new $serviceOrFactory();
        }

        return null;
    }

    /**
     * @param mixed $handler
     *
     * @return array|HandlerInterface
     */
    private function resolveHandler($handler)
    {
        if ($handler instanceof HandlerInterface) {
            return $handler;
        }

        if (!is_array($handler)) {
            $handler = $this->resolveFromContainer($handler);
        } elseif (isset($handler[HandlerConfig::FORMATTER])) {
            $handler[HandlerConfig::FORMATTER] = $this->resolveFormatter($handler[HandlerConfig::FORMATTER]);
        }

        return $handler;
    }

    /**
     * @param mixed $processor
     *
     * @return array|ProcessorInterface
     */
    private function resolveProcessor($processor)
    {
        if ($processor instanceof ProcessorInterface) {
            return $processor;
        }

        return $this->resolveFromContainer($processor);
    }

    /**
     * @param mixed $formatter
     */
    private function resolveFormatter($formatter): FormatterInterface
    {
        if ($formatter instanceof FormatterInterface) {
            return $formatter;
        }

        return $this->resolveFromContainer($formatter);
    }
}
