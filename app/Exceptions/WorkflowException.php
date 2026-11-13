<?php

namespace App\Exceptions;

use DomainException;

/**
 * A business rule said no (insufficient credit, service unavailable, invalid status change...).
 * The message is safe to show to the user; controllers turn it into a form error.
 */
class WorkflowException extends DomainException
{
}
