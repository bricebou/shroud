<?php

/**
 * The configuration file of a project, as the tests load it.
 */

declare(strict_types=1);

use Shroud\Configuration;
use Shroud\Declaration\Table;
use Shroud\Language\French;
use Shroud\Schema\Glossary\English;
use Shroud\Schema\Glossary\French as FrenchGlossary;
use Shroud\Strategy\Value\Keep;
use Shroud\Strategy\Value\ShapedText;
use Shroud\Tests\Unit\Platform\TestPlatform;

$platform = new TestPlatform();

// The bare declaration of the platform, completed with the tables and columns of the site.
$declaration = $platform->bareDeclaration()->with(
    $platform->bareDeclaration()->getTable('people')->withColumns([
        'institution' => new ShapedText(),
    ]),
    new Table('visits', 'date', [
        'date' => new Keep(),
        'visits' => new Keep(),
    ]),
);

return new Configuration(
    declaration: $declaration,
    salt: 'a salt of the project, chosen once and for all',
    locale: 'fr_FR',
    language: new French(),
    platform: $platform,
    glossaries: [new FrenchGlossary(), new English()],
);
