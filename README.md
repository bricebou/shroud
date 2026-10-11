# Shroud

Deterministic, CMS-agnostic pseudonymisation of a relational database: consistent fake
identities, reversible ids, shape-preserving text replacement, verifiable coverage.

Shroud is being rebuilt brick by brick. This repository holds the tooling only for now.

## Development

```sh
composer install
composer check   # syntax, coding style, Rector, static analysis, tests
composer fix     # apply Rector, then the coding style
```

Shroud requires PHP 8.4 or later.

## License

Shroud is released under the [MIT License](LICENSE).
