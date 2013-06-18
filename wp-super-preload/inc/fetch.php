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
		$xml = wp_remote_retrieve_body( wp_remote_get( $sitemap ) ); // @since 2.7.0

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
	 * @return array of string: Array of contents.
	 */
	static public function fetch_multi_urls(
		$url_list,
		$timeout = 0,
		$user_agent = NULL
	) {
		// Prepare multi handle
		$mh = curl_multi_init(); // PHP 5

		// List of cURLs hadles
		$ch_list = array();

		foreach ( $url_list as $i => $url ) {
			$ch_list[$i] = curl_init( $url ); // PHP 4 >= 4.0.2, PHP 5
			curl_setopt( $ch_list[$i], CURLOPT_FAILONERROR, TRUE );
			curl_setopt( $ch_list[$i], CURLOPT_RETURNTRANSFER, TRUE );
			curl_setopt( $ch_list[$i], CURLOPT_FOLLOWLOCATION, TRUE );
			curl_setopt( $ch_list[$i], CURLOPT_MAXREDIRS, 5 );
			curl_setopt( $ch_list[$i], CURLOPT_HEADER, FALSE );

			// No cookies
			curl_setopt( $ch_list[$i], CURLOPT_COOKIE, '' );

			// Ignore SSL Certification
			curl_setopt( $ch_list[$i], CURLOPT_SSL_VERIFYPEER, FALSE );

			// Set timeout
			if ( $timeout ) {
				curl_setopt( $ch_list[$i], CURLOPT_CONNECTTIMEOUT, $timeout );
				curl_setopt( $ch_list[$i], CURLOPT_TIMEOUT, $timeout );
			}

			// Set User Agent
			if ( ! is_null( $user_agent ) )
				curl_setopt( $ch_list[$i], CURLOPT_USERAGENT, $user_agent );

			curl_multi_add_handle( $mh, $ch_list[$i] ); // PHP 5
		}

		// Run the sub-connections of the current cURL handle
		// @link http://www.php.net/manual/function.curl-multi-init.php
		// @link http://www.php.net/manual/function.curl-multi-exec.php
		$running = NULL;
		do {
			curl_multi_exec( $mh, $running ); // PHP 5
		} while ( $running );

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
				throw new RuntimeException( "$err at $url" ); // PHP 5 >= 5.1.0
			}

			curl_multi_remove_handle( $mh, $ch_list[$i] ); // PHP 5
			curl_close( $ch_list[$i] ); // PHP 4 >= 4.0.2, PHP 5
		}

		// Close multi handle
		curl_multi_close( $mh ); // PHP 5

		return $res;
	} // end fetch_multi_urls

} // end class
