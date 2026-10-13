# How it works

A **declaration** describes each table: its primary key, the columns that carry the reference
to its object, and a **strategy** per column. A column absent from the declaration stops the
transformation: nothing passes by default.

The **Disguiser** receives a batch of tables, observes it whole, then transforms each row
column by column. A strategy knows only the value and its context: the table, the row, the
referenced object, the generator and the declaration. The strategies come in families, by
what they need:

- `Value`: the strategy needs the value alone. Keep it, clear it, set a constant, replace a text
  or digits while keeping their shape, shift a date.
- `Pseudonym`: the strategy draws from the **pseudonym** of the referenced object, the complete
  fake identity drawn once for it: a gender and first names of that gender, last names,
  organization words, email, handle, street, postal code, city, the digits of a phone number.
  A civility follows the drawn gender, never the real one; an IP address, one per object, follows
  its id. A row without reference is refused.
- `Reference`: the strategy renumbers an id. `RenumberedId('person')` on any column that carries
  the id of a person, primary or foreign key, declares persons renumbered everywhere;
  `RenumberedPolymorphicId('object_type')` on the id of a polymorphic link reads the type from
  the row; `OwnerLink` learns during the observation that an object belongs to another.
- `Nested`: the strategy carries others and applies them to the parts of the value: by key of
  an array, inside a serialized blob or a JSON document, or according to another column of the
  row. `Serialized` given a strategy for the plain values also takes a column the application
  may or may not have serialized.
- `Wrapper`: the strategy wraps another one and acts on the whole value. `Fallback` applies a
  second strategy to the values the first refuses, so that the caller decides what becomes of
  malformed data; `WithoutEmails`, `WithoutPhones` and `WithoutIps` replace the email addresses,
  phone numbers and IP addresses of what the wrapped strategy returns, `WithoutIdentifiers` all
  three at once; `Hashed` hashes it, for a column that stores the digest of another.

## The generator

The **generator** draws each value from the SHA-256 blocks of the salt, the table, the key of
the row and the column; the pseudonym of an object is drawn the same way, from the salt and its
reference, each of its fields on its own. No draw shares a state with another, nor with the
random generator of PHP: two runs give the same result, whatever the order of the rows, the
version of PHP or the libraries loaded beside.

The **language** holds the words a fake identity is drawn from, first names by gender, last
names, streets, cities, organizations, and the conventions that the shape-preserving
replacement follows: the diacritic to put on a replacement whose original carried one, the
elided words to drop, `d'Aubigné`, the civilities. The core ships `French` and `English`; their
lists come from Faker, reviewed, and live in `resources/words`.

The **platform** holds what belongs to the CMS: the markup of its texts to leave untouched,
beyond HTML, and the references it embeds in them, which follow the renumbering of their
object. Without a platform package, `Generic` knows HTML alone.

## The renumbering

The ids are renumbered by a **permutation** specific to each object type and to the salt: a
Feistel network of four rounds over HMAC-SHA256, within the domain of a signed 32-bit integer.
It is reversible, so that the real id can be found behind a renumbered one by whoever holds the
salt, and it leaves zero and the negatives alone, the values that mean "no object".

The renumbered ids of a type spread over that whole domain, up to 2147483647, whatever the real
ones were. A copy that creates rows after its import then continues from the largest, close to
the limit of a signed `INT`. `RenumberedId('person', bits: 24)` narrows the domain of the type to
1 to 2^24 - 1, leaving room above, and fits a narrower column; a real id beyond it is refused.
The width holds for the type: declared once, or the same everywhere.

Declaring `RenumberedId('person')` somewhere renumbers the persons everywhere: the foreign keys,
the polymorphic links whose type is renumbered, the ids inside the blobs, the references the
platform embeds in the texts. A link to an object of a type that is not renumbered stays as it
is. `new Generator($salt, renumbering: false)`, or `Configuration::disguiser(renumbering: false)`,
turns the renumbering off without touching the declaration: a pass on the fly over a live base
then keeps its ids, so that what the site reads back by id lands on the same object
([On the fly](on-the-fly.md)).

The email of a pseudonym carries the renumbered id of its object in base 36: unique within its
type, even for two objects that draw the same names, which a unique key on the column requires.

## The observation pass

Some facts of shape must be known before any row is transformed: `FirstName` notes how many
words the first name of a person counts, so that `FullName` serves exactly as many to the full
name of the same person, in another table; `Phone` notes whose number it is, so that
`WithoutPhones` gives the same digits to the number where a text quotes it. The disguiser
therefore observes the whole batch first, and transforms second; the dump reads every observing
table once, whole, before writing anything, and the order of the tables does not matter.

## Following the owner

A coordinate, an email or an address, has in its row only its own id: the person is at the end
of a link table, then of a contact card. `OwnerLink`, placed on a column of the link table or
of the card, learns during the observation that the object of the row belongs to another one,
the email to the contact, the contact to the person. A reference declared `followingOwner()`
climbs that chain, and the email of the person carries the same pseudonym as their name.

## Extending a declaration

A site does not start from scratch. It takes the bare declaration of its platform and completes
it:

- `Table::withColumns()` returns a copy of a table with columns added, or strategies replaced;
  `withReference()` changes its reference columns.
- `Declaration::with()` replaces tables by their name, or adds them.
- `Declaration::merge()` makes another declaration win, table by table: the stack of a project
  is the bare declaration of the platform, then the site, then a local file that is not
  versioned, to keep a table on one machine without touching what is shared.

The renumbered types are computed again on the whole: a local table that no longer carries
`RenumberedId('person')` stops renumbering persons everywhere.
[`examples/shroud.php`](examples/shroud.php) shows the complete stack.

The rules that govern each replacement are in [Replacement rules](rules.md).
