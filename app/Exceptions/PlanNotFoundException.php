<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when a requested plan does not exist in the database.
 *
 * HTTP Response: 404 Not Found
 */
class PlanNotFoundException extends RuntimeException
{
    /**
     * Create a new exception instance.
     *
     * @param  int  $planId  The ID of the plan that was not found
     */
    public function __construct(int $planId)
    {
        parent::__construct("Plan (ID: {$planId}) not found.");
    }
}
