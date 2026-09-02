# ACS Courier for WooCommerce — Design

**Date:** 2026-09-02
**Target:** apoel.com.cy (Cloudways) — WP 7.1, WooCommerce 10.5.3, PHP 8.4
**Scope:** Shipping (voucher creation, labels, pickup list) + tracking, home delivery and
Smartpoint locker pickup, Cyprus. Prepaid only.

---

## 1. Purpose

Let store staff turn a paid WooCommerce order into an ACS shipment without leaving wp-admin,
and let customers choose locker pickup at checkout and follow their parcel afterwards.

**In scope:** voucher creation, label printing, the pickup-list workflow, tracking sync,
customer-facing tracking, locker/store pickup selection, a local rate table.

**Out of scope (v1):** COD, Greece-bound shipments, multi-warehouse, returns/RVO,
label re-print after 6 months, ACS price calculation (unavailable for Cyprus — see §4).

---

## 2. Constraints discovered by probing the live API

These were verified against the staging account, not taken from the PDF.

| Finding | Consequence |
|---|---|
| Errors return **HTTP 200** with `ACSExecution_HasError: false` and the real message in `ACSValueOutput[0].Error_Message` | The client must check **both** channels. This is the single most likely source of silent failure. |
| Response envelope key is misspelled `ACSOutputResponce` | Contained in one mapper; never leaks past the client. |
| Field names misspelled `Cod_Ammount`, `Insurance_Ammount` | Same — one mapping table. |
| `ACS_Price_Calculation` does not support Cyprus | Rates must be local. See §6. |
| `Content_Type_ID` mandatory for `Recipient_Country: CY` | Hard validation before send; failure means Larnaca customs fines. |
| 18 content types, 33 CY stores, **73 CY Smartpoint lockers** | Cached reference data, refreshed daily. |
| Rate limit 10 concurrent calls/sec → **406** | Client-side throttle + backoff. |
| Vouchers must be printed *before* the pickup list is issued | Workflow guardrail, not a free-form button. |
| An issued pickup list makes vouchers permanently undeletable | Confirmation step before issuing. |
| Cypriot ZIP is 4-digit (Greek 5-digit) | Validation differs by country. |
| Weight min 0.5 kg, max 999; max 99 pieces; volumetric = L×W×H/5000 | Clamp and warn at mapping time. |

---

## 3. Architecture

Four layers, each independently testable. Dependencies point downward only.

```
┌─────────────────────────────────────────────────────────┐
│ 4. WordPress / WooCommerce surface                      │
│    shipping method · order meta box · bulk actions      │
│    checkout locker selector · emails · my-account       │
└───────────────────────┬─────────────────────────────────┘
                        │
┌───────────────────────▼─────────────────────────────────┐
│ 3. Services (WP-aware, orchestration)                   │
│    ShipmentService · TrackingService · PickupListService│
│    PickupPointRepository · RateCalculator               │
└───────────────────────┬─────────────────────────────────┘
                        │
┌───────────────────────▼─────────────────────────────────┐
│ 2. Domain (pure PHP value objects)                      │
│    Shipment · Voucher · PickupPoint · TrackingEvent     │
│    OrderMapper (WC order → ACS fields)                  │
└───────────────────────┬─────────────────────────────────┘
                        │
┌───────────────────────▼─────────────────────────────────┐
│ 1. AcsClient — ZERO WordPress dependencies              │
│    envelope · dual error check · throttle · Transport   │
└─────────────────────────────────────────────────────────┘
```

**Why layer 1 has no WordPress:** it can be tested with plain PHPUnit and a fake `Transport`,
using fixtures recorded from the staging account. No WP bootstrap, tests run in milliseconds,
and every ACS quirk in §2 gets a regression test. When ACS changes their contract, one layer
changes.

`Transport` is an interface. Production uses a `WpHttpTransport` wrapping `wp_remote_post`;
tests use `ArrayTransport` replaying fixtures. This is the only seam between layers 1 and 2.

### File layout

```
wc-acs-courier/
├── wc-acs-courier.php              # bootstrap, HPOS declaration
├── src/
│   ├── Api/  AcsClient · Transport · WpHttpTransport · AcsException · Throttle
│   ├── Domain/  Shipment · Voucher · PickupPoint · TrackingEvent · ContentType
│   ├── Mapping/ OrderMapper · FieldMap
│   ├── Service/ ShipmentService · TrackingService · PickupListService
│   │            PickupPointRepository · RateCalculator
│   ├── Admin/   SettingsPage · OrderMetaBox · BulkActions · PickupListScreen
│   ├── Frontend/ LockerSelector · TrackingDisplay
│   └── Integration/ ShippingMethod · Emails · Scheduler
├── assets/
├── languages/                      # el_GR + en_GB
└── tests/  Unit/ · Integration/ · fixtures/
```

---

## 4. Data flow

```
Order paid
   │
   ▼
[ShipmentService::create]
   ├─ OrderMapper: WC order → ACS fields
   │    · weight: sum line items, clamp to 0.5 kg floor
   │    · address: street/number split, region separated (ACS rejects region in address)
   │    · CY → require Content_Type_ID
   │    · locker chosen → Acs_Station_Destination + Branch + product "REC"
   ├─ validate locally (fail fast, no API call on invalid data)
   └─ ACS_Create_Voucher → Voucher_No stored on order meta + order note
   │
   ▼
[Print label]  ACS_Print_Voucher_V2 → base64 byte array → PDF
   · thermal (Print_Type 1) or laser A4 (Print_Type 2, Start_Position 1-3)
   · marks order as "label printed" — required before the next step
   │
   ▼
[Issue pickup list]  ACS_Issue_Pickup_List  ← MANDATORY, else barcodes are dead
   · blocked in UI unless every selected voucher is printed
   · confirmation: after this, vouchers cannot be deleted
   └─ PickupList_No → print via ACS_Print_Pickup_List
   │
   ▼
[Tracking]  Action Scheduler, every 6h for shipments < 30 days old
   · ACS_Trackingsummary → shipment_status
   · status 4 = delivered → mark order complete (configurable)
   · status 6/7 = returning/returned → order note + admin notice
   · non-delivery codes (AD1/AP1/LS3/…) → human-readable order note
```

**Idempotency.** Voucher creation is guarded by an order meta lock plus a check for an existing
`Voucher_No`. A double-click, a retried webhook, or a duplicated Action Scheduler job must never
produce two vouchers for one order — that would be a real cost and a real parcel.

---

## 5. Locker pickup at checkout

**Data.** `PickupPointRepository` caches ACS stores (`ACS_SHOP_KIND` 1) and Smartpoint lockers
(kind 8) for `CY` in a custom table, refreshed daily by Action Scheduler. Each row keeps
station id, branch id, description, address, lat/lng, and opening hours. Checkout never calls ACS.

**UX.** When the customer picks the "ACS Pickup Point" shipping method, a selector appears
under the shipping block:

- their postcode pre-filters to the nearest points (haversine against cached lat/lng)
- a searchable list showing name, address and opening hours, with distance
- selection stores `station_id` + `branch_id` in session, then order meta
- the chosen point is echoed in the order confirmation, admin order screen, and emails

A map is deliberately **not** in v1 — it needs a tile provider and an API key for marginal gain
over a distance-sorted list. The data model supports adding one later without migration.

**Validation.** ACS rejects locker delivery when `Item_Quantity > 1`, and requires a recipient
mobile number. Both are checked at checkout, not at voucher time, so the customer sees the
problem while they can still fix it.

---

## 6. Rates

`ACS_Price_Calculation` does not support Cyprus, so the shipping method cannot ask ACS what to
charge. `RateCalculator` uses a weight-banded table, editable in settings:

| Band | Home delivery | Locker |
|---|---|---|
| 0–2 kg | configurable | configurable, lower |
| 2–5 kg | | |
| 5–10 kg | | |
| 10 kg+ | per-kg increment | |

Free-shipping threshold, and a flag to charge on volumetric weight (L×W×H/5000) when it exceeds
actual. Values are the merchant's commercial decision, seeded with placeholders — **not invented
by me**, since real ACS pricing depends on their contract.

---

## 7. Error handling

**Three failure classes, handled differently:**

1. **Transport** (timeout, DNS, 5xx) — retry with exponential backoff, max 3, then surface and
   leave the order untouched. Never partially mutate.
2. **Auth / rate limit** (403 / 406) — no retry on 403 (it will never succeed); 406 backs off and
   requeues via Action Scheduler.
3. **Business** (HTTP 200 + error in either channel) — parsed into `AcsException` with the ACS
   message, written to an order note verbatim, shown as an admin notice. ACS messages are often
   Greek; they are shown as-is rather than mistranslated.

**Logging** goes through `WC_Logger` (source `acs-courier`), so it lands in WooCommerce → Status →
Logs. Requests and responses are logged with **credentials redacted**.

---

## 8. Security

- Credentials live in `wp_options`, writable only by `manage_woocommerce`, and are **overridable
  by constants in `wp-config.php`** (`ACS_API_KEY` etc.) so production secrets need never be in
  the database or a git repo.
- The API key is never rendered in HTML, never logged, and masked in the settings field.
- All admin actions are nonce-protected and capability-checked; label PDFs stream through an
  authenticated handler rather than a guessable uploads URL.
- No customer data is sent to ACS beyond what the voucher requires.

---

## 9. Testing

**Unit (no WordPress, fast):** `AcsClient` against recorded fixtures — the dual-error case,
the misspelled envelope, 403, 406, malformed JSON, timeout. `OrderMapper` — weight clamping,
address/region splitting, CY content-type enforcement, locker field population. `RateCalculator` —
every band boundary, volumetric crossover, free-shipping threshold.

**Integration (WP + WooCommerce):** order → voucher → label → pickup list against the **staging
ACS account**, using a scratch order. Every created voucher is deleted in teardown *before* a
pickup list is issued, since issuing makes deletion impossible.

**Manual:** checkout locker selection on mobile and desktop; label print on both thermal and A4.

TDD throughout: the ACS quirks in §2 each get a failing test first.

---

## 10. Prerequisites — blocked on the client

The store cannot currently produce an order. These are required before end-to-end testing:

1. **Restore WooCommerce pages** — cart, checkout, my-account are all deleted (IDs 288/289/287).
2. **Units** — store is set to **oz / inches**; ACS needs **kg / cm**.
3. **Store address** — currently the theme placeholder *"350 5th Ave New York 10118"*. Needs
   APOEL's real sender address and phone; it prints on every voucher.
4. **Product weights** — none of the 14 products has a weight. ACS requires ≥ 0.5 kg and weight
   drives the rate band.
5. **Payment gateway** — handled externally per decision; testing uses programmatic orders.
6. **Rate table values** — merchant's ACS contract pricing.

---

## 11. Decisions and their reasons

| Decision | Why |
|---|---|
| WP-free API client | ACS's contract is quirky; isolating it makes every quirk regression-testable without a WP bootstrap. |
| Action Scheduler, not wp-cron | Already running on this install; observable, retryable, survives traffic gaps. |
| Cache pickup points | 73+33 rows; checkout must never block on a third party. |
| Local rate table | ACS price calculation genuinely does not support Cyprus. |
| No map in v1 | Tile provider + key for marginal gain over a sorted list. |
| Declare HPOS compatible | Costs nothing now, avoids a migration wall later. |
| Prepaid only, COD seam left | Per decision; `Cod_*` fields exist in `FieldMap` but are always null, so COD is additive. |
| Idempotency lock on voucher creation | A duplicate voucher is a real parcel and a real cost. |

---

## 12. Build phases

Sequenced so each phase is independently verifiable and the risky parts come first.

| # | Phase | Hours | Verifiable when |
|---|---|---|---|
| 0 | Shop restoration (pages, metric units, store address, CY shipping zone, weights) | 3–5 | A programmatic order completes with a shipping line |
| 1 | `AcsClient` + fixtures + throttle | 3–4 | Unit tests green for every §2 quirk |
| 2 | Domain + `OrderMapper` | 3 | Order maps to a valid ACS payload, offline |
| 3 | Settings screen + credential handling | 2 | Round-trips creds; constants override |
| 4 | Voucher creation + idempotency | 4 | Real voucher on staging, deleted in teardown |
| 5 | Label printing (thermal + laser, bulk) | 3 | Valid PDF from byte array |
| 6 | Pickup list + workflow guardrails | 3 | Cannot issue with unprinted vouchers |
| 7 | Pickup point cache + checkout selector | 5–7 | Customer selects a locker; it reaches the voucher |
| 8 | Shipping method + rate table | 3 | Correct rate at every band boundary |
| 9 | Tracking sync + customer display | 4 | Status transitions drive order notes |
| 10 | i18n (EL/EN), hardening, end-to-end pass | 3 | Full create→print→issue→track cycle |

## 13. Estimate

**Core plugin including locker pickup: ~33–39 h.** With shop restoration (phase 0):
**~36–44 h** — consistent with the 40 h figure quoted before scope was fixed.

Dropping COD and the payment gateway removed work from the earlier quote, but locker pickup
(phase 7) added it back; the two roughly cancel.

Excludes: payment gateway integration, real ACS contract rate values, product weight data entry.
