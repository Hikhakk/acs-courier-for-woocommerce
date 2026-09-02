<?php
/**
 * @package AcsCourier
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace AcsCourier\Api;

final class Credentials {

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
	public function toArray(): array {
		return array(
			'Company_ID'       => $this->companyId,
			'Company_Password' => $this->companyPassword,
			'User_ID'          => $this->userId,
			'User_Password'    => $this->userPassword,
		);
	}

	public function apiKey(): string {
		return $this->apiKey;
	}

	public function isComplete(): bool {
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
	public function redacted(): array {
		return array(
			'Company_ID'       => $this->companyId,
			'Company_Password' => '***',
			'User_ID'          => $this->userId,
			'User_Password'    => '***',
			'AcsApiKey'        => '***',
		);
	}
}
