# Replacement rules

A transformed value keeps the shape of the original and nothing of its content. The rules, in
the order they apply:

| Element of the original | Becomes |
| --- | --- |
| A word, a run of letters touching no digit | The fake word of the same rank: first name, last name, organization word or filler, according to the strategy |
| The case of the word: `DUPONT`, `Dupont`, `dupont` | The same case on the replacement |
| A diacritic in the word: `Müller` | A diacritic of the language on the replacement, `Bôyer` in French; none in English |
| A civility, before a full name or in its own column: `Mme`, `Mr.`, `Monsieur` | The civility of the gender of the pseudonym, which agrees with its first names, in the same language and form |
| A code, a run of letters and digits carrying at least one digit: `3e`, `AB12`, `a84ab1130` | Each digit and each letter replaced, case kept |
| An elision of the language before a word: `d'Aubigné`, `qu'importe` | Nothing: it disappears with its apostrophe |
| Blanks, non-breaking spaces, punctuation, hyphens | Unchanged, in place |
| HTML tags and entities, the markup of the platform | Unchanged; a reference embedded by the platform follows the renumbering of its object |
| A `+` and up to three digits at the head of a phone number | Unchanged, the rest of the digits replaced; `Phone` keeps the `0` of a national number too, and lays the digits of the pseudonym from the last, so that a number written two ways stays one |
| The host of a URL | Kept, or replaced by the fake domain of the pseudonym, as the declaration chooses; the path is rewritten, query and fragment dropped |
| A URL declared unique | Its last segment takes ten characters drawn from the original, so that two alike pseudonyms do not collide on a primary key |
| A phone number in a kept text, with `WithoutPhones`: 8 to 15 digits in groups, or glued after a `+` | The digits of the pseudonym of the person whose phone column holds it, or digits drawn from the number itself, laid like `Phone` does; dates, amounts, IP addresses, versions and ranges of years are left alone |
| An IP address in a kept text or a column of addresses, with `WithoutIps` | Another address, the same everywhere for the same address, never the original nor shared with another: a routable IPv4 address becomes another routable one, an IPv6 address one of `2001:db8::/32`; loopback, private and reserved addresses stay |
| The IP address of an object, with `Ip` | One address per object, in `240.0.0.0/4` or `2001:db8::/32` as the original, never shared within its type |
| An email address in a kept text, when the declaration asks for it | The address of the pseudonym of the person whose email column holds it, or a fake address on a reserved domain drawn from the address, the same in the whole base, case ignored |
| A date | Shifted by a number of days specific to the referenced object, so that the gaps between the dates of one object remain; the SQL zero date stays |
| A hexadecimal digest of another column | The digest of the fake value of that column, with the same algorithm |
| An id | The number the permutation of its type gives, the same everywhere, within the width of the type, 31 bits by default; zero and negatives stay |

The language, which holds the words of the fake identities and decides the diacritics, the
elisions and the civilities, and the observation pass, which holds first name, last name and full
name together, are described in [How it works](how-it-works.md).
