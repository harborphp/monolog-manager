<?php

namespace Harbor\MonologManager;

use Monolog\Logger;
use Psr\Log\LoggerInterface;

interface ManagerInterface extends LoggerInterface
{
    /**
     * Adds a new channel to the manager.
     */
    public function add(string $name, array $config): ManagerInterface;

    /**
     * @param string|null $name
     * @return Logger
     */
    public function channel(?string $name = null): Logger;

    /**
     * Get the currently set default channel name
     */
    public function getDefaultChannel(): ?string;

    /**
     * Set the default channel name
     */
    public function setDefaultChannel(string $name): ManagerInterface;

    /**
     * Configure whether the fallback emergency channel should be used in the event of an
     * error while creating the logger/fetching the channel.
     *
     * This prevents an issue in the logger from stopping execution.
     *
     * Default: true
     */
    public function useEmergencyChannel(bool $useEmergencyChannel = true): \Harbor\MonologManager\Manager;

    /**
     * Attempts to proxy any invalid method calls to the default channel
     *
     * @return mixed
     */
    public function __call(string $method, array $parameters);
}
