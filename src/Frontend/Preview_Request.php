<?php
/**
 * Authenticated real-page promotion preview request.
 *
 * @package NpcinkAd
 */

namespace Npcink\Ad\Frontend;

use Npcink\Ad\Data\Post_Types;
use Npcink\Ad\Data\Repository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Replaces normal automatic delivery with one forced, truthful preview.
 */
final class Preview_Request {
	/**
	 * Promotion selected by the authorized preview URL.
	 *
	 * @var int
	 */
	private int $promotion_id = 0;

	/**
	 * Device context simulated by the preview canvas.
	 *
	 * @var string
	 */
	private string $device = 'desktop';

	/**
	 * Whether the preview has already been inserted into the main loop.
	 *
	 * @var bool
	 */
	private bool $rendered = false;

	/**
	 * Compose preview handling from the normal delivery and repository services.
	 *
	 * @param Delivery   $delivery   Normal promotion delivery service.
	 * @param Repository $repository Native promotion repository.
	 */
	public function __construct(
		private readonly Delivery $delivery,
		private readonly Repository $repository
	) {}

	/**
	 * Register preview request detection before the template renders.
	 */
	public function register(): void {
		add_action( 'template_redirect', array( $this, 'activate' ), 0 );
	}

	/**
	 * Validate and activate a manager-only preview request.
	 */
	public function activate(): void {
		if ( ! isset( $_GET['npcink_ad_preview'] ) ) {
			return;
		}

		$promotion_id = absint( wp_unslash( $_GET['npcink_ad_preview'] ) );
		$device       = isset( $_GET['npcink_ad_preview_device'] )
			? sanitize_key( wp_unslash( $_GET['npcink_ad_preview_device'] ) )
			: 'desktop';
		$nonce        = isset( $_GET['npcink_ad_preview_nonce'] )
			? sanitize_text_field( wp_unslash( $_GET['npcink_ad_preview_nonce'] ) )
			: '';
		$promotion    = $this->repository->find_promotion( $promotion_id );

		if (
			! current_user_can( 'manage_npcink_ads' ) ||
			! wp_verify_nonce( $nonce, 'npcink_ad_preview_' . $promotion_id ) ||
			null === $promotion
		) {
			wp_die( esc_html__( 'This promotion preview is not available.', 'npcink-ad' ), '', array( 'response' => 403 ) );
		}

		$target_id = get_queried_object_id();
		if ( ! $this->supports_preview_target( $promotion, $target_id ) ) {
			wp_die( esc_html__( 'Promotion previews require a public post or page.', 'npcink-ad' ), '', array( 'response' => 400 ) );
		}

		$this->promotion_id = $promotion_id;
		$this->device       = in_array( $device, array( 'desktop', 'mobile' ), true ) ? $device : 'desktop';
		$location = isset( $promotion['location'] ) ? (string) $promotion['location'] : 'content_after';
		$this->delivery->enable_preview_request();
		if ( 'block' === $location ) {
			$this->delivery->enable_block_preview(
				$promotion_id,
				$this->device,
				$target_id
			);
		} elseif ( 'content_after_paragraph' === $location ) {
			$this->delivery->enable_paragraph_preview(
				isset( $promotion['paragraph_number'] ) ? (int) $promotion['paragraph_number'] : 3,
				! array_key_exists( 'paragraph_number_valid', $promotion ) || (bool) $promotion['paragraph_number_valid'],
				$target_id
			);
		} elseif ( in_array( $location, Post_Types::BAR_LOCATIONS, true ) ) {
			$this->delivery->enable_page_bar_preview(
				$promotion_id,
				$location,
				$this->device,
				$target_id
			);
			add_action( 'wp_footer', array( $this, 'render_page_bar_fallback' ), 999 );
		}

		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
		nocache_headers();
		header( 'X-Robots-Tag: noindex, nofollow, noarchive', true );
		add_filter( 'show_admin_bar', array( $this, 'hide_admin_bar' ) );

		/*
		 * Keep Delivery::prepare_content() at priority 8. Paragraph previews
		 * consume its top-level block marker later in this request; only normal
		 * automatic rendering at priority 10 must be replaced.
		 */
		remove_filter( 'the_content', array( $this->delivery, 'filter_content' ), 10 );
		add_filter( 'the_content', array( $this, 'filter_content' ), 999 );
	}

	/**
	 * Remove the signed-in toolbar from the framed page so the canvas matches a visitor viewport.
	 *
	 * @param bool $show Whether WordPress would otherwise show the toolbar.
	 */
	public function hide_admin_bar( bool $show ): bool {
		unset( $show );

		return false;
	}

	/**
	 * Keep automatic real-page previews on the same post/page boundary as delivery.
	 *
	 * Manual block previews retain the existing explicit singular-target path.
	 *
	 * @param array<string, mixed> $promotion Promotion domain data.
	 * @param int                  $target_id Current queried post ID.
	 */
	private function supports_preview_target( array $promotion, int $target_id ): bool {
		if ( ! is_singular() || 1 > $target_id ) {
			return false;
		}
		if ( 'publish' !== get_post_status( $target_id ) || ! is_post_publicly_viewable( $target_id ) ) {
			return false;
		}

		$location = isset( $promotion['location'] ) ? (string) $promotion['location'] : 'content_after';
		if ( ! in_array( $location, Post_Types::AUTOMATIC_LOCATIONS, true ) ) {
			return true;
		}

		return in_array( get_post_type( $target_id ), array( 'post', 'page' ), true );
	}

	/**
	 * Insert the forced preview once into the selected real page.
	 *
	 * @param string $content Processed page content.
	 */
	public function filter_content( string $content ): string {
		if ( $this->rendered || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}

		$promotion = $this->repository->find_promotion( $this->promotion_id );
		if ( null === $promotion ) {
			return $content;
		}

		$this->rendered = true;

		$location = isset( $promotion['location'] ) ? (string) $promotion['location'] : 'content_after';
		if ( in_array( $location, Post_Types::BAR_LOCATIONS, true ) ) {
			return $content;
		}
		if ( 'block' === $location && $this->delivery->has_rendered_block_preview() ) {
			return $content;
		}
		if ( 'content_after_paragraph' === $location ) {
			return $this->delivery->insert_paragraph_preview(
				$content,
				$this->promotion_id,
				isset( $promotion['paragraph_number'] ) ? (int) $promotion['paragraph_number'] : 3,
				! array_key_exists( 'paragraph_number_valid', $promotion ) || (bool) $promotion['paragraph_number_valid'],
				$this->device,
				get_the_ID()
			);
		}

		$preview  = $this->delivery->render_preview(
			$this->promotion_id,
			$location,
			$this->device,
			get_the_ID()
		);
		if ( 'block' === $location ) {
			$preview = sprintf(
				'<div class="npcink-ad-preview-verdict">%s</div>%s',
				esc_html__( 'Manual block preview is shown after the page content. The live position is controlled by where the block is inserted.', 'npcink-ad' ),
				$preview
			);
		}

		return 'content_before' === $location ? $preview . $content : $content . $preview;
	}

	/**
	 * Explain a missing page-bar theme hook without faking another location.
	 *
	 * This fallback itself requires `wp_footer`. A theme that omits that hook
	 * cannot support bottom-bar delivery or its contextual warning.
	 */
	public function render_page_bar_fallback(): void {
		if ( $this->delivery->has_rendered_page_bar_preview() ) {
			return;
		}

		echo '<div class="npcink-ad-preview-verdict is-blocked">';
		echo esc_html__( 'The active theme did not render the selected page-bar hook. This placement cannot display in this template.', 'npcink-ad' );
		echo '</div>';
	}
}
