# Development

## Tools

```sh
composer install    # the library and its development tools
composer check      # every check, without changing anything
composer fix        # Rector, then the coding style
```

| Script | Tool | Changes the files |
| --- | --- | --- |
| `check` | the five below, in order | no |
| `check:syntax` | parallel-lint | no |
| `check:cs` | EasyCodingStandard | no |
| `check:rector` | Rector, dry run | no |
| `check:static` | PHPStan, level 9 with the strict rules | no |
| `test` | PHPUnit | no |
| `fix` | Rector, then EasyCodingStandard | yes |
| `fix:cs` | EasyCodingStandard | yes |
| `fix:rector` | Rector | yes |

The verb says whether the command changes files, the suffix names the concern. The commands of
`bin/` go through the same checks as the source. The continuous integration runs `composer
check` on PHP 8.4 and 8.5.

## Standards

- **Style**: the `psr12`, `common` and `cleanCode` sets of EasyCodingStandard, plus the strict
  rules, `declare(strict_types=1)`, the trailing comma in multiline constructs and no vertical
  alignment in docblocks. No exemption to the sets. The `.editorconfig` follows.
- **Rector**: the PHP 8.4 set, the floor declared in `composer.json`, with the `deadCode`,
  `codeQuality`, `namedArgs` and `earlyReturn` sets and the PHPUnit attributes. The rule that
  names the arguments of attributes is skipped: PHPUnit forbids named arguments on its own.
- **Static analysis**: PHPStan at level 9 on `src/`, `tests/` and `bin/`, with
  `phpstan-strict-rules` and `phpstan-phpunit`.
- **Tests**: PHPUnit, suite `unit` in `tests/Unit/`. A test bears a name that says what it
  checks, and its expectations are established by running, never assumed. The data of the
  tests are made up: no value taken from a real base, anywhere in the repository. The tests
  have their own platform, `Shroud\Tests\Unit\Platform\TestPlatform`, with two frozen schemas
  and a connection file in `tests/fixtures/`.
- **PHP**: 8.4 at least, with the `intl`, `mbstring` and `pdo` extensions.

## Conventions

- Everything is in **English**: names, docblocks, comments, documentation and commit messages.
- Each method, constructors excluded, opens its docblock with **one line** that says what it
  does, with an action verb; a short paragraph follows when the mechanism deserves it. An
  inline comment accompanies any logic that does not read by itself. No `@todo` in the code.
- An abstract class bears the prefix `Abstract`, an interface the suffix `Interface`, and every
  other class is `final`. The common interface of the exceptions, `ShroudException`, is the
  one exception to the suffix.
- The strategies sort by family, according to what they need: `Value`, `Pseudonym`,
  `Reference`, `Nested`, `Wrapper`. The contract and the abstract classes stay at the root of
  `Strategy/`.
- The commits follow [Conventional Commits](https://www.conventionalcommits.org/): a type, a subject in the imperative,
  a body that says why rather than what.

## Writing a platform package

A platform package implements `Shroud\Platform\PlatformInterface` and depends on the core:

- `name()`, in lowercase, for the messages and the file names;
- `preservedMarkup()`, the regular expression fragments of the markup of its texts to leave
  untouched, beyond HTML;
- `renumberReferences()`, the renumbering of the references it embeds in a text, consulting
  `Context::$declaration` for the renumbered types;
- `bareDeclaration()`, the declaration of its own tables, before any extension;
- `frozenSchemas()`, a `Schema\FrozenSchemas` on a directory of the package, one JSON file per
  version whose schema changed, produced by `shroud-baseline`;
- `readVersion()`, the version installed on an open base;
- `connectionFile()` and `readConnection()`, the connection file of a site, read without
  executing it;
- `draftRules()` and `glossaries()`, the vocabulary of its columns for the draft.

The package keeps a test that compares its bare declaration to its latest frozen schema with
`Schema\Coverage`, so that any gap fails. For a new version of the platform: install it bare on
an empty base, freeze its schema with `shroud-baseline`, run the test, draft the new columns
with `shroud-diff --config` on a project file that uses the bare declaration alone, classify
each column marked for review, remove the columns that disappeared, run the test again. The
frozen schemas of the older versions stay: `shroud-diff` uses them as baseline for the sites
that have not migrated yet.
