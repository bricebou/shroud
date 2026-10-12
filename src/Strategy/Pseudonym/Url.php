<?php

declare(strict_types=1);

namespace Shroud\Strategy\Pseudonym;

use Shroud\Context;
use Shroud\Exception\InvalidValueException;
use Shroud\Strategy\AbstractTextStrategy;

/**
 * Rewrites the path of a URL word by word with the words of the referenced object, digits and codes included.
 *
 * The first segments of the path can be kept (`author/…`, `/in/…`); the host is kept, or replaced
 * by the fake domain; query and fragment are dropped. A value without host is a path. With `unique`,
 * the last segment takes a ten-character suffix drawn from the original: two objects with alike
 * pseudonyms no longer collide on a primary key.
 */
final class Url extends AbstractTextStrategy
{
    public function __construct(
        private readonly bool $keepHost = true,
        private readonly int $keptSegments = 0,
        private readonly bool $unique = false,
    ) {
    }

    protected function transform(string $value, Context $context): string
    {
        $pseudonym = $context->requirePseudonym();
        $words = [...$pseudonym->listFullNameWords(), ...$pseudonym->organizationWords];
        $text = $context->generator->text();
        $draw = $context->draw();
        $parts = parse_url($value);

        // A `mailto:` in a URL column: the address is that of the pseudonym, nothing else.
        if (is_array($parts) && strtolower($parts['scheme'] ?? '') === 'mailto') {
            return 'mailto:' . $pseudonym->email;
        }
        // Without host, the whole value is a path, "author/name"; except an email address typed
        // in the field, which becomes that of the pseudonym.
        $hasHost = $parts !== false && isset($parts['host']);
        if (! $hasHost && str_contains($value, '@') && ! str_contains($value, '/')) {
            return $pseudonym->email;
        }

        $path = $hasHost ? ($parts['path'] ?? '') : $value;
        $segments = [];
        $kept = 0;
        foreach (explode('/', $path) as $segment) {
            // The empty segments, on either side of the slashes, do not count among those to keep.
            if ($segment === '' || $kept++ < $this->keptSegments) {
                $segments[] = $segment;
                continue;
            }
            // Decoded so that its words are seen, encoded again when the URL is absolute. A percent
            // encoding may hide latin-1, inherited from an old copy and paste: it is read as such.
            $decoded = rawurldecode($segment);
            if (! mb_check_encoding($decoded, 'UTF-8')) {
                $decoded = mb_convert_encoding($decoded, 'UTF-8', 'ISO-8859-1');
            }
            $fake = $text->replaceWords($decoded, static fn (int $rank): string => $words[$rank] ?? 'lorem')
                ?? throw new InvalidValueException($context->table, $context->column, 'invalid UTF-8');
            $fake = $text->replaceCodes($fake, $draw->letter(...), $draw->digit(...));
            $segments[] = $hasHost ? rawurlencode($fake) : $fake;
        }
        if ($this->unique) {
            // On the last non-empty segment, before a trailing slash if any.
            for ($i = count($segments) - 1; $i >= 0; --$i) {
                if ($segments[$i] !== '') {
                    $segments[$i] .= '-' . substr($context->generator->digest('url', $value), 0, 10);
                    break;
                }
            }
        }

        $path = implode('/', $segments);
        if (! $hasHost) {
            return $path;
        }

        // Query and fragment are not carried over: they may hold anything.
        return ($parts['scheme'] ?? 'https') . '://'
            . ($this->keepHost ? $parts['host'] : $pseudonym->domain)
            . $path;
    }
}
