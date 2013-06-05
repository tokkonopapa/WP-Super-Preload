<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package   wp-super-preload
 * @author    tokkonopapa
 * @license   GPL-2.0+
 * @link      http://tokkono.cute.coocan.jp/blog/slow/
 * @copyright 2013 tokkonopapa (tokkonopapa@gmail.com)
 */

// If uninstall, not called from WordPress, then exit
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) )
	exit;

// TODO: Define uninstall functionality here
include plugin_dir_path( __FILE__ ) . 'inc/super-preload.php';
WP_Super_Preload::uninstall();
