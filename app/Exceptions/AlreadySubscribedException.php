<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when a user attempts to subscribe while already having
 * an active, trialing, or past_due subscription.
 *
 * This exception enforces the idempotency guarantee of the
 * subscribe operation — a user can only have one active
 * subscription at any given time.
 *
 * HTTP Response: 409 Conflict
 */
class AlreadySubscribedException extends RuntimeException
{
    /**
     * Create a new exception instance.
     *
     * @param  int  $userId  The ID of the user who already has an active subscription
     */
    public function __construct(int $userId)
    {
        parent::__construct("User (ID: {$userId}) already has an active subscription.");
    }
}
