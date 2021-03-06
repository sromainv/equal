<?php

namespace qinoa\auth;

class JWT {
	/**
	 * Decodes a JWT string into a PHP object.
	 *
	 * @param string      $jwt    The JWT
	 * @param bool        $verify Don't skip verification process 
	 * @param string 	  $key    The secret key* 
	 *
	 * @return array      A map holding JWT header and payload
	 * @throws Exception
	 * 
	 * @uses urlsafeB64Decode
	 */
	public static function decode($jwt, $verify = false, $key = null) {
		$header  = [];
        $payload = [];
		$parts = explode('.', $jwt);

		if (count($parts) != 3) {
			throw new \Exception('JWT_malformed_token');
		}
		else {
            list($headb64, $bodyb64, $cryptob64) = $parts;
            if ( !($header = json_decode(JWT::urlsafeB64Decode($headb64), true)) ) {
                throw new \Exception('JWT_header_unreadable');
            }
            if ( !($payload = json_decode(JWT::urlsafeB64Decode($bodyb64), true)) ) {
                throw new \Exception('JWT_payload_unreadable');
            }
            if ($verify) {
				$sig = JWT::urlsafeB64Decode($cryptob64);
                if (empty($header['alg'])) {
                    throw new \Exception('JWT_invalid_algorithm');
                }
                if ($sig != JWT::sign("$headb64.$bodyb64", $key, $header['alg'])) {
                    throw new \Exception('JWT_invalid_signature');
                }
            }
        }

		return ['header' => $header, 'payload' => $payload];
	}

	/**
	 * Converts and signs a PHP object or array into a JWT string.
	 *
	 * @param object|array $payload PHP object or array
	 * @param string       $key     The secret key
	 * @param string       $algo    The signing algorithm. Supported
	 *                              algorithms are 'HS256', 'HS384' and 'HS512'
	 *
	 * @return string      A signed JWT
	 * @uses jsonEncode
	 * @uses urlsafeB64Encode
	 */
	public static function encode($payload, $key, $algo = 'HS256') {
		$header = array('typ' => 'JWT', 'alg' => $algo);
		$segments = array();
		$segments[] = JWT::urlsafeB64Encode(JWT::jsonEncode($header));
		$segments[] = JWT::urlsafeB64Encode(JWT::jsonEncode($payload));
		$signing_input = implode('.', $segments);
		$signature = JWT::sign($signing_input, $key, $algo);
		$segments[] = JWT::urlsafeB64Encode($signature);
		return implode('.', $segments);
	}

	/**
	 * Sign a string with a given key and algorithm.
	 *
	 * @param string $msg    The message to sign
	 * @param string $key    The secret key
	 * @param string $method The signing algorithm. Supported
	 *                       algorithms are 'HS256', 'HS384' and 'HS512'
	 *
	 * @return string          An encrypted message
	 * @throws DomainException Unsupported algorithm was specified
	 */
	private static function sign($msg, $key, $method = 'HS256') {
		$methods = array(
			'HS256' => 'sha256',
			'HS384' => 'sha384',
			'HS512' => 'sha512',
		);
		if (!in_array($method, array_keys($methods))) {
			throw new DomainException('Algorithm not supported');
		}
		return hash_hmac($methods[$method], $msg, $key, true);
	}

	/**
	 * Encode a PHP object into a JSON string.
	 *
	 * @param object|array $input A PHP object or array
	 *
	 * @return string          JSON representation of the PHP object or array
	 * @throws DomainException Provided object could not be encoded to valid JSON
	 */
	public static function jsonEncode($input) {
		$json = json_encode($input);
		if (function_exists('json_last_error') && $errno = json_last_error()) {
			JWT::_handleJsonError($errno);
		} else if ($json === 'null' && $input !== null) {
			throw new DomainException('Null result with non-null input');
		}
		return $json;
	}

	/**
	 * Decode a string with URL-safe Base64.
	 *
	 * @param string $input A Base64 encoded string
	 *
	 * @return string A decoded string
	 */
	public static function urlsafeB64Decode($input) {
		$remainder = strlen($input) % 4;
		if ($remainder) {
			$padlen = 4 - $remainder;
			$input .= str_repeat('=', $padlen);
		}
		return base64_decode(strtr($input, '-_', '+/'));
	}

	/**
	 * Encode a string with URL-safe Base64.
	 *
	 * @param string $input The string you want encoded
	 *
	 * @return string The base64 encode of what you passed in
	 */
	public static function urlsafeB64Encode($input) {
		return str_replace('=', '', strtr(base64_encode($input), '+/', '-_'));
	}


	/**
	 * Helper method to create a JSON error.
	 *
	 * @param int $errno An error number from json_last_error()
	 *
	 * @return void
	 */
	private static function _handleJsonError($errno) {
		$messages = array(
			JSON_ERROR_DEPTH => 'Maximum stack depth exceeded',
			JSON_ERROR_CTRL_CHAR => 'Unexpected control character found',
			JSON_ERROR_SYNTAX => 'Syntax error, malformed JSON'
		);
		throw new DomainException(
			isset($messages[$errno])
			? $messages[$errno]
			: 'Unknown JSON error: ' . $errno
		);
	}
}