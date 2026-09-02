# Contributing

Thanks for helping improve this plugin.

## Before opening a pull request

```bash
composer install
composer check   # lint + static analysis + tests
```

All three must pass. CI runs the same gate across PHP 8.0–8.4.

## Conventions

- **Tests first.** Every behavioural change ships with a test that failed before it.
- `src/Api` must stay free of WordPress. If you need a WordPress function there, you
  probably want a new `Transport` implementation or a service in `src/Service` instead.
- ACS's misspelled field names belong in `src/Mapping/FieldMap.php` and nowhere else.
- Show ACS's error messages verbatim. They are frequently Greek; do not translate them.

## Reporting ACS API behaviour

If you find ACS behaving differently from the documentation, please include the request
alias, the response body with credentials removed, and the HTTP status. Recorded responses
become fixtures, which is how this plugin stays correct.

## Security

See [SECURITY.md](SECURITY.md). Please do not open a public issue for a vulnerability.
