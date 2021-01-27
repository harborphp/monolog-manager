<?php

declare(strict_types=1);

namespace Harbor\MonologManager\Tests;

use Harbor\MonologManager\Factory;
use Harbor\MonologManager\Tests\Stub\Container;
use Harbor\MonologManager\Tests\Stub\HandlerFactory;
use InvalidArgumentException;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\ErrorLogHandler;
use Monolog\Handler\NoopHandler;
use Monolog\Handler\NullHandler;
use Monolog\Processor\MemoryUsageProcessor;
use Monolog\Processor\PsrLogMessageProcessor;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

class FactoryTest extends TestCase
{
    private Factory $factory;

    protected function setUp(): void
    {
        $this->factory = new Factory();
    }

    public function testItHasContainer(): void
    {
        $this->assertFalse($this->factory->hasContainer());
    }

    public function testItCanUseContainer(): void
    {
        $this->factory->useContainer($this->createContainer());
        $this->assertTrue($this->factory->hasContainer());
    }

    public function testItCanCreateWithoutContainer(): void
    {
        $logger = $this->factory->create(
            'logger',
            [
                'handlers' => [
                    new NoopHandler(),
                ],
            ]
        );

        $this->assertSame('logger', $logger->getName());
        $this->assertContainsOnlyInstancesOf(NoopHandler::class, $logger->getHandlers());
    }

    public function testItCanCreateUsingContainer(): void
    {
        $this->factory->useContainer(
            $this->createContainer(
                [
                    NoopHandler::class => new NoopHandler(),
                    PsrLogMessageProcessor::class => new PsrLogMessageProcessor(),
                    LineFormatter::class => new LineFormatter(),
                ]
            )
        );

        $logger = $this->factory->create(
            'logger',
            [
                'handlers' => [
                    NoopHandler::class,
                    HandlerFactory::class,
                    new NoopHandler(),
                    static fn () => new NoopHandler(),
                    [
                        'name' => ErrorLogHandler::class,
                        'formatter' => LineFormatter::class,
                    ],
                    [
                        'name' => ErrorLogHandler::class,
                        'formatter' => new LineFormatter(),
                    ],
                    [
                        'name' => ErrorLogHandler::class,
                        'formatter' => [
                            'name' => LineFormatter::class,
                        ],
                    ],
                ],
                'processors' => [
                    PsrLogMessageProcessor::class,
                    new MemoryUsageProcessor(),
                ],
            ]
        );

        $this->assertSame('logger', $logger->getName());

        $this->assertCount(7, $logger->getHandlers());
        $this->assertInstanceOf(NoopHandler::class, $logger->popHandler());
        $this->assertInstanceOf(NullHandler::class, $logger->popHandler());
        $this->assertInstanceOf(NoopHandler::class, $logger->popHandler());
        $this->assertInstanceOf(NoopHandler::class, $logger->popHandler());

        /** @var ErrorLogHandler $errorLogHandler */
        $errorLogHandler = $logger->popHandler();
        $this->assertInstanceOf(ErrorLogHandler::class, $errorLogHandler);
        $this->assertInstanceOf(LineFormatter::class, $errorLogHandler->getFormatter());

        /** @var ErrorLogHandler $errorLogHandler */
        $errorLogHandler = $logger->popHandler();
        $this->assertInstanceOf(ErrorLogHandler::class, $errorLogHandler);
        $this->assertInstanceOf(LineFormatter::class, $errorLogHandler->getFormatter());

        $this->assertCount(2, $logger->getProcessors());
        $this->assertInstanceOf(PsrLogMessageProcessor::class, $logger->popProcessor());
        $this->assertInstanceOf(MemoryUsageProcessor::class, $logger->popProcessor());
    }

    public function testItThrowsWhenCannotResolveUsingContainer(): void
    {
        $this->factory->useContainer($this->createContainer());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Could not resolve "Monolog\Handler\NoopHandler"');

        $this->factory->create(
            'logger',
            [
                'handlers' => [
                    NoopHandler::class,
                ],
            ]
        );
    }

    private function createContainer(array $definition = []): ContainerInterface
    {
        return new Container($definition);
    }
}
