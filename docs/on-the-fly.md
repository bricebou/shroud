# Disguising on the fly

The dump is the usual way: the real base is read once and a disguised copy is written, which is
imported elsewhere. Some uses cannot wait for a copy. A test suite that runs against the real base
of a developer, or an indexing job that reads the base row by row, can still keep the personal data
out of what it produces: the rows are disguised in memory, as they are read, and only disguised
rows come out.

This is not an anonymisation of the base. The base stays real, and so does everything read from
it without going through the disguiser. On the fly protects what the code produces, documents,
fixtures, snapshots, logs; it says nothing about who may read the base.

## Three rules

**One disguiser for the whole run.** The pseudonyms are deterministic and come out the same from
any disguiser built on the same salt and the same language, whatever else the process draws, but the facts observed on the objects live in the disguiser
that observed them: the number of words of a first name, the owner of an email. Build it once,
keep it for the run, drop it at the end.

**No renumbering.** The code that reads the base often reads more of it by id, behind the
disguiser's back: the keywords of an article, the URL of a person. `disguiser(renumbering: false)`
keeps the ids as they are, so that those reads land on the same object. What they bring back is
real, as said above; everything that goes through the disguiser is not.

**Observe the batch before disguising it.** The shape of a full name depends on what was observed
on its person in other tables. Observe every row the run will disguise first, with `observeRow()`,
then disguise them with `disguiseRow()`: the result depends neither on the order of the reads nor
on the order of the tests. `disguiseTables()` does both on a set of tables already in memory.

## A sketch

The code that loads the rows offers a hook, here `RowLoader::transformRowsWith()`, which receives
each row before it is used:

```php
use Shroud\Configuration;
use Shroud\Disguiser;

final class DisguisedRows
{
    private static ?Disguiser $disguiser = null;

    /**
     * Builds the disguiser of the run and observes every row it will disguise.
     *
     * @param array<string, list<array<string, mixed>>> $batch the rows by table
     */
    public static function start(array $batch): void
    {
        $disguiser = Configuration::fromFile(__DIR__ . '/shroud.php')->disguiser(renumbering: false);
        foreach ($batch as $table => $rows) {
            foreach ($rows as $row) {
                $disguiser->observeRow($table, $row);
            }
        }
        self::$disguiser = $disguiser;
        RowLoader::transformRowsWith(self::row(...));
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    public static function row(string $table, array $row): array
    {
        return (self::$disguiser ?? throw new \LogicException('call start() first'))->disguiseRow($table, $row);
    }

    public static function stop(): void
    {
        RowLoader::transformRowsWith(null);
        self::$disguiser = null;
    }
}
```

A test suite calls `start()` once before the tests that read the base, with the rows of the objects
they use, and `stop()` after them.

The declaration is the one of the dump: the same `shroud.php`, the same salt, so that a person
carries the same pseudonym on the fly and in a dump, ids aside. A truncated table has no disguised
form: `disguiseRow()` refuses its rows, and `disguiseTables()` returns it empty, as the dump writes
it.
