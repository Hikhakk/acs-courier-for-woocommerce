# Changelog

All notable changes to this project are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the project uses
[Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Planned
- Smartpoint locker selection at checkout, shipping method with rates, cash on delivery.

## [0.2.0] - 2026-09-02

### Added
- Label printing in thermal and A4 laser formats, streamed through an authenticated handler.
- Pickup list workflow. Issuing the list is mandatory, since ACS does not recognise voucher
  barcodes until it exists, and its vouchers can never be deleted afterwards.
- Shipment tracking with status, checkpoint history and English non-delivery reasons.
- Print label and Refresh tracking actions on the order screen.

### Fixed
- A returned parcel is no longer reported as delivered. ACS sets `delivery_flag` to 1 when a
  returned shipment reaches the sender, so delivery now requires the status, the flag and the
  absence of a return.

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
