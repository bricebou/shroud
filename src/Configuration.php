<?php

declare(strict_types=1);

namespace Shroud;

use Faker\Factory;
use Shroud\Declaration\Declaration;
use Shroud\Exception\InvalidConfigurationException;
use Shroud\Language\LanguageInterface;
use Shroud\Language\Plain;
use Shroud\Platform\Generic;
use Shroud\Platform\PlatformInterface;
use Shroud\Schema\Draft;
use Shroud\Schema\Glossary\GlossaryInterface;

/**
 * Holds what a project settles once and versions: its declaration, its salt, its platform and its language.
 *
 * The `shroud.php` file of the project returns an instance, which the commands load. The salt
 * seeds everything the library draws: the same salt on the same base gives the same result,
 * byte for byte.
 */
final readonly class Configuration
{
    /**
     * @param string $locale the Faker locale, which picks the lists of names and cities
     * @param LanguageInterface $language the conventions of the texts of the base
     * @param PlatformInterface $platform the platform that owns the base
     * @param list<GlossaryInterface> $glossaries the glossaries of the draft, those of the platform when empty
     */
    public function __construct(
        public Declaration $declaration,
        public string $salt,
        public string $locale = Factory::DEFAULT_LOCALE,
        public LanguageInterface $language = new Plain(),
        public PlatformInterface $platform = new Generic(),
        public array $glossaries = [],
    ) {
        if ($this->salt === '') {
            throw new InvalidConfigurationException('the salt is empty');
        }
    }

    /**
     * Loads the configuration file of a project, which must return an instance.
     */
    public static function fromFile(string $path): self
    {
        if (! is_file($path)) {
            throw new InvalidConfigurationException(sprintf('file not found: %s', $path));
        }
        $configuration = require $path;
        if (! $configuration instanceof self) {
            throw new InvalidConfigurationException(sprintf('%s must return a %s object', $path, self::class));
        }

        return $configuration;
    }

    /**
     * Assembles the generator of the project; without renumbering on request.
     */
    public function generator(bool $renumbering = true): Generator
    {
        return new Generator($this->salt, $this->locale, $renumbering, $this->language, $this->platform);
    }

    /**
     * Assembles the disguiser of the project; without renumbering on request.
     */
    public function disguiser(bool $renumbering = true): Disguiser
    {
        return new Disguiser($this->declaration, $this->generator($renumbering));
    }

    /**
     * Assembles the draft of the project, on its platform and its glossaries.
     */
    public function draft(): Draft
    {
        return new Draft($this->platform, ...$this->glossaries);
    }
}
