# Monolog Manager

[Monolog][link-monolog] Manager allows you to easily create and use multiple logging channels. The manager utilizes [Monolog Factory][link-monolog-factory] for easy, configuration-based, creation of the Loggers.

You can optionally use a [PSR-11][link-psr11] dependency injection container for Handler, Processor, and Formatter resolution within your logger configuration.

## Installation

The preferred method of installation is via [Composer](http://getcomposer.org/). Run the following command to install
the latest version of a package and add it to your project's `composer.json`:

```bash
composer require harbor/monolog-manager
```

## Usage

### Adding logging channels

```php
use Monolog\Formatter\HtmlFormatter;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\NativeMailerHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Processor\PsrLogMessageProcessor;
use Psr\Log\LogLevel;

$manager = new Harbor\MonologManager\Manager();

// Setup the default logger
$manager->add('app', [
    'default' => true,
    'handlers' => [
        'name' => StreamHandler::class,
        'params' => [
            'stream' => 'path/to/your.log',
            'level' => LogLevel::WARNING,
        ],
        'formatter' => [
            'name' => LineFormatter::class,
            'params' => [
                'allowInlineLineBreaks' => false,
                'ignoreEmptyContextAndExtra' => true,
            ],
        ],
    ],
    'processors' => [
        [
            'name' => PsrLogMessageProcessor::class,
        ]
    ],
]);

// Add an additional logging channel
$manager->add('alert', [
    'handlers' => [
        [
            'name' => NativeMailerHandler::class,
            'params' => [
                'to' => 'alerts@example.com',
                'subject' => 'Application Alert',
                'from' => 'noreply@example.com',
                'level' => Logger::ALERT,
            ],
            'formatter' => [
                'name' => HtmlFormatter::class,
            ],
        ],
    ],
    'processors' => [
        [
            'name' => PsrLogMessageProcessor::class,
        ]
    ],
]);
```

### Logging to the default channel

The `Manager` implements the `Psr\Log\LoggerInterface`, and sends the log message to the default channel.

```php
// These logs be sent to the `app` channel we configured above.
$manager->emergency('test emergency');
$manager->alert('test alert');
$manager->critical('test critical');
$manager->error('test error');
$manager->warning('test warning');
$manager->notice('test notice');
$manager->info('test info');
$manager->debug('test debug');
$manager->log(LogLevel::INFO, 'test log');
```

Additionally, you can call any method on the default channel's `Mongolog\Logger` instance:

```php
$manager->getName(); // returns "app"
```

### Logging to a specific channel

Using the `channel(?string $name = null)` method on `Manager`, you can get that channel's `Mongolog\Logger` instance:

```php
/** @var \Mongolog\Logger */
$logger = $manager->channel('alert');
$logger->alert('Something important happened!');
```

## Emergency Logger

By default, the `Manager` will return a default "emergency logger" if an error occurs while creating the Logger instance".

This is to prevent logging configuration/usage issues from stopping script execution.

The default emergency logger added via:

```php
$manager->add(Manager::EMERGENCY_CHANNEL, [
    'handlers' => [
        [
            'name' => \Monolog\Handler\StreamHandler::class,
            'params' => [
                'stream' => 'php://stderr',
                'level' => \Psr\Log\LogLevel::DEBUG,
            ],
        ],
    ],
]);
```

**Supplying your own emergency logger:**

Simply add a channel, using `Manager::EMERGENCY_CHANNEL` as the channel name.

### Disabling the emergency logger

```php
$manager->useEmergencyChannel(false);
```

## Using a Dependency Injection Container

To use a [PSR-11][link-psr11] container to resolve dependencies:

### Setup

```php
use Harbor\MonologManager\Factory;
use Harbor\MonologManager\Manager;
use Psr\Container\ContainerInterface;

/** @var ContainerInterface */
$container = getContainer();

// Create a new Factory, providing the container
$factory = new Factory($container);

// Provide your factory to the Manager
$manager = new Manager($factory);
```

### Using the container in your channel config

The `handlers`, `processors`, and `formatter` are resolved using the following logic:

_**Note:** The term `callable` refers to any value where `is_callable($value) === true` **OR** any class where `method_exists($value, '__invoke')`_

1. If value is an object of the correct type: `$value = $value`
2. If value is a string, and `$container->has($value)`: `$value = $container->get($value)`
3. If value is a string, and is callable: `$value = $value($container)`
5. If value is an array, and `isset($value['formatter'])`: `$value['formatter'] = $this->resolve($value['formatter'])`

If the value cannot be resolved, an `InvalidArgumentException` is thrown (which gets sent to the emergency logger by default, see above)

**Example of all possible values:**

```php
$manager->add('useless-logger', [
    'handlers' => [
        HandlerFactory::class,
        NoopHandler::class,
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
    ],
    'processors' => [
        PsrLogMessageProcessor::class,
        new MemoryUsageProcessor(),
    ],
]);
```

## License

Released under MIT License

[link-monolog]: https://github.com/Seldaek/monolog
[link-monolog-factory]: https://github.com/nikolaposa/monolog-factory
[link-psr11]: https://www.php-fig.org/psr/psr-11/
[link-packagist]: https://packagist.org/packages/nikolaposa/monolog-factory
