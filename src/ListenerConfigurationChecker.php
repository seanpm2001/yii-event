<?php

declare(strict_types=1);

namespace Yiisoft\Yii\Event;

use Psr\Container\ContainerExceptionInterface;

use function get_class;
use function gettype;
use function is_object;
use function is_string;

/**
 * ListenerConfigurationChecker could be used in development mode to check if listeners are defined correctly.
 *
 * ```php
 * $checker->check($configuration->get('events-web'));
 * ```
 */
final class ListenerConfigurationChecker
{
    private CallableFactory $callableFactory;

    public function __construct(CallableFactory $callableFactory)
    {
        $this->callableFactory = $callableFactory;
    }

    /**
     * Checks the given event configuration and throws an exception in some cases:
     * - incorrect configuration format
     * - incorrect listener format
     * - listener is not a callable
     * - listener is meant to be a method of an object which can't be instantiated
     *
     * @param array $configuration An array in format of [eventClassName => [listeners]]
     */
    public function check(array $configuration): void
    {
        foreach ($configuration as $eventName => $listeners) {
            if (!is_string($eventName) || !class_exists($eventName)) {
                throw new InvalidEventConfigurationFormatException(
                    'Incorrect event listener format. Format with event name must be used. Got ' .
                    var_export($eventName, true) . '.'
                );
            }

            if (!is_iterable($listeners)) {
                $type = is_object($listeners) ? get_class($listeners) : gettype($listeners);

                throw new InvalidEventConfigurationFormatException(
                    "Event listeners for $eventName must be an iterable, $type given."
                );
            }

            /** @var mixed */
            foreach ($listeners as $listener) {
                try {
                    if (!$this->isCallable($listener)) {
                        throw new InvalidListenerConfigurationException(
                            'Listener must be a callable. Got ' . $this->listenerDump($listener) . '.'
                        );
                    }
                } catch (ContainerExceptionInterface $exception) {
                    throw new InvalidListenerConfigurationException(
                        'Could not instantiate event listener or listener class has invalid configuration. Got ' .
                        $this->listenerDump($listener) . '.',
                        0,
                        $exception
                    );
                }
            }
        }
    }

    /**
     * @param mixed $definition
     *
     * @throws ContainerExceptionInterface Error while retrieving the entry from container.
     */
    private function isCallable($definition): bool
    {
        try {
            $this->callableFactory->create($definition);
        } catch (InvalidListenerConfigurationException $e) {
            return false;
        }

        return true;
    }

    /**
     * @param mixed $listener
     */
    private function listenerDump($listener): string
    {
        return is_object($listener) ? get_class($listener) : var_export($listener, true);
    }
}