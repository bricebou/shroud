<?php

declare(strict_types=1);

namespace Shroud\Exception;

/**
 * Marks every refusal of the library, so that one catch block handles them all.
 */
interface ShroudException extends \Throwable
{
}
