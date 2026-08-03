<?php
/**
 * Native admin menu registration.
 *
 * @package NpcinkAd
 */

namespace Npcink\Ad\Admin;

use Npcink\Ad\Data\Post_Types;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adds one menu for the native promotion workflow.
 */
final class Menu {
	/**
	 * Add a direct Promotion-management shortcut to the Plugins screen.
	 *
	 * @param array<string|int, string> $links Existing plugin action links.
	 * @return array<string|int, string>
	 */
	public static function add_settings_action_link( array $links ): array {
		$settings_link = sprintf(
			'<a href="%1$s">%2$s</a>',
			esc_url( admin_url( 'edit.php?post_type=' . Post_Types::PROMOTION_POST_TYPE ) ),
			esc_html( __( 'Settings', 'npcink-ad' ) )
		);

		array_unshift( $links, $settings_link );

		return $links;
	}

	/**
	 * Register the Npcink Ad menu and promotion list submenu.
	 */
	public static function register(): void {
		$promotions_url = 'edit.php?post_type=' . Post_Types::PROMOTION_POST_TYPE;

		add_menu_page(
			__( 'Npcink Ad', 'npcink-ad' ),
			__( 'Npcink Ad', 'npcink-ad' ),
			'manage_npcink_ads',
			$promotions_url,
			'',
			'dashicons-megaphone'
		);

		add_submenu_page(
			$promotions_url,
			__( 'Promotions', 'npcink-ad' ),
			__( 'Promotions', 'npcink-ad' ),
			'manage_npcink_ads',
			$promotions_url
		);
	}
}
