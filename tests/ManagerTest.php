<?php

declare(strict_types=1);

namespace Harbor\MonologManager\Tests;

use Harbor\MonologManager\Manager;
use InvalidArgumentException;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use ReflectionException;
use ReflectionMethod;

class ManagerTest extends TestCase
{
    private Manager $manager;
    private TestHandler $handler;

    protected function setUp(): void
    {
        $this->handler = new TestHandler();
        $this->manager = $this->getMockBuilder(Manager::class)
            ->onlyMethods(['addEmergencyLogger'])
            ->setProxyTarget(new Manager())
            ->getMock();

        $this->manager->method('addEmergencyLogger')
            ->will(
                $this->returnCallback(
                    function (string $name): void {
                        $this->manager->add($name, $this->getTestConfig());
                    }
                )
            );
    }

    public function testItCanAddChannel(): void
    {
        $this->manager->add('logger', $this->getTestConfig());

        $this->assertTrue($this->manager->has('logger'));
    }

    public function testItCanAddChannelAsDefault(): void
    {
        $this->manager->add('logger', $this->getTestConfig(true));

        $this->assertTrue($this->manager->has('logger'));
        $this->assertSame('logger', $this->manager->getDefaultChannel());
    }

    public function testItCanSetChannelAsDefault(): void
    {
        $this->manager->add('logger', $this->getTestConfig());

        $this->assertNull($this->manager->getDefaultChannel());

        $this->manager->setDefaultChannel('logger');

        $this->assertSame('logger', $this->manager->getDefaultChannel());
    }

    public function testItReturnsDefaultChannel(): void
    {
        $this->manager->add('logger', $this->getTestConfig(true));

        $this->assertSame('logger', $this->manager->channel()->getName());
    }

    public function testItCanGetChannel(): void
    {
        $this->manager->add('logger', $this->getTestConfig());

        $this->assertInstanceOf(Logger::class, $this->manager->channel('logger'));
    }

    public function testItThrowsWhenMissingAndNoEmergencyChannel(): void
    {
        $this->manager->useEmergencyChannel(false);

        self::expectException(InvalidArgumentException::class);
        $this->manager->channel('foo');
    }

    public function testItReturnsEmergencyChannelWhenNotFound(): void
    {
        $logger = $this->manager->channel('foo');
        $this->assertSame(Manager::EMERGENCY_CHANNEL, $logger->getName());
    }

    public function testItReturnsEmergencyChannelWhenDefaultNotSet(): void
    {
        $logger = $this->manager->channel();
        $this->assertSame(Manager::EMERGENCY_CHANNEL, $logger->getName());
    }

    public function testItAddsEmergencyChannel(): void
    {
        $manager = new Manager();

        try {
            $method = new ReflectionMethod($manager, 'addEmergencyLogger');
            $method->setAccessible(true);
            $method->invokeArgs($manager, [Manager::EMERGENCY_CHANNEL]);
        } catch (ReflectionException $e) {
            $this->fail($e->getMessage());
        }

        $this->assertTrue($manager->has(Manager::EMERGENCY_CHANNEL));
    }

    public function testItProxiesUndefinedMethodCallsToDefaultLogger(): void
    {
        $this->manager->add('logger', $this->getTestConfig(true));

        $this->assertSame('logger', $this->manager->getName());
    }

    public function testItProxiesLogMethodsToDefaultLogger(): void
    {
        $this->manager->add('logger', $this->getTestConfig(true));

        $this->manager->emergency('test emergency');
        $this->manager->alert('test alert');
        $this->manager->critical('test critical');
        $this->manager->error('test error');
        $this->manager->warning('test warning');
        $this->manager->notice('test notice');
        $this->manager->info('test info');
        $this->manager->debug('test debug');
        $this->manager->log(LogLevel::INFO, 'test log');

        $this->assertTrue($this->handler->hasEmergency('test emergency'));
        $this->assertTrue($this->handler->hasAlert('test alert'));
        $this->assertTrue($this->handler->hasCritical('test critical'));
        $this->assertTrue($this->handler->hasError('test error'));
        $this->assertTrue($this->handler->hasWarning('test warning'));
        $this->assertTrue($this->handler->hasNotice('test notice'));
        $this->assertTrue($this->handler->hasInfo('test info'));
        $this->assertTrue($this->handler->hasDebug('test debug'));
        $this->assertTrue($this->handler->hasInfo('test log'));
    }

    private function getTestConfig(bool $default = false): array
    {
        return [
            'default' => $default,
            'handlers' => [
                $this->handler,
            ],
        ];
    }
}
