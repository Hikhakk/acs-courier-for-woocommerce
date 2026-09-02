<?php
/**
 * ACS account credentials, kept separate from the API key header.
 *
 * @package AcsCourier
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace AcsCourier\Api;

/**
 * ACS account credentials, kept separate from the API key header.
 */
final class Credentials {

	/**
	 * ACS company identifier.
	 *
	 * @var string
	 */
	private string $companyId;
	/**
	 * ACS company password.
	 *
	 * @var string
	 */
	private string $companyPassword;
	/**
	 * ACS user identifier.
	 *
	 * @var string
	 */
	private string $userId;
	/**
	 * ACS user password.
	 *
	 * @var string
	 */
	private string $userPassword;
	/**
	 * ACS API key, sent as a header.
	 *
	 * @var string
	 */
	private string $apiKey;

	/**
	 * __construct.
	 *
	 * @param string $companyId Company id.
	 * @param string $companyPassword Company password.
	 * @param string $userId User id.
	 * @param string $userPassword User password.
	 * @param string $apiKey Api key.
	 */
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

	/**
	 * Returns the credential fields ACS expects in the request body.
	 *
	 * @return array<string,string>
	 */
	public function toArray(): array {
		return array(
			'Company_ID'       => $this->companyId,
			'Company_Password' => $this->companyPassword,
			'User_ID'          => $this->userId,
			'User_Password'    => $this->userPassword,
		);
	}

	/**
	 * Api key.
	 *
	 * @return string
	 */
	public function apiKey(): string {
		return $this->apiKey;
	}

	/**
	 * Is complete.
	 *
	 * @return bool
	 */
	public function isComplete(): bool {
		return '' !== $this->companyId
			&& '' !== $this->companyPassword
			&& '' !== $this->userId
			&& '' !== $this->userPassword
			&& '' !== $this->apiKey;
	}

	/**
	 * Returns a redacted copy, safe for logs and error reports.
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
