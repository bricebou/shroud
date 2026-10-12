<?php

declare(strict_types=1);

namespace Shroud\Strategy\Nested;

use Shroud\Context;
use Shroud\Exception\InvalidConfigurationException;
use Shroud\Strategy\AbstractTextStrategy;
use Shroud\Strategy\Observation;
use Shroud\Strategy\RelayingObserverInterface;
use Shroud\Strategy\StrategyInterface;

/**
 * Applies a strategy to each item of a delimited list, `12,13,14`, and joins them again.
 *
 * The blanks around an item are kept in place, and an empty item stays empty: `12, 13,` keeps its
 * shape. A list of ids whose type is renumbered, an image gallery for instance, keeps its links.
 */
final class Delimited extends AbstractTextStrategy implements RelayingObserverInterface
{
    /**
     * @var non-empty-string
     */
    private readonly string $separator;

    public function __construct(
        string $separator,
        private readonly StrategyInterface $item,
    ) {
        if ($separator === '') {
            throw new InvalidConfigurationException('the separator of a delimited list is empty');
        }
        $this->separator = $separator;
    }

    /**
     * Relays the observation to each item.
     */
    public function observe(mixed $value, Context $context): void
    {
        if (is_string($value) && $value !== '') {
            foreach (explode($this->separator, $value) as $item) {
                Observation::relay($this->item, trim($item), $context);
            }
        }
    }

    public function innerStrategies(): iterable
    {
        return [$this->item];
    }

    protected function transform(string $value, Context $context): string
    {
        $items = [];
        foreach (explode($this->separator, $value) as $item) {
            $trimmed = trim($item);
            if ($trimmed === '') {
                $items[] = $item;
                continue;
            }
            $start = (int) strpos($item, $trimmed);
            $result = $this->item->apply($trimmed, $context);
            $items[] = substr($item, 0, $start) . (is_scalar($result) ? (string) $result : '') . substr($item, $start + strlen($trimmed));
        }

        return implode($this->separator, $items);
    }
}
