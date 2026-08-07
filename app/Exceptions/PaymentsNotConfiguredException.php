<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when a customer tries to pay but the store owner hasn't set a
 * receiving wallet yet. Callers turn this into a friendly message rather than
 * letting it surface as a 500.
 */
class PaymentsNotConfiguredException extends RuntimeException
{
}
