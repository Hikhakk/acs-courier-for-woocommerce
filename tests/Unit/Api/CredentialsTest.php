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

        self::assertNotContains('secret-key', $c->toArray());
        self::assertSame('secret-key', $c->apiKey());
    }

    public function test_incomplete_credentials_are_detected(): void
    {
        self::assertFalse((new Credentials('', 'cpw', 'USER', 'upw', 'k'))->isComplete());
        self::assertFalse((new Credentials('CO', 'cpw', 'USER', 'upw', ''))->isComplete());
        self::assertTrue((new Credentials('CO', 'cpw', 'USER', 'upw', 'k'))->isComplete());
    }

    public function test_it_never_exposes_secrets_when_redacted(): void
    {
        $c = new Credentials('CO', 'cpw', 'USER', 'upw', 'secret-key');
        $dump = print_r($c->redacted(), true);

        self::assertStringNotContainsString('secret-key', $dump);
        self::assertStringNotContainsString('cpw', $dump);
        self::assertStringNotContainsString('upw', $dump);
    }
}
