<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when an operation is attempted on a subscription
 * that is in an invalid state for that operation.
 *
 * This exception enforces the subscription state machine rules
 * and prevents illegal transitions such as:
 *   - canceled → active  (terminal state)
 *   - active → trialing  (can't return to trial)
 *   - trialing → past_due  (trial must be activated first)
 *
 * HTTP Response: 422 Unprocessable Entity
 */
class InvalidSubscriptionStateException extends RuntimeException
{
    /**
     * Create a new exception instance.
     *
     * @param  string  $operation  The operation that was attempted (e.g. 'cancel')
     * @param  string  $currentState  The current status value of the subscription
     * @param  list<string>  $expectedStates  The list of valid states for this operation
     */
    public function __construct(string $operation, string $currentState, array $expectedStates = [])
    {
        $message = "Cannot {$operation}. Subscription is in '{$currentState}' state.";

        if (! empty($expectedStates)) {
            $message .= ' Expected: ' . implode(', ', $expectedStates) . '.';
        }

        parent::__construct($message);
    }
}
