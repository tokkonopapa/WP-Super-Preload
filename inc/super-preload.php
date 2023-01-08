<?php
/**
 * WP Super Preload
 *
 * @package   wp-super-preload
 * @author    tokkonopapa
 * @license   GPL-2.0+
 * @link      http://tokkono.cute.coocan.jp/blog/slow/
 * @copyright 2013 tokkonopapa (tokkonopapa@yahoo.com)
 */

if( ! class_exists( 'WP_Super_Preload' ) ) :

class WP_Super_Preload {

	/*--------------------------------------------*
	 * Properties
	 *--------------------------------------------*/
	const TEXTDOMAIN  = 'super-preload';
	const EVENT_HOOK  = 'super_preload_event';
	const OPTION_DATA = 'super_preload_settings';
	const UPDATE_DATA = 'super_preload_updates';

	/**
	 * Plugin version, used for cache-busting of style and script file references.
	 */
	protected $version = '1.0.0';

	/**
	 * Unique identifier for your plugin.
	 */
	protected $plugin_class; // __CLASS__
	protected $plugin_file;  // __FILE__
	protected $plugin_base;  // plugin_basename( __FILE__ )
	protected $plugin_slug;  // 'wp-super-preload'

	/**
	 * Instance of this class.
	 */
	protected static $instance = null;

	/**
	 * Option table default value to be cached into options database table.
	 * @link http://wpengineer.com/968/wordpress-working-with-options/
	 */
	protected static $option_table = array(
		// Read only while preloading
		self::OPTION_DATA => array(
			// Basic settings
			'sitemap_urls'         => '',            // textarea
			'additional_contents'  => array(         // checkbox
				'front_pages'      => FALSE,
				'fixed_pages'      => FALSE,
				'categories'       => FALSE,
				'tags'             => FALSE,
				'authors'          => FALSE,
				'monthly_archives' => FALSE,
				'yearly_archives'  => FALSE,
			),
			'additional_pages'     => '',            // textarea
			'max_pages'            => '1200',        // text
			'user_agent'           => '',            // text
			'synchronize_gc'       => 'disable',     // select
			'preload_freq'         => 'disable',     // select
			'preload_hh'           => '00',          // text
			'preload_mm'           => '00',          // text
			// Advanced settings
			'split_preload'        => TRUE,          // checkbox
			'requests_per_split'   => '100',         // text
			'parallel_requests'    => '10',          // text in numbers
			'interval_in_msec'     => '500',         // text in milliseconds
			'timeout_per_fetch'    => '10',          // text in seconds
			'preload_time_limit'   => '600',         // text in seconds
			'initial_delay'        => '10',          // text in seconds
		),
		// Read and write while preloading
		self::UPDATE_DATA => array(
			'timestamp'            => 0,
			'proc_time'            => 0,
			'next_preload'         => 0,
		),
	);

	/**
	 * Initialize the plugin.
	 */
	public function __construct( $file, $class ) {
		( WP_SUPER_PRELOAD_DEBUG and self::debug_log( ( $this->plugin_class = $class ) . '__construct' ) );

		// Initialize for property
		$this->plugin_file = $file;
		$this->plugin_base = plugin_basename( $file ); // @since 1.5
		$this->plugin_slug = dirname( $this->plugin_base );

		// Load plugin text domain
		add_action( 'plugins_loaded', array( $this, 'plugin_textdomain' ) ); // @since 1.2.0

		// Register scheduled actions
		$this->register_actions();

		( WP_SUPER_PRELOAD_DEBUG and add_filter( 'exec_preload_hook', array( $this, 'post_preload' ), 10, 3 ) ); // @since 0.71
	}

	public function __destruct () {
		( WP_SUPER_PRELOAD_DEBUG and self::debug_log( $this->plugin_class . '__destruct' ) );
	}

	/**
	 * Return an instance of this class.
	 */
	public static function get_instance( $file ) {
		// If the single instance hasn't been set, set it now.
		if ( null === self::$instance )
			self::$instance = new self( $file, __CLASS__ );

		return self::$instance;
	}

	/**
	 * Fired when the plugin is activated.
	 */
	public static function activate() {
		// Set default sitemap url
		self::$option_table[ self::OPTION_DATA ]['sitemap_urls'] = home_url( '/' ) . 'sitemap.xml';

		// Register options into database table
		foreach ( self::$option_table as $key => $value ) {
			add_option( $key, self::$option_table[ $key ] ); // @since 1.0.0
		}

		// Register scheduled event
		self::register_schedule( get_option( self::OPTION_DATA ) );
	}

	/**
	 * Fired when the plugin is deactivated.
	 */
	public static function deactivate() {
		// Clear scheduled event hook
		self::deregister_schedule();
	}

	/**
	 * Fired when the plugin is uninstalled.
	 */
	public static function uninstall() {
		// Delete options from database table
		foreach ( self::$option_table as $key => $value ) {
			delete_option( $key ); // @since 1.2.0
		}
	}

	/**
	 * Load the plugin text domain for translation.
	 */
	public function plugin_textdomain() {
		load_plugin_textdomain( self::TEXTDOMAIN, FALSE, dirname( $this->plugin_base ) . '/lang/' ); // @since 1.5.0
	}

	/**
	 * Get version from plugin header
	 * @link http://codex.wordpress.org/Function_Reference/get_plugin_data
	 */
	protected function get_plugin_info() {
		return get_plugin_data( $this->plugin_file, FALSE, FALSE ); // @since 1.5.0
	}

	/*----------------------------------------*
	 * Core Functions
	 *----------------------------------------*/

	/**
	 * Register shceduled event
	 */
	protected static function register_schedule( $options ) {
		if ( 'disable' !== $options['preload_freq'] ) {
			$timestamp = strtotime( sprintf( '%02d:%02d', $options['preload_hh'], $options['preload_mm'] ) );
			wp_schedule_event( $timestamp - WP_SUPER_PRELOAD_GMT_OFFSET, $options['preload_freq'], self::EVENT_HOOK );
		}
	}

	/**
	 * Deregister shceduled event
	 */
	protected static function deregister_schedule() {
		wp_clear_scheduled_hook( self::EVENT_HOOK );
	}

	/**
	 * Register the action connected with garbage collection
	 */
	private function register_actions() {
		$options = get_option( self::OPTION_DATA ); // @since 1.5.0

		// Synchronize with garbage collection
		if ( 'disable' !== $options['synchronize_gc'] && ! empty( $options['synchronize_gc'] ) ) {
			$next = wp_next_scheduled( $options['synchronize_gc'] ); // @since 2.1.0
			if ( FALSE !== $next ) {
				add_action(
					$options['synchronize_gc'],
					array( $this, 'exec_preload' ), 11
				);
				( WP_SUPER_PRELOAD_DEBUG and self::debug_log( 'scheduled gc at ' . date( 'd/M/Y H:i:s', intval( $next ) + WP_SUPER_PRELOAD_GMT_OFFSET ) ) );
			}
		}

		// Own scheduled event
		$next = wp_next_scheduled( self::EVENT_HOOK );
		if ( FALSE !== $next ) {
			add_action( self::EVENT_HOOK, array( $this, 'exec_preload' ) );
			( WP_SUPER_PRELOAD_DEBUG and self::debug_log( 'scheduled hook at ' . date( 'd/M/Y H:i:s', intval( $next ) + WP_SUPER_PRELOAD_GMT_OFFSET ) ) );
		}
	}

	/**
	 * Get URLs from sitemaps
	 */
	private function get_urls( $opt_sitemap, $home ) {
		$len = strlen( $home );

		$urls = array();
		foreach ( explode( ' ', $opt_sitemap ) as $url ) {
			if ( strncmp( $url, $home, $len ) === 0 )
				$urls = array_merge( $urls, WP_Super_Fetch::get_urls_sitemap( $url ) );
		}

		return $urls;
	}

	/**
	 * Set URLs of additional contents to be preloaded
	 */
	private function add_contents( &$urls, $opt_contents, $home ) {
		if ( $opt_contents['front_pages'] ) {
			global $wp_rewrite;
			$pages = strpos( $wp_rewrite->permalink_structure, 'index.php' ) === FALSE ? "$home/page" : "$home/index.php/page";

			// @link http://codex.wordpress.org/Class_Reference/WP_Query
			$query = new WP_Query( 'post_type=post' ); // 'post_status=publish'
			$n = $query->max_num_pages;
			for ( $i = 2; $i < $n; $i++ ) {
				$urls[] = "$pages/$i/";
			}
			unset( $query );
/*
			$archives = paginate_links( array(
				'base' =>  "$home/%_%",
				'format' => "page/%#%",
				'total' => $n,
				'show_all' => TRUE,
			) ); // @since 2.7.0
			$archives = preg_replace( "|<a[^>]+href=[\"']?([^\"']*)[\"']?.*</a>|", "$1", $archives );
			$pages = explode( "\n", $archives );
			array_shift( $pages );
			$_urls = array();
			foreach ( $pages as $page ) {
				$_urls[] = trailingslashit( $page );
			}
			( WP_SUPER_PRELOAD_DEBUG and self::debug_log( print_r( $_urls, TRUE ) ) );
*/
		}

		if ( $opt_contents['fixed_pages'] ) {
			// @link http://codex.wordpress.org/Function_Reference/get_pages
			$pages = get_pages( array( 'post_type' => 'page' ) ); // @since 1.5.0
			foreach ( $pages as $page ) {
				$urls[] = get_page_link( $page->ID );
			}
		}

		if ( $opt_contents['categories'] ) {
			// @link http://codex.wordpress.org/Function_Reference/get_categories
			$pages = get_categories(); // @since 2.1.0
			foreach ( $pages as $page ) {
				$urls[] = $url = get_category_link( $page->term_id ); // @since 1.0.0
				$query = new WP_Query( "cat=$page->term_id" );
				$n = $query->max_num_pages;
				for ( $i = 2; $i <= $n; $i++ ) {
					$urls[] = untrailingslashit( $url ) . "/page/$i/";
				}
				unset( $query );
			}
		}

		if ( $opt_contents['tags'] ) {
			// @link http://codex.wordpress.org/Function_Reference/get_tags
			$pages = get_tags(); // @since 2.3.0
			foreach ( $pages as $page ) {
				$urls[] = $url = get_tag_link( $page->term_id ); // @since 2.3.0
				$query = new WP_Query( "tag_id=$page->term_id" );
				$n = $query->max_num_pages;
				for ( $i = 2; $i <= $n; $i++ ) {
					$urls[] = untrailingslashit( $url ) . "/page/$i/";
				}
				unset( $query );
			}
		}

		if ( $opt_contents['authors'] ) {
			// @link http://codex.wordpress.org/Function_Reference/get_users
			// @link http://codex.wordpress.org/Function_Reference/get_author_posts_url
			$pages = get_users( ['capability' => 'edit_posts'] ); // @see https://developer.wordpress.org/reference/classes/wp_user_query/prepare_query/#comment-5643
			foreach ( $pages as $page ) {
				$urls[] = $url = get_author_posts_url( $page->ID ); // @since 2.1.0
				$query = new WP_Query( "author=$page->ID" );
				$n = $query->max_num_pages;
				for ( $i = 2; $i < $n; $i++ ) {
					$urls[] = untrailingslashit( $url ) . "/page/$i/";
				}
				unset( $query );
			}
		}

		if ( $opt_contents['monthly_archives'] ) {
			// @link http://codex.wordpress.org/Function_Reference/wp_get_archives
			// @since 1.2.0 (except 'order' parameter)
			$archives = wp_get_archives( 'type=monthly&format=custom&echo=0' );
			$archives = preg_replace( "|<a[^>]+href=[\"']?([^\"']*)[\"']?.*</a>|", "$1", $archives );
			$pages = explode( "\n", $archives );
			array_pop( $pages );
			foreach ( $pages as $page ) {
				$urls[] = trim( $page );
			}
		}

		if ( $opt_contents['yearly_archives'] ) {
			// @link http://codex.wordpress.org/Function_Reference/wp_get_archives
			// @since 1.2.0 (except 'order' parameter)
			$archives = wp_get_archives( 'type=yearly&format=custom&echo=0' );
			$archives = preg_replace( "|<a[^>]+href=[\"']?([^\"']*)[\"']?.*</a>|", "$1", $archives );
			$pages = explode( "\n", $archives );
			array_pop( $pages );
			foreach ( $pages as $page ) {
				$urls[] = trim( $page );
			}
		}
	} // end add_contents

	/**
	 * Add specified pages
	 */
	private function add_pages( &$urls, $opt_pages ) {
		if ( ! empty( $opt_pages ) )
			$urls = array_merge( $urls, explode( ' ', $opt_pages ) );
	}

	/**
	 * Check same origin policy and remove duplicate URLs
	 */
	private function check_urls( &$urls, $home ) {
		$len = strlen( $home );

		$list = array();
		foreach ( $urls as $url ) {
			if ( strncmp( $url, $home, $len ) === 0 )
				$list[] = $url;
		}

		$urls = array_unique( $list );
	}

	/**
	 * Get start number to split
	 * @todo consider exclusive access
	 */
	private function get_split( $opt_requests, $total ) {
		$updates = get_option( self::UPDATE_DATA );

		$start = intval( $updates['next_preload'] );
		$updates['next_preload'] = $start + intval( $opt_requests );

		if( $updates['next_preload'] >= $total )
			$updates['next_preload'] = 0;

		if ( $total > 0 )
			update_option( self::UPDATE_DATA, $updates ); // @since 1.0.0

		return $start;
	}

	/**
	 * Get user agent
	 */
	private function get_user_agents( $opt_ua ) {
		$list = array( $this->plugin_slug );
		return empty( $opt_ua ) ? $list : array_merge( $list, explode( ' ', $opt_ua ) );
	}

	/**
	 * Preload pages based on sitemap.xml
	 * @param boolean $mode: 'fetch-now': No wait / 'no-fetch': No wait, No fetch
	 */
	public function exec_preload( $mode = NULL ) {
		$time = microtime( TRUE ); // PHP 4, PHP 5
		$home = home_url(); // @since 3.0.0
		$options = get_option( self::OPTION_DATA ); // @since 1.5.0

		// Ignore client aborts and disallow the script to run forever
		ignore_user_abort( TRUE ); // PHP 4, PHP 5
		set_time_limit( intval( $options['preload_time_limit'] ) ); // PHP 4, PHP 5

		// Include WP_Super_Fetch module
		require_once WP_SUPER_PRELOAD_PATH . 'inc/fetch.php';

		// Wait for synchronizing garbage collection
//		if ( 'fetch-now' !== $mode && 'no-fetch' !== $mode )
//			sleep( intval( $options['initial_delay'] ) ); // PHP 4, PHP 5

		// Get URLs from sitemap
		$urls = $this->get_urls( $options['sitemap_urls'], $home );

		// Additional contents
		$this->add_contents( $urls, $options['additional_contents'], $home );

		// Additional URLs
		$this->add_pages( $urls, $options['additional_pages'] );

		// Remove duplicate URLs
		$this->check_urls( $urls, $home );

		// Limit the number of URLs
		$urls = array_slice( $urls, 0, intval( $options['max_pages'] ) );

		// Split preloading
		if( 'no-fetch' !== $mode && $options['split_preload'] ) {
			$urls = array_slice(
				$urls,
				intval( $this->get_split( $options['requests_per_split'], count( $urls ) ) ),
				intval( $options['requests_per_split'] )
			);
		}

		( WP_SUPER_PRELOAD_DEBUG and self::debug_log( print_r( $urls, TRUE ) ) ); // PHP 4.3.0 or later

		if ( 'no-fetch' === $mode ) {
			$count = count( $urls );
		} else {
			// Set additional User Agent
			$user_agent = $this->get_user_agents( $options['user_agent'] );

			// parameters for crawling URLs
			$requests = intval( $options['parallel_requests'] );
			$timeout  = intval( $options['timeout_per_fetch'] );
			$interval = intval( $options['interval_in_msec'] ) * 1000;

			// crawl URLs with specified user agent
			foreach ( $user_agent as $ua ) {
				$count = 0;
				$pages = $urls;

				while ( count( $pages ) ) {
					// Fetch pages
					$retry = array();
					$count += WP_Super_Fetch::fetch_multi_urls(
						array_splice( $pages, 0, $requests ),
						$timeout,
						$ua,
						$retry
					);

					// Retry
					if ( ! empty( $retry ) ) {
						$count += WP_Super_Fetch::fetch_multi_urls(
							$retry,
							$timeout,
							$ua
						);
					}

					// Take a break
					usleep( $interval ); // PHP 4, PHP 5
				}
			}
		}

		// Do more staff
		return apply_filters( 'exec_preload_hook', $time, $mode, $count ); // @since 0.71
	} // end exec_preload

	/**
	 * Make a detailed message
	 */
	public function post_preload( $time, $mode, $count ) {
		// End crawling
		$time = sprintf(
			__( '%s %d pages (%.3f sec)', self::TEXTDOMAIN ),
			'no-fetch' === $mode ?
				__( 'Number of pages to be preloaded:', self::TEXTDOMAIN ) :
				__( 'Number of requested pages:', self::TEXTDOMAIN ),
			$count,
			microtime( TRUE ) - $time
		);

		( WP_SUPER_PRELOAD_DEBUG and self::debug_log( $time ) );
		return $time;
	}

	/**
	 * Output log to a file
	 * @param string $msg: message strings.
	 */
	protected static function debug_log( $msg = '' ) {
		$who = '';
		if ( is_admin() ) $who .= 'admin';
		if ( defined( 'DOING_AJAX' ) ) $who .= 'ajax';
		if ( defined( 'DOING_CRON' ) ) $who .= 'cron';
		if ( isset( $_GET['doing_wp_cron'] ) ) $who .= 'doing';
		$mod = empty( $msg ) ? 'w' : 'a';
		$fp = @fopen( WP_SUPER_PRELOAD_PATH . 'debug.log', $mod );
		if ( FALSE !== $fp ) {
			$msg = trim( $msg );
			@fwrite( $fp, date( 'd/M/Y H:i:s', time() + WP_SUPER_PRELOAD_GMT_OFFSET ) . " ${who}/${msg}\n" );
			@fclose( $fp );
		}
	}
} // end class

endif; // class_exists
