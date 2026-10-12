<p align="center">
  <picture>
    <source media="(prefers-color-scheme: dark)" srcset="shroud-dark.svg">
    <img src="shroud.svg" alt="" width="128" height="128">
  </picture>
</p>

# Shroud

Deterministic, CMS-agnostic pseudonymisation of a relational database: consistent fake
identities, reversible ids, shape-preserving text replacement, verifiable coverage.

Shroud turns the base of a site into a dump without a real name, email or address that still
looks and behaves like the original: the same person keeps the same fake name and the same fake
email in every table, the ids are renumbered consistently, the texts keep their shape, the
markup of the platform survives. The dump imports into an empty base, for development, tests,
demonstrations or hand-overs.

## What it does

- **One declaration per table**: a primary key, the columns that reference an object, and a
  strategy per column. A column absent from the declaration stops the run: nothing passes by
  default.
- **A deterministic generator**: every fake value derives from a salt, the table, the row and
  the column. Never from the real value, never from the order of the reads. Two runs give the
  same result, byte for byte.
- **A pseudonym per object**: the same person receives the same fake identity in every table
  where they appear, serialized blobs and JSON documents included, and a coordinate linked to
  a person through a chain of tables carries their pseudonym.
- **Shape-preserving strategies**: case, punctuation, blanks, diacritics, digits and codes,
  HTML and the markup of the platform keep their place; only the content changes.
- **A reversible renumbering** of the ids, by a Feistel network over HMAC-SHA256, propagated
  through the declared columns, the polymorphic links, the blobs and the references embedded
  in the texts.
- **A verifiable coverage**: the schema of a base compares to its declaration both ways, and
  what is missing comes out as a draft of declaration, column by column, with the vocabulary
  of the platform and the language of the schema.

The core knows no CMS. It handles arrays of rows, a declaration and a generator, and depends on
no other library: its draws come from SHA-256, its word lists, first names, last names, streets,
cities, from Faker, reviewed and frozen in `resources/words`. A platform package brings what belongs to a CMS: its bare declaration, its frozen
schemas, the markup of its texts, the references it embeds in them, its connection file and its
column vocabulary. See [Platforms](#platforms).

## Use cases

- **A development base.** Each developer works on a copy of production without its people. The
  salt is versioned with the project, so the dump is reproducible, and whoever holds the salt
  can trace a renumbered id back to the real row to reproduce a bug. This is the
  [pseudonymisation](#pseudonymisation-the-default) regime.
- **A staging or demonstration site.** The site keeps the volume, the relations and the look of
  real content, without showing a real name, email or address to a client or an audience.
- **A base handed to a contractor.** An agency, a freelancer or a hosting provider receives a
  base to work on. `--random-salt` cuts every link to the original, and the tables of mass data
  come out empty; whether the result is anonymous remains for the controller to assess, see
  [towards anonymisation](#towards-anonymisation-with-a-random-salt).
- **Tests in continuous integration.** The dump is made where the real base lives, then imported
  by the pipeline as a fixture: with the same salt it comes out the same, byte for byte, and the
  tests run against realistic data without a real name, email or address.
- **Tests or jobs against the real base.** When a copy is not an option, the rows are disguised
  in memory as they are read, and only disguised rows reach the documents, snapshots or logs the
  code produces. See [On the fly](docs/on-the-fly.md).

## Two regimes, in the sense of the GDPR

Shroud removes the direct identifiers and replaces them with values that derive from none of the
real ones. What it keeps is the structure: the number of rows, the relations between them, the
shape of the values, the dates shifted by a per-person offset. Whether the result is personal
data or not depends on what else exists, and the regulation draws the line there.

### Pseudonymisation, the default

The salt of the project is versioned with its declaration. Whoever holds the salt and the
original base can invert the renumbering and relate every row of the dump to its origin; the
dump alone cannot be attributed to anyone.

This is **pseudonymisation** as [Article 4(5) of the GDPR](https://gdpr-info.eu/art-4-gdpr/)
defines it:

> ‘pseudonymisation’ means the processing of personal data in such a manner that the personal
> data can no longer be attributed to a specific data subject without the use of additional
> information, provided that such additional information is kept separately and is subject to
> technical and organisational measures to ensure that the personal data are not attributed to
> an identified or identifiable natural person;

Pseudonymised data remains personal data, and the regulation keeps applying to it
([Recital 26](https://gdpr-info.eu/recitals/no-26/)). Pseudonymisation is a safeguard that the
regulation names among the measures of data protection by design
([Article 25](https://gdpr-info.eu/art-25-gdpr/)) and of security of processing
([Article 32(1)(a)](https://gdpr-info.eu/art-32-gdpr/)). This regime fits a base that stays
inside the organisation: a development or test base, a staging site, a reproducible fixture.

### Towards anonymisation, with a random salt

For a base handed to a third party, `shroud-dump --random-salt` replaces the salt of the project
by one drawn for the run and kept nowhere. Nobody, not even the holder of the project, can then
relate the values of the dump to the base. Tables of mass data that identify by their volume,
statistics, logs, search results, are declared truncated and come out empty.

Whether the result is anonymous is for the controller to assess, not for the tool to claim.
Anonymous information is information that does not relate to an identified or identifiable
person, taking account of all the means reasonably likely to be used to identify them
([Recital 26](https://gdpr-info.eu/recitals/no-26/)). The
[Opinion 05/2014 of the Article 29 Working Party](https://ec.europa.eu/justice/article-29/documentation/opinion-recommendation/files/2014/wp216_en.pdf)
measures it against three risks: singling out an individual, linking records that relate to
the same individual, inferring information about them. The
[Guidelines 01/2025 of the European Data Protection Board](https://www.edpb.europa.eu/our-work-tools/documents/public-consultations/2025/guidelines-012025-pseudonymisation_en),
adopted for public consultation on 16 January 2025, detail what pseudonymisation guarantees and
what it does not; the French authority sums the three criteria up on its page
[L'anonymisation de données personnelles](https://www.cnil.fr/fr/lanonymisation-de-donnees-personnelles).

Shroud addresses the first two risks on the values, and leaves the structure untouched: a
person who is the only one to have placed forty orders in a small shop remains singled out by
the forty orders. Hence, "towards": the tool brings a base as close to anonymous as a
value-level transformation can, and the assessment of the residual risk belongs to the
controller, dataset by dataset.

## Prerequisites

- **PHP 8.4** or later, with the `intl`, `mbstring` and `pdo` extensions, and `pdo_mysql` for
  the commands. Disguising rows [on the fly](docs/on-the-fly.md) needs no connection of its own.
- **A MySQL or MariaDB base.** The commands read the schema from `information_schema` and write
  a dump in the dialect of MySQL: no other database is supported. The trials ran on MariaDB 10.11
  and 11.8.
- **Read access to the base**, from the machine that runs the commands.

## Installation

```sh
composer require --dev momh/shroud
```

The commands `shroud-dump`, `shroud-diff` and `shroud-baseline` land in `vendor/bin/`, and the
project versions at its root a `shroud.php` file, its declaration, its salt and its platform, of
which [`docs/examples/shroud.php`](docs/examples/shroud.php) gives the model.

```sh
vendor/bin/shroud-diff --host=db --database=site --user=site   # what the declaration misses
vendor/bin/shroud-dump --host=db --database=site --user=site --dry-run
vendor/bin/shroud-dump --host=db --database=site --user=site --output=dump.sql
```

## Platforms

A platform package implements `Shroud\Platform\PlatformInterface` and hands the core what
belongs to its CMS: the bare declaration of its tables, its frozen schemas per version, the
markup of its texts to preserve, the renumbering of the references it embeds in them, its
connection file, its draft rules and its glossaries. A project then starts from the bare
declaration and adds its own tables and columns; `shroud-diff` tells apart what belongs to the
site from what belongs to the version of the platform.

The first platform package, for SPIP, is being built on
[git.spip.net](https://git.spip.net/spip-contrib-outils/shroud-spip). Without a platform
package, the core treats the base as a generic relational base whose texts carry HTML at most,
and the declaration is written by hand, with `shroud-diff` drafting it.

## Documentation

- [How it works](docs/how-it-works.md): declaration, strategies, pseudonyms, renumbering,
  generator, ownership, extending a declaration.
- [Replacement rules](docs/rules.md): what a transformed value keeps of the original, and what
  disappears.
- [The commands](docs/commands.md): `shroud-dump` and its dry run, `shroud-diff` and its draft,
  `shroud-baseline`, the project file.
- [On the fly](docs/on-the-fly.md): disguising rows as they are read, for a test suite or an
  indexing job that runs against the real base.
- [Development](docs/development.md): tools, standards and conventions.

## License

Shroud is released under the [MIT License](LICENSE). The word lists of `resources/words` come
from [Faker](https://github.com/FakerPHP/Faker), released under the MIT License as well.
