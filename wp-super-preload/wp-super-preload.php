<?php
/**
 * WP Super Preload
 *
 * @package   wp-super-preload
 * @author    tokkonopapa
 * @license   GPL-2.0+
 * @link      http://tokkono.cute.coocan.jp/blog/slow/
 * @copyright 2013 tokkonopapa (tokkonopapa@gmail.com)
 *
 * Plugin Name: WP Super Preload
 * Plugin URI: http://wordpress.org/extend/plugins/wp-super-preload/
 * Description: This plugin helps to keep whole pages of your site always being cached in the fresh based on the sitemap.xml and your own settings.
 * Version: 1.0.0
 * Author: tokkonopapa
 * Author URI: http://tokkono.cute.coocan.jp/blog/slow/
 * Requires: WordPress 3.1.0 or higher / PHP 5 with libcurl
 * Compatible up to: WordPress 3.5.1
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) )
	die;

// Only admin, Ajax or WP-Cron should execute this class
if ( ! is_admin() && ! defined( 'DOING_AJAX' ) && ! defined( 'DOING_CRON' ) && ! isset( $_GET['doing_wp_cron'] ) )
	return;

define( 'WP_SUPER_PRELOAD_DEBUG', FALSE ); // output log
define( 'WP_SUPER_PRELOAD_PATH', plugin_dir_path( __FILE__ ) ); // @since 2.8
define( 'WP_SUPER_PRELOAD_GMT_OFFSET', intval( get_option( 'gmt_offset' ) ) * 3600 );

// Core class file
require_once WP_SUPER_PRELOAD_PATH . 'inc/super-preload.php';

// Register hooks that are fired when the plugin is activated, deactivated.
register_activation_hook( __FILE__, array( 'WP_Super_Preload', 'activate' ) ); // @since 2.0
register_deactivation_hook( __FILE__, array( 'WP_Super_Preload', 'deactivate' ) );

// Instantiate this plugin
if ( is_admin() ) {
	require_once WP_SUPER_PRELOAD_PATH . 'inc/admin.php';
	WP_Super_Preload_Admin::get_instance( __FILE__ );
} else {
	WP_Super_Preload::get_instance( __FILE__ );
}
