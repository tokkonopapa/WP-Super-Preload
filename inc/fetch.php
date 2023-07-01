<?php
/**
 * WP Super Preload - Connect and Communicate URLs
 *
 * @package   wp-super-preload
 * @author    tokkonopapa
 * @license   GPL-2.0+
 * @link      http://tokkono.cute.coocan.jp/blog/slow/
 * @copyright 2013 tokkonopapa (tokkonopapa@yahoo.com)
 *
 * @see https://github.com/jmathai/php-multi-curl
 * @see https://github.com/petewarden/ParallelCurl
 */

class WP_Super_Fetch {
/*
	static function access_log( $msg = NULL ) {
		$msg = trim( $msg );
		$fp = @fopen( WP_SUPER_PRELOAD_PATH . "access.log", is_null( $msg ) ? 'w' : 'a' );
		@fwrite( $fp, date( "Y/m/d,D,H:i:s" ) . " ${msg}\n" );
		@fclose( $fp );
	}
*/
	/**
	 * Get urls from sitemap
	 *
	 * @param string $sitemap: url of sitemap.
	 * @param int $timeout: time out in seconds.
	 * @todo consider to use `simplexml_load_file()` and `simplexml_load_string()`.
	 * @see http://www.php.net/manual/en/function.simplexml-load-file.php
	 * @see http://php.net/manual/en/function.simplexml-load-string.php
	 */
	static public function get_urls_sitemap( $sitemap ) {
		// Get contents of sitemap
		$response = wp_remote_get( $sitemap );
		$xml = wp_remote_retrieve_body( $response ); // @since 2.7.0

		// Get URLs from sitemap
		// @todo: consider sub sitemap.
		$urls = array();
		if ( preg_match_all( "/\<loc\>(.+?)\<\/loc\>/i", $xml, $matches ) !== FALSE ) {
			if ( is_array( $matches[1] ) && ! empty( $matches[1] ) ) {
				foreach ( $matches[1] as $url ) {
					$urls[] = trim( $url );
				}
			}
		}
		return $urls;
	}

	/**
	 * Simulate multiple threads request
	 *
	 * @param array $url_list: Array of URLs to be connected.
	 * @param int $timeout: Time out in seconds. If 0 then forever.
	 * @param string $user_agent: `User-Agent:` header for request.
	 * @param array &$fail: Unfetched urls because of error.
	 * @return int: A Number of urls which have been fetched successfully.
	 */
	static public function fetch_multi_urls(
		$url_list,
		$timeout = 15,
		$user_agent = NULL,
		&$fail = array()
	) {
		// Prepare multi handle
		$mh = curl_multi_init(); // PHP 5

		// List of cURLs hadles
		$ch_list = array();

		foreach ( $url_list as $i => $url ) {
			$ch_list[$i] = curl_init( $url ); // PHP 4 >= 4.0.2, PHP 5

			$curl_setopt_defaults = [
				CURLOPT_FAILONERROR    => TRUE,
				CURLOPT_RETURNTRANSFER => TRUE,
				CURLOPT_FOLLOWLOCATION => TRUE,
				CURLOPT_MAXREDIRS      => 5,
				CURLOPT_HEADER         => FALSE,
				// No cookies
				CURLOPT_COOKIE         => '',
				// Ignore SSL Certification
				CURLOPT_SSL_VERIFYPEER => FALSE,
				// Set timeout ('0' means indefinitely)
				CURLOPT_TIMEOUT        => $timeout,
				CURLOPT_CONNECTTIMEOUT => $timeout,
			];
			// Set User Agent
			if ( ! is_null( $user_agent ) )
				$curl_setopt_defaults[ CURLOPT_USERAGENT ] = $user_agent;

			$curl_options = \apply_filters( 'wp-super-preload\curl_setopt', $curl_setopt_defaults );

			// set curl options coming from filter
			array_walk( $curl_options, function( $curl_value, $curl_option ) use ($ch_list, $i) {
				curl_setopt( $ch_list[$i], $curl_option, $curl_value );
			});


			curl_multi_add_handle( $mh, $ch_list[$i] ); // PHP 5
		}

		// Run the sub-connections of the current cURL handle
		// @link http://www.php.net/manual/function.curl-multi-init.php
		// @link http://www.php.net/manual/function.curl-multi-exec.php
		$active = NULL;
		do {
			$res = curl_multi_exec( $mh, $active ); // PHP 5
		} while ( CURLM_CALL_MULTI_PERFORM === $res );
	
		while ( $active && CURLM_OK === $res ) {
			if ( curl_multi_select( $mh ) !== -1 ) { // PHP 5
				do {
					$res = curl_multi_exec( $mh, $active );
				} while ( CURLM_CALL_MULTI_PERFORM === $res );
			}
		}

		// Get status of each request
		$res = 0;
		foreach ( $url_list as $i => $url ) {
			// CURLOPT_FAILONERROR should be set to true.
			$err = curl_error( $ch_list[$i] ); // PHP 4 >= 4.0.3, PHP 5
			if ( empty( $err ) ) {
//				self::access_log( curl_multi_getcontent( $ch_list[$i] ) );
//				self::access_log( $url );
				$res++;
			} else {
//				self::access_log( "$err at $url" );
				$fail[] = $url;
			}

			curl_multi_remove_handle( $mh, $ch_list[$i] ); // PHP 5
			curl_close( $ch_list[$i] ); // PHP 4 >= 4.0.2, PHP 5
		}

		// Close multi handle
		curl_multi_close( $mh ); // PHP 5

		return $res;
	} // end fetch_multi_urls

} // end class
