<?php

declare(strict_types=1);

namespace Shroud\Strategy\Value;

use Shroud\Context;
use Shroud\Strategy\AbstractShapedWords;

/**
 * Replaces a free text by one of the same shape: the words come from a draw specific to the value, tied to no object.
 */
final class ShapedText extends AbstractShapedWords
{
    /**
     * Provides no word: everything comes from the draw specific to the value.
     */
    protected function provideWords(Context $context, string $value): array
    {
        return [];
    }
}
