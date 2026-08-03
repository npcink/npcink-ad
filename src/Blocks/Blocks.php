<?php
/**
 * Dynamic promotion placement block registration.
 *
 * @package NpcinkAd
 */

namespace Npcink\Ad\Blocks;

use Npcink\Ad\Frontend\Delivery;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the one server-rendered Npcink Ad block.
 */
final class Blocks {
	private const EDITOR_SCRIPT = 'npcink-ad-block-editor';
	private const EDITOR_STYLE  = 'npcink-ad-block-editor';
	private const FRONTEND_STYLE = 'npcink-ad-frontend';
	private const PAGE_BAR_SCRIPT = 'npcink-ad-page-bar';

	/**
	 * Store the delivery orchestrator used by the render callback.
	 *
	 * @param Delivery $delivery Promotion delivery orchestrator.
	 */
	public function __construct( private readonly Delivery $delivery ) {}

	/**
	 * Register the block, build-produced editor script, and editor styles.
	 */
	public function register(): void {
		$block_dir = NPCINK_AD_PATH . 'assets/blocks/npcink-ad-promotion';
		if ( ! file_exists( $block_dir . '/block.json' ) ) {
			return;
		}

		$this->register_editor_assets();

		register_block_type(
			$block_dir,
			array(
				'title'           => _x( 'Npcink Ad Promotion', 'block title', 'npcink-ad' ),
				'description'     => _x( 'Insert a manually placed Npcink Ad promotion.', 'block description', 'npcink-ad' ),
				'editor_script'   => self::EDITOR_SCRIPT,
				'editor_style'    => self::EDITOR_STYLE,
				'render_callback' => array( $this, 'render' ),
			)
		);
	}

	/**
	 * Render the promotion referenced by block attributes.
	 *
	 * The editor controls whether it calls server-side rendering. The server
	 * protects delivery diagnostics with the manage_npcink_ads capability.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 */
	public function render( array $attributes ): string {
		$promotion_id = isset( $attributes['promotionId'] ) ? absint( $attributes['promotionId'] ) : 0;
		$reserve      = isset( $attributes['reserveHeight'] ) ? absint( $attributes['reserveHeight'] ) : 0;
		$is_editor_preview =
			! empty( $attributes['preview'] ) &&
			defined( 'REST_REQUEST' ) &&
			REST_REQUEST &&
			current_user_can( 'manage_npcink_ads' );
		$content = $is_editor_preview
			? $this->delivery->render_preview( $promotion_id, 'block', null, null, $reserve )
			: $this->delivery->render_promotion( $promotion_id, 'block', $reserve );
		if ( '' === $content ) {
			return '';
		}

		return sprintf(
			'<div %1$s>%2$s</div>',
			get_block_wrapper_attributes( array( 'class' => 'npcink-ad-block' ) ),
			$content
		);
	}

	/**
	 * Register the focused block editor entrypoint and its stylesheet.
	 */
	private function register_editor_assets(): void {
		$asset_file = NPCINK_AD_PATH . 'build/block-editor.asset.php';
		$asset      = file_exists( $asset_file ) ? require $asset_file : array();
		$asset      = is_array( $asset ) ? $asset : array();
		$version    = isset( $asset['version'] ) ? (string) $asset['version'] : NPCINK_AD_VERSION;
		$depends    = isset( $asset['dependencies'] ) && is_array( $asset['dependencies'] )
			? $asset['dependencies']
			: array();

		wp_register_script(
			self::EDITOR_SCRIPT,
			NPCINK_AD_URL . 'build/block-editor.js',
			$depends,
			$version,
			true
		);
		wp_set_script_translations(
			self::EDITOR_SCRIPT,
			'npcink-ad'
		);

		wp_register_style(
			self::EDITOR_STYLE,
			NPCINK_AD_URL . 'build/block-editor.css',
			array( 'wp-edit-blocks' ),
			$version
		);

		wp_register_style(
			self::FRONTEND_STYLE,
			NPCINK_AD_URL . 'assets/css/frontend.css',
			array(),
			NPCINK_AD_VERSION
		);

		$page_bar_asset_file = NPCINK_AD_PATH . 'build/page-bar.asset.php';
		$page_bar_asset      = file_exists( $page_bar_asset_file ) ? require $page_bar_asset_file : array();
		$page_bar_asset      = is_array( $page_bar_asset ) ? $page_bar_asset : array();
		$page_bar_version    = isset( $page_bar_asset['version'] )
			? (string) $page_bar_asset['version']
			: NPCINK_AD_VERSION;
		$page_bar_depends    = isset( $page_bar_asset['dependencies'] ) && is_array( $page_bar_asset['dependencies'] )
			? $page_bar_asset['dependencies']
			: array();

		wp_register_script(
			self::PAGE_BAR_SCRIPT,
			NPCINK_AD_URL . 'build/page-bar.js',
			$page_bar_depends,
			$page_bar_version,
			true
		);
	}
}
