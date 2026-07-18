<?php declare( strict_types=1 );

namespace A8C\SpecialProjects\Atlantis\Modules\Snippets;

defined( 'ABSPATH' ) || exit;

/**
 * Verify-only Ed25519 signature checks for deployed snippets.
 *
 * The private signing key lives only in OpsOasis. Atlantis ships one or more
 * *public* keys (public by design — a public key in a public repo is fine) and
 * verifies that every snippet it is asked to store or remove was signed by the
 * holder of the corresponding private key. Authentication on the REST route
 * gates *who can knock*; this signature gates *what can run*.
 *
 * The canonical signed messages are defined here and MUST be reproduced
 * byte-for-byte by the OpsOasis signer. See the design doc bundled with this
 * module (docs/snippet-runner-design.md) for the specification.
 *
 * @since   1.3.0
 * @version 1.3.0
 */
class Signature {
	// region FIELDS AND CONSTANTS

	/**
	 * Baked-in verify-only Ed25519 public keys, hex-encoded.
	 *
	 * Public by design. Multiple keys are supported so the signing key can be
	 * rotated without a synchronized flag day: keep the old key here until every
	 * site has shipped the release carrying the new one, then drop it.
	 *
	 * Sites may additionally override/extend this set by defining the
	 * `A8CSP_ATLANTIS_SNIPPET_PUBLIC_KEYS` constant (array of hex strings) in
	 * wp-config.php.
	 *
	 * If this set is empty, verification fails closed and nothing is deployable —
	 * a deliberately safe default until the real key is provisioned.
	 *
	 * @var array<int, string>
	 */
	private const DEFAULT_PUBLIC_KEYS = array(
		// TODO: Replace with the real OpsOasis snippet-signing public key (hex)
		// before this feature is released. Until then verification fails closed.
	);

	// endregion

	// region METHODS

	/**
	 * Returns the set of accepted public keys (raw binary), from the baked-in
	 * constant plus any wp-config override.
	 *
	 * @return  array<int, string> Raw 32-byte Ed25519 public keys.
	 */
	public static function get_public_keys(): array {
		$hex_keys = self::DEFAULT_PUBLIC_KEYS;

		if ( defined( 'A8CSP_ATLANTIS_SNIPPET_PUBLIC_KEYS' ) && is_array( A8CSP_ATLANTIS_SNIPPET_PUBLIC_KEYS ) ) {
			$hex_keys = array_merge( $hex_keys, A8CSP_ATLANTIS_SNIPPET_PUBLIC_KEYS );
		}

		$keys = array();
		foreach ( array_unique( $hex_keys ) as $hex_key ) {
			if ( ! is_string( $hex_key ) || '' === $hex_key ) {
				continue;
			}

			try {
				$raw = sodium_hex2bin( $hex_key );
			} catch ( \SodiumException ) {
				continue; // Malformed key; skip it rather than fatal.
			}

			if ( SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES === strlen( $raw ) ) {
				$keys[] = $raw;
			}
		}

		return $keys;
	}

	/**
	 * Builds the canonical signed message for a snippet deployment.
	 *
	 * The content is bound via its sha256, so signing this short header is
	 * equivalent to signing the code while keeping the message compact and
	 * trivial to reproduce on the signer side.
	 *
	 * @param   string $snippet_id The stable snippet slug.
	 * @param   int    $version    The monotonic version for this snippet id.
	 * @param   string $sha256     Lowercase hex sha256 of the raw code bytes.
	 * @param   string $expires    ISO-8601 expiry, or '' if the snippet never expires.
	 *
	 * @return  string
	 */
	public static function deploy_message( string $snippet_id, int $version, string $sha256, string $expires ): string {
		return implode(
			"\n",
			array( 'a8csp-atlantis-snippet-deploy', 'v1', $snippet_id, (string) $version, $sha256, $expires )
		);
	}

	/**
	 * Builds the canonical signed message for a snippet removal.
	 *
	 * Removals are signed and version-bumped so an old deployment cannot be
	 * replayed to "un-remove" a snippet.
	 *
	 * @param   string $snippet_id The stable snippet slug.
	 * @param   int    $version    The monotonic version for this removal.
	 *
	 * @return  string
	 */
	public static function remove_message( string $snippet_id, int $version ): string {
		return implode(
			"\n",
			array( 'a8csp-atlantis-snippet-remove', 'v1', $snippet_id, (string) $version )
		);
	}

	/**
	 * Verifies a base64-encoded detached signature against a message using any
	 * of the accepted public keys.
	 *
	 * Fails closed: returns false if no keys are configured, the signature is
	 * malformed, or none of the keys validate it.
	 *
	 * @param   string $message           The exact signed message.
	 * @param   string $signature_base64  The detached signature, base64-encoded.
	 *
	 * @return  bool
	 */
	public static function verify( string $message, string $signature_base64 ): bool {
		$signature = base64_decode( $signature_base64, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		if ( false === $signature || SODIUM_CRYPTO_SIGN_BYTES !== strlen( $signature ) ) {
			return false;
		}

		foreach ( self::get_public_keys() as $public_key ) {
			if ( '' === $public_key ) {
				continue;
			}

			try {
				if ( sodium_crypto_sign_verify_detached( $signature, $message, $public_key ) ) {
					return true;
				}
			} catch ( \SodiumException ) {
				continue;
			}
		}

		return false;
	}

	// endregion
}
