# Security Policy

## Supported versions

The latest released version receives security fixes.

## Reporting a vulnerability

Please report privately through
[GitHub Security Advisories](https://github.com/Hikhakk/acs-courier-for-woocommerce/security/advisories/new)
rather than opening a public issue.

Please include the affected version, reproduction steps, and the impact you believe it has.

## Handling of credentials

- ACS credentials are stored with `autoload` disabled, so they are not loaded on front-end requests.
- They can be supplied by `wp-config.php` constants instead, keeping them out of the database.
- The API key is never rendered into HTML and never written to logs.
- Every admin action is nonce-protected and capability-checked.
