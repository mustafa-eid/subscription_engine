<?php

namespace App\Gateways;

use App\Contracts\PaymentGatewayInterface;
use InvalidArgumentException;

/**
 * Payment Gateway Manager
 *
 * Manages multiple payment gateway implementations and provides
 * a unified interface for accessing them.
 */
class PaymentGatewayManager
{
    /**
     * Registered gateway instances.
     *
     * @var array<string, PaymentGatewayInterface>
     */
    protected array $gateways = [];

    /**
     * Gateway class name mappings.
     *
     * @var array<string, class-string>
     */
    protected array $gatewayClasses = [
        'stripe' => StripePaymentGateway::class,
        'stub' => StubPaymentGateway::class,
    ];

    /**
     * Get a payment gateway instance.
     *
     * @param string|null $driver The gateway driver name (uses config default if null)
     * @return PaymentGatewayInterface
     */
    public function gateway(?string $driver = null): PaymentGatewayInterface
    {
        $driver = $driver ?? config('subscriptions.payment.default_driver', 'stub');

        if (isset($this->gateways[$driver])) {
            return $this->gateways[$driver];
        }

        $gateway = $this->resolve($driver);
        $this->gateways[$driver] = $gateway;

        return $gateway;
    }

    /**
     * Resolve a gateway instance from its class name.
     *
     * @param string $driver The gateway driver name
     * @return PaymentGatewayInterface
     *
     * @throws InvalidArgumentException
     */
    protected function resolve(string $driver): PaymentGatewayInterface
    {
        if (!isset($this->gatewayClasses[$driver])) {
            throw new InvalidArgumentException(
                "Payment gateway [{$driver}] is not supported. Supported gateways: " .
                implode(', ', array_keys($this->gatewayClasses))
            );
        }

        $class = $this->gatewayClasses[$driver];

        return app($class);
    }

    /**
     * Get the default payment gateway.
     *
     * @return PaymentGatewayInterface
     */
    public function getDefaultDriver(): PaymentGatewayInterface
    {
        return $this->gateway();
    }

    /**
     * Register a custom gateway driver.
     *
     * @param string $driver The driver name
     * @param PaymentGatewayInterface|string $gateway The gateway instance or class name
     * @return $this
     */
    public function extend(string $driver, PaymentGatewayInterface|string $gateway): self
    {
        if (is_string($gateway)) {
            $this->gatewayClasses[$driver] = $gateway;
        } else {
            $this->gateways[$driver] = $gateway;
        }

        return $this;
    }

    /**
     * Get all registered gateway names.
     *
     * @return array<string>
     */
    public function getDrivers(): array
    {
        return array_keys($this->gatewayClasses);
    }
}
