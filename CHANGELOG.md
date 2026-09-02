# Changelog

All notable changes to this project are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the project uses
[Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.3.0] - 2026-09-02

### Added
- WooCommerce shipping method offering ACS home delivery and pickup-point rates,
  configurable per shipping zone.
- Checkout pickup point selector, served from a local indexed table so checkout never
  waits on ACS. Around 1,600 points across Greece and Cyprus.
- Daily pickup point refresh through Action Scheduler.
- The chosen pickup point now routes the voucher, adding the ACS `REC` product.
- Origin station and label format settings.

### Added earlier in this cycle
- Country-aware rate resolution. Greece is priced live by ACS; Cyprus uses a local
  weight-banded table, because `ACS_Price_Calculation` does not support Cyprus.
- A rate table with weight bands, separate home and locker pricing, a per-kilo increment
  above the heaviest band, and a free-shipping threshold.
- `PickupPoint`, mapping ACS store and Smartpoint rows with great-circle distance.
  Verified against 1,620 live points (73 Cypriot lockers, 33 Cypriot stores, 1,514 Greek lockers).

### Planned
- Checkout locker selector, WooCommerce shipping method, cash on delivery.

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
