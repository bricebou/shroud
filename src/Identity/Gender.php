<?php

declare(strict_types=1);

namespace Shroud\Identity;

/**
 * The gender a pseudonym draws, which its first names and its civility follow.
 *
 * The values name the lists of first names of a vocabulary.
 */
enum Gender: string
{
    case Female = 'female';
    case Male = 'male';
}
