<?php
declare(strict_types=1);

namespace AcsCourier\Tests\Unit\Service;

use AcsCourier\Api\AcsClient;
use AcsCourier\Api\AcsException;
use AcsCourier\Api\ArrayTransport;
use AcsCourier\Api\Credentials;
use AcsCourier\Api\RetryingClient;
use AcsCourier\Api\TransportResponse;
use AcsCourier\Service\LabelService;
use PHPUnit\Framework\TestCase;

final class LabelServiceTest extends TestCase {

	private function service( array $queue, ?ArrayTransport &$transport = null ): LabelService {
		$transport = new ArrayTransport( $queue );
		$client    = new AcsClient( $transport, new Credentials( 'C', 'p', 'U', 'p', 'k' ) );
		return new LabelService( new RetryingClient( $client, 1, static function ( float $s ): void {} ) );
	}

	private function pdfResponse( string $voucher, string $pdfBytes ): string {
		return (string) json_encode(
			array(
				'ACSExecution_HasError' => false,
				'ACSOutputResponce'     => array(
					'ACSObjectOutput' => array( array( $voucher => base64_encode( $pdfBytes ) ) ),
					'ACSValueOutput'  => array( array( 'Error_Message' => null ) ),
				),
			)
		);
	}

	public function test_it_returns_decoded_pdf_bytes_keyed_by_voucher(): void {
		$service = $this->service( array( new TransportResponse( 200, $this->pdfResponse( '7227889174', '%PDF-1.4 fake' ) ) ) );

		$labels = $service->fetch( array( '7227889174' ), LabelService::PRINT_LASER, 1 );

		self::assertArrayHasKey( '7227889174', $labels );
		self::assertSame( '%PDF-1.4 fake', $labels['7227889174'] );
	}

	public function test_it_sends_the_v2_alias_and_joins_vouchers_with_commas(): void {
		$service = $this->service( array( new TransportResponse( 200, $this->pdfResponse( '1', 'x' ) ) ), $transport );
		$service->fetch( array( '111', '222' ), LabelService::PRINT_THERMAL, 1 );

		$sent = $transport->requests()[0]['payload'];
		self::assertSame( 'ACS_Print_Voucher_V2', $sent['ACSAlias'] );
		self::assertSame( '111,222', $sent['ACSInputParameters']['Voucher_No'] );
		self::assertSame( LabelService::PRINT_THERMAL, $sent['ACSInputParameters']['Print_Type'] );
	}

	public function test_it_refuses_more_than_ten_vouchers_per_call(): void {
		$service = $this->service( array(), $transport );

		$this->expectException( \InvalidArgumentException::class );
		try {
			$service->fetch( array_map( 'strval', range( 1, 11 ) ), LabelService::PRINT_LASER, 1 );
		} finally {
			self::assertSame( array(), $transport->requests(), 'No API call may be made.' );
		}
	}

	public function test_it_refuses_an_empty_voucher_list(): void {
		$service = $this->service( array() );
		$this->expectException( \InvalidArgumentException::class );
		$service->fetch( array(), LabelService::PRINT_LASER, 1 );
	}

	/** @dataProvider startPositionProvider */
	public function test_start_position_is_only_valid_for_laser( int $printType, int $position, bool $valid ): void {
		$service = $this->service( array( new TransportResponse( 200, $this->pdfResponse( '1', 'x' ) ) ) );

		if ( ! $valid ) {
			$this->expectException( \InvalidArgumentException::class );
		}
		$service->fetch( array( '1' ), $printType, $position );
		if ( $valid ) {
			self::assertTrue( true );
		}
	}

	public static function startPositionProvider(): array {
		return array(
			'laser position 1'   => array( LabelService::PRINT_LASER, 1, true ),
			'laser position 3'   => array( LabelService::PRINT_LASER, 3, true ),
			'laser position 4'   => array( LabelService::PRINT_LASER, 4, false ),
			'laser position 0'   => array( LabelService::PRINT_LASER, 0, false ),
			'thermal ignores it' => array( LabelService::PRINT_THERMAL, 1, true ),
		);
	}

	public function test_a_response_without_a_pdf_is_an_error(): void {
		$body    = (string) json_encode(
			array(
				'ACSExecution_HasError' => false,
				'ACSOutputResponce'     => array( 'ACSValueOutput' => array( array( 'Error_Message' => null ) ) ),
			)
		);
		$service = $this->service( array( new TransportResponse( 200, $body ) ) );

		$this->expectException( AcsException::class );
		$service->fetch( array( '1' ), LabelService::PRINT_LASER, 1 );
	}
}
