# Replacement rules

A transformed value keeps the shape of the original and nothing of its content. The rules, in
the order they apply:

| Element of the original | Becomes |
| --- | --- |
| A word, a run of letters touching no digit | The fake word of the same rank: first name, last name, organization word or filler, according to the strategy |
| The case of the word: `DUPONT`, `Dupont`, `dupont` | The same case on the replacement |
| A diacritic in the word: `Müller` | A diacritic of the language on the replacement, `Bôyer` in French; none in a plain language |
| A code, a run of letters and digits carrying at least one digit: `3e`, `AB12`, `a84ab1130` | Each digit and each letter replaced, case kept |
| An elision of the language before a word: `d'Aubigné`, `qu'importe` | Nothing: it disappears with its apostrophe |
| Blanks, non-breaking spaces, punctuation, hyphens | Unchanged, in place |
| HTML tags and entities, the markup of the platform | Unchanged; a reference embedded by the platform follows the renumbering of its object |
| A `+` and up to three digits at the head of a phone number | Unchanged, the rest of the digits replaced |
| The host of a URL | Kept, or replaced by the fake domain of the pseudonym, as the declaration chooses; the path is rewritten, query and fragment dropped |
| A URL declared unique | Its last segment takes ten characters drawn from the original, so that two alike pseudonyms do not collide on a primary key |
| An email address in a kept text, when the declaration asks for it | A fake address on a reserved domain, the same for the same address in the whole base, case ignored |
| A date | Shifted by a number of days specific to the referenced object, so that the gaps between the dates of one object remain; the SQL zero date stays |
| A hexadecimal digest of another column | The digest of the fake value of that column, with the same algorithm |
| An id | The number the permutation of its type gives, the same everywhere; zero and negatives stay |

The language, which decides the diacritics and the elisions, and the observation pass, which
holds first name, last name and full name together, are described in [How it works](how-it-works.md).
