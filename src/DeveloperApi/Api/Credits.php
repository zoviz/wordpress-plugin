<?php
/**
 * Credit balance value object.
 *
 * @package Zoviz
 */

namespace Zoviz\DeveloperApi\Api;

/**
 * Credit balance of the account or workspace an API key is bound to.
 */
final class Credits {

	/**
	 * Available credits.
	 *
	 * @var int
	 */
	private $credit;

	/**
	 * Credits temporarily held by in-flight jobs.
	 *
	 * @var int
	 */
	private $reserved_credit;

	/**
	 * Constructor.
	 *
	 * @param int $credit          Available credits.
	 * @param int $reserved_credit Reserved credits.
	 */
	public function __construct( $credit, $reserved_credit = 0 ) {
		$this->credit          = (int) $credit;
		$this->reserved_credit = (int) $reserved_credit;
	}

	/**
	 * Builds an instance from an API response payload.
	 *
	 * @param array<string, mixed> $data Decoded JSON payload.
	 * @return Credits
	 */
	public static function from_array( array $data ) {
		return new self(
			isset( $data['credit'] ) ? (int) $data['credit'] : 0,
			isset( $data['reserved_credit'] ) ? (int) $data['reserved_credit'] : 0
		);
	}

	/**
	 * Available credits.
	 *
	 * @return int
	 */
	public function credit() {
		return $this->credit;
	}

	/**
	 * Credits reserved by in-flight jobs.
	 *
	 * @return int
	 */
	public function reserved_credit() {
		return $this->reserved_credit;
	}

	/**
	 * Array representation (REST/UI shape).
	 *
	 * @return array<string, int>
	 */
	public function to_array() {
		return array(
			'credit'          => $this->credit,
			'reserved_credit' => $this->reserved_credit,
		);
	}
}
