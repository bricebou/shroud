# The commands

The library ships three commands in `vendor/bin/`: `shroud-dump`, which writes the
pseudonymised dump of a base, or rehearses it; `shroud-diff`, which compares the base of a site
to its declaration and suggests what is missing; and `shroud-baseline`, which freezes the schema
of a bare platform. All three read the same project file and connect the same way.

## What the commands share

The project settles once, in a `shroud.php` file versioned at its root, its declaration, its
salt, its language, its platform and its glossaries;
[`examples/shroud.php`](examples/shroud.php) gives the model. The commands read it in the
current directory, or in the file that `--config` names.

`--connect` reads the connection file of the platform, the one of the current directory by
default, or the file given as value; the generic platform has none. Elsewhere, `--host`,
`--port`, `--database` and `--user` replace it, or `--dsn` for a complete PDO DSN; each option
takes precedence over the file. Without `--password`, the password is asked on the terminal,
without echo. `--help` details everything.

A usage or execution error, connection refused, unreadable configuration, output file that
cannot be opened, gives the exit code 2.

## `shroud-dump`

`Adapter\Pdo\MysqlDump` reads a MySQL or MariaDB base through PDO and writes a pseudonymised
SQL dump: the schema of each table taken from the server, the rows transformed by batches,
the truncated tables reduced to their schema. A table of the base absent from the declaration
stops everything; [`shroud-diff`](#shroud-diff) lists the gap before the dump is run.

```sh
vendor/bin/shroud-dump --connect --output=dump.sql
```

The dump imports with `mysql` into an empty base.

**Beware**: every real password is cleared, and the dump adds no account. To log into the
imported site, create an administrator again with the tools of the platform.

### The dry run

A sample of a few rows per table does not find the malformed data of an old base; only a full
pass does, and it must not stop at the first refusal. `--dry-run` reads the whole base and
passes every row through the strategies without writing anything, then reports the refusals by
table, column and reason:

```
contacts.phone: letters found (2 rows)
contacts.email: The strategy of contacts.email needs an object reference, but the row carries none (1 row)
Dry run: 5712394 rows in 140 tables, 3 refusals in 2 columns.
```

Each line names what to fix in the declaration: a `Fallback` around the strategy that refuses,
a reference column for the row that has none. The exit code is 1 on a refusal, 0 when every
row passes.

### The random salt

`--random-salt` replaces the salt of the project by one drawn for the run and kept nowhere:
the dump cannot be related to the original base, not even by the holder of the project. It is
the regime towards anonymisation described in the [README](../README.md#two-regimes-in-the-sense-of-the-gdpr),
for a base handed to a third party. The ids are still renumbered, consistently within the dump.

## `shroud-diff`

A declaration must cover the base of the site exactly: every table and every column, and
nothing more. A site starts from the bare declaration of its platform, and `shroud-diff` shows
it what its base carries in addition, tables of extensions and added columns, with a suggested
declaration for each: the diff of the site against the bare platform.

### Three things compared

```sh
vendor/bin/shroud-diff --connect
```

The command compares three things, which its preamble names:

- the **base** of the site, read through `information_schema`, with the version of the platform
  that the platform reads in it;
- the **declaration** of the site, the one `shroud.php` returns;
- the **baseline**: the frozen schema of a bare install of the platform at the version of the
  site, without any extension. The platform package embeds one per version whose schema
  changes; a site at a later version refers to the nearest frozen schema below, and the
  preamble says so.

The baseline splits the gap between the base and the declaration: what the base carries that
the bare platform does not have belongs to the site, its extensions and its columns; what the
bare platform has belongs to the platform, and its bare declaration should already declare it.
Without a readable version, or without a frozen schema, there is no baseline, and everything the
base carries in addition is presented as specific to the site.

### The output

After the preamble come numbered sections, each in three steps: a title that says where the
gap comes from and what it calls for, a sentence that explains it, then the tables, whole or
with their columns. A summary counts them. Here, on a site that triggers them all, are the four
possible sections:

```
Base         site: 29 tables, test 1.0.0
Declaration  /var/www/html/shroud.php: 30 tables
Baseline     bare test 1.0.0, frozen schema shipped with the platform package: tells apart what belongs to the site from what belongs to the platform

1. Specific to the site: to declare
   The base carries this, which a bare test 1.0.0 does not have: extensions, added columns.
   The draft at the end of the output declares it.

     contacts  whole table
     people    institution

2. Missing from the bare declaration of the platform: to declare, and to report
   A bare test 1.0.0 carries this, which the bare declaration of the platform does not declare.
   The draft at the end of the output declares it too, until the platform package is updated; report it there.

     people    recovery_code

3. Declared without existing in the base: to remove from the declaration
   The declaration names this, which the base does not have.
   Remove it, unless the base is incomplete; the draft recalls it as a todo.

     old_table whole table
     people    former

4. No effect: declared by the bare declaration of the platform for another version
   The bare declaration of the platform declares this for another version than 1.0.0, and the base does not have it.
   A declared column that the base does not have hinders neither the dump nor the transformation: nothing to do.

     people    newer

Summary: 3 to declare, 2 to remove, 1 with no effect. Exit code 1.
```

A truncated table is an exception both ways: its columns do not count, nor does its absence
from the base, since nothing of it is kept. When everything is covered, one line says so:

```
Complete declaration: every table and every column of the base is declared, and nothing more. Exit code 0.
```

The exit code follows `diff`: 0 when the declaration covers the base exactly, 1 on a gap, 2 on
a usage or execution error. The first three sections give 1; the fourth alone leaves 0. A script
or a CI that has the base at hand can thus make a guard of it: a migration that adds a column is
classified in the same commit, or the guard fails.

### The draft

As soon as something is to declare, the command ends its output with the draft: a complete
PHP file, with its imports, that returns a function receiving the declaration of the site and
returning it completed. It is copied as one block, between the two rules. `--write` also writes
it to a file, `shroud.suggestions.php` of the current directory, or the file given as value,
created or replaced. The command never writes into `shroud.php`.

```sh
vendor/bin/shroud-diff --connect --write
```

On the site of the example, the draft is this:

```php
<?php

/**
 * Declaration draft suggested by shroud-diff on 2026-10-12 for test 1.0.0.
 *
 * This file returns a function that receives the declaration of the site and returns it completed.
 * Read it column by column, the todos first, then fold it into shroud.php: either by copying
 * the tables and columns below into it, or by having it apply this file:
 *     $declaration = (require __DIR__ . '/shroud.suggestions.php')($declaration);
 *
 * @todo Remove from the declaration, named without existing in the base:
 *     old_table (whole table)
 *     people: former
 */

declare(strict_types=1);

use Shroud\Declaration\Declaration;
use Shroud\Declaration\ReferenceColumns;
use Shroud\Declaration\Table;
use Shroud\Strategy\Pseudonym\Email;
use Shroud\Strategy\Pseudonym\FirstName;
use Shroud\Strategy\Pseudonym\FullName;
use Shroud\Strategy\Pseudonym\Organization;
use Shroud\Strategy\Pseudonym\Phone;
use Shroud\Strategy\Reference\RenumberedId;
use Shroud\Strategy\Value\Clear;
use Shroud\Strategy\Value\Keep;

return static fn (Declaration $declaration): Declaration => $declaration->with(
    new Table('contacts', 'id_contact', [
        'id_contact' => new Keep(),
        'id_person' => new RenumberedId('person'),
        'first_name' => new FirstName(),
        'name' => new FullName(), // @todo review
        'email' => new Email(),
        'phone' => new Phone(),
        'updated_at' => new Keep(),
    ], ReferenceColumns::fromFixedType('contact', 'id_contact')),
    $declaration->getTable('people')->withColumns([
        'institution' => new Organization(),
        'recovery_code' => new Clear(), // test 1.0.0, not declared by the bare declaration
    ]),
);
```

The columns of one table, specific to the site or due to the version of the platform, join
in one `withColumns()`, since `Declaration::with()` replaces a table by its name; the latter
carry a comment that says so. The file runs as it is, but it is read first: a suggestion marked
`@todo` is a question, not an answer.

Once read, it folds into `shroud.php` in two ways. Either its tables and columns are copied
into it, and the file is deleted; or `shroud.php` applies it to its declaration before handing
it to `Configuration`, and the file stays beside it:

```php
$declaration = $platform->bareDeclaration();
$declaration = (require __DIR__ . '/shroud.suggestions.php')($declaration);

return new Configuration(declaration: $declaration, salt: '…', platform: $platform);
```

A new `shroud-diff` must then say "Complete declaration", or show only what is to remove.

### The heuristics

Each column receives a strategy from the rules of the platform first, then from its id shape,
then from the glossaries, then from its SQL type:

| Column | Suggestion |
| --- | --- |
| What the platform rules name: its secrets, its polymorphic link columns | What the platform says |
| `id_xxx` or `xxx_id` of a type renumbered in the declaration | `RenumberedId('xxx')`, `Keep` otherwise |
| `password`, `secret`, `token`, `cookie`, `iban`, `gender`… | `Clear` |
| `ip`, `ips`, `ip_address`, `remote_addr` | `WithoutIps`: each address disguised, the same everywhere |
| `birth_date`, `date_naissance` | `ShiftedDate` |
| `status`, `lang`, `type`, `rank`, `date`, `amount`, `price`… | `Keep` |
| `data`, `prefs`, `config`, `uuid`… | `Keep`, to review: a blob or an external id |
| `email`, `first_name`, `last_name`, `login` | `Email`, `FirstName`, `LastName`, `Login` |
| `civility`, `salutation`, `civilite` | `Civility` |
| `name`, `author`, `signature`, `nom`, `from_name` | `FullName`, to review: a person or a title? |
| `phone`, `fax`, `mobile`, `telephone` | `Phone`: the digits of the pseudonym, the dialling code kept |
| `address`, `postal_code`, `city`, `adresse`, `code_postal`, `ville` | `Street`, `PostalCode`, `City` |
| `company`, `organization`, `institution`, `societe` | `Organization` |
| `url`, `link`, `website`, `lien` | `Url` without the host, to review |
| numbers, dates, enumerations | `Keep` |
| free text | `ShapedText`, to review: public or personal? |

The glossaries are `Schema\Glossary\English` and `Schema\Glossary\French`; the platform names
the ones that fit its schema, and the project may give its own. An uncertain suggestion carries
the comment `// @todo review`, which the editor picks up. The draft never replaces a
classification made by hand.

## `shroud-baseline`

The bare declaration of a platform must cover the schema of a bare install of the platform,
and that schema is frozen in the platform package, one JSON file per version whose schema
changes, so that the coverage can be checked without a base and `shroud-diff` has its baseline.
`shroud-baseline` freezes it:

```sh
vendor/bin/shroud-baseline --connect=/path/to/the/bare/site/config/connect.php
```

The base must be a bare setup of the platform, without extension. The platform reads its
version in the base, or `--version` gives it; the file is written where the platform keeps its
frozen schemas, which is a directory of the platform package, so the command is run from a
checkout of that package. A version already frozen is replaced with `--force` only. A version
whose schema equals the previous frozen one needs no file.
