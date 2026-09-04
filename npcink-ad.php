<?php
/**
 * Plugin Name: Npcink Ad
 * Description: Create, preview, and publish site-owned WordPress promotions.
 * Version: 0.3.5
 * Requires at least: 6.5
 * Tested up to: 7.1
 * Requires PHP: 8.1
 * Author: Npcink
 * Text Domain: npcink-ad
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package NpcinkAd
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'NPCINK_AD_FILE', __FILE__ );
define( 'NPCINK_AD_PATH', plugin_dir_path( __FILE__ ) );
define( 'NPCINK_AD_URL', plugin_dir_url( __FILE__ ) );
define( 'NPCINK_AD_VERSION', '0.3.5' );

/**
 * Load Npcink Ad classes from the src directory.
 *
 * @param string $class_name Fully qualified class name.
 */
function npcink_ad_autoload( string $class_name ): void {
	$prefix = 'Npcink\\Ad\\';
	if ( ! str_starts_with( $class_name, $prefix ) ) {
		return;
	}

	$relative = str_replace( '\\', '/', substr( $class_name, strlen( $prefix ) ) );
	$file     = NPCINK_AD_PATH . 'src/' . $relative . '.php';
	if ( is_readable( $file ) ) {
		require_once $file;
	}
}
spl_autoload_register( 'npcink_ad_autoload' );

/**
 * Establish the clean-baseline content types and capability.
 *
 * @param bool $network_wide Whether activation applies to the current network.
 */
function npcink_ad_activate( bool $network_wide = false ): void {
	\Npcink\Ad\Lifecycle::activate( $network_wide );
}

/**
 * Refresh rewrite rules after deactivation.
 *
 * @param bool $network_wide Whether deactivation applies to the current network.
 */
function npcink_ad_deactivate( bool $network_wide = false ): void {
	\Npcink\Ad\Lifecycle::deactivate( $network_wide );
}

register_activation_hook( __FILE__, 'npcink_ad_activate' );
register_deactivation_hook( __FILE__, 'npcink_ad_deactivate' );

( new \Npcink\Ad\Plugin() )->init();
