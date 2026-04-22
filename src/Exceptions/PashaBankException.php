<?php

declare(strict_types=1);

namespace Romanalisoy\PashaBank\Exceptions;

use RuntimeException;

/**
 * Root exception for every error this SDK raises. Catch this in a global
 * error handler when you want to treat all PASHA Bank integration errors
 * uniformly; catch the specific subclasses for targeted recovery.
 */
class PashaBankException extends RuntimeException {}
