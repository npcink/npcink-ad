<?php
/**
 * Real-page preview target-boundary tests.
 *
 * @package NpcinkAd
 */

use Npcink\Ad\Data\Post_Types;
use Npcink\Ad\Data\Repository;
use Npcink\Ad\Domain\Eligibility_Evaluator;
use Npcink\Ad\Frontend\Delivery;
use Npcink\Ad\Frontend\Paragraph_Inserter;
use Npcink\Ad\Frontend\Preview_Request;
use Npcink\Ad\Frontend\Renderer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/PromotionStatusWordPressStubs.php';
require_once __DIR__ . '/PromotionStatusWordPressPost.php';
require_once __DIR__ . '/DeliveryWordPressStubs.php';
require_once __DIR__ . '/PreviewRequestWpDieException.php';
require_once __DIR__ . '/PreviewRequestWordPressStubs.php';
require_once __DIR__ . '/ParagraphTestLocator.php';
require_once dirname( __DIR__, 2 ) . '/src/Data/Post_Types.php';
require_once dirname( __DIR__, 2 ) . '/src/Data/Repository.php';
require_once dirname( __DIR__, 2 ) . '/src/Presentation/Eligibility_Messages.php';
require_once dirname( __DIR__, 2 ) . '/src/Frontend/Renderer.php';
require_once dirname( __DIR__, 2 ) . '/src/Frontend/Paragraph_Inserter.php';
require_once dirname( __DIR__, 2 ) . '/src/Frontend/Delivery.php';
require_once dirname( __DIR__, 2 ) . '/src/Frontend/Preview_Request.php';

/**
 * Covers the target-type boundary before forced preview filters are installed.
 */
final class PreviewRequestTest extends TestCase {
	private const PROMOTION_ID = 7;
	private const TARGET_ID    = 99;

	/**
	 * Reset preview request and post fixtures.
	 */
	protected function setUp(): void {
		$_GET = array(
			'npcink_ad_preview'       => self::PROMOTION_ID,
			'npcink_ad_preview_nonce' => 'nonce:npcink_ad_preview_' . self::PROMOTION_ID,
		);
		$GLOBALS['npcink_ad_test_posts'] = array(
			self::PROMOTION_ID => new WP_Post(
				array(
					'ID'           => self::PROMOTION_ID,
					'post_type'    => Post_Types::PROMOTION_POST_TYPE,
					'post_status'  => 'draft',
					'post_content' => '<p>Preview creative</p>',
				)
			),
		);
		$GLOBALS['npcink_ad_test_meta'] = array(
			self::PROMOTION_ID => $this->promotion_meta( 'content_after' ),
		);
		$GLOBALS['npcink_ad_test_singular_post_type']  = 'post';
		$GLOBALS['npcink_ad_test_preview_target_id']   = self::TARGET_ID;
		$GLOBALS['npcink_ad_test_preview_target_type'] = 'post';
		$GLOBALS['npcink_ad_test_preview_target_status'] = 'publish';
		$GLOBALS['npcink_ad_test_preview_target_public'] = true;
		$GLOBALS['npcink_ad_test_preview_filters']     = array();
		$GLOBALS['npcink_ad_test_preview_removed_callbacks'] = array();
		$GLOBALS['npcink_ad_test_preview_headers']     = array();
		$GLOBALS['npcink_ad_test_preview_nocache']     = false;
		$GLOBALS['npcink_ad_test_current_post_id']     = self::TARGET_ID;
		$GLOBALS['npcink_ad_test_queried_post_id']     = self::TARGET_ID;
		$GLOBALS['npcink_ad_test_enqueued_styles']     = array();
		$GLOBALS['npcink_ad_test_enqueued_scripts']    = array();
		$GLOBALS['npcink_ad_test_actions']             = array();
	}

	/**
	 * Clear request input after each test.
	 */
	protected function tearDown(): void {
		$_GET = array();
	}

	/**
	 * Automatic placement cannot force-render on a public custom post type.
	 */
	public function test_automatic_preview_rejects_a_custom_post_type_before_installing_filters(): void {
		$GLOBALS['npcink_ad_test_preview_target_type'] = 'product';
		$GLOBALS['npcink_ad_test_singular_post_type']  = 'product';

		try {
			$this->preview_request()->activate();
			self::fail( 'Expected the automatic custom-post-type preview to be rejected.' );
		} catch ( PreviewRequestWpDieException $exception ) {
			self::assertSame( 400, $exception->response );
			self::assertSame( 'Promotion previews require a public post or page.', $exception->getMessage() );
		}

		self::assertSame( array(), $GLOBALS['npcink_ad_test_preview_filters'] );
		self::assertFalse( $GLOBALS['npcink_ad_test_preview_nocache'] );
	}

	/**
	 * Paragraph placement remains inside the standard post/page boundary.
	 */
	public function test_paragraph_preview_rejects_a_custom_post_type_before_installing_filters(): void {
		$GLOBALS['npcink_ad_test_meta'][ self::PROMOTION_ID ] = $this->promotion_meta( 'content_after_paragraph', 3 );
		$GLOBALS['npcink_ad_test_preview_target_type']        = 'product';
		$GLOBALS['npcink_ad_test_singular_post_type']         = 'product';

		try {
			$this->preview_request()->activate();
			self::fail( 'Expected the paragraph custom-post-type preview to be rejected.' );
		} catch ( PreviewRequestWpDieException $exception ) {
			self::assertSame( 400, $exception->response );
			self::assertSame( 'Promotion previews require a public post or page.', $exception->getMessage() );
		}

		self::assertSame( array(), $GLOBALS['npcink_ad_test_preview_filters'] );
		self::assertFalse( $GLOBALS['npcink_ad_test_preview_nocache'] );
	}

	/**
	 * Page bars remain inside the standard post/page preview boundary.
	 */
	public function test_page_bar_preview_rejects_a_custom_post_type(): void {
		$GLOBALS['npcink_ad_test_meta'][ self::PROMOTION_ID ] = $this->promotion_meta( 'bar_top' );
		$GLOBALS['npcink_ad_test_preview_target_type']        = 'product';
		$GLOBALS['npcink_ad_test_singular_post_type']         = 'product';

		try {
			$this->preview_request()->activate();
			self::fail( 'Expected the page-bar custom-post-type preview to be rejected.' );
		} catch ( PreviewRequestWpDieException $exception ) {
			self::assertSame( 400, $exception->response );
		}

		self::assertSame( array(), $GLOBALS['npcink_ad_test_preview_filters'] );
	}

	/**
	 * Standard post and page targets retain automatic real-page preview.
	 *
	 * @param string $post_type Target post type.
	 */
	#[DataProvider( 'standard_post_types' )]
	public function test_automatic_preview_accepts_standard_posts_and_pages( string $post_type ): void {
		$GLOBALS['npcink_ad_test_preview_target_type'] = $post_type;
		$GLOBALS['npcink_ad_test_singular_post_type']  = $post_type;

		$this->preview_request()->activate();

		self::assertSame(
			array( 'add:show_admin_bar:10', 'remove:the_content', 'add:the_content:999' ),
			$GLOBALS['npcink_ad_test_preview_filters']
		);
		self::assertSame(
			array(
				array(
					'method' => 'filter_content',
					'priority' => 10,
				),
			),
			$GLOBALS['npcink_ad_test_preview_removed_callbacks']
		);
		self::assertTrue( $GLOBALS['npcink_ad_test_preview_nocache'] );
		self::assertFalse( $this->preview_request()->hide_admin_bar( true ) );
	}

	/**
	 * Provide standard automatic-delivery target types.
	 *
	 * @return array<string, array{string}>
	 */
	public static function standard_post_types(): array {
		return array(
			'post' => array( 'post' ),
			'page' => array( 'page' ),
		);
	}

	/**
	 * Automatic previews reject targets that are not public, even when the
	 * signed request is otherwise valid.
	 */
	public function test_automatic_preview_rejects_non_public_targets(): void {
		foreach ( array( 'draft', 'private' ) as $status ) {
			$GLOBALS['npcink_ad_test_preview_target_status'] = $status;
			$GLOBALS['npcink_ad_test_preview_target_public'] = false;

			try {
				$this->preview_request()->activate();
				self::fail( 'Expected the non-public preview target to be rejected.' );
			} catch ( PreviewRequestWpDieException $exception ) {
				self::assertSame( 400, $exception->response );
				self::assertSame( 'Promotion previews require a public post or page.', $exception->getMessage() );
			}
		}
	}

	/**
	 * A manual block remains an explicit singular-target preview on a CPT.
	 */
	public function test_manual_block_preview_keeps_its_explicit_custom_post_type_path(): void {
		$GLOBALS['npcink_ad_test_meta'][ self::PROMOTION_ID ] = $this->promotion_meta( 'block' );
		$GLOBALS['npcink_ad_test_preview_target_type']        = 'product';
		$GLOBALS['npcink_ad_test_singular_post_type']         = 'product';
		$request = $this->preview_request();

		$request->activate();

		self::assertSame(
			array( 'add:show_admin_bar:10', 'remove:the_content', 'add:the_content:999' ),
			$GLOBALS['npcink_ad_test_preview_filters']
		);
		self::assertTrue( $GLOBALS['npcink_ad_test_preview_nocache'] );
	}

	/**
	 * Non-paragraph previews do not leak normal paragraph preparation markers.
	 */
	public function test_non_paragraph_preview_suppresses_automatic_paragraph_markers(): void {
		$paragraph_id = 8;
		$GLOBALS['npcink_ad_test_posts'][ $paragraph_id ] = new WP_Post(
			array(
				'ID'           => $paragraph_id,
				'post_type'    => Post_Types::PROMOTION_POST_TYPE,
				'post_status'  => 'publish',
				'post_content' => '<p>Other promotion</p>',
			)
		);
		$GLOBALS['npcink_ad_test_meta'][ $paragraph_id ] = $this->promotion_meta( 'content_after_paragraph', 1 );
		$services = $this->preview_services( array( $this->block( 'core/paragraph', '<p>One</p>' ) ) );
		$request  = $services['request'];
		$delivery = $services['delivery'];

		$request->activate();

		self::assertSame( 'BLOCK_SOURCE', $delivery->prepare_content( 'BLOCK_SOURCE' ) );
	}

	/**
	 * A page bar leaves content untouched and renders through its actual hook.
	 */
	public function test_top_page_bar_preview_renders_through_body_open(): void {
		$GLOBALS['npcink_ad_test_meta'][ self::PROMOTION_ID ] = $this->promotion_meta( 'bar_top' );
		$services = $this->preview_services( array( $this->block( null, '<p>Classic</p>' ) ) );
		$request  = $services['request'];
		$delivery = $services['delivery'];

		$request->activate();
		self::assertSame( '<p>Body</p>', $request->filter_content( '<p>Body</p>' ) );

		ob_start();
		$delivery->render_top_page_bar();
		$bar = (string) ob_get_clean();
		ob_start();
		$request->render_page_bar_fallback();
		$fallback = (string) ob_get_clean();

		self::assertStringContainsString( 'data-npcink-ad-promotion="' . self::PROMOTION_ID . '"', $bar );
		self::assertStringContainsString( 'npcink-ad-page-bar--top', $bar );
		self::assertSame( '', $fallback );
		self::assertTrue( $delivery->has_rendered_page_bar_preview() );
	}

	/**
	 * Missing theme integration is reported without moving the preview into content.
	 */
	public function test_missing_page_bar_hook_has_a_contextual_fallback(): void {
		$GLOBALS['npcink_ad_test_meta'][ self::PROMOTION_ID ] = $this->promotion_meta( 'bar_top' );
		$request = $this->preview_request();

		$request->activate();
		ob_start();
		$request->render_page_bar_fallback();
		$fallback = (string) ob_get_clean();

		self::assertStringContainsString( 'did not render the selected page-bar hook', $fallback );
		self::assertStringContainsString( 'is-blocked', $fallback );
	}

	/**
	 * A valid Gutenberg anchor receives the forced preview at its marker.
	 */
	public function test_paragraph_preview_renders_at_a_top_level_block_anchor(): void {
		$GLOBALS['npcink_ad_test_meta'][ self::PROMOTION_ID ] = $this->promotion_meta( 'content_after_paragraph', 2 );
		$services = $this->preview_services(
			array(
				$this->block( 'core/paragraph', '<p>One</p>' ),
				$this->block( 'core/paragraph', '<p>Two</p>' ),
				$this->block( 'core/paragraph', '<p>Three</p>' ),
			)
		);
		$request  = $services['request'];
		$delivery = $services['delivery'];

		$request->activate();
		$output    = $request->filter_content( $delivery->prepare_content( 'BLOCK_SOURCE' ) );
		$promotion = strpos( $output, 'data-npcink-ad-promotion="' . self::PROMOTION_ID . '"' );
		$second     = strpos( $output, '<p>Two</p>' );
		$third      = strpos( $output, '<p>Three</p>' );

		self::assertIsInt( $promotion );
		self::assertIsInt( $second );
		self::assertIsInt( $third );
		self::assertGreaterThan( $second, $promotion );
		self::assertLessThan( $third, $promotion );
		self::assertStringNotContainsString( 'The selected paragraph is not available in this content.', $output );
		self::assertStringNotContainsString( 'npcink-ad-paragraph-anchor-', $output );
	}

	/**
	 * A missing Gutenberg anchor appends a diagnostic preview, never a live ad.
	 */
	public function test_missing_block_anchor_appends_a_diagnostic_preview(): void {
		$GLOBALS['npcink_ad_test_meta'][ self::PROMOTION_ID ] = $this->promotion_meta( 'content_after_paragraph', 3 );
		$services = $this->preview_services(
			array(
				$this->block( 'core/paragraph', '<p>One</p>' ),
				$this->block( 'core/paragraph', '<p>Two</p>' ),
			)
		);
		$request  = $services['request'];
		$delivery = $services['delivery'];

		$request->activate();
		$output     = $request->filter_content( $delivery->prepare_content( 'BLOCK_SOURCE' ) );
		$promotion  = strpos( $output, 'data-npcink-ad-promotion="' . self::PROMOTION_ID . '"' );
		$last_paragraph = strpos( $output, '<p>Two</p>' );

		self::assertIsInt( $promotion );
		self::assertIsInt( $last_paragraph );
		self::assertGreaterThan( $last_paragraph, $promotion );
		self::assertStringContainsString( 'The selected paragraph is not available in this content.', $output );
		self::assertStringNotContainsString( 'npcink-ad-block-content-', $output );
	}

	/**
	 * Classic content uses the same missing-anchor preview contract.
	 */
	public function test_missing_classic_anchor_appends_a_diagnostic_preview(): void {
		$GLOBALS['npcink_ad_test_meta'][ self::PROMOTION_ID ] = $this->promotion_meta( 'content_after_paragraph', 3 );
		$services = $this->preview_services( array( $this->block( null, '<p>Classic</p>' ) ) );
		$request  = $services['request'];
		$delivery = $services['delivery'];

		$request->activate();
		$content   = '<p>Only one</p>';
		$output    = $request->filter_content( $delivery->prepare_content( $content ) );
		$promotion = strpos( $output, 'data-npcink-ad-promotion="' . self::PROMOTION_ID . '"' );

		self::assertIsInt( $promotion );
		self::assertGreaterThan( strpos( $output, '</p>' ), $promotion );
		self::assertStringContainsString( 'The selected paragraph is not available in this content.', $output );
	}

	/**
	 * Invalid stored paragraph metadata is not disguised as a missing third anchor.
	 */
	public function test_invalid_paragraph_preview_reports_only_configuration_invalidity(): void {
		$GLOBALS['npcink_ad_test_meta'][ self::PROMOTION_ID ] = $this->promotion_meta( 'content_after_paragraph', 0 );
		$GLOBALS['npcink_ad_test_posts'][ self::PROMOTION_ID ]->post_status = 'publish';
		$services = $this->preview_services( array( $this->block( null, '<p>Classic</p>' ) ) );
		$request  = $services['request'];
		$delivery = $services['delivery'];

		$request->activate();
		$output = $request->filter_content( $delivery->prepare_content( '<p>Only one</p>' ) );

		self::assertStringContainsString( 'Choose a paragraph number from 1 to 20.', $output );
		self::assertStringNotContainsString( 'The promotion is not published.', $output );
		self::assertStringNotContainsString( 'The selected paragraph is not available in this content.', $output );
		self::assertStringNotContainsString( 'npcink-ad-paragraph-anchor-', $output );
	}

	/**
	 * Compose the real preview request with production services.
	 */
	private function preview_request(): Preview_Request {
		$services = $this->preview_services( array( $this->block( null, '<p>Classic</p>' ) ) );

		return $services['request'];
	}

	/**
	 * Compose preview services around deterministic parsed block fixtures.
	 *
	 * @param array<int, array<string, mixed>> $blocks Parsed blocks.
	 * @return array{request: Preview_Request, delivery: Delivery}
	 */
	private function preview_services( array $blocks ): array {
		$repository = new Repository();
		$inserter   = new Paragraph_Inserter(
			static fn ( string $content ): array => $blocks,
			static function ( array $serialized_blocks ): string {
				$output = '';
				foreach ( $serialized_blocks as $block ) {
					$output .= (string) ( $block['innerHTML'] ?? '' );
				}

				return $output;
			},
			\Closure::fromCallable( array( ParagraphTestLocator::class, 'locate' ) )
		);
		$delivery   = new Delivery( $repository, new Eligibility_Evaluator(), new Renderer(), $inserter );

		return array(
			'request'  => new Preview_Request( $delivery, $repository ),
			'delivery' => $delivery,
		);
	}

	/**
	 * Build complete Promotion metadata for one preview fixture.
	 *
	 * @param string   $location         Stored delivery location.
	 * @param int|null $paragraph_number Optional stored paragraph number.
	 * @return array<string, mixed>
	 */
	private function promotion_meta( string $location, ?int $paragraph_number = null ): array {
		$meta = array(
			Post_Types::LOCATION_META    => $location,
			Post_Types::CONTENT_SCOPE_META => 'all',
			Post_Types::INCLUDE_IDS_META => array(),
			Post_Types::EXCLUDE_IDS_META => array(),
			Post_Types::DEVICE_META      => 'all',
			Post_Types::START_AT_META    => '',
			Post_Types::END_AT_META      => '',
		);
		if ( null !== $paragraph_number ) {
			$meta[ Post_Types::PARAGRAPH_NUMBER_META ] = $paragraph_number;
		}

		return $meta;
	}

	/**
	 * Build one parsed block fixture.
	 *
	 * @param string|null $name Block name.
	 * @param string      $html Block inner HTML.
	 * @return array<string, mixed>
	 */
	private function block( ?string $name, string $html ): array {
		return array(
			'blockName'    => $name,
			'attrs'        => array(),
			'innerBlocks'  => array(),
			'innerHTML'    => $html,
			'innerContent' => array( $html ),
		);
	}
}
