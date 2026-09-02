# ACS Courier for WooCommerce — Plan 1: Foundation & Voucher Creation

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a WooCommerce plugin that turns a real order into a real ACS voucher, on a foundation whose ACS-facing layer is fully unit-tested without WordPress.

**Architecture:** Four layers, dependencies pointing downward only. Layer 1 (`AcsClient`) has zero WordPress dependencies and talks to a `Transport` interface, so every quirk of ACS's contract is testable against recorded fixtures in milliseconds. Layer 2 is pure-PHP domain objects and the field mapper. Layers 3–4 (services, WordPress surface) are added in this plan only as far as voucher creation needs.

**Tech Stack:** PHP 8.0+ (dev on 8.5), Composer (dev deps + optimized autoloader), PHPUnit 10, PHPCS with WordPress-Extra, PHPStan level 6, WordPress 6.0+, WooCommerce 8.0+.

**Spec:** `docs/specs/2026-09-02-acs-courier-plugin-design.md`

## Global Constraints

- Licence **GPL-2.0-or-later**; header in every PHP file.
- Plugin slug and text domain: **`acs-courier-for-woocommerce`** (identical).
- Display name: **"ACS Courier for WooCommerce"**. Never "WooCommerce ACS".
- Root namespace: **`AcsCourier\`**, PSR-4 from `src/`.
- Option / meta / hook prefix: **`acs_wc_`**.
- Supported: WordPress 6.0+, WooCommerce 8.0+, PHP 8.0–8.4. **No PHP 8.1+ syntax** (no `readonly`, no enums, no `never`) — 8.0 is the floor.
- **No feature gating, no paywalls, no telemetry, no phone-home, no storefront "powered by" output.** Directory rule.
- API endpoint: `https://webservices.acscourier.net/ACSRestServices/api/ACSAutoRest`
- Auth header name: **`AcsApiKey`** (exact casing).
- Credentials must be overridable by `wp-config.php` constants and never logged.
- ACS field misspellings (`ACSOutputResponce`, `Cod_Ammount`, `Insurance_Ammount`) exist **only** inside `FieldMap`/`AcsClient`.
- Weight bounds 0.5–999 kg; max 99 pieces; volumetric divisor 5000.
- Never commit real credentials. `tests/fixtures/live/` is gitignored.

---

### Task 1: Project scaffold and test harness

**Files:**
- Create: `composer.json`
- Create: `phpunit.xml.dist`
- Create: `tests/bootstrap.php`
- Create: `.gitattributes`
- Test: `tests/Unit/SanityTest.php`

**Interfaces:**
- Consumes: nothing.
- Produces: autoload root `AcsCourier\` → `src/`, test root `AcsCourier\Tests\` → `tests/`. Command `composer test` runs PHPUnit.

- [ ] **Step 1: Write the failing test**

`tests/Unit/SanityTest.php`:
```php
<?php
declare(strict_types=1);

namespace AcsCourier\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class SanityTest extends TestCase
{
    public function test_autoloader_maps_the_plugin_namespace(): void
    {
        self::assertTrue(
            class_exists(\AcsCourier\Support\Version::class),
            'Expected AcsCourier\\Support\\Version to autoload from src/'
        );
    }
}
```

- [ ] **Step 2: Create composer.json and PHPUnit config**

`composer.json`:
```json
{
    "name": "kdvassiliou/acs-courier-for-woocommerce",
    "description": "ACS Courier shipping and tracking for WooCommerce (Greece & Cyprus).",
    "type": "wordpress-plugin",
    "license": "GPL-2.0-or-later",
    "require": {
        "php": ">=8.0"
    },
    "require-dev": {
        "phpunit/phpunit": "^10.5",
        "squizlabs/php_codesniffer": "^3.9",
        "wp-coding-standards/wpcs": "^3.1",
        "phpcompatibility/phpcompatibility-wp": "^2.1",
        "phpstan/phpstan": "^1.11",
        "dealerdirect/phpcodesniffer-composer-installer": "^1.0"
    },
    "autoload": {
        "psr-4": { "AcsCourier\\": "src/" }
    },
    "autoload-dev": {
        "psr-4": { "AcsCourier\\Tests\\": "tests/" }
    },
    "config": {
        "allow-plugins": { "dealerdirect/phpcodesniffer-composer-installer": true },
        "optimize-autoloader": true,
        "sort-packages": true
    },
    "scripts": {
        "test": "phpunit",
        "test:unit": "phpunit --testsuite unit",
        "lint": "phpcs",
        "lint:fix": "phpcbf",
        "analyse": "phpstan analyse"
    }
}
```

`phpunit.xml.dist`:
```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="vendor/phpunit/phpunit/phpunit.xsd"
         bootstrap="tests/bootstrap.php"
         colors="true"
         failOnWarning="true"
         failOnRisky="true"
         beStrictAboutOutputDuringTests="true">
    <testsuites>
        <testsuite name="unit">
            <directory>tests/Unit</directory>
        </testsuite>
        <testsuite name="integration">
            <directory>tests/Integration</directory>
        </testsuite>
    </testsuites>
    <source>
        <include><directory>src</directory></include>
    </source>
</phpunit>
```

`tests/bootstrap.php`:
```php
<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
```

`.gitattributes` (keeps dev files out of the distributed zip):
```
/tests            export-ignore
/docs             export-ignore
/.github          export-ignore
phpunit.xml.dist  export-ignore
phpcs.xml.dist    export-ignore
phpstan.neon.dist export-ignore
.gitattributes    export-ignore
.gitignore        export-ignore
```

- [ ] **Step 3: Run the test to verify it fails**

```bash
composer install
composer test -- --testsuite unit
```
Expected: FAIL — `AcsCourier\Support\Version` does not exist.

- [ ] **Step 4: Write the minimal implementation**

`src/Support/Version.php`:
```php
<?php
/**
 * Plugin version constants.
 *
 * @package AcsCourier
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace AcsCourier\Support;

final class Version
{
    public const PLUGIN  = '0.1.0';
    public const MIN_PHP = '8.0';
    public const MIN_WP  = '6.0';
    public const MIN_WC  = '8.0';
}
```

- [ ] **Step 5: Run the test to verify it passes**

```bash
composer test -- --testsuite unit
```
Expected: PASS, 1 test.

- [ ] **Step 6: Commit**

```bash
git add composer.json composer.lock phpunit.xml.dist tests/ src/ .gitattributes
git commit -m "build: add composer, phpunit and autoload scaffold"
```

---

### Task 2: Transport seam

**Files:**
- Create: `src/Api/TransportResponse.php`
- Create: `src/Api/Transport.php`
- Create: `src/Api/ArrayTransport.php`
- Test: `tests/Unit/Api/ArrayTransportTest.php`

**Interfaces:**
- Consumes: nothing.
- Produces:
  - `TransportResponse` — public readonly-style props `int $status`, `string $body`; constructor `__construct(int $status, string $body)`.
  - `Transport::post(string $url, array $payload, array $headers): TransportResponse`.
  - `ArrayTransport::__construct(array $queue)` where each element is a `TransportResponse`; `->requests(): array` returns recorded `['url','payload','headers']`.

- [ ] **Step 1: Write the failing test**

`tests/Unit/Api/ArrayTransportTest.php`:
```php
<?php
declare(strict_types=1);

namespace AcsCourier\Tests\Unit\Api;

use AcsCourier\Api\ArrayTransport;
use AcsCourier\Api\TransportResponse;
use PHPUnit\Framework\TestCase;

final class ArrayTransportTest extends TestCase
{
    public function test_it_returns_queued_responses_in_order(): void
    {
        $transport = new ArrayTransport([
            new TransportResponse(200, '{"a":1}'),
            new TransportResponse(403, 'denied'),
        ]);

        self::assertSame('{"a":1}', $transport->post('https://x', [], [])->body);
        self::assertSame(403, $transport->post('https://x', [], [])->status);
    }

    public function test_it_records_what_was_sent(): void
    {
        $transport = new ArrayTransport([new TransportResponse(200, '{}')]);
        $transport->post('https://x', ['ACSAlias' => 'Ping'], ['AcsApiKey' => 'k']);

        $recorded = $transport->requests();
        self::assertCount(1, $recorded);
        self::assertSame('Ping', $recorded[0]['payload']['ACSAlias']);
        self::assertSame('k', $recorded[0]['headers']['AcsApiKey']);
    }

    public function test_it_throws_when_the_queue_is_exhausted(): void
    {
        $transport = new ArrayTransport([]);
        $this->expectException(\RuntimeException::class);
        $transport->post('https://x', [], []);
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

```bash
composer test -- --testsuite unit
```
Expected: FAIL — `AcsCourier\Api\ArrayTransport` not found.

- [ ] **Step 3: Write the minimal implementation**

`src/Api/TransportResponse.php`:
```php
<?php
/**
 * @package AcsCourier
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace AcsCourier\Api;

final class TransportResponse
{
    public int $status;
    public string $body;

    public function __construct(int $status, string $body)
    {
        $this->status = $status;
        $this->body   = $body;
    }
}
```

`src/Api/Transport.php`:
```php
<?php
/**
 * @package AcsCourier
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace AcsCourier\Api;

interface Transport
{
    /**
     * @param array<string,mixed> $payload
     * @param array<string,string> $headers
     * @throws TransportFailure On network-level failure.
     */
    public function post(string $url, array $payload, array $headers): TransportResponse;
}
```

`src/Api/TransportFailure.php`:
```php
<?php
/**
 * @package AcsCourier
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace AcsCourier\Api;

final class TransportFailure extends \RuntimeException
{
}
```

`src/Api/ArrayTransport.php`:
```php
<?php
/**
 * Test double: replays queued responses and records requests.
 *
 * @package AcsCourier
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace AcsCourier\Api;

final class ArrayTransport implements Transport
{
    /** @var list<TransportResponse> */
    private array $queue;

    /** @var list<array{url:string,payload:array<string,mixed>,headers:array<string,string>}> */
    private array $requests = [];

    /** @param list<TransportResponse> $queue */
    public function __construct(array $queue)
    {
        $this->queue = $queue;
    }

    public function post(string $url, array $payload, array $headers): TransportResponse
    {
        $this->requests[] = ['url' => $url, 'payload' => $payload, 'headers' => $headers];

        if ($this->queue === []) {
            throw new \RuntimeException('ArrayTransport queue exhausted.');
        }

        return array_shift($this->queue);
    }

    /** @return list<array{url:string,payload:array<string,mixed>,headers:array<string,string>}> */
    public function requests(): array
    {
        return $this->requests;
    }
}
```

- [ ] **Step 4: Run the test to verify it passes**

```bash
composer test -- --testsuite unit
```
Expected: PASS, 4 tests.

- [ ] **Step 5: Commit**

```bash
git add src/Api tests/Unit/Api
git commit -m "feat(api): add transport seam with recording test double"
```

---

### Task 3: AcsException

**Files:**
- Create: `src/Api/AcsException.php`
- Test: `tests/Unit/Api/AcsExceptionTest.php`

**Interfaces:**
- Consumes: nothing.
- Produces: `AcsException` with named constructors `business(string $message, string $alias)`, `auth(string $alias)`, `rateLimited(string $alias)`, `malformed(string $alias)`, `transport(string $message, string $alias)`; accessors `->alias(): string`, `->kind(): string` returning one of `business|auth|rate_limited|malformed|transport`; `->isRetryable(): bool` true only for `rate_limited` and `transport`.

- [ ] **Step 1: Write the failing test**

`tests/Unit/Api/AcsExceptionTest.php`:
```php
<?php
declare(strict_types=1);

namespace AcsCourier\Tests\Unit\Api;

use AcsCourier\Api\AcsException;
use PHPUnit\Framework\TestCase;

final class AcsExceptionTest extends TestCase
{
    public function test_business_errors_carry_the_acs_message_verbatim(): void
    {
        $e = AcsException::business('Invalid pick-up date', 'ACS_Create_Voucher');

        self::assertSame('Invalid pick-up date', $e->getMessage());
        self::assertSame('ACS_Create_Voucher', $e->alias());
        self::assertSame('business', $e->kind());
        self::assertFalse($e->isRetryable());
    }

    public function test_auth_failures_are_never_retryable(): void
    {
        self::assertFalse(AcsException::auth('X')->isRetryable());
        self::assertSame('auth', AcsException::auth('X')->kind());
    }

    /**
     * @dataProvider retryableProvider
     */
    public function test_only_transient_kinds_are_retryable(AcsException $e, bool $expected): void
    {
        self::assertSame($expected, $e->isRetryable());
    }

    public static function retryableProvider(): array
    {
        return [
            'rate limited' => [AcsException::rateLimited('X'), true],
            'transport'    => [AcsException::transport('timeout', 'X'), true],
            'malformed'    => [AcsException::malformed('X'), false],
            'business'     => [AcsException::business('bad', 'X'), false],
        ];
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

```bash
composer test -- --testsuite unit
```
Expected: FAIL — `AcsCourier\Api\AcsException` not found.

- [ ] **Step 3: Write the minimal implementation**

`src/Api/AcsException.php`:
```php
<?php
/**
 * @package AcsCourier
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace AcsCourier\Api;

final class AcsException extends \RuntimeException
{
    public const KIND_BUSINESS     = 'business';
    public const KIND_AUTH         = 'auth';
    public const KIND_RATE_LIMITED = 'rate_limited';
    public const KIND_MALFORMED    = 'malformed';
    public const KIND_TRANSPORT    = 'transport';

    private string $alias;
    private string $kind;

    private function __construct(string $message, string $alias, string $kind)
    {
        parent::__construct($message);
        $this->alias = $alias;
        $this->kind  = $kind;
    }

    public static function business(string $message, string $alias): self
    {
        return new self($message, $alias, self::KIND_BUSINESS);
    }

    public static function auth(string $alias): self
    {
        return new self('ACS rejected the API key or credentials.', $alias, self::KIND_AUTH);
    }

    public static function rateLimited(string $alias): self
    {
        return new self('ACS rate limit exceeded.', $alias, self::KIND_RATE_LIMITED);
    }

    public static function malformed(string $alias): self
    {
        return new self('ACS returned a response that could not be parsed.', $alias, self::KIND_MALFORMED);
    }

    public static function transport(string $message, string $alias): self
    {
        return new self($message, $alias, self::KIND_TRANSPORT);
    }

    public function alias(): string
    {
        return $this->alias;
    }

    public function kind(): string
    {
        return $this->kind;
    }

    public function isRetryable(): bool
    {
        return in_array($this->kind, [self::KIND_RATE_LIMITED, self::KIND_TRANSPORT], true);
    }
}
```

- [ ] **Step 4: Run the test to verify it passes**

```bash
composer test -- --testsuite unit
```
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add src/Api/AcsException.php tests/Unit/Api/AcsExceptionTest.php
git commit -m "feat(api): add AcsException with retryability classification"
```

---

### Task 4: Credentials value object

**Files:**
- Create: `src/Api/Credentials.php`
- Test: `tests/Unit/Api/CredentialsTest.php`

**Interfaces:**
- Consumes: nothing.
- Produces: `Credentials::__construct(string $companyId, string $companyPassword, string $userId, string $userPassword, string $apiKey)`; `->toArray(): array` returning exactly the four body keys `Company_ID`, `Company_Password`, `User_ID`, `User_Password`; `->apiKey(): string`; `->isComplete(): bool`.

- [ ] **Step 1: Write the failing test**

`tests/Unit/Api/CredentialsTest.php`:
```php
<?php
declare(strict_types=1);

namespace AcsCourier\Tests\Unit\Api;

use AcsCourier\Api\Credentials;
use PHPUnit\Framework\TestCase;

final class CredentialsTest extends TestCase
{
    public function test_it_maps_to_the_exact_body_keys_acs_expects(): void
    {
        $c = new Credentials('CO', 'cpw', 'USER', 'upw', 'key');

        self::assertSame(
            ['Company_ID' => 'CO', 'Company_Password' => 'cpw', 'User_ID' => 'USER', 'User_Password' => 'upw'],
            $c->toArray()
        );
    }

    public function test_the_api_key_is_not_part_of_the_body(): void
    {
        $c = new Credentials('CO', 'cpw', 'USER', 'upw', 'secret-key');

        self::assertArrayNotHasKey('apiKey', $c->toArray());
        self::assertNotContains('secret-key', $c->toArray());
        self::assertSame('secret-key', $c->apiKey());
    }

    public function test_incomplete_credentials_are_detected(): void
    {
        self::assertFalse((new Credentials('', 'cpw', 'USER', 'upw', 'k'))->isComplete());
        self::assertFalse((new Credentials('CO', 'cpw', 'USER', 'upw', ''))->isComplete());
        self::assertTrue((new Credentials('CO', 'cpw', 'USER', 'upw', 'k'))->isComplete());
    }

    public function test_it_never_exposes_secrets_when_cast_to_string(): void
    {
        $c = new Credentials('CO', 'cpw', 'USER', 'upw', 'secret-key');
        $dump = print_r($c->redacted(), true);

        self::assertStringNotContainsString('secret-key', $dump);
        self::assertStringNotContainsString('cpw', $dump);
        self::assertStringNotContainsString('upw', $dump);
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

```bash
composer test -- --testsuite unit
```
Expected: FAIL — class not found.

- [ ] **Step 3: Write the minimal implementation**

`src/Api/Credentials.php`:
```php
<?php
/**
 * @package AcsCourier
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace AcsCourier\Api;

final class Credentials
{
    private string $companyId;
    private string $companyPassword;
    private string $userId;
    private string $userPassword;
    private string $apiKey;

    public function __construct(
        string $companyId,
        string $companyPassword,
        string $userId,
        string $userPassword,
        string $apiKey
    ) {
        $this->companyId       = $companyId;
        $this->companyPassword = $companyPassword;
        $this->userId          = $userId;
        $this->userPassword    = $userPassword;
        $this->apiKey          = $apiKey;
    }

    /** @return array<string,string> */
    public function toArray(): array
    {
        return [
            'Company_ID'       => $this->companyId,
            'Company_Password' => $this->companyPassword,
            'User_ID'          => $this->userId,
            'User_Password'    => $this->userPassword,
        ];
    }

    public function apiKey(): string
    {
        return $this->apiKey;
    }

    public function isComplete(): bool
    {
        return '' !== $this->companyId
            && '' !== $this->companyPassword
            && '' !== $this->userId
            && '' !== $this->userPassword
            && '' !== $this->apiKey;
    }

    /**
     * Safe for logs and error reports.
     *
     * @return array<string,string>
     */
    public function redacted(): array
    {
        return [
            'Company_ID'       => $this->companyId,
            'Company_Password' => '***',
            'User_ID'          => $this->userId,
            'User_Password'    => '***',
            'AcsApiKey'        => '***',
        ];
    }
}
```

- [ ] **Step 4: Run the test to verify it passes**

```bash
composer test -- --testsuite unit
```
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add src/Api/Credentials.php tests/Unit/Api/CredentialsTest.php
git commit -m "feat(api): add Credentials value object with redaction"
```

---

### Task 5: AcsClient — envelope parsing and the dual error check

This is the highest-risk unit in the plugin. ACS returns HTTP 200 with `ACSExecution_HasError: false` for genuine failures, hiding the message in `ACSValueOutput[0].Error_Message`. Verified live: requesting a bogus voucher returns exactly that shape.

**Files:**
- Create: `src/Api/AcsClient.php`
- Create: `tests/fixtures/tracking_bogus_voucher.json`
- Create: `tests/fixtures/station_by_zip_cy.json`
- Test: `tests/Unit/Api/AcsClientTest.php`

**Interfaces:**
- Consumes: `Transport`, `TransportResponse`, `Credentials`, `AcsException` (Tasks 2–4).
- Produces: `AcsClient::__construct(Transport $transport, Credentials $credentials, string $endpoint = AcsClient::ENDPOINT)`; `->call(string $alias, array $params = []): array` returning the **contents of `ACSOutputResponce`**; helpers `->valueOutput(array $response): array` and `->tableData(array $response): array`. Constant `AcsClient::ENDPOINT`.

- [ ] **Step 1: Record the fixtures**

`tests/fixtures/tracking_bogus_voucher.json` — the real shape observed from ACS:
```json
{
  "ACSExecution_HasError": false,
  "ACSExecutionErrorMessage": "",
  "ACSOutputResponce": {
    "ACSValueOutput": [
      { "Error_Message": "Νο Acs shipment found for your company with voucher no: 0" }
    ],
    "ACSTableOutput": {}
  }
}
```

`tests/fixtures/station_by_zip_cy.json`:
```json
{
  "ACSExecution_HasError": false,
  "ACSExecutionErrorMessage": "",
  "ACSOutputResponce": {
    "ACSValueOutput": [ { "Error_Message": null } ],
    "ACSTableOutput": {
      "Table_Data": [
        { "PostalCode": 1010, "ACS Station": "NI", "PostalCode Description": "Λευκωσία", "Prefecture": "" }
      ]
    }
  }
}
```

- [ ] **Step 2: Write the failing test**

`tests/Unit/Api/AcsClientTest.php`:
```php
<?php
declare(strict_types=1);

namespace AcsCourier\Tests\Unit\Api;

use AcsCourier\Api\AcsClient;
use AcsCourier\Api\AcsException;
use AcsCourier\Api\ArrayTransport;
use AcsCourier\Api\Credentials;
use AcsCourier\Api\TransportResponse;
use PHPUnit\Framework\TestCase;

final class AcsClientTest extends TestCase
{
    private function fixture(string $name): string
    {
        return (string) file_get_contents(__DIR__ . '/../../fixtures/' . $name . '.json');
    }

    private function client(array $queue, ?ArrayTransport &$transport = null): AcsClient
    {
        $transport = new ArrayTransport($queue);
        return new AcsClient($transport, new Credentials('CO', 'cpw', 'USER', 'upw', 'api-key'));
    }

    public function test_it_sends_the_alias_credentials_and_api_key_header(): void
    {
        $client = $this->client(
            [new TransportResponse(200, $this->fixture('station_by_zip_cy'))],
            $transport
        );

        $client->call('ACS_Find_Station_By_Zip_Code', ['Zip_Code' => '1010']);

        $sent = $transport->requests()[0];
        self::assertSame(AcsClient::ENDPOINT, $sent['url']);
        self::assertSame('ACS_Find_Station_By_Zip_Code', $sent['payload']['ACSAlias']);
        self::assertSame('CO', $sent['payload']['ACSInputParameters']['Company_ID']);
        self::assertSame('1010', $sent['payload']['ACSInputParameters']['Zip_Code']);
        self::assertSame('api-key', $sent['headers']['AcsApiKey']);
        self::assertSame('application/json', $sent['headers']['Content-Type']);
    }

    public function test_it_returns_the_misspelled_envelope_contents(): void
    {
        $client   = $this->client([new TransportResponse(200, $this->fixture('station_by_zip_cy'))]);
        $response = $client->call('ACS_Find_Station_By_Zip_Code', ['Zip_Code' => '1010']);

        self::assertArrayHasKey('ACSTableOutput', $response);
        self::assertSame('NI', $client->tableData($response)[0]['ACS Station']);
    }

    /**
     * The critical case: HTTP 200, HasError false, real error nested in ACSValueOutput.
     */
    public function test_it_detects_the_silent_business_error(): void
    {
        $client = $this->client([new TransportResponse(200, $this->fixture('tracking_bogus_voucher'))]);

        $this->expectException(AcsException::class);
        $this->expectExceptionMessage('Νο Acs shipment found for your company with voucher no: 0');

        $client->call('ACS_Trackingsummary', ['Voucher_No' => '0000000000']);
    }

    public function test_a_null_nested_error_is_not_an_error(): void
    {
        $client = $this->client([new TransportResponse(200, $this->fixture('station_by_zip_cy'))]);
        $this->expectNotToPerformAssertions();
        $client->call('ACS_Find_Station_By_Zip_Code', ['Zip_Code' => '1010']);
    }

    public function test_it_detects_the_top_level_error_channel(): void
    {
        $body = json_encode([
            'ACSExecution_HasError'    => true,
            'ACSExecutionErrorMessage' => 'Invalid pick-up date',
            'ACSOutputResponce'        => [],
        ]);
        $client = $this->client([new TransportResponse(200, (string) $body)]);

        $this->expectException(AcsException::class);
        $this->expectExceptionMessage('Invalid pick-up date');
        $client->call('ACS_Create_Voucher');
    }

    public function test_403_is_an_auth_error(): void
    {
        $client = $this->client([new TransportResponse(403, 'Forbidden')]);
        try {
            $client->call('ACS_Stations');
            self::fail('Expected AcsException');
        } catch (AcsException $e) {
            self::assertSame(AcsException::KIND_AUTH, $e->kind());
            self::assertFalse($e->isRetryable());
        }
    }

    public function test_406_is_a_retryable_rate_limit(): void
    {
        $client = $this->client([new TransportResponse(406, 'Not Acceptable')]);
        try {
            $client->call('ACS_Stations');
            self::fail('Expected AcsException');
        } catch (AcsException $e) {
            self::assertSame(AcsException::KIND_RATE_LIMITED, $e->kind());
            self::assertTrue($e->isRetryable());
        }
    }

    public function test_malformed_json_is_reported_as_malformed(): void
    {
        $client = $this->client([new TransportResponse(200, 'not json at all')]);
        try {
            $client->call('ACS_Stations');
            self::fail('Expected AcsException');
        } catch (AcsException $e) {
            self::assertSame(AcsException::KIND_MALFORMED, $e->kind());
        }
    }

    public function test_server_errors_are_retryable_transport_failures(): void
    {
        $client = $this->client([new TransportResponse(503, 'Service Unavailable')]);
        try {
            $client->call('ACS_Stations');
            self::fail('Expected AcsException');
        } catch (AcsException $e) {
            self::assertSame(AcsException::KIND_TRANSPORT, $e->kind());
            self::assertTrue($e->isRetryable());
        }
    }
}
```

- [ ] **Step 3: Run the test to verify it fails**

```bash
composer test -- --testsuite unit
```
Expected: FAIL — `AcsCourier\Api\AcsClient` not found.

- [ ] **Step 4: Write the minimal implementation**

`src/Api/AcsClient.php`:
```php
<?php
/**
 * Framework-agnostic ACS REST client.
 *
 * Deliberately has no WordPress dependency so the whole of ACS's contract
 * can be regression-tested without a WordPress bootstrap.
 *
 * @package AcsCourier
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace AcsCourier\Api;

final class AcsClient
{
    public const ENDPOINT = 'https://webservices.acscourier.net/ACSRestServices/api/ACSAutoRest';

    /**
     * ACS nests business errors under several spellings depending on the method.
     */
    private const NESTED_ERROR_KEYS = ['Error_Message', 'error_message', 'Error_msg', 'error_msg'];

    private Transport $transport;
    private Credentials $credentials;
    private string $endpoint;

    public function __construct(Transport $transport, Credentials $credentials, string $endpoint = self::ENDPOINT)
    {
        $this->transport   = $transport;
        $this->credentials = $credentials;
        $this->endpoint    = $endpoint;
    }

    /**
     * @param array<string,mixed> $params
     * @return array<string,mixed> Contents of ACSOutputResponce.
     * @throws AcsException
     */
    public function call(string $alias, array $params = []): array
    {
        $payload = [
            'ACSAlias'           => $alias,
            'ACSInputParameters' => array_merge($this->credentials->toArray(), $params),
        ];

        try {
            $raw = $this->transport->post($this->endpoint, $payload, $this->headers());
        } catch (TransportFailure $e) {
            throw AcsException::transport($e->getMessage(), $alias);
        }

        return $this->parse($alias, $raw);
    }

    /** @return array<string,string> */
    private function headers(): array
    {
        return [
            'Content-Type' => 'application/json',
            'Accept'       => 'application/json',
            'AcsApiKey'    => $this->credentials->apiKey(),
        ];
    }

    /**
     * @return array<string,mixed>
     * @throws AcsException
     */
    private function parse(string $alias, TransportResponse $raw): array
    {
        if (403 === $raw->status) {
            throw AcsException::auth($alias);
        }
        if (406 === $raw->status) {
            throw AcsException::rateLimited($alias);
        }
        if ($raw->status >= 500) {
            throw AcsException::transport('HTTP ' . $raw->status . ' from ACS.', $alias);
        }
        if (200 !== $raw->status) {
            throw AcsException::transport('Unexpected HTTP ' . $raw->status . ' from ACS.', $alias);
        }

        $data = json_decode($raw->body, true);
        if (!is_array($data)) {
            throw AcsException::malformed($alias);
        }

        // Channel 1: the documented flag.
        if (!empty($data['ACSExecution_HasError'])) {
            $message = isset($data['ACSExecutionErrorMessage']) && '' !== $data['ACSExecutionErrorMessage']
                ? (string) $data['ACSExecutionErrorMessage']
                : 'ACS reported an unspecified error.';
            throw AcsException::business($message, $alias);
        }

        // Note the misspelling: it is ACS's, not ours. Contained here.
        $response = isset($data['ACSOutputResponce']) && is_array($data['ACSOutputResponce'])
            ? $data['ACSOutputResponce']
            : [];

        // Channel 2: the silent one. HTTP 200 + HasError false + a real error nested here.
        $this->assertNoNestedError($alias, $response);

        return $response;
    }

    /**
     * @param array<string,mixed> $response
     * @throws AcsException
     */
    private function assertNoNestedError(string $alias, array $response): void
    {
        $values = $response['ACSValueOutput'] ?? null;
        if (!is_array($values) || !isset($values[0]) || !is_array($values[0])) {
            return;
        }

        foreach (self::NESTED_ERROR_KEYS as $key) {
            if (!array_key_exists($key, $values[0])) {
                continue;
            }
            $message = $values[0][$key];
            if (null === $message || '' === $message) {
                continue;
            }
            throw AcsException::business((string) $message, $alias);
        }
    }

    /**
     * @param array<string,mixed> $response
     * @return array<int,array<string,mixed>>
     */
    public function valueOutput(array $response): array
    {
        $values = $response['ACSValueOutput'] ?? [];
        return is_array($values) ? $values : [];
    }

    /**
     * @param array<string,mixed> $response
     * @return array<int,array<string,mixed>>
     */
    public function tableData(array $response): array
    {
        $table = $response['ACSTableOutput'] ?? [];
        if (!is_array($table)) {
            return [];
        }
        $rows = $table['Table_Data'] ?? [];
        return is_array($rows) ? $rows : [];
    }
}
```

- [ ] **Step 5: Run the test to verify it passes**

```bash
composer test -- --testsuite unit
```
Expected: PASS, all 10 `AcsClientTest` cases.

- [ ] **Step 6: Commit**

```bash
git add src/Api/AcsClient.php src/Api/TransportFailure.php tests/Unit/Api/AcsClientTest.php tests/fixtures
git commit -m "feat(api): add AcsClient with dual-channel error detection

ACS returns HTTP 200 with ACSExecution_HasError false for genuine failures,
hiding the message in ACSValueOutput[0].Error_Message. Both channels are
checked; fixtures recorded from the live API."
```

---

### Task 6: Throttle and retry

ACS rejects more than 10 simultaneous calls per second with HTTP 406.

**Files:**
- Create: `src/Api/Throttle.php`
- Create: `src/Api/RetryingClient.php`
- Test: `tests/Unit/Api/RetryingClientTest.php`

**Interfaces:**
- Consumes: `AcsClient`, `AcsException`.
- Produces: `Throttle::__construct(int $maxPerSecond = 8, ?callable $sleeper = null, ?callable $clock = null)` with `->acquire(): void`. `RetryingClient::__construct(AcsClient $inner, int $maxAttempts = 3, ?callable $sleeper = null)` with `->call(string $alias, array $params = []): array` — same signature as `AcsClient::call`, so it is a drop-in.

- [ ] **Step 1: Write the failing test**

`tests/Unit/Api/RetryingClientTest.php`:
```php
<?php
declare(strict_types=1);

namespace AcsCourier\Tests\Unit\Api;

use AcsCourier\Api\AcsClient;
use AcsCourier\Api\AcsException;
use AcsCourier\Api\ArrayTransport;
use AcsCourier\Api\Credentials;
use AcsCourier\Api\RetryingClient;
use AcsCourier\Api\TransportResponse;
use PHPUnit\Framework\TestCase;

final class RetryingClientTest extends TestCase
{
    private const OK = '{"ACSExecution_HasError":false,"ACSExecutionErrorMessage":"","ACSOutputResponce":{"ACSTableOutput":{"Table_Data":[]}}}';

    private function inner(array $queue): AcsClient
    {
        return new AcsClient(new ArrayTransport($queue), new Credentials('C', 'p', 'U', 'p', 'k'));
    }

    public function test_it_retries_a_rate_limit_and_then_succeeds(): void
    {
        $slept  = [];
        $client = new RetryingClient(
            $this->inner([
                new TransportResponse(406, 'Not Acceptable'),
                new TransportResponse(200, self::OK),
            ]),
            3,
            static function (float $seconds) use (&$slept): void {
                $slept[] = $seconds;
            }
        );

        $client->call('ACS_Stations');

        self::assertCount(1, $slept, 'Expected exactly one backoff.');
        self::assertGreaterThan(0.0, $slept[0]);
    }

    public function test_it_backs_off_exponentially(): void
    {
        $slept  = [];
        $client = new RetryingClient(
            $this->inner([
                new TransportResponse(503, 'x'),
                new TransportResponse(503, 'x'),
                new TransportResponse(200, self::OK),
            ]),
            3,
            static function (float $s) use (&$slept): void {
                $slept[] = $s;
            }
        );

        $client->call('ACS_Stations');

        self::assertCount(2, $slept);
        self::assertGreaterThan($slept[0], $slept[1], 'Second backoff must exceed the first.');
    }

    public function test_it_never_retries_an_auth_failure(): void
    {
        $slept  = [];
        $client = new RetryingClient(
            $this->inner([new TransportResponse(403, 'Forbidden')]),
            3,
            static function (float $s) use (&$slept): void {
                $slept[] = $s;
            }
        );

        try {
            $client->call('ACS_Stations');
            self::fail('Expected AcsException');
        } catch (AcsException $e) {
            self::assertSame(AcsException::KIND_AUTH, $e->kind());
        }
        self::assertSame([], $slept, 'A 403 must not be retried.');
    }

    public function test_it_never_retries_a_business_error(): void
    {
        $body = '{"ACSExecution_HasError":true,"ACSExecutionErrorMessage":"Invalid pick-up date","ACSOutputResponce":{}}';
        $slept = [];
        $client = new RetryingClient(
            $this->inner([new TransportResponse(200, $body)]),
            3,
            static function (float $s) use (&$slept): void {
                $slept[] = $s;
            }
        );

        $this->expectException(AcsException::class);
        try {
            $client->call('ACS_Create_Voucher');
        } finally {
            self::assertSame([], $slept);
        }
    }

    public function test_it_gives_up_after_max_attempts(): void
    {
        $client = new RetryingClient(
            $this->inner([
                new TransportResponse(503, 'x'),
                new TransportResponse(503, 'x'),
                new TransportResponse(503, 'x'),
            ]),
            3,
            static function (float $s): void {
            }
        );

        $this->expectException(AcsException::class);
        $client->call('ACS_Stations');
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

```bash
composer test -- --testsuite unit
```
Expected: FAIL — `AcsCourier\Api\RetryingClient` not found.

- [ ] **Step 3: Write the minimal implementation**

`src/Api/Throttle.php`:
```php
<?php
/**
 * Keeps request rate under ACS's 10-calls-per-second ceiling.
 *
 * @package AcsCourier
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace AcsCourier\Api;

final class Throttle
{
    private int $maxPerSecond;
    /** @var callable */
    private $sleeper;
    /** @var callable */
    private $clock;
    /** @var list<float> */
    private array $recent = [];

    public function __construct(int $maxPerSecond = 8, ?callable $sleeper = null, ?callable $clock = null)
    {
        $this->maxPerSecond = max(1, $maxPerSecond);
        $this->sleeper      = $sleeper ?? static function (float $seconds): void {
            usleep((int) round($seconds * 1000000));
        };
        $this->clock        = $clock ?? static function (): float {
            return microtime(true);
        };
    }

    public function acquire(): void
    {
        $now          = ($this->clock)();
        $this->recent = array_values(array_filter(
            $this->recent,
            static function (float $t) use ($now): bool {
                return $t > $now - 1.0;
            }
        ));

        if (count($this->recent) >= $this->maxPerSecond) {
            $oldest = $this->recent[0];
            $wait   = 1.0 - ($now - $oldest);
            if ($wait > 0) {
                ($this->sleeper)($wait);
            }
        }

        $this->recent[] = ($this->clock)();
    }
}
```

`src/Api/RetryingClient.php`:
```php
<?php
/**
 * Decorates AcsClient with bounded exponential backoff for transient failures.
 *
 * @package AcsCourier
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace AcsCourier\Api;

final class RetryingClient
{
    private AcsClient $inner;
    private int $maxAttempts;
    /** @var callable */
    private $sleeper;

    public function __construct(AcsClient $inner, int $maxAttempts = 3, ?callable $sleeper = null)
    {
        $this->inner       = $inner;
        $this->maxAttempts = max(1, $maxAttempts);
        $this->sleeper     = $sleeper ?? static function (float $seconds): void {
            usleep((int) round($seconds * 1000000));
        };
    }

    /**
     * @param array<string,mixed> $params
     * @return array<string,mixed>
     * @throws AcsException
     */
    public function call(string $alias, array $params = []): array
    {
        $attempt = 0;

        while (true) {
            ++$attempt;
            try {
                return $this->inner->call($alias, $params);
            } catch (AcsException $e) {
                if (!$e->isRetryable() || $attempt >= $this->maxAttempts) {
                    throw $e;
                }
                ($this->sleeper)($this->backoffSeconds($attempt));
            }
        }
    }

    private function backoffSeconds(int $attempt): float
    {
        // 0.5s, 1s, 2s ... capped at 8s.
        return min(8.0, 0.5 * (2 ** ($attempt - 1)));
    }
}
```

- [ ] **Step 4: Run the test to verify it passes**

```bash
composer test -- --testsuite unit
```
Expected: PASS.

- [ ] **Step 5: Add a throttle test**

`tests/Unit/Api/ThrottleTest.php`:
```php
<?php
declare(strict_types=1);

namespace AcsCourier\Tests\Unit\Api;

use AcsCourier\Api\Throttle;
use PHPUnit\Framework\TestCase;

final class ThrottleTest extends TestCase
{
    public function test_it_sleeps_once_the_per_second_ceiling_is_reached(): void
    {
        $now   = 1000.0;
        $slept = [];

        $throttle = new Throttle(
            3,
            static function (float $s) use (&$slept): void {
                $slept[] = $s;
            },
            static function () use (&$now): float {
                return $now;
            }
        );

        $throttle->acquire();
        $throttle->acquire();
        $throttle->acquire();
        self::assertSame([], $slept, 'First three calls are within budget.');

        $throttle->acquire();
        self::assertCount(1, $slept, 'Fourth call in the same second must wait.');
    }

    public function test_it_does_not_sleep_when_calls_are_spread_out(): void
    {
        $now   = 1000.0;
        $slept = [];

        $throttle = new Throttle(
            2,
            static function (float $s) use (&$slept): void {
                $slept[] = $s;
            },
            static function () use (&$now): float {
                return $now;
            }
        );

        $throttle->acquire();
        $now += 1.5;
        $throttle->acquire();
        $now += 1.5;
        $throttle->acquire();

        self::assertSame([], $slept);
    }
}
```

- [ ] **Step 6: Run the full unit suite**

```bash
composer test -- --testsuite unit
```
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add src/Api/Throttle.php src/Api/RetryingClient.php tests/Unit/Api/RetryingClientTest.php tests/Unit/Api/ThrottleTest.php
git commit -m "feat(api): add rate throttle and bounded retry with backoff"
```

---

### Task 7: Country, Weight and ZIP rules

**Files:**
- Create: `src/Domain/Country.php`
- Create: `src/Domain/Weight.php`
- Test: `tests/Unit/Domain/CountryTest.php`
- Test: `tests/Unit/Domain/WeightTest.php`

**Interfaces:**
- Consumes: nothing.
- Produces:
  - `Country::greece(): self`, `Country::cyprus(): self`, `Country::fromCode(string $code): self` (throws `InvalidArgumentException` for anything but `GR`/`CY`), `->code(): string`, `->isCyprus(): bool`, `->isGreece(): bool`, `->zipLength(): int` (5 / 4), `->isValidZip(string $zip): bool`, `->requiresContentType(): bool` (true only for CY), `->supportsLivePricing(): bool` (true only for GR).
  - `Weight::fromKilograms(float $kg): self`, `Weight::volumetric(float $l, float $w, float $h): self`, `->kilograms(): float`, `->forAcs(): float` (clamped to 0.5 min, 999 max, rounded to 2dp), `->isAboveMaximum(): bool`.

- [ ] **Step 1: Write the failing tests**

`tests/Unit/Domain/CountryTest.php`:
```php
<?php
declare(strict_types=1);

namespace AcsCourier\Tests\Unit\Domain;

use AcsCourier\Domain\Country;
use PHPUnit\Framework\TestCase;

final class CountryTest extends TestCase
{
    public function test_cyprus_uses_four_digit_zips_and_greece_five(): void
    {
        self::assertSame(4, Country::cyprus()->zipLength());
        self::assertSame(5, Country::greece()->zipLength());

        self::assertTrue(Country::cyprus()->isValidZip('1010'));
        self::assertFalse(Country::cyprus()->isValidZip('17778'));
        self::assertTrue(Country::greece()->isValidZip('17778'));
        self::assertFalse(Country::greece()->isValidZip('1010'));
    }

    public function test_only_cyprus_requires_a_content_type(): void
    {
        self::assertTrue(Country::cyprus()->requiresContentType());
        self::assertFalse(Country::greece()->requiresContentType());
    }

    public function test_only_greece_supports_live_pricing(): void
    {
        self::assertTrue(Country::greece()->supportsLivePricing());
        self::assertFalse(Country::cyprus()->supportsLivePricing());
    }

    public function test_unsupported_countries_are_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Country::fromCode('DE');
    }

    public function test_codes_are_normalised(): void
    {
        self::assertSame('CY', Country::fromCode('cy')->code());
        self::assertSame('GR', Country::fromCode(' gr ')->code());
    }
}
```

`tests/Unit/Domain/WeightTest.php`:
```php
<?php
declare(strict_types=1);

namespace AcsCourier\Tests\Unit\Domain;

use AcsCourier\Domain\Weight;
use PHPUnit\Framework\TestCase;

final class WeightTest extends TestCase
{
    public function test_it_clamps_to_the_half_kilo_floor(): void
    {
        self::assertSame(0.5, Weight::fromKilograms(0.0)->forAcs());
        self::assertSame(0.5, Weight::fromKilograms(0.2)->forAcs());
        self::assertSame(0.75, Weight::fromKilograms(0.75)->forAcs());
    }

    public function test_it_flags_weights_above_the_maximum(): void
    {
        self::assertTrue(Weight::fromKilograms(1000.0)->isAboveMaximum());
        self::assertFalse(Weight::fromKilograms(999.0)->isAboveMaximum());
        self::assertSame(999.0, Weight::fromKilograms(1500.0)->forAcs());
    }

    public function test_volumetric_uses_the_5000_divisor(): void
    {
        // 50 x 40 x 30 / 5000 = 12 kg
        self::assertSame(12.0, Weight::volumetric(50, 40, 30)->kilograms());
    }

    public function test_it_rounds_to_two_decimals(): void
    {
        self::assertSame(1.23, Weight::fromKilograms(1.2345)->forAcs());
    }
}
```

- [ ] **Step 2: Run the tests to verify they fail**

```bash
composer test -- --testsuite unit
```
Expected: FAIL — domain classes not found.

- [ ] **Step 3: Write the minimal implementation**

`src/Domain/Country.php`:
```php
<?php
/**
 * @package AcsCourier
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace AcsCourier\Domain;

final class Country
{
    public const GR = 'GR';
    public const CY = 'CY';

    private string $code;

    private function __construct(string $code)
    {
        $this->code = $code;
    }

    public static function greece(): self
    {
        return new self(self::GR);
    }

    public static function cyprus(): self
    {
        return new self(self::CY);
    }

    public static function fromCode(string $code): self
    {
        $normalised = strtoupper(trim($code));

        if (self::GR === $normalised) {
            return self::greece();
        }
        if (self::CY === $normalised) {
            return self::cyprus();
        }

        throw new \InvalidArgumentException(
            'ACS supports voucher creation for GR and CY only; received "' . $code . '".'
        );
    }

    public static function isSupported(string $code): bool
    {
        return in_array(strtoupper(trim($code)), [self::GR, self::CY], true);
    }

    public function code(): string
    {
        return $this->code;
    }

    public function isCyprus(): bool
    {
        return self::CY === $this->code;
    }

    public function isGreece(): bool
    {
        return self::GR === $this->code;
    }

    public function zipLength(): int
    {
        return $this->isCyprus() ? 4 : 5;
    }

    public function isValidZip(string $zip): bool
    {
        return 1 === preg_match('/^\d{' . $this->zipLength() . '}$/', trim($zip));
    }

    public function requiresContentType(): bool
    {
        return $this->isCyprus();
    }

    public function supportsLivePricing(): bool
    {
        return $this->isGreece();
    }
}
```

`src/Domain/Weight.php`:
```php
<?php
/**
 * @package AcsCourier
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace AcsCourier\Domain;

final class Weight
{
    public const MIN_KG           = 0.5;
    public const MAX_KG           = 999.0;
    public const VOLUMETRIC_DIVISOR = 5000;

    private float $kilograms;

    private function __construct(float $kilograms)
    {
        $this->kilograms = $kilograms;
    }

    public static function fromKilograms(float $kg): self
    {
        return new self(max(0.0, $kg));
    }

    public static function volumetric(float $lengthCm, float $widthCm, float $heightCm): self
    {
        return new self(($lengthCm * $widthCm * $heightCm) / self::VOLUMETRIC_DIVISOR);
    }

    public function kilograms(): float
    {
        return $this->kilograms;
    }

    public function isAboveMaximum(): bool
    {
        return $this->kilograms > self::MAX_KG;
    }

    /**
     * Clamped and rounded exactly as ACS expects it on the wire.
     */
    public function forAcs(): float
    {
        $clamped = min(self::MAX_KG, max(self::MIN_KG, $this->kilograms));
        return round($clamped, 2);
    }

    public function isHeavierThan(self $other): bool
    {
        return $this->kilograms > $other->kilograms;
    }
}
```

- [ ] **Step 4: Run the tests to verify they pass**

```bash
composer test -- --testsuite unit
```
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add src/Domain tests/Unit/Domain
git commit -m "feat(domain): add Country and Weight with ACS bounds and zip rules"
```

---

### Task 8: AddressSplitter

ACS rejects an address that contains the region, and wants the street number in its own field.

**Files:**
- Create: `src/Mapping/AddressSplitter.php`
- Test: `tests/Unit/Mapping/AddressSplitterTest.php`

**Interfaces:**
- Consumes: nothing.
- Produces: `AddressSplitter::split(string $address): array` returning `['street' => string, 'number' => string]`. Number is `''` when absent.

- [ ] **Step 1: Write the failing test**

`tests/Unit/Mapping/AddressSplitterTest.php`:
```php
<?php
declare(strict_types=1);

namespace AcsCourier\Tests\Unit\Mapping;

use AcsCourier\Mapping\AddressSplitter;
use PHPUnit\Framework\TestCase;

final class AddressSplitterTest extends TestCase
{
    /**
     * @dataProvider addressProvider
     */
    public function test_it_splits_street_from_number(string $input, string $street, string $number): void
    {
        $result = AddressSplitter::split($input);

        self::assertSame($street, $result['street']);
        self::assertSame($number, $result['number']);
    }

    public static function addressProvider(): array
    {
        return [
            'trailing number'        => ['ΑΣΚΛΗΠΙΟΥ 25', 'ΑΣΚΛΗΠΙΟΥ', '25'],
            'latin trailing number'  => ['P. RALLI 45', 'P. RALLI', '45'],
            'number with letter'     => ['ΚΗΦΙΣΙΑΣ 12Α', 'ΚΗΦΙΣΙΑΣ', '12Α'],
            'no number'              => ['ΑΓΙΟΥ ΔΟΜΕΤΙΟΥ', 'ΑΓΙΟΥ ΔΟΜΕΤΙΟΥ', ''],
            'extra whitespace'       => ['  ΕΡΜΟΥ   10  ', 'ΕΡΜΟΥ', '10'],
            'hyphenated number'      => ['ΣΤΑΔΙΟΥ 5-7', 'ΣΤΑΔΙΟΥ', '5-7'],
            'empty'                  => ['', '', ''],
        ];
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

```bash
composer test -- --testsuite unit
```
Expected: FAIL — class not found.

- [ ] **Step 3: Write the minimal implementation**

`src/Mapping/AddressSplitter.php`:
```php
<?php
/**
 * Splits a one-line address into street and number, which ACS wants separately.
 *
 * @package AcsCourier
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace AcsCourier\Mapping;

final class AddressSplitter
{
    /**
     * @return array{street:string,number:string}
     */
    public static function split(string $address): array
    {
        $normalised = trim(preg_replace('/\s+/u', ' ', $address) ?? '');

        if ('' === $normalised) {
            return ['street' => '', 'number' => ''];
        }

        // A trailing token that starts with a digit is the street number.
        // Covers 25, 12Α, 5-7, 45B.
        if (preg_match('/^(.*?)\s+(\d[\d\-\/]*[\p{L}]?)$/u', $normalised, $m) === 1) {
            return ['street' => trim($m[1]), 'number' => $m[2]];
        }

        return ['street' => $normalised, 'number' => ''];
    }
}
```

- [ ] **Step 4: Run the test to verify it passes**

```bash
composer test -- --testsuite unit
```
Expected: PASS, 7 data sets.

- [ ] **Step 5: Commit**

```bash
git add src/Mapping tests/Unit/Mapping
git commit -m "feat(mapping): split street from number for ACS address fields"
```

---

### Task 9: Shipment and FieldMap

The single place ACS's misspellings are allowed to exist.

**Files:**
- Create: `src/Domain/Shipment.php`
- Create: `src/Mapping/FieldMap.php`
- Test: `tests/Unit/Mapping/FieldMapTest.php`

**Interfaces:**
- Consumes: `Country`, `Weight`, `AddressSplitter`.
- Produces:
  - `Shipment` with public typed properties and a `__construct` taking named-ish ordered args (see code). Fields used later: `recipientName`, `recipientAddress`, `recipientAddressNumber`, `recipientZip`, `recipientRegion`, `recipientPhone`, `recipientCellPhone`, `recipientEmail`, `recipientCompany`, `country`, `weight`, `itemQuantity`, `pickupDate`, `sender`, `billingCode`, `chargeType`, `deliveryProducts`, `contentTypeId`, `stationDestination`, `stationBranchDestination`, `deliveryNotes`, `referenceKey1`, `language`.
  - `FieldMap::toCreateVoucherParams(Shipment $s): array` returning the exact ACS parameter array.
  - `FieldMap::validate(Shipment $s): array` returning a list of human-readable problems; empty means valid.

- [ ] **Step 1: Write the failing test**

`tests/Unit/Mapping/FieldMapTest.php`:
```php
<?php
declare(strict_types=1);

namespace AcsCourier\Tests\Unit\Mapping;

use AcsCourier\Domain\Country;
use AcsCourier\Domain\Shipment;
use AcsCourier\Domain\Weight;
use AcsCourier\Mapping\FieldMap;
use PHPUnit\Framework\TestCase;

final class FieldMapTest extends TestCase
{
    private function cyprusShipment(array $overrides = []): Shipment
    {
        $s = new Shipment();
        $s->recipientName           = 'TEST RECIPIENT';
        $s->recipientAddress        = 'ΑΣΚΛΗΠΙΟΥ';
        $s->recipientAddressNumber  = '25';
        $s->recipientZip            = '1010';
        $s->recipientRegion         = 'ΛΕΥΚΩΣΙΑ';
        $s->recipientPhone          = '22000000';
        $s->recipientCellPhone      = '99000000';
        $s->recipientEmail          = 'buyer@example.com';
        $s->country                 = Country::cyprus();
        $s->weight                  = Weight::fromKilograms(1.2);
        $s->itemQuantity            = 1;
        $s->pickupDate              = '2026-09-03';
        $s->sender                  = 'ESHOP';
        $s->billingCode             = '2XX000000';
        $s->chargeType              = 2;
        $s->contentTypeId           = 6;
        $s->language                = 'EN';

        foreach ($overrides as $k => $v) {
            $s->$k = $v;
        }

        return $s;
    }

    public function test_it_emits_the_exact_acs_field_names_including_misspellings(): void
    {
        $params = FieldMap::toCreateVoucherParams($this->cyprusShipment());

        self::assertSame('TEST RECIPIENT', $params['Recipient_Name']);
        self::assertSame('ΑΣΚΛΗΠΙΟΥ', $params['Recipient_Address']);
        self::assertSame('25', $params['Recipient_Address_Number']);
        self::assertSame('1010', $params['Recipient_Zipcode']);
        self::assertSame('CY', $params['Recipient_Country']);
        self::assertSame(1.2, $params['Weight']);
        self::assertSame(2, $params['Charge_Type']);

        // ACS's own misspellings must appear on the wire.
        self::assertArrayHasKey('Cod_Ammount', $params);
        self::assertArrayHasKey('Insurance_Ammount', $params);
        self::assertNull($params['Cod_Ammount'], 'Prepaid: COD must be null.');
    }

    public function test_weight_is_clamped_to_the_acs_floor(): void
    {
        $params = FieldMap::toCreateVoucherParams(
            $this->cyprusShipment(['weight' => Weight::fromKilograms(0.1)])
        );

        self::assertSame(0.5, $params['Weight']);
    }

    public function test_cyprus_requires_a_content_type(): void
    {
        $problems = FieldMap::validate($this->cyprusShipment(['contentTypeId' => null]));

        self::assertNotEmpty($problems);
        self::assertStringContainsString('content type', strtolower(implode(' ', $problems)));
    }

    public function test_greece_does_not_require_a_content_type(): void
    {
        $s = $this->cyprusShipment([
            'country'         => Country::greece(),
            'recipientZip'    => '17778',
            'contentTypeId'   => null,
        ]);

        self::assertSame([], FieldMap::validate($s));
    }

    public function test_a_zip_of_the_wrong_length_is_rejected(): void
    {
        $problems = FieldMap::validate($this->cyprusShipment(['recipientZip' => '17778']));

        self::assertNotEmpty($problems);
        self::assertStringContainsString('postcode', strtolower(implode(' ', $problems)));
    }

    public function test_a_missing_recipient_name_is_rejected(): void
    {
        $problems = FieldMap::validate($this->cyprusShipment(['recipientName' => '  ']));

        self::assertNotEmpty($problems);
    }

    public function test_locker_delivery_rejects_multi_piece_shipments(): void
    {
        $problems = FieldMap::validate($this->cyprusShipment([
            'stationDestination'       => 'NI',
            'stationBranchDestination' => 503,
            'itemQuantity'             => 2,
        ]));

        self::assertNotEmpty($problems);
        self::assertStringContainsString('pickup point', strtolower(implode(' ', $problems)));
    }

    public function test_locker_fields_are_emitted_when_a_point_is_chosen(): void
    {
        $params = FieldMap::toCreateVoucherParams($this->cyprusShipment([
            'stationDestination'       => 'NI',
            'stationBranchDestination' => 503,
            'deliveryProducts'         => ['REC'],
        ]));

        self::assertSame('NI', $params['Acs_Station_Destination']);
        self::assertSame(503, $params['Acs_Station_Branch_Destination']);
        self::assertSame('REC', $params['Acs_Delivery_Products']);
    }

    public function test_multiple_delivery_products_are_comma_joined(): void
    {
        $params = FieldMap::toCreateVoucherParams(
            $this->cyprusShipment(['deliveryProducts' => ['REC', 'SAT']])
        );

        self::assertSame('REC,SAT', $params['Acs_Delivery_Products']);
    }

    public function test_more_than_99_pieces_is_rejected(): void
    {
        $problems = FieldMap::validate($this->cyprusShipment(['itemQuantity' => 100]));
        self::assertNotEmpty($problems);
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

```bash
composer test -- --testsuite unit
```
Expected: FAIL — `Shipment` / `FieldMap` not found.

- [ ] **Step 3: Write the minimal implementation**

`src/Domain/Shipment.php`:
```php
<?php
/**
 * A shipment as the plugin understands it, before ACS field naming is applied.
 *
 * @package AcsCourier
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace AcsCourier\Domain;

final class Shipment
{
    public string $recipientName = '';
    public string $recipientAddress = '';
    public string $recipientAddressNumber = '';
    public string $recipientZip = '';
    public string $recipientRegion = '';
    public string $recipientPhone = '';
    public string $recipientCellPhone = '';
    public string $recipientEmail = '';
    public string $recipientCompany = '';

    public ?Country $country = null;
    public ?Weight $weight = null;

    public int $itemQuantity = 1;
    public string $pickupDate = '';
    public string $sender = '';
    public string $billingCode = '';

    /** 2 = charge sender, 4 = charge recipient. */
    public int $chargeType = 2;

    /** @var list<string> ACS product codes, e.g. REC, SAT, COD. */
    public array $deliveryProducts = [];

    public ?int $contentTypeId = null;

    public ?string $stationDestination = null;
    public ?int $stationBranchDestination = null;

    public ?float $codAmount = null;
    public ?int $codPaymentWay = null;
    public ?float $insuranceAmount = null;

    public ?float $lengthCm = null;
    public ?float $widthCm = null;
    public ?float $heightCm = null;

    public string $deliveryNotes = '';
    public string $referenceKey1 = '';
    public string $referenceKey2 = '';
    public string $language = 'EN';

    public function isToPickupPoint(): bool
    {
        return null !== $this->stationDestination && null !== $this->stationBranchDestination;
    }
}
```

`src/Mapping/FieldMap.php`:
```php
<?php
/**
 * The one and only place ACS's field naming - including its misspellings -
 * is allowed to exist.
 *
 * @package AcsCourier
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace AcsCourier\Mapping;

use AcsCourier\Domain\Shipment;

final class FieldMap
{
    public const MAX_PIECES = 99;

    /**
     * @return array<string,mixed>
     */
    public static function toCreateVoucherParams(Shipment $s): array
    {
        $country = null !== $s->country ? $s->country->code() : '';
        $weight  = null !== $s->weight ? $s->weight->forAcs() : null;

        return [
            'Pickup_Date'                   => $s->pickupDate,
            'Sender'                        => $s->sender,
            'Recipient_Name'                => $s->recipientName,
            'Recipient_Address'             => $s->recipientAddress,
            'Recipient_Address_Number'      => $s->recipientAddressNumber,
            'Recipient_Zipcode'             => $s->recipientZip,
            'Recipient_Region'              => $s->recipientRegion,
            'Recipient_Phone'               => $s->recipientPhone,
            'Recipient_Cell_Phone'          => $s->recipientCellPhone,
            'Recipient_Floor'               => null,
            'Recipient_Company_Name'        => '' !== $s->recipientCompany ? $s->recipientCompany : null,
            'Recipient_Country'             => $country,
            'Acs_Station_Destination'       => $s->stationDestination,
            'Acs_Station_Branch_Destination' => $s->stationBranchDestination,
            'Billing_Code'                  => $s->billingCode,
            'Charge_Type'                   => $s->chargeType,
            'Cost_Center_Code'              => null,
            'Item_Quantity'                 => $s->itemQuantity,
            'Weight'                        => $weight,
            'Dimension_X_In_Cm'             => $s->lengthCm,
            'Dimension_Y_in_Cm'             => $s->widthCm,
            'Dimension_Z_in_Cm'             => $s->heightCm,
            // ACS spells these with a doubled m. Do not "fix" them.
            'Cod_Ammount'                   => $s->codAmount,
            'Cod_Payment_Way'               => $s->codPaymentWay,
            'Insurance_Ammount'             => $s->insuranceAmount,
            'Acs_Delivery_Products'         => $s->deliveryProducts === []
                ? null
                : implode(',', $s->deliveryProducts),
            'Delivery_Notes'                => '' !== $s->deliveryNotes ? $s->deliveryNotes : null,
            'Appointment_Until_Time'        => null,
            'Recipient_Email'               => '' !== $s->recipientEmail ? $s->recipientEmail : null,
            'Reference_Key1'                => '' !== $s->referenceKey1 ? $s->referenceKey1 : null,
            'Reference_Key2'                => '' !== $s->referenceKey2 ? $s->referenceKey2 : null,
            'With_Return_Voucher'           => null,
            'Content_Type_ID'               => $s->contentTypeId,
            'Language'                      => $s->language,
        ];
    }

    /**
     * Local pre-flight validation, so we never spend an API call on data ACS will reject.
     *
     * @return list<string> Human-readable problems; empty means valid.
     */
    public static function validate(Shipment $s): array
    {
        $problems = [];

        if ('' === trim($s->recipientName)) {
            $problems[] = 'The recipient name is required.';
        }
        if ('' === trim($s->recipientAddress)) {
            $problems[] = 'The recipient address is required.';
        }
        if (null === $s->country) {
            $problems[] = 'A destination country of GR or CY is required.';
            return $problems;
        }
        if (!$s->country->isValidZip($s->recipientZip)) {
            $problems[] = sprintf(
                'The postcode "%s" is not a valid %s postcode (%d digits expected).',
                $s->recipientZip,
                $s->country->code(),
                $s->country->zipLength()
            );
        }
        if ($s->country->requiresContentType() && null === $s->contentTypeId) {
            $problems[] = 'Shipments to Cyprus must declare a content type, or customs will hold them.';
        }
        if ($s->itemQuantity < 1 || $s->itemQuantity > self::MAX_PIECES) {
            $problems[] = sprintf('Item quantity must be between 1 and %d.', self::MAX_PIECES);
        }
        if (null !== $s->weight && $s->weight->isAboveMaximum()) {
            $problems[] = 'The shipment weight exceeds the 999 kg maximum.';
        }
        if (!in_array($s->chargeType, [2, 4], true)) {
            $problems[] = 'Charge type must be 2 (sender) or 4 (recipient).';
        }
        if ('' === trim($s->billingCode)) {
            $problems[] = 'A billing code is required.';
        }
        if ('' === trim($s->pickupDate)) {
            $problems[] = 'A pickup date is required.';
        }

        if ($s->isToPickupPoint()) {
            if ($s->itemQuantity > 1) {
                $problems[] = 'ACS cannot deliver a multi-piece shipment to a pickup point.';
            }
            if ('' === trim($s->recipientCellPhone)) {
                $problems[] = 'Delivery to a pickup point requires a recipient mobile number.';
            }
            if (null !== $s->codAmount && '' === trim($s->recipientEmail)) {
                $problems[] = 'Cash on delivery to a pickup point requires a recipient email address.';
            }
        }

        return $problems;
    }
}
```

- [ ] **Step 4: Run the test to verify it passes**

```bash
composer test -- --testsuite unit
```
Expected: PASS.

- [ ] **Step 5: Run static analysis and linting for the first time**

Create `phpstan.neon.dist`:
```neon
parameters:
    level: 6
    paths:
        - src
```

Create `phpcs.xml.dist`:
```xml
<?xml version="1.0"?>
<ruleset name="ACS Courier for WooCommerce">
    <file>src</file>
    <file>tests</file>
    <arg name="extensions" value="php"/>
    <arg value="ps"/>
    <rule ref="WordPress-Extra">
        <exclude name="WordPress.Files.FileName"/>
    </rule>
    <rule ref="PHPCompatibilityWP"/>
    <config name="testVersion" value="8.0-"/>
    <config name="minimum_wp_version" value="6.0"/>
</ruleset>
```

Run:
```bash
composer analyse
composer lint
```
Fix anything reported. `WordPress.Files.FileName` is excluded because PSR-4 class files intentionally use StudlyCase.

- [ ] **Step 6: Commit**

```bash
git add src/Domain/Shipment.php src/Mapping/FieldMap.php tests/Unit/Mapping/FieldMapTest.php phpstan.neon.dist phpcs.xml.dist
git commit -m "feat(mapping): add Shipment and FieldMap with pre-flight validation

ACS's misspelled field names are confined to FieldMap. Validation runs
locally so an invalid shipment never costs an API call."
```

---

### Task 10: Plugin bootstrap and requirement checks

First WordPress-facing code. The plugin must refuse to run rather than fatal on an unsupported stack.

**Files:**
- Create: `acs-courier-for-woocommerce.php`
- Create: `src/Support/Requirements.php`
- Create: `src/Plugin.php`
- Test: `tests/Unit/Support/RequirementsTest.php`

**Interfaces:**
- Consumes: `Version` (Task 1).
- Produces: `Requirements::__construct(string $phpVersion, string $wpVersion, ?string $wcVersion)`; `->unmet(): array` returning human-readable strings; `->isSatisfied(): bool`. `Plugin::boot(string $file): void` — idempotent.

- [ ] **Step 1: Write the failing test**

`tests/Unit/Support/RequirementsTest.php`:
```php
<?php
declare(strict_types=1);

namespace AcsCourier\Tests\Unit\Support;

use AcsCourier\Support\Requirements;
use PHPUnit\Framework\TestCase;

final class RequirementsTest extends TestCase
{
    public function test_a_supported_stack_is_satisfied(): void
    {
        $r = new Requirements('8.1.0', '6.5', '9.0.0');

        self::assertTrue($r->isSatisfied());
        self::assertSame([], $r->unmet());
    }

    public function test_old_php_is_reported(): void
    {
        $r = new Requirements('7.4.0', '6.5', '9.0.0');

        self::assertFalse($r->isSatisfied());
        self::assertStringContainsString('PHP', $r->unmet()[0]);
    }

    public function test_missing_woocommerce_is_reported(): void
    {
        $r = new Requirements('8.1.0', '6.5', null);

        self::assertFalse($r->isSatisfied());
        self::assertStringContainsString('WooCommerce', implode(' ', $r->unmet()));
    }

    public function test_every_unmet_requirement_is_listed_not_just_the_first(): void
    {
        $r = new Requirements('7.4.0', '5.9', null);

        self::assertCount(3, $r->unmet());
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

```bash
composer test -- --testsuite unit
```
Expected: FAIL — class not found.

- [ ] **Step 3: Write the minimal implementation**

`src/Support/Requirements.php`:
```php
<?php
/**
 * @package AcsCourier
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace AcsCourier\Support;

final class Requirements
{
    private string $phpVersion;
    private string $wpVersion;
    private ?string $wcVersion;

    public function __construct(string $phpVersion, string $wpVersion, ?string $wcVersion)
    {
        $this->phpVersion = $phpVersion;
        $this->wpVersion  = $wpVersion;
        $this->wcVersion  = $wcVersion;
    }

    /** @return list<string> */
    public function unmet(): array
    {
        $problems = [];

        if (version_compare($this->phpVersion, Version::MIN_PHP, '<')) {
            $problems[] = sprintf('PHP %s or newer is required; this site runs %s.', Version::MIN_PHP, $this->phpVersion);
        }
        if (version_compare($this->wpVersion, Version::MIN_WP, '<')) {
            $problems[] = sprintf('WordPress %s or newer is required; this site runs %s.', Version::MIN_WP, $this->wpVersion);
        }
        if (null === $this->wcVersion) {
            $problems[] = 'WooCommerce is required but is not active.';
        } elseif (version_compare($this->wcVersion, Version::MIN_WC, '<')) {
            $problems[] = sprintf('WooCommerce %s or newer is required; this site runs %s.', Version::MIN_WC, $this->wcVersion);
        }

        return $problems;
    }

    public function isSatisfied(): bool
    {
        return [] === $this->unmet();
    }
}
```

`src/Plugin.php`:
```php
<?php
/**
 * @package AcsCourier
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace AcsCourier;

use AcsCourier\Support\Requirements;

final class Plugin
{
    private static bool $booted = false;

    public static function boot(string $file): void
    {
        if (self::$booted) {
            return;
        }
        self::$booted = true;

        add_action(
            'before_woocommerce_init',
            static function () use ($file): void {
                if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
                    \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
                        'custom_order_tables',
                        $file,
                        true
                    );
                }
            }
        );

        add_action('plugins_loaded', static function () use ($file): void {
            $requirements = new Requirements(
                PHP_VERSION,
                get_bloginfo('version'),
                defined('WC_VERSION') ? WC_VERSION : null
            );

            if (!$requirements->isSatisfied()) {
                add_action('admin_notices', static function () use ($requirements): void {
                    echo '<div class="notice notice-error"><p><strong>'
                        . esc_html__('ACS Courier for WooCommerce', 'acs-courier-for-woocommerce')
                        . '</strong></p><ul>';
                    foreach ($requirements->unmet() as $problem) {
                        echo '<li>' . esc_html($problem) . '</li>';
                    }
                    echo '</ul></div>';
                });
                return;
            }

            load_plugin_textdomain(
                'acs-courier-for-woocommerce',
                false,
                dirname(plugin_basename($file)) . '/languages'
            );

            do_action('acs_wc_booted');
        });
    }
}
```

`acs-courier-for-woocommerce.php`:
```php
<?php
/**
 * Plugin Name:       ACS Courier for WooCommerce
 * Plugin URI:        https://github.com/kdvassiliou/acs-courier-for-woocommerce
 * Description:       Create ACS Courier vouchers, print labels, issue pickup lists and track shipments from WooCommerce. Supports Greece and Cyprus.
 * Version:           0.1.0
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            KD Vassiliou Group
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       acs-courier-for-woocommerce
 * Domain Path:       /languages
 * Requires Plugins:  woocommerce
 *
 * @package AcsCourier
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

\AcsCourier\Plugin::boot(__FILE__);
```

- [ ] **Step 4: Run the test to verify it passes**

```bash
composer test -- --testsuite unit
```
Expected: PASS.

- [ ] **Step 5: Verify the plugin activates on a real site**

Install on a scratch WordPress with WooCommerce active and confirm:
- no PHP notices on activation,
- no admin notice when requirements are met,
- deactivating WooCommerce shows the "WooCommerce is required" notice rather than a fatal.

- [ ] **Step 6: Commit**

```bash
git add acs-courier-for-woocommerce.php src/Plugin.php src/Support/Requirements.php tests/Unit/Support
git commit -m "feat: add plugin bootstrap with requirement guard and HPOS declaration"
```

---

### Task 11: Settings with constant override

**Files:**
- Create: `src/Admin/Settings.php`
- Test: `tests/Unit/Admin/SettingsResolverTest.php`
- Create: `src/Admin/SettingsResolver.php`

**Interfaces:**
- Consumes: `Credentials` (Task 4).
- Produces: `SettingsResolver::resolve(array $stored, array $constants): Credentials` — constants win over stored values. `Settings::register(): void` registers the WooCommerce settings section.

- [ ] **Step 1: Write the failing test**

`tests/Unit/Admin/SettingsResolverTest.php`:
```php
<?php
declare(strict_types=1);

namespace AcsCourier\Tests\Unit\Admin;

use AcsCourier\Admin\SettingsResolver;
use PHPUnit\Framework\TestCase;

final class SettingsResolverTest extends TestCase
{
    public function test_stored_values_are_used_when_no_constants_are_defined(): void
    {
        $c = SettingsResolver::resolve(
            [
                'company_id'       => 'CO',
                'company_password' => 'cpw',
                'user_id'          => 'U',
                'user_password'    => 'upw',
                'api_key'          => 'k',
            ],
            []
        );

        self::assertSame('CO', $c->toArray()['Company_ID']);
        self::assertSame('k', $c->apiKey());
    }

    public function test_constants_override_stored_values(): void
    {
        $c = SettingsResolver::resolve(
            [
                'company_id'       => 'STORED',
                'company_password' => 'cpw',
                'user_id'          => 'U',
                'user_password'    => 'upw',
                'api_key'          => 'stored-key',
            ],
            [
                'ACS_WC_COMPANY_ID' => 'FROM_CONFIG',
                'ACS_WC_API_KEY'    => 'config-key',
            ]
        );

        self::assertSame('FROM_CONFIG', $c->toArray()['Company_ID']);
        self::assertSame('config-key', $c->apiKey());
        self::assertSame('cpw', $c->toArray()['Company_Password'], 'Unset constants fall through to stored.');
    }

    public function test_missing_values_yield_incomplete_credentials(): void
    {
        $c = SettingsResolver::resolve([], []);
        self::assertFalse($c->isComplete());
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

```bash
composer test -- --testsuite unit
```
Expected: FAIL — class not found.

- [ ] **Step 3: Write the minimal implementation**

`src/Admin/SettingsResolver.php`:
```php
<?php
/**
 * Resolves credentials, letting wp-config.php constants win over stored options
 * so production secrets need never live in the database.
 *
 * @package AcsCourier
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace AcsCourier\Admin;

use AcsCourier\Api\Credentials;

final class SettingsResolver
{
    private const MAP = [
        'company_id'       => 'ACS_WC_COMPANY_ID',
        'company_password' => 'ACS_WC_COMPANY_PASSWORD',
        'user_id'          => 'ACS_WC_USER_ID',
        'user_password'    => 'ACS_WC_USER_PASSWORD',
        'api_key'          => 'ACS_WC_API_KEY',
    ];

    /**
     * @param array<string,string> $stored
     * @param array<string,string> $constants
     */
    public static function resolve(array $stored, array $constants): Credentials
    {
        $value = static function (string $key) use ($stored, $constants): string {
            $constant = self::MAP[$key];
            if (isset($constants[$constant]) && '' !== $constants[$constant]) {
                return (string) $constants[$constant];
            }
            return isset($stored[$key]) ? (string) $stored[$key] : '';
        };

        return new Credentials(
            $value('company_id'),
            $value('company_password'),
            $value('user_id'),
            $value('user_password'),
            $value('api_key')
        );
    }

    /**
     * Reads the defined constants from the runtime.
     *
     * @return array<string,string>
     */
    public static function definedConstants(): array
    {
        $found = [];
        foreach (self::MAP as $constant) {
            if (defined($constant)) {
                $found[$constant] = (string) constant($constant);
            }
        }
        return $found;
    }
}
```

- [ ] **Step 4: Run the test to verify it passes**

```bash
composer test -- --testsuite unit
```
Expected: PASS.

- [ ] **Step 5: Add the WooCommerce settings section**

`src/Admin/Settings.php`:
```php
<?php
/**
 * @package AcsCourier
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace AcsCourier\Admin;

final class Settings
{
    public const OPTION = 'acs_wc_settings';

    public static function register(): void
    {
        add_filter('woocommerce_get_sections_shipping', static function (array $sections): array {
            $sections['acs_courier'] = __('ACS Courier', 'acs-courier-for-woocommerce');
            return $sections;
        });

        add_filter('woocommerce_get_settings_shipping', [self::class, 'fields'], 10, 2);
        add_action('woocommerce_update_options_shipping_acs_courier', [self::class, 'save']);
    }

    /**
     * @param array<int,array<string,mixed>> $settings
     * @return array<int,array<string,mixed>>
     */
    public static function fields(array $settings, string $section): array
    {
        if ('acs_courier' !== $section) {
            return $settings;
        }

        $stored    = self::all();
        $constants = SettingsResolver::definedConstants();

        $credentialField = static function (string $key, string $label, string $constant, string $type) use ($stored, $constants): array {
            $fromConstant = isset($constants[$constant]);
            return [
                'id'                => 'acs_wc_' . $key,
                'title'             => $label,
                'type'              => $type,
                'value'             => $fromConstant ? '' : ($stored[$key] ?? ''),
                'custom_attributes' => $fromConstant ? ['disabled' => 'disabled'] : [],
                'desc'              => $fromConstant
                    /* translators: %s: PHP constant name. */
                    ? sprintf(__('Set by the %s constant in wp-config.php.', 'acs-courier-for-woocommerce'), $constant)
                    : '',
            ];
        };

        return [
            [
                'title' => __('ACS Courier', 'acs-courier-for-woocommerce'),
                'type'  => 'title',
                'desc'  => __('Credentials are issued by ACS. Leave a password field blank to keep the stored value.', 'acs-courier-for-woocommerce'),
                'id'    => 'acs_wc_options',
            ],
            $credentialField('company_id', __('Company ID', 'acs-courier-for-woocommerce'), 'ACS_WC_COMPANY_ID', 'text'),
            $credentialField('company_password', __('Company password', 'acs-courier-for-woocommerce'), 'ACS_WC_COMPANY_PASSWORD', 'password'),
            $credentialField('user_id', __('User ID', 'acs-courier-for-woocommerce'), 'ACS_WC_USER_ID', 'text'),
            $credentialField('user_password', __('User password', 'acs-courier-for-woocommerce'), 'ACS_WC_USER_PASSWORD', 'password'),
            $credentialField('api_key', __('API key', 'acs-courier-for-woocommerce'), 'ACS_WC_API_KEY', 'password'),
            [
                'id'    => 'acs_wc_billing_code',
                'title' => __('Billing code', 'acs-courier-for-woocommerce'),
                'type'  => 'text',
                'value' => $stored['billing_code'] ?? '',
            ],
            [
                'id'    => 'acs_wc_sender_name',
                'title' => __('Sender name', 'acs-courier-for-woocommerce'),
                'type'  => 'text',
                'value' => $stored['sender_name'] ?? '',
            ],
            [
                'id'      => 'acs_wc_charge_type',
                'title'   => __('Who pays', 'acs-courier-for-woocommerce'),
                'type'    => 'select',
                'value'   => $stored['charge_type'] ?? '2',
                'options' => [
                    '2' => __('Sender', 'acs-courier-for-woocommerce'),
                    '4' => __('Recipient', 'acs-courier-for-woocommerce'),
                ],
            ],
            ['type' => 'sectionend', 'id' => 'acs_wc_options'],
        ];
    }

    public static function save(): void
    {
        // WooCommerce verifies its own settings nonce before firing this action.
        $stored = self::all();

        foreach (['company_id', 'user_id', 'billing_code', 'sender_name', 'charge_type'] as $key) {
            if (isset($_POST['acs_wc_' . $key])) {
                $stored[$key] = sanitize_text_field(wp_unslash($_POST['acs_wc_' . $key]));
            }
        }

        // Blank secrets mean "leave unchanged", so an admin never has to retype them.
        foreach (['company_password', 'user_password', 'api_key'] as $key) {
            if (isset($_POST['acs_wc_' . $key]) && '' !== $_POST['acs_wc_' . $key]) {
                $stored[$key] = sanitize_text_field(wp_unslash($_POST['acs_wc_' . $key]));
            }
        }

        update_option(self::OPTION, $stored, false);
    }

    /** @return array<string,string> */
    public static function all(): array
    {
        $stored = get_option(self::OPTION, []);
        return is_array($stored) ? $stored : [];
    }
}
```

Three properties this must preserve, and they are the reason for the shape above: a field backed
by a constant renders **disabled and empty** so a secret is never echoed into HTML; a blank
password submission **keeps** the stored value rather than wiping it; and the option is stored
with autoload `false` so credentials are not loaded on every front-end request.

- [ ] **Step 6: Verify on a real site**

Save settings, confirm they round-trip; define `ACS_WC_API_KEY` in `wp-config.php` and confirm the field becomes disabled and the constant takes effect.

- [ ] **Step 7: Commit**

```bash
git add src/Admin tests/Unit/Admin
git commit -m "feat(admin): add settings with wp-config constant override"
```

---

### Task 12: WpHttpTransport

**Files:**
- Create: `src/Api/WpHttpTransport.php`
- Test: manual, plus an integration test in Task 13.

**Interfaces:**
- Consumes: `Transport`, `TransportResponse`, `TransportFailure`.
- Produces: `WpHttpTransport::__construct(int $timeoutSeconds = 45)` implementing `Transport`.

- [ ] **Step 1: Write the implementation**

`src/Api/WpHttpTransport.php`:
```php
<?php
/**
 * @package AcsCourier
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace AcsCourier\Api;

final class WpHttpTransport implements Transport
{
    private int $timeout;

    public function __construct(int $timeoutSeconds = 45)
    {
        $this->timeout = $timeoutSeconds;
    }

    public function post(string $url, array $payload, array $headers): TransportResponse
    {
        $encoded = wp_json_encode($payload);
        if (false === $encoded) {
            throw new TransportFailure('Could not encode the ACS request payload.');
        }

        $response = wp_remote_post(
            $url,
            [
                'headers' => $headers,
                'body'    => $encoded,
                'timeout' => $this->timeout,
            ]
        );

        if (is_wp_error($response)) {
            throw new TransportFailure($response->get_error_message());
        }

        return new TransportResponse(
            (int) wp_remote_retrieve_response_code($response),
            (string) wp_remote_retrieve_body($response)
        );
    }
}
```

- [ ] **Step 2: Verify against the real API from the target site**

With credentials configured, run a read-only call and confirm a station lookup returns data.

- [ ] **Step 3: Commit**

```bash
git add src/Api/WpHttpTransport.php
git commit -m "feat(api): add WordPress HTTP transport"
```

---

### Task 13: ShipmentService — voucher creation with idempotency

A duplicate voucher is a second real parcel and a real charge. This is the safety-critical task.

**Files:**
- Create: `src/Service/ShipmentService.php`
- Create: `src/Service/OrderLock.php`
- Test: `tests/Unit/Service/OrderLockTest.php`
- Test: `tests/Integration/CreateVoucherTest.php`

**Interfaces:**
- Consumes: `RetryingClient`, `FieldMap`, `Shipment`, `AcsException`.
- Produces: `ShipmentService::__construct(RetryingClient $client)`; `->create(Shipment $shipment): string` returning the voucher number, throwing `AcsException` on failure or `\InvalidArgumentException` when `FieldMap::validate()` is non-empty. `OrderLock::acquire(int $orderId): bool`, `OrderLock::release(int $orderId): void`.

- [ ] **Step 1: Write the failing test**

`tests/Unit/Service/ShipmentServiceTest.php`:
```php
<?php
declare(strict_types=1);

namespace AcsCourier\Tests\Unit\Service;

use AcsCourier\Api\AcsClient;
use AcsCourier\Api\ArrayTransport;
use AcsCourier\Api\Credentials;
use AcsCourier\Api\RetryingClient;
use AcsCourier\Api\TransportResponse;
use AcsCourier\Domain\Country;
use AcsCourier\Domain\Shipment;
use AcsCourier\Domain\Weight;
use AcsCourier\Service\ShipmentService;
use PHPUnit\Framework\TestCase;

final class ShipmentServiceTest extends TestCase
{
    private function validShipment(): Shipment
    {
        $s = new Shipment();
        $s->recipientName          = 'TEST RECIPIENT';
        $s->recipientAddress       = 'ΑΣΚΛΗΠΙΟΥ';
        $s->recipientAddressNumber = '25';
        $s->recipientZip           = '1010';
        $s->recipientRegion        = 'ΛΕΥΚΩΣΙΑ';
        $s->recipientCellPhone     = '99000000';
        $s->country                = Country::cyprus();
        $s->weight                 = Weight::fromKilograms(1.0);
        $s->pickupDate             = '2026-09-03';
        $s->sender                 = 'ESHOP';
        $s->billingCode            = '2XX000000';
        $s->contentTypeId          = 6;
        return $s;
    }

    private function service(array $queue, ?ArrayTransport &$transport = null): ShipmentService
    {
        $transport = new ArrayTransport($queue);
        $client    = new AcsClient($transport, new Credentials('C', 'p', 'U', 'p', 'k'));
        return new ShipmentService(new RetryingClient($client, 1, static function (float $s): void {
        }));
    }

    public function test_it_returns_the_voucher_number(): void
    {
        $body = json_encode([
            'ACSExecution_HasError'    => false,
            'ACSExecutionErrorMessage' => '',
            'ACSOutputResponce'        => [
                'ACSValueOutput' => [
                    ['Voucher_No' => '7227889174', 'Voucher_No_Return' => null, 'Error_Message' => ''],
                ],
                'ACSTableOutput' => [],
            ],
        ]);

        $service = $this->service([new TransportResponse(200, (string) $body)]);

        self::assertSame('7227889174', $service->create($this->validShipment()));
    }

    public function test_it_refuses_to_call_acs_with_invalid_data(): void
    {
        $service  = $this->service([], $transport);
        $shipment = $this->validShipment();
        $shipment->contentTypeId = null; // Cyprus requires one.

        try {
            $service->create($shipment);
            self::fail('Expected InvalidArgumentException');
        } catch (\InvalidArgumentException $e) {
            self::assertStringContainsString('content type', strtolower($e->getMessage()));
        }

        self::assertSame([], $transport->requests(), 'No API call may be made for invalid data.');
    }

    public function test_it_sends_the_create_voucher_alias(): void
    {
        $body = json_encode([
            'ACSExecution_HasError' => false,
            'ACSOutputResponce'     => [
                'ACSValueOutput' => [['Voucher_No' => '1', 'Error_Message' => '']],
            ],
        ]);

        $service = $this->service([new TransportResponse(200, (string) $body)], $transport);
        $service->create($this->validShipment());

        self::assertSame('ACS_Create_Voucher', $transport->requests()[0]['payload']['ACSAlias']);
    }

    public function test_a_business_error_surfaces_as_an_exception(): void
    {
        $body = json_encode([
            'ACSExecution_HasError' => false,
            'ACSOutputResponce'     => [
                'ACSValueOutput' => [['Voucher_No' => null, 'Error_Message' => 'Invalid pick-up date']],
            ],
        ]);

        $service = $this->service([new TransportResponse(200, (string) $body)]);

        $this->expectExceptionMessage('Invalid pick-up date');
        $service->create($this->validShipment());
    }

    public function test_a_missing_voucher_number_is_an_error_not_a_silent_success(): void
    {
        $body = json_encode([
            'ACSExecution_HasError' => false,
            'ACSOutputResponce'     => ['ACSValueOutput' => [['Error_Message' => '']]],
        ]);

        $service = $this->service([new TransportResponse(200, (string) $body)]);

        $this->expectException(\AcsCourier\Api\AcsException::class);
        $service->create($this->validShipment());
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

```bash
composer test -- --testsuite unit
```
Expected: FAIL — `ShipmentService` not found.

- [ ] **Step 3: Write the minimal implementation**

`src/Service/ShipmentService.php`:
```php
<?php
/**
 * @package AcsCourier
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace AcsCourier\Service;

use AcsCourier\Api\AcsException;
use AcsCourier\Api\RetryingClient;
use AcsCourier\Domain\Shipment;
use AcsCourier\Mapping\FieldMap;

final class ShipmentService
{
    public const ALIAS_CREATE = 'ACS_Create_Voucher';

    private RetryingClient $client;

    public function __construct(RetryingClient $client)
    {
        $this->client = $client;
    }

    /**
     * @throws \InvalidArgumentException When the shipment is locally invalid.
     * @throws AcsException When ACS rejects it.
     */
    public function create(Shipment $shipment): string
    {
        $problems = FieldMap::validate($shipment);
        if ([] !== $problems) {
            throw new \InvalidArgumentException(implode(' ', $problems));
        }

        $response = $this->client->call(
            self::ALIAS_CREATE,
            FieldMap::toCreateVoucherParams($shipment)
        );

        $values  = $response['ACSValueOutput'] ?? [];
        $voucher = is_array($values) && isset($values[0]['Voucher_No'])
            ? trim((string) $values[0]['Voucher_No'])
            : '';

        if ('' === $voucher) {
            throw AcsException::business(
                'ACS accepted the request but returned no voucher number.',
                self::ALIAS_CREATE
            );
        }

        return $voucher;
    }
}
```

- [ ] **Step 4: Run the test to verify it passes**

```bash
composer test -- --testsuite unit
```
Expected: PASS.

- [ ] **Step 5: Write the idempotency lock test**

`tests/Unit/Service/OrderLockTest.php`:
```php
<?php
declare(strict_types=1);

namespace AcsCourier\Tests\Unit\Service;

use AcsCourier\Service\OrderLock;
use PHPUnit\Framework\TestCase;

final class OrderLockTest extends TestCase
{
    public function test_a_second_acquire_for_the_same_order_fails(): void
    {
        $store = [];
        $lock  = new OrderLock(
            static function (string $k) use (&$store) {
                return $store[$k] ?? false;
            },
            static function (string $k, $v) use (&$store): bool {
                if (isset($store[$k])) {
                    return false;
                }
                $store[$k] = $v;
                return true;
            },
            static function (string $k) use (&$store): void {
                unset($store[$k]);
            }
        );

        self::assertTrue($lock->acquire(42));
        self::assertFalse($lock->acquire(42), 'A concurrent create must be refused.');

        $lock->release(42);
        self::assertTrue($lock->acquire(42), 'Released locks can be re-acquired.');
    }

    public function test_locks_are_per_order(): void
    {
        $store = [];
        $lock  = new OrderLock(
            static function (string $k) use (&$store) {
                return $store[$k] ?? false;
            },
            static function (string $k, $v) use (&$store): bool {
                if (isset($store[$k])) {
                    return false;
                }
                $store[$k] = $v;
                return true;
            },
            static function (string $k) use (&$store): void {
                unset($store[$k]);
            }
        );

        self::assertTrue($lock->acquire(1));
        self::assertTrue($lock->acquire(2));
    }
}
```

`src/Service/OrderLock.php`:
```php
<?php
/**
 * Prevents two concurrent requests creating two vouchers for one order.
 *
 * Injected callables keep this unit testable without WordPress; in production
 * they are wired to add_option/get_option, whose INSERT is atomic.
 *
 * @package AcsCourier
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace AcsCourier\Service;

final class OrderLock
{
    private const PREFIX = 'acs_wc_lock_order_';

    /** @var callable */
    private $get;
    /** @var callable */
    private $add;
    /** @var callable */
    private $delete;

    public function __construct(?callable $get = null, ?callable $add = null, ?callable $delete = null)
    {
        $this->get    = $get ?? static function (string $key) {
            return get_transient($key);
        };
        $this->add    = $add ?? static function (string $key, $value): bool {
            // add_option returns false if the key already exists: an atomic INSERT.
            return add_option($key, $value, '', 'no');
        };
        $this->delete = $delete ?? static function (string $key): void {
            delete_option($key);
        };
    }

    public function acquire(int $orderId): bool
    {
        return (bool) ($this->add)(self::PREFIX . $orderId, time());
    }

    public function release(int $orderId): void
    {
        ($this->delete)(self::PREFIX . $orderId);
    }
}
```

- [ ] **Step 6: Run the full unit suite, lint and analyse**

```bash
composer test -- --testsuite unit
composer lint
composer analyse
```
Expected: all green.

- [ ] **Step 7: Write the integration test against the real ACS account**

`tests/Integration/CreateVoucherTest.php` — skipped unless `ACS_IT_COMPANY_ID` and friends are set in the environment, so the suite stays green for contributors without credentials.

It must:
1. Create a voucher for a Cyprus address with a valid content type.
2. Assert a non-empty numeric voucher number comes back.
3. **In `tearDown`, delete the voucher via `ACS_Delete_Voucher`** — deletion is impossible once a pickup list is issued, so it must happen immediately.
4. Never issue a pickup list.

```php
<?php
declare(strict_types=1);

namespace AcsCourier\Tests\Integration;

use AcsCourier\Api\AcsClient;
use AcsCourier\Api\Credentials;
use AcsCourier\Api\RetryingClient;
use AcsCourier\Api\WpHttpTransport;
use PHPUnit\Framework\TestCase;

final class CreateVoucherTest extends TestCase
{
    private ?string $voucher = null;
    private ?RetryingClient $client = null;

    protected function setUp(): void
    {
        foreach (['ACS_IT_COMPANY_ID', 'ACS_IT_COMPANY_PASSWORD', 'ACS_IT_USER_ID', 'ACS_IT_USER_PASSWORD', 'ACS_IT_API_KEY', 'ACS_IT_BILLING_CODE'] as $var) {
            if (false === getenv($var)) {
                self::markTestSkipped('Set ' . $var . ' to run ACS integration tests.');
            }
        }
    }

    protected function tearDown(): void
    {
        if (null !== $this->voucher && null !== $this->client) {
            // Must happen before any pickup list is issued, or it becomes impossible.
            $this->client->call('ACS_Delete_Voucher', ['Voucher_No' => $this->voucher, 'Language' => 'EN']);
            $this->voucher = null;
        }
    }

    public function test_it_creates_and_then_deletes_a_real_voucher(): void
    {
        $credentials = new Credentials(
            (string) getenv('ACS_IT_COMPANY_ID'),
            (string) getenv('ACS_IT_COMPANY_PASSWORD'),
            (string) getenv('ACS_IT_USER_ID'),
            (string) getenv('ACS_IT_USER_PASSWORD'),
            (string) getenv('ACS_IT_API_KEY')
        );

        $this->client = new RetryingClient(
            new AcsClient(new WpHttpTransport(), $credentials)
        );

        $shipment                         = new \AcsCourier\Domain\Shipment();
        $shipment->recipientName          = 'INTEGRATION TEST';
        $shipment->recipientAddress       = 'ΑΣΚΛΗΠΙΟΥ';
        $shipment->recipientAddressNumber = '25';
        $shipment->recipientZip           = '1010';
        $shipment->recipientRegion        = 'ΛΕΥΚΩΣΙΑ';
        $shipment->recipientPhone         = '22000000';
        $shipment->recipientCellPhone     = '99000000';
        $shipment->recipientEmail         = 'integration@example.test';
        $shipment->country                = \AcsCourier\Domain\Country::cyprus();
        $shipment->weight                 = \AcsCourier\Domain\Weight::fromKilograms(1.0);
        $shipment->itemQuantity           = 1;
        $shipment->pickupDate             = gmdate('Y-m-d', strtotime('+1 weekday'));
        $shipment->sender                 = 'INTEGRATION TEST';
        $shipment->billingCode            = (string) getenv('ACS_IT_BILLING_CODE');
        $shipment->chargeType             = 2;
        $shipment->contentTypeId          = 6; // ELECTRONICS, from ACS_Get_Content_Types.
        $shipment->language               = 'EN';

        $service       = new \AcsCourier\Service\ShipmentService($this->client);
        $this->voucher = $service->create($shipment);

        self::assertMatchesRegularExpression(
            '/^\d{6,}$/',
            $this->voucher,
            'ACS should return a numeric voucher number.'
        );
    }
}
```

**Note on the pickup date:** ACS rejects past dates and Sundays or public holidays. `+1 weekday`
avoids Sunday but not holidays — if the test fails with "Pickup date is not allowed on Sunday or
national holiday", advance the date rather than treating it as a code defect.

- [ ] **Step 8: Commit**

```bash
git add src/Service tests/Unit/Service tests/Integration
git commit -m "feat(service): create vouchers with pre-flight validation and an order lock

A duplicate voucher is a second real parcel, so creation is guarded by an
atomic add_option lock as well as an existing-voucher check."
```

---

### Task 14: OrderMapper — WooCommerce order to Shipment

The mapper stays pure by taking a plain `OrderData` struct, so it is unit-testable without
WordPress. A thin reader converts a `WC_Order` into that struct.

**Files:**
- Create: `src/Mapping/OrderData.php`
- Create: `src/Mapping/MapperSettings.php`
- Create: `src/Mapping/OrderMapper.php`
- Create: `src/Integration/WooOrderReader.php`
- Test: `tests/Unit/Mapping/OrderMapperTest.php`

**Interfaces:**
- Consumes: `Shipment`, `Country`, `Weight`, `AddressSplitter`.
- Produces:
  - `OrderData` public props: `int $id`, `string $name`, `string $company`, `string $address1`,
    `string $address2`, `string $city`, `string $postcode`, `string $countryCode`, `string $phone`,
    `string $email`, `float $weight`, `string $weightUnit`, `int $itemCount`, `string $customerNote`.
  - `MapperSettings` public props: `string $sender`, `string $billingCode`, `int $chargeType`,
    `?int $defaultContentTypeId`, `string $language`, `string $pickupDate`.
  - `OrderMapper::toShipment(OrderData $order, MapperSettings $settings): Shipment`.
  - `WooOrderReader::read(\WC_Order $order): OrderData`.

- [ ] **Step 1: Write the failing test**

`tests/Unit/Mapping/OrderMapperTest.php`:
```php
<?php
declare(strict_types=1);

namespace AcsCourier\Tests\Unit\Mapping;

use AcsCourier\Mapping\MapperSettings;
use AcsCourier\Mapping\OrderData;
use AcsCourier\Mapping\OrderMapper;
use PHPUnit\Framework\TestCase;

final class OrderMapperTest extends TestCase
{
    private function order(array $overrides = []): OrderData
    {
        $o = new OrderData();
        $o->id           = 123;
        $o->name         = 'Γιώργος Παπαδόπουλος';
        $o->address1     = 'ΑΣΚΛΗΠΙΟΥ 25';
        $o->city         = 'ΛΕΥΚΩΣΙΑ';
        $o->postcode     = '1010';
        $o->countryCode  = 'CY';
        $o->phone        = '99000000';
        $o->email        = 'buyer@example.com';
        $o->weight       = 1.5;
        $o->weightUnit   = 'kg';
        $o->itemCount    = 1;

        foreach ($overrides as $k => $v) {
            $o->$k = $v;
        }
        return $o;
    }

    private function settings(array $overrides = []): MapperSettings
    {
        $s = new MapperSettings();
        $s->sender                = 'ESHOP';
        $s->billingCode           = '2XX000000';
        $s->chargeType            = 2;
        $s->defaultContentTypeId  = 6;
        $s->language              = 'EN';
        $s->pickupDate            = '2026-09-03';

        foreach ($overrides as $k => $v) {
            $s->$k = $v;
        }
        return $s;
    }

    public function test_it_splits_the_street_number_out_of_address_line_one(): void
    {
        $shipment = OrderMapper::toShipment($this->order(), $this->settings());

        self::assertSame('ΑΣΚΛΗΠΙΟΥ', $shipment->recipientAddress);
        self::assertSame('25', $shipment->recipientAddressNumber);
    }

    public function test_the_city_becomes_the_region_not_part_of_the_address(): void
    {
        $shipment = OrderMapper::toShipment($this->order(), $this->settings());

        self::assertSame('ΛΕΥΚΩΣΙΑ', $shipment->recipientRegion);
        self::assertStringNotContainsString('ΛΕΥΚΩΣΙΑ', $shipment->recipientAddress);
    }

    /**
     * A store selling in ounces must still send kilograms to ACS.
     *
     * @dataProvider weightUnitProvider
     */
    public function test_it_converts_store_weight_units_to_kilograms(
        float $input,
        string $unit,
        float $expectedKg
    ): void {
        $shipment = OrderMapper::toShipment(
            $this->order(['weight' => $input, 'weightUnit' => $unit]),
            $this->settings()
        );

        self::assertEqualsWithDelta($expectedKg, $shipment->weight->kilograms(), 0.001);
    }

    public static function weightUnitProvider(): array
    {
        return [
            'kilograms pass through' => [2.0, 'kg', 2.0],
            'grams'                  => [1500.0, 'g', 1.5],
            'pounds'                 => [2.0, 'lbs', 0.907185],
            'ounces'                 => [16.0, 'oz', 0.453592],
        ];
    }

    public function test_a_zero_weight_order_still_meets_the_acs_floor(): void
    {
        $shipment = OrderMapper::toShipment(
            $this->order(['weight' => 0.0]),
            $this->settings()
        );

        self::assertSame(0.5, $shipment->weight->forAcs());
    }

    public function test_the_order_id_is_carried_as_a_reference_key(): void
    {
        $shipment = OrderMapper::toShipment($this->order(), $this->settings());

        self::assertSame('123', $shipment->referenceKey1);
    }

    public function test_cyprus_orders_receive_the_default_content_type(): void
    {
        $shipment = OrderMapper::toShipment($this->order(), $this->settings());

        self::assertSame(6, $shipment->contentTypeId);
    }

    public function test_greek_orders_do_not_need_a_content_type(): void
    {
        $shipment = OrderMapper::toShipment(
            $this->order(['countryCode' => 'GR', 'postcode' => '17778']),
            $this->settings(['defaultContentTypeId' => null])
        );

        self::assertNull($shipment->contentTypeId);
        self::assertTrue($shipment->country->isGreece());
    }

    public function test_the_customer_note_becomes_delivery_notes(): void
    {
        $shipment = OrderMapper::toShipment(
            $this->order(['customerNote' => 'Ring twice']),
            $this->settings()
        );

        self::assertSame('Ring twice', $shipment->deliveryNotes);
    }

    public function test_an_unsupported_country_is_rejected_early(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        OrderMapper::toShipment(
            $this->order(['countryCode' => 'DE']),
            $this->settings()
        );
    }

    public function test_address_line_two_is_appended_to_the_street(): void
    {
        $shipment = OrderMapper::toShipment(
            $this->order(['address1' => 'ΑΣΚΛΗΠΙΟΥ 25', 'address2' => 'Flat 3']),
            $this->settings()
        );

        self::assertStringContainsString('Flat 3', $shipment->deliveryNotes);
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

```bash
composer test -- --testsuite unit
```
Expected: FAIL — `OrderMapper` not found.

- [ ] **Step 3: Write the minimal implementation**

`src/Mapping/OrderData.php`:
```php
<?php
/**
 * @package AcsCourier
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace AcsCourier\Mapping;

final class OrderData
{
    public int $id = 0;
    public string $name = '';
    public string $company = '';
    public string $address1 = '';
    public string $address2 = '';
    public string $city = '';
    public string $postcode = '';
    public string $countryCode = '';
    public string $phone = '';
    public string $email = '';
    public float $weight = 0.0;
    public string $weightUnit = 'kg';
    public int $itemCount = 1;
    public string $customerNote = '';
}
```

`src/Mapping/MapperSettings.php`:
```php
<?php
/**
 * @package AcsCourier
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace AcsCourier\Mapping;

final class MapperSettings
{
    public string $sender = '';
    public string $billingCode = '';
    public int $chargeType = 2;
    public ?int $defaultContentTypeId = null;
    public string $language = 'EN';
    public string $pickupDate = '';
}
```

`src/Mapping/OrderMapper.php`:
```php
<?php
/**
 * Turns a WooCommerce order (as a plain struct) into a Shipment.
 *
 * Pure by design: no WordPress calls, so every mapping rule is unit-testable.
 *
 * @package AcsCourier
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace AcsCourier\Mapping;

use AcsCourier\Domain\Country;
use AcsCourier\Domain\Shipment;
use AcsCourier\Domain\Weight;

final class OrderMapper
{
    /** Multipliers to kilograms, keyed by the WooCommerce weight unit. */
    private const TO_KILOGRAMS = [
        'kg'  => 1.0,
        'g'   => 0.001,
        'lbs' => 0.45359237,
        'oz'  => 0.028349523125,
    ];

    public static function toShipment(OrderData $order, MapperSettings $settings): Shipment
    {
        // Throws for anything ACS cannot ship to, before we build anything else.
        $country = Country::fromCode($order->countryCode);

        $split = AddressSplitter::split($order->address1);

        $shipment = new Shipment();
        $shipment->recipientName           = trim($order->name);
        $shipment->recipientCompany        = trim($order->company);
        $shipment->recipientAddress        = $split['street'];
        $shipment->recipientAddressNumber  = $split['number'];
        $shipment->recipientZip            = trim($order->postcode);
        // ACS rejects the region inside the address, so the city travels separately.
        $shipment->recipientRegion         = trim($order->city);
        $shipment->recipientPhone          = trim($order->phone);
        $shipment->recipientCellPhone      = trim($order->phone);
        $shipment->recipientEmail          = trim($order->email);
        $shipment->country                 = $country;
        $shipment->weight                  = self::weight($order);
        $shipment->itemQuantity            = max(1, $order->itemCount);
        $shipment->pickupDate              = $settings->pickupDate;
        $shipment->sender                  = $settings->sender;
        $shipment->billingCode             = $settings->billingCode;
        $shipment->chargeType              = $settings->chargeType;
        $shipment->language                = $settings->language;
        $shipment->referenceKey1           = (string) $order->id;
        $shipment->deliveryNotes           = self::notes($order);

        if ($country->requiresContentType()) {
            $shipment->contentTypeId = $settings->defaultContentTypeId;
        }

        return $shipment;
    }

    private static function weight(OrderData $order): Weight
    {
        $unit       = strtolower(trim($order->weightUnit));
        $multiplier = self::TO_KILOGRAMS[$unit] ?? 1.0;

        return Weight::fromKilograms($order->weight * $multiplier);
    }

    /**
     * ACS has no second address line, so anything there has to reach the courier
     * as a delivery note rather than being silently dropped.
     */
    private static function notes(OrderData $order): string
    {
        $parts = [];
        if ('' !== trim($order->address2)) {
            $parts[] = trim($order->address2);
        }
        if ('' !== trim($order->customerNote)) {
            $parts[] = trim($order->customerNote);
        }

        return implode(' — ', $parts);
    }
}
```

`src/Integration/WooOrderReader.php`:
```php
<?php
/**
 * The only place a WC_Order is touched. Keeps OrderMapper pure.
 *
 * @package AcsCourier
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace AcsCourier\Integration;

use AcsCourier\Mapping\OrderData;

final class WooOrderReader
{
    public static function read(\WC_Order $order): OrderData
    {
        $data = new OrderData();

        $data->id          = (int) $order->get_id();
        $data->name        = trim($order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name());
        $data->company     = (string) $order->get_shipping_company();
        $data->address1    = (string) $order->get_shipping_address_1();
        $data->address2    = (string) $order->get_shipping_address_2();
        $data->city        = (string) $order->get_shipping_city();
        $data->postcode    = (string) $order->get_shipping_postcode();
        $data->countryCode = (string) $order->get_shipping_country();
        $data->phone       = (string) ($order->get_shipping_phone() ?: $order->get_billing_phone());
        $data->email       = (string) $order->get_billing_email();
        $data->customerNote = (string) $order->get_customer_note();
        $data->weightUnit  = (string) get_option('woocommerce_weight_unit', 'kg');

        // Fall back to billing when the order has no shipping address at all.
        if ('' === $data->address1) {
            $data->name        = trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name());
            $data->company     = (string) $order->get_billing_company();
            $data->address1    = (string) $order->get_billing_address_1();
            $data->address2    = (string) $order->get_billing_address_2();
            $data->city        = (string) $order->get_billing_city();
            $data->postcode    = (string) $order->get_billing_postcode();
            $data->countryCode = (string) $order->get_billing_country();
        }

        $weight = 0.0;
        $count  = 0;
        foreach ($order->get_items() as $item) {
            if (!$item instanceof \WC_Order_Item_Product) {
                continue;
            }
            $product  = $item->get_product();
            $quantity = (int) $item->get_quantity();
            $count   += $quantity;
            if ($product instanceof \WC_Product && '' !== (string) $product->get_weight()) {
                $weight += (float) $product->get_weight() * $quantity;
            }
        }

        $data->weight    = $weight;
        $data->itemCount = max(1, $count);

        return $data;
    }
}
```

> **Note:** `itemCount` is the number of *units ordered*, which is not the same as the number of
> *parcels*. ACS's `Item_Quantity` means parcels. Plan 1 sends 1 parcel per order; a per-order
> parcel count is a Plan 2 concern, and until then locker deliveries stay valid because
> `Item_Quantity` is 1.

- [ ] **Step 4: Fix the itemCount semantics before the test passes**

Change `OrderMapper::toShipment` to always set `$shipment->itemQuantity = 1;` and delete the
`max(1, $order->itemCount)` line. Update the `OrderData::$itemCount` docblock to say it records
units ordered for later use, not parcels. This keeps locker validation correct.

- [ ] **Step 5: Run the test to verify it passes**

```bash
composer test -- --testsuite unit
```
Expected: PASS, including all four weight-unit conversions.

- [ ] **Step 6: Commit**

```bash
git add src/Mapping/OrderData.php src/Mapping/MapperSettings.php src/Mapping/OrderMapper.php src/Integration/WooOrderReader.php tests/Unit/Mapping/OrderMapperTest.php
git commit -m "feat(mapping): map WooCommerce orders to shipments

Weight is converted from the store's unit; a shop selling in ounces must
still send kilograms. Address line 2 becomes a delivery note rather than
being dropped, since ACS has no second address line."
```

---

### Task 15: Order screen action — create the voucher

**Files:**
- Create: `src/Admin/OrderMetaBox.php`
- Modify: `src/Plugin.php` (register the meta box on `acs_wc_booted`)
- Test: manual on a real order.

**Interfaces:**
- Consumes: `WooOrderReader`, `OrderMapper`, `ShipmentService`, `OrderLock`, `Settings`.
- Produces: order meta keys `_acs_wc_voucher_no` (string) and `_acs_wc_created_at` (int, gmt
  timestamp); admin-post action `acs_wc_create_voucher`.

- [ ] **Step 1: Write the meta box**

`src/Admin/OrderMetaBox.php` — renders on the order edit screen for both HPOS and legacy screens:

```php
<?php
/**
 * @package AcsCourier
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace AcsCourier\Admin;

use AcsCourier\Api\AcsClient;
use AcsCourier\Api\AcsException;
use AcsCourier\Api\RetryingClient;
use AcsCourier\Api\WpHttpTransport;
use AcsCourier\Integration\WooOrderReader;
use AcsCourier\Mapping\MapperSettings;
use AcsCourier\Mapping\OrderMapper;
use AcsCourier\Service\OrderLock;
use AcsCourier\Service\ShipmentService;

final class OrderMetaBox
{
    public const META_VOUCHER = '_acs_wc_voucher_no';
    public const META_CREATED = '_acs_wc_created_at';
    public const ACTION       = 'acs_wc_create_voucher';

    public static function register(): void
    {
        add_action('add_meta_boxes', static function (string $screen): void {
            $screens = ['shop_order', 'woocommerce_page_wc-orders'];
            if (!in_array($screen, $screens, true)) {
                return;
            }
            add_meta_box(
                'acs-wc-shipment',
                __('ACS Courier', 'acs-courier-for-woocommerce'),
                [self::class, 'render'],
                $screen,
                'side',
                'default'
            );
        });

        add_action('admin_post_' . self::ACTION, [self::class, 'handle']);
    }

    /** @param mixed $post_or_order */
    public static function render($post_or_order): void
    {
        $order = $post_or_order instanceof \WC_Order
            ? $post_or_order
            : wc_get_order($post_or_order->ID ?? 0);

        if (!$order instanceof \WC_Order) {
            return;
        }

        $voucher = (string) $order->get_meta(self::META_VOUCHER);

        if ('' !== $voucher) {
            echo '<p><strong>' . esc_html__('Voucher', 'acs-courier-for-woocommerce') . ':</strong> '
                . esc_html($voucher) . '</p>';
            return;
        }

        $url = wp_nonce_url(
            admin_url('admin-post.php?action=' . self::ACTION . '&order_id=' . $order->get_id()),
            self::ACTION . '_' . $order->get_id()
        );

        echo '<p><a href="' . esc_url($url) . '" class="button button-primary">'
            . esc_html__('Create ACS voucher', 'acs-courier-for-woocommerce')
            . '</a></p>';
    }

    public static function handle(): void
    {
        $orderId = isset($_GET['order_id']) ? absint($_GET['order_id']) : 0;

        if (!current_user_can('edit_shop_orders')) {
            wp_die(esc_html__('You are not allowed to do that.', 'acs-courier-for-woocommerce'));
        }
        check_admin_referer(self::ACTION . '_' . $orderId);

        $order = wc_get_order($orderId);
        if (!$order instanceof \WC_Order) {
            wp_die(esc_html__('Order not found.', 'acs-courier-for-woocommerce'));
        }

        $lock = new OrderLock();

        // Two guards, because a duplicate voucher is a second real parcel:
        // an existing number, and an atomic lock against concurrent requests.
        if ('' !== (string) $order->get_meta(self::META_VOUCHER)) {
            self::redirect($order, 'exists');
        }
        if (!$lock->acquire($orderId)) {
            self::redirect($order, 'busy');
        }

        try {
            $settings                       = Settings::all();
            $mapperSettings                 = new MapperSettings();
            $mapperSettings->sender         = $settings['sender_name'] ?? '';
            $mapperSettings->billingCode    = $settings['billing_code'] ?? '';
            $mapperSettings->chargeType     = (int) ($settings['charge_type'] ?? 2);
            $mapperSettings->defaultContentTypeId = isset($settings['content_type_id'])
                ? (int) $settings['content_type_id']
                : null;
            $mapperSettings->pickupDate     = gmdate('Y-m-d');

            $shipment = OrderMapper::toShipment(WooOrderReader::read($order), $mapperSettings);

            $client = new RetryingClient(
                new AcsClient(
                    new WpHttpTransport(),
                    SettingsResolver::resolve($settings, SettingsResolver::definedConstants())
                )
            );

            $voucher = (new ShipmentService($client))->create($shipment);

            $order->update_meta_data(self::META_VOUCHER, $voucher);
            $order->update_meta_data(self::META_CREATED, time());
            /* translators: %s: ACS voucher number. */
            $order->add_order_note(sprintf(__('ACS voucher created: %s', 'acs-courier-for-woocommerce'), $voucher));
            $order->save();

            self::redirect($order, 'created');
        } catch (\InvalidArgumentException $e) {
            $order->add_order_note(
                /* translators: %s: validation problems. */
                sprintf(__('ACS voucher not created: %s', 'acs-courier-for-woocommerce'), $e->getMessage())
            );
            $order->save();
            self::redirect($order, 'invalid');
        } catch (AcsException $e) {
            // ACS messages are often Greek; shown verbatim rather than mistranslated.
            $order->add_order_note(
                /* translators: %s: message returned by ACS. */
                sprintf(__('ACS rejected the shipment: %s', 'acs-courier-for-woocommerce'), $e->getMessage())
            );
            $order->save();
            self::redirect($order, 'failed');
        } finally {
            $lock->release($orderId);
        }
    }

    private static function redirect(\WC_Order $order, string $result): void
    {
        wp_safe_redirect(
            add_query_arg('acs_wc_result', $result, $order->get_edit_order_url())
        );
        exit;
    }
}
```

- [ ] **Step 2: Register it**

In `src/Plugin.php`, inside the `plugins_loaded` callback after `load_plugin_textdomain`, add:

```php
\AcsCourier\Admin\Settings::register();
\AcsCourier\Admin\OrderMetaBox::register();
```

- [ ] **Step 3: Verify on a real order**

On the target site with staging credentials configured:
1. Create an order with a Cyprus shipping address and a product that has a weight.
2. Click **Create ACS voucher**; confirm a voucher number appears and an order note is added.
3. Click again; confirm it does **not** create a second voucher.
4. Blank the billing code in settings and retry; confirm a validation note, **no API call**, and no voucher.
5. Delete the created voucher via `ACS_Delete_Voucher` so it never reaches a pickup list.

- [ ] **Step 4: Lint, analyse, commit**

```bash
composer lint
composer analyse
git add src/Admin/OrderMetaBox.php src/Plugin.php
git commit -m "feat(admin): create ACS vouchers from the order screen

Guarded by both an existing-voucher check and an atomic lock; ACS's own
error text is written to the order note verbatim."
```

---

## Definition of done for Plan 1

- [ ] `composer test` green; `composer lint` and `composer analyse` clean.
- [ ] Every constraint in spec §3 has a passing regression test.
- [ ] Plugin activates on WP 6.0 + WC 8.0 and on the target WP 7.1 + WC 10.5.3 without notices.
- [ ] Deactivating WooCommerce produces a notice, never a fatal.
- [ ] Credentials can be supplied by `wp-config.php` constants and are never written to logs.
- [ ] A real voucher is created against the staging account and deleted again in teardown.

**Next:** Plan 2 — labels and the pickup-list workflow.
