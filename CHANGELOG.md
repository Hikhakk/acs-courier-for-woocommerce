# Changelog

All notable changes to this project are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the project uses
[Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Planned
- Label printing, pickup list workflow, locker selection, rates, tracking, COD.

## [0.1.0] - 2026-09-02

### Added
- ACS REST client with no WordPress dependency, covering both of ACS's error channels.
- Rate throttling (ACS allows 10 calls per second) and bounded exponential backoff.
- Domain model for Greece and Cyprus, including per-country postcode rules and the
  Cyprus content-type requirement.
- Weight handling with the ACS 0.5–999 kg bounds and volumetric calculation.
- Mapping from WooCommerce orders, converting kg, g, lbs and oz to kilograms.
- Voucher creation with local pre-flight validation and an atomic per-order lock.
- Settings screen with `wp-config.php` constant override for credentials.
- Order screen panel for creating a voucher.
- Uninstall handler covering both HPOS and legacy order meta.
