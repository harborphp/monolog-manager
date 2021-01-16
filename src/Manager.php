<?php

declare(strict_types=1);

namespace Harbor\MonologManager;

use InvalidArgumentException;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Throwable;

use function sprintf;

class Manager implements LoggerInterface, ManagerInterface
{
    public const EMERGENCY_CHANNEL = 'monolog-manager-emergency';

    private array $channels = [];
    private array $channelConfigs = [];
    private ?string $defaultChannel = null;
    private Factory $factory;
    private bool $useEmergencyChannel;

    public function __construct(?Factory $factory = null)
    {
        $this->factory = $factory ?? new Factory();
        $this->useEmergencyChannel = true;
    }

    public function add(string $name, array $config): self
    {
        $config['default'] ??= false;
        $this->channelConfigs[$name] = $config;

        if ($config['default']) {
            $this->setDefaultChannel($name);
        }

        return $this;
    }

    public function setDefaultChannel(string $name): self
    {
        $this->defaultChannel = $name;

        return $this;
    }

    public function getDefaultChannel(): ?string
    {
        return $this->defaultChannel;
    }

    public function has(string $name): bool
    {
        return isset($this->channels[$name]) || isset($this->channelConfigs[$name]);
    }

    public function channel(?string $name = null): Logger
    {
        try {
            $name ??= $this->getDefaultChannel();
            if ($name === null) {
                throw new InvalidArgumentException('No default channel has been set');
            }

            return $this->get($name);
        } catch (Throwable $e) {
            if (!$this->useEmergencyChannel) {
                throw $e;
            }

            return $this->getEmergencyLogger('Error encountered, using fallback emergency logger', $e);
        }
    }

    public function useEmergencyChannel(bool $useEmergencyChannel = true): self
    {
        $this->useEmergencyChannel = $useEmergencyChannel;

        return $this;
    }

    protected function get(string $name): Logger
    {
        if (!$this->has($name)) {
            throw new InvalidArgumentException(sprintf('Channel "%s" is not defined', $name));
        }

        $this->channels[$name] ??= $this->factory->create($name, $this->channelConfigs[$name]);

        return $this->channels[$name];
    }

    protected function getEmergencyLogger(string $reason, ?Throwable $exception = null): Logger
    {
        if (!$this->has(self::EMERGENCY_CHANNEL)) {
            $this->addEmergencyLogger(self::EMERGENCY_CHANNEL);
            $this->setDefaultChannel(self::EMERGENCY_CHANNEL);
        }

        $logger = $this->get(self::EMERGENCY_CHANNEL);
        $logger->emergency($reason, ['exception' => $exception]);

        return $logger;
    }

    protected function addEmergencyLogger(string $name): void
    {
        $this->add($name, [
            'handlers' => [
                [
                    'name' => StreamHandler::class,
                    'params' => [
                        'stream' => 'php://stderr',
                        'level' => LogLevel::DEBUG,
                    ],
                ],
            ],
        ]);
    }

    public function __call(string $method, array $parameters)
    {
        return $this->channel()->{$method}(...$parameters);
    }

    /**
     * @inheritDoc
     */
    public function emergency($message, array $context = []): void
    {
        $this->channel()->emergency($message, $context);
    }

    /**
     * @inheritDoc
     */
    public function alert($message, array $context = []): void
    {
        $this->channel()->alert($message, $context);
    }

    /**
     * @inheritDoc
     */
    public function critical($message, array $context = []): void
    {
        $this->channel()->critical($message, $context);
    }

    /**
     * @inheritDoc
     */
    public function error($message, array $context = []): void
    {
        $this->channel()->error($message, $context);
    }

    /**
     * @inheritDoc
     */
    public function warning($message, array $context = []): void
    {
        $this->channel()->warning($message, $context);
    }

    /**
     * @inheritDoc
     */
    public function notice($message, array $context = []): void
    {
        $this->channel()->notice($message, $context);
    }

    /**
     * @inheritDoc
     */
    public function info($message, array $context = []): void
    {
        $this->channel()->info($message, $context);
    }

    /**
     * @inheritDoc
     */
    public function debug($message, array $context = []): void
    {
        $this->channel()->debug($message, $context);
    }

    /**
     * @inheritDoc
     */
    public function log($level, $message, array $context = []): void
    {
        $this->channel()->log($level, $message, $context);
    }
}
