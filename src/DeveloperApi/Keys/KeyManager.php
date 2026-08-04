<?php
/**
 * API key management.
 *
 * @package Zoviz
 */

namespace Zoviz\DeveloperApi\Keys;

use Zoviz\DeveloperApi\Api\ApiClient;
use Zoviz\DeveloperApi\Api\ApiKey;
use Zoviz\DeveloperApi\Api\Credits;
use Zoviz\DeveloperApi\Exception\ApiException;
use Zoviz\DeveloperApi\Exception\AuthException;

/**
 * Orchestrates key lifecycle: keys are validated against the live API
 * before they are persisted, the most recently added key automatically
 * becomes the default, and per-key credit balances are cached briefly.
 */
final class KeyManager {

	/**
	 * Credit cache TTL in seconds.
	 *
	 * @var int
	 */
	const CREDITS_CACHE_TTL = 300;

	/**
	 * API client.
	 *
	 * @var ApiClient
	 */
	private $client;

	/**
	 * Key repository.
	 *
	 * @var KeyRepository
	 */
	private $repository;

	/**
	 * Constructor.
	 *
	 * @param ApiClient     $client     API client.
	 * @param KeyRepository $repository Key repository.
	 */
	public function __construct( ApiClient $client, KeyRepository $repository ) {
		$this->client     = $client;
		$this->repository = $repository;
	}

	/**
	 * Validates a secret against the live API, persists it, and promotes it
	 * to default (most-recently-added key wins).
	 *
	 * @param string $label  Label.
	 * @param string $secret Plaintext secret.
	 * @return ApiKey
	 * @throws AuthException When the API rejects the key; nothing is persisted then.
	 * @throws ApiException On other API failures (network, server); nothing is persisted then.
	 */
	public function add_key( $label, $secret ) {
		$secret    = trim( $secret );
		$candidate = new ApiKey( 'candidate', $label, $secret, substr( $secret, -4 ) );

		// Throws AuthException when the key is invalid — nothing is stored.
		$credits = $this->client->get_credits( $candidate );

		$key = $this->repository->insert( $label, $secret );
		$this->repository->set_default( $key->id() );
		$this->cache_credits( $key->id(), $credits );

		return $key;
	}

	/**
	 * Returns the credit balance for a key, cached for a few minutes.
	 *
	 * @param string $key_id        Key id.
	 * @param bool   $force_refresh Bypass the cache.
	 * @return Credits
	 * @throws AuthException When the key is invalid (it is also marked invalid).
	 * @throws ApiException On other API failures (network, server errors).
	 */
	public function credits_for( $key_id, $force_refresh = false ) {
		if ( ! $force_refresh ) {
			$cached = get_transient( $this->cache_key( $key_id ) );

			if ( is_array( $cached ) && isset( $cached['credit'] ) ) {
				return Credits::from_array( $cached );
			}
		}

		$key = $this->repository->find( $key_id );

		if ( null === $key || ! $key->has_secret() ) {
			$this->mark_invalid( $key_id, __( 'The stored API key could not be read. Please enter it again.', 'zoviz-ai-studio' ) );

			throw new AuthException(
				esc_html__( 'The stored API key could not be read. Please enter it again.', 'zoviz-ai-studio' )
			);
		}

		try {
			$credits = $this->client->get_credits( $key );
		} catch ( AuthException $e ) {
			$this->mark_invalid( $key_id, $e->getMessage() );
			throw $e;
		}

		$this->cache_credits( $key_id, $credits );

		if ( ! $key->is_valid() ) {
			$this->repository->update(
				$key_id,
				array(
					'is_valid'          => true,
					'last_error'        => '',
					'last_validated_at' => gmdate( 'Y-m-d H:i:s' ),
				)
			);
		}

		return $credits;
	}

	/**
	 * Deletes a key; the repository re-points the default to the newest
	 * remaining key automatically.
	 *
	 * @param string $id Key id.
	 * @return bool
	 */
	public function delete_key( $id ) {
		delete_transient( $this->cache_key( $id ) );

		return $this->repository->delete( $id );
	}

	/**
	 * Marks a key invalid with a reason (shown in the settings UI and used
	 * by the admin notice).
	 *
	 * @param string $id     Key id.
	 * @param string $reason Human-readable reason.
	 * @return void
	 */
	public function mark_invalid( $id, $reason ) {
		$this->repository->update(
			$id,
			array(
				'is_valid'   => false,
				'last_error' => $reason,
			)
		);
		delete_transient( $this->cache_key( $id ) );
	}

	/**
	 * Caches a credit balance.
	 *
	 * @param string  $key_id  Key id.
	 * @param Credits $credits Balance.
	 * @return void
	 */
	private function cache_credits( $key_id, Credits $credits ) {
		set_transient( $this->cache_key( $key_id ), $credits->to_array(), self::CREDITS_CACHE_TTL );
	}

	/**
	 * Transient name for a key's credit cache.
	 *
	 * @param string $key_id Key id.
	 * @return string
	 */
	private function cache_key( $key_id ) {
		return 'zoviz_credits_' . $key_id;
	}
}
