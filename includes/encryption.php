<?php declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

/**
 * Returns whether the Atlantis encryption key is defined.
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @return  bool
 */
function a8csp_atlantis_has_encryption_key(): bool {
	return defined( 'A8CSP_ATLANTIS_ENCRYPTION_KEY' );
}

/**
 * Generates a random encryption key.
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @return  string|WP_Error
 */
function a8csp_atlantis_generate_random_encryption_key(): string|WP_Error {
	try {
		return sodium_bin2hex( sodium_crypto_secretbox_keygen() );
	} catch ( Exception $e ) {
		return new WP_Error( 'encrypt-key-error', sprintf( 'Error while creating new encryption key: %s', $e->getMessage() ) );
	}
}

/**
 * Returns the Atlantis encryption key and salt.
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @param   string|null $salt The salt to use for encryption. If null, a new salt will be generated.
 *
 * @return  array{key: string, salt: string}|WP_Error
 */
function a8csp_atlantis_get_encryption_key_data( ?string $salt = null ): array|WP_Error {
	if ( ! a8csp_atlantis_has_encryption_key() ) {
		return new WP_Error( 'encrypt-key-error', 'The encryption key is not defined.' );
	}

	try {
		return array(
			'key'  => sodium_hex2bin( A8CSP_ATLANTIS_ENCRYPTION_KEY ),
			'salt' => $salt ?? random_bytes( SODIUM_CRYPTO_PWHASH_SALTBYTES ),
		);
	} catch ( Exception $e ) {
		return new WP_Error( 'encrypt-key-error', sprintf( 'Error while getting encryption key data: %s', $e->getMessage() ) );
	}
}

/**
 * Encrypts the given data using the Atlantis encryption key.
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @param   string $to_encrypt The data to encrypt.
 *
 * @return  string|WP_Error
 */
function a8csp_atlantis_encrypt_data( string $to_encrypt ): string|WP_Error {
	$key_data = a8csp_atlantis_get_encryption_key_data();
	if ( is_wp_error( $key_data ) ) {
		return $key_data;
	}

	try {
		$nonce = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		return base64_encode( $key_data['salt'] . $nonce . sodium_crypto_secretbox( $to_encrypt, $nonce, $key_data['key'] ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	} catch ( Exception $e ) {
		return new WP_Error( 'encrypt-error', sprintf( 'Error while encrypting data: %s', $e->getMessage() ) );
	}
}

/**
 * Decrypts the given data using the Atlantis encryption key.
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @param   string $to_decrypt The data to decrypt.
 *
 * @return  string|WP_Error
 */
function a8csp_atlantis_decrypt_data( string $to_decrypt ): string|WP_Error {
	$to_decrypt = base64_decode( $to_decrypt ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
	if ( false === $to_decrypt ) {
		return new WP_Error( 'decrypt-error', 'Error while decoding data.' );
	}

	if ( mb_strlen( $to_decrypt, '8bit' ) < ( SODIUM_CRYPTO_PWHASH_SALTBYTES + SODIUM_CRYPTO_SECRETBOX_NONCEBYTES + SODIUM_CRYPTO_SECRETBOX_MACBYTES ) ) {
		return new WP_Error( 'decrypt-error', 'Data was truncated.' );
	}

	try {
		$salt     = mb_substr( $to_decrypt, 0, SODIUM_CRYPTO_PWHASH_SALTBYTES, '8bit' );
		$key_data = a8csp_atlantis_get_encryption_key_data( $salt );
		if ( is_wp_error( $key_data ) ) {
			return $key_data;
		}

		$key        = $key_data['key'];
		$nonce      = mb_substr( $to_decrypt, SODIUM_CRYPTO_PWHASH_SALTBYTES, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES, '8bit' );
		$ciphertext = mb_substr( $to_decrypt, SODIUM_CRYPTO_PWHASH_SALTBYTES + SODIUM_CRYPTO_SECRETBOX_NONCEBYTES, null, '8bit' );

		$plain = sodium_crypto_secretbox_open( $ciphertext, $nonce, $key );
		if ( false === $plain ) {
			return new WP_Error( 'decrypt-error', 'Message could not be decrypted.' );
		}

		try {
			sodium_memzero( $ciphertext );
			sodium_memzero( $nonce );
		} catch ( SodiumException $e ) {
			// Ignore. Nothing we can do here.
			return $plain;
		}

		return $plain;
	} catch ( Exception $e ) {
		return new WP_Error( 'decrypt-error', sprintf( 'Error while decrypting data: %s', $e->getMessage() ) );
	}
}
