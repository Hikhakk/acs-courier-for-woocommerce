<?php
declare(strict_types=1);

namespace AcsCourier\Tests\Unit\Service;

use AcsCourier\Api\AcsClient;
use AcsCourier\Api\ArrayTransport;
use AcsCourier\Api\Credentials;
use AcsCourier\Api\RetryingClient;
use AcsCourier\Api\TransportResponse;
use AcsCourier\Domain\Country;
use AcsCourier\Domain\Weight;
use AcsCourier\Service\RateResolver;
use AcsCourier\Service\RateTable;
use PHPUnit\Framework\TestCase;

final class RateResolverTest extends TestCase {

	private const TABLE = array(
		array(
			'max_kg' => 2.0,
			'home'   => 3.50,
			'locker' => 2.50,
		),
	);

	private function resolver( array $queue, ?ArrayTransport &$transport = null ): RateResolver {
		$transport = new ArrayTransport( $queue );
		$client    = new AcsClient( $transport, new Credentials( 'C', 'p', 'U', 'p', 'k' ) );
		return new RateResolver(
			new RetryingClient( $client, 1, static function ( float $s ): void {} ),
			new RateTable( self::TABLE, 1.20, 0.0 ),
			'ΑΘ',
			'2XX000000'
		);
	}

	private function priceResponse( float $total ): string {
		return (string) json_encode(
			array(
				'ACSExecution_HasError' => false,
				'ACSOutputResponce'     => array(
					'ACSValueOutput' => array(
						array(
							'Basic_Ammount'         => $total - 0.3,
							'Extra_Service_Ammount' => 0.3,
							'Total_Ammount'         => $total,
							'Total_Vat_Ammount'     => 1.39,
							'Error_Message'         => '',
						),
					),
				),
			)
		);
	}

	public function test_greece_uses_the_live_acs_price(): void {
		$resolver = $this->resolver( array( new TransportResponse( 200, $this->priceResponse( 5.80 ) ) ), $transport );

		$rate = $resolver->resolve( Country::greece(), Weight::fromKilograms( 1.0 ), 'ΑΘ', false, 0.0 );

		self::assertSame( 5.80, $rate );
		self::assertSame( 'ACS_Price_Calculation', $transport->requests()[0]['payload']['ACSAlias'] );
	}

	/**
	 * ACS_Price_Calculation does not support Cyprus, so asking would waste a call
	 * and return nothing useful.
	 */
	public function test_cyprus_never_calls_the_pricing_api(): void {
		$resolver = $this->resolver( array(), $transport );

		$rate = $resolver->resolve( Country::cyprus(), Weight::fromKilograms( 1.0 ), 'NI', false, 0.0 );

		self::assertSame( 3.50, $rate );
		self::assertSame( array(), $transport->requests(), 'Cyprus must not hit the pricing API.' );
	}

	public function test_cyprus_locker_delivery_is_cheaper(): void {
		$resolver = $this->resolver( array() );
		self::assertSame( 2.50, $resolver->resolve( Country::cyprus(), Weight::fromKilograms( 1.0 ), 'NI', true, 0.0 ) );
	}

	/**
	 * A checkout must not break because a third party is unreachable.
	 */
	public function test_greece_falls_back_to_the_table_when_acs_is_down(): void {
		$resolver = $this->resolver(
			array(
				new TransportResponse( 503, 'down' ),
			)
		);

		self::assertSame( 3.50, $resolver->resolve( Country::greece(), Weight::fromKilograms( 1.0 ), 'ΑΘ', false, 0.0 ) );
	}

	public function test_it_returns_null_when_neither_source_can_price_it(): void {
		$transport = new ArrayTransport( array( new TransportResponse( 503, 'down' ) ) );
		$client    = new AcsClient( $transport, new Credentials( 'C', 'p', 'U', 'p', 'k' ) );
		$resolver  = new RateResolver(
			new RetryingClient( $client, 1, static function ( float $s ): void {} ),
			new RateTable( array(), 0.0, 0.0 ),
			'ΑΘ',
			'2XX000000'
		);

		self::assertNull( $resolver->resolve( Country::greece(), Weight::fromKilograms( 1.0 ), 'ΑΘ', false, 0.0 ) );
	}

	public function test_the_free_shipping_threshold_wins_before_any_api_call(): void {
		$transport = new ArrayTransport( array() );
		$client    = new AcsClient( $transport, new Credentials( 'C', 'p', 'U', 'p', 'k' ) );
		$resolver  = new RateResolver(
			new RetryingClient( $client, 1, static function ( float $s ): void {} ),
			new RateTable( self::TABLE, 1.20, 50.0 ),
			'ΑΘ',
			'2XX000000'
		);

		self::assertSame( 0.0, $resolver->resolve( Country::greece(), Weight::fromKilograms( 1.0 ), 'ΑΘ', false, 60.0 ) );
		self::assertSame( array(), $transport->requests(), 'Free shipping needs no price lookup.' );
	}
}
