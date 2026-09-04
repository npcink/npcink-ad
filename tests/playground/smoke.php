<?php
/**
 * WordPress Playground integration smoke test for a packaged Npcink Ad build.
 *
 * @package NpcinkAd
 */

use Npcink\Ad\Admin\Editor;
use Npcink\Ad\Admin\Preview_Page;
use Npcink\Ad\Admin\Promotion_Duplicate_Action;
use Npcink\Ad\Admin\Promotion_List;
use Npcink\Ad\Admin\Promotion_Status_Action;
use Npcink\Ad\Blocks\Blocks;
use Npcink\Ad\Data\Post_Types;
use Npcink\Ad\Data\Repository;
use Npcink\Ad\Domain\Eligibility_Evaluator;
use Npcink\Ad\Domain\Overlap_Detector;
use Npcink\Ad\Frontend\Classic_Paragraph_Tag_Processor;
use Npcink\Ad\Frontend\Delivery;
use Npcink\Ad\Frontend\Paragraph_Inserter;
use Npcink\Ad\Frontend\Preview_Request;
use Npcink\Ad\Frontend\Renderer;

require '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';

$check = static function ( bool $condition, string $message ): void {
	if ( ! $condition ) {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI smoke protocol emits a fixed developer assertion before WordPress handles the exception.
		echo 'NPCINK_AD_SMOKE_FAIL ' . $message . PHP_EOL;
		// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Test failure messages are fixed developer strings.
		throw new RuntimeException( $message );
	}
};

/**
 * Exception used to observe wp_die() responses without ending the CLI smoke.
 */
final class Npcink_Ad_Smoke_Wp_Die_Exception extends RuntimeException {}

$expect_wp_die = static function ( callable $callback ): int {
	$wp_die_handler = static function ( mixed $message, string $title, array $args ): void {
		unset( $title );
		$response = isset( $args['response'] ) ? absint( $args['response'] ) : 0;

		// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Test-only handler preserves wp_die details for assertions.
		throw new Npcink_Ad_Smoke_Wp_Die_Exception( is_string( $message ) ? $message : 'wp_die', $response );
	};
	$wp_die_filter  = static fn (): callable => $wp_die_handler;

	add_filter( 'wp_die_handler', $wp_die_filter );
	try {
		$callback();
	} catch ( Npcink_Ad_Smoke_Wp_Die_Exception $exception ) {
		return $exception->getCode();
	} finally {
		remove_filter( 'wp_die_handler', $wp_die_filter );
	}

	throw new RuntimeException( 'Expected the request to terminate through wp_die().' );
};

$check( is_plugin_active( 'npcink-ad/npcink-ad.php' ), 'Packaged plugin was not activated.' );
$check( class_exists( WP_HTML_Tag_Processor::class ), 'WordPress did not load the Core HTML Tag Processor before plugins.' );
$check(
	is_subclass_of( Classic_Paragraph_Tag_Processor::class, WP_HTML_Tag_Processor::class ),
	'The packaged Classic paragraph processor did not load against the Core HTML API.'
);
$plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/npcink-ad/npcink-ad.php', false, false );
$check( ! isset( $plugin_data['DomainPath'] ) || '' === $plugin_data['DomainPath'], 'The packaged plugin declares a stale languages directory.' );
$check( post_type_exists( 'npcink_promotion' ), 'The npcink_promotion post type was not registered.' );
$check( ! post_type_exists( 'npcink_ad_placement' ), 'The removed npcink_ad_placement post type is still registered.' );
$check( get_role( 'administrator' )->has_cap( 'manage_npcink_ads' ), 'Administrators did not receive the management capability.' );
$check( get_role( 'editor' )->has_cap( 'manage_npcink_ads' ), 'Editors did not receive the management capability.' );

$promotion_meta = get_registered_meta_keys( 'post', 'npcink_promotion' );
$required_meta  = array(
	'_npcink_ad_location',
	'_npcink_ad_content_scope',
	'_npcink_ad_include_ids',
	'_npcink_ad_exclude_ids',
	'_npcink_ad_category_ids',
	'_npcink_ad_tag_ids',
	'_npcink_ad_device',
	'_npcink_ad_start_at',
	'_npcink_ad_end_at',
	'_npcink_ad_paragraph_number',
);
foreach ( $required_meta as $meta_key ) {
	$check( isset( $promotion_meta[ $meta_key ] ), 'Missing registered promotion meta: ' . $meta_key );
}
$check( 'array' === $promotion_meta['_npcink_ad_include_ids']['type'], 'Include IDs meta is not typed as an array.' );
$check( 'array' === $promotion_meta['_npcink_ad_exclude_ids']['type'], 'Exclude IDs meta is not typed as an array.' );
$check( 'array' === $promotion_meta['_npcink_ad_category_ids']['type'], 'Category IDs meta is not typed as an array.' );
$check( 'array' === $promotion_meta['_npcink_ad_tag_ids']['type'], 'Tag IDs meta is not typed as an array.' );
$check( ! isset( $promotion_meta['_npcink_ad_page_scope'] ), 'The removed page-scope meta is still registered.' );
$check( 'integer' === $promotion_meta['_npcink_ad_paragraph_number']['type'], 'Paragraph number meta is not typed as an integer.' );
$check( 3 === $promotion_meta['_npcink_ad_paragraph_number']['default'], 'Paragraph number meta does not default to 3.' );
$check(
	Post_Types::LOCATIONS === $promotion_meta['_npcink_ad_location']['show_in_rest']['schema']['enum'],
	'The REST location enum does not expose the complete single-Promotion location contract.'
);
$check( shortcode_exists( 'npcink_ad' ), 'The npcink_ad shortcode was not registered.' );
$promotion_block = WP_Block_Type_Registry::get_instance()->get_registered( 'npcink-ad/promotion' );
$check( $promotion_block instanceof WP_Block_Type, 'The npcink-ad/promotion block was not registered.' );
$check(
	$promotion_block instanceof WP_Block_Type && 'Insert a manually placed Npcink Ad promotion.' === $promotion_block->description,
	'The registered Promotion block description does not explain manual placement.'
);

$block_metadata_path = WP_PLUGIN_DIR . '/npcink-ad/assets/blocks/npcink-ad-promotion/block.json';
$block_metadata      = json_decode( (string) file_get_contents( $block_metadata_path ), true );
$check( is_array( $block_metadata ), 'The packaged Promotion block metadata is not valid JSON.' );
$check(
	is_array( $block_metadata ) && 'Insert a manually placed Npcink Ad promotion.' === ( $block_metadata['description'] ?? '' ),
	'The packaged Promotion block metadata description does not match the PHP registration.'
);
$block_attribute_names = is_array( $block_metadata ) && isset( $block_metadata['attributes'] ) && is_array( $block_metadata['attributes'] )
	? array_keys( $block_metadata['attributes'] )
	: array();
sort( $block_attribute_names, SORT_STRING );
$check(
	array( 'preview', 'promotionId', 'reserveHeight' ) === $block_attribute_names,
	'The Promotion block attribute contract changed outside its explicit three-field boundary.'
);

$frontend_css_path = WP_PLUGIN_DIR . '/npcink-ad/assets/css/frontend.css';
$frontend_css      = (string) file_get_contents( $frontend_css_path );
$check(
	1 === preg_match_all(
		'/@media\s*\(\s*max-width:\s*781px\s*\)\s*\{\s*\.npcink-ad-device-desktop\s*\{\s*display:\s*none\s*!important;\s*\}\s*\}/',
		$frontend_css
	),
	'The frontend CSS does not hide desktop-only Promotions exactly once at 781px and below.'
);
$check(
	1 === preg_match_all(
		'/@media\s*\(\s*min-width:\s*782px\s*\)\s*\{\s*\.npcink-ad-device-mobile\s*\{\s*display:\s*none\s*!important;\s*\}\s*\}/',
		$frontend_css
	),
	'The frontend CSS does not hide mobile-only Promotions exactly once at 782px and above.'
);
$check( 1 === substr_count( $frontend_css, '.npcink-ad-device-desktop' ), 'The desktop device selector is duplicated outside its fixed breakpoint rule.' );
$check( 1 === substr_count( $frontend_css, '.npcink-ad-device-mobile' ), 'The mobile device selector is duplicated outside its fixed breakpoint rule.' );
$check( str_contains( $frontend_css, '.npcink-ad-page-bar' ), 'The packaged frontend CSS omitted page-bar presentation.' );
$check( str_contains( $frontend_css, '.npcink-ad-page-bar__inner' ), 'The page-bar omitted its theme-width inner container.' );
$check(
	str_contains( $frontend_css, 'width: calc(100% - 32px);' )
	&& str_contains( $frontend_css, 'max-width: var(--wp--style--global--wide-size, 1200px);' ),
	'The page-bar inner container does not use the theme wide size with a classic-theme fallback.'
);
$check(
	str_contains( $frontend_css, 'grid-template-columns: minmax(0, 1fr) 44px;' ),
	'The page-bar inner container does not reserve a dedicated dismiss-control column.'
);
$check(
	1 === preg_match(
		'/\.npcink-ad-page-bar__dismiss\s*\{[^}]*\bwidth:\s*44px;[^}]*\bheight:\s*44px;/s',
		$frontend_css
	),
	'The page-bar dismiss control is smaller than the 44 by 44 CSS pixel touch target.'
);
$check(
	1 === preg_match(
		'/\.npcink-ad-page-bar__dismiss-icon\s*\{[^}]*\bwidth:\s*16px;[^}]*\bheight:\s*16px;/s',
		$frontend_css
	),
	'The page-bar dismiss icon is not visually separated from its 44 by 44 touch target.'
);

$announcement_pattern = WP_Block_Patterns_Registry::get_instance()->get_registered( 'npcink-ad/announcement' );
$check( is_array( $announcement_pattern ), 'The compact announcement pattern was not registered.' );
$announcement_blocks = is_array( $announcement_pattern ) ? parse_blocks( (string) ( $announcement_pattern['content'] ?? '' ) ) : array();
$announcement_group  = $announcement_blocks[0] ?? array();
$announcement_layout = is_array( $announcement_group ) ? ( $announcement_group['attrs']['layout'] ?? array() ) : array();
$check(
	is_array( $announcement_layout )
	&& 'flex' === ( $announcement_layout['type'] ?? '' )
	&& 'wrap' === ( $announcement_layout['flexWrap'] ?? '' )
	&& 'space-between' === ( $announcement_layout['justifyContent'] ?? '' ),
	'The compact announcement pattern does not use a wrapping horizontal layout.'
);
$announcement_inner_blocks = is_array( $announcement_group ) ? ( $announcement_group['innerBlocks'] ?? array() ) : array();
$announcement_inner_names  = array_map(
	static fn ( array $block ): string => (string) ( $block['blockName'] ?? '' ),
	is_array( $announcement_inner_blocks ) ? $announcement_inner_blocks : array()
);
$check( in_array( 'core/paragraph', $announcement_inner_names, true ), 'The compact announcement pattern omitted its short copy.' );
$check( in_array( 'core/buttons', $announcement_inner_names, true ), 'The compact announcement pattern omitted its call-to-action buttons block.' );
$announcement_buttons_index = array_search( 'core/buttons', $announcement_inner_names, true );
$announcement_buttons       = false !== $announcement_buttons_index ? $announcement_inner_blocks[ $announcement_buttons_index ] : array();
$announcement_button_names  = array_map(
	static fn ( array $block ): string => (string) ( $block['blockName'] ?? '' ),
	is_array( $announcement_buttons ) ? ( $announcement_buttons['innerBlocks'] ?? array() ) : array()
);
$check( in_array( 'core/button', $announcement_button_names, true ), 'The compact announcement pattern omitted its editable Core Button CTA.' );

$pattern_keywords = array(
	'npcink-ad/cta-banner'     => array( 'offer', 'campaign', 'call to action' ),
	'npcink-ad/promotion-card' => array( 'affiliate', 'product', 'recommendation' ),
	'npcink-ad/announcement'   => array( 'announcement', 'notice', 'update' ),
);
foreach ( $pattern_keywords as $pattern_name => $expected_keywords ) {
	$pattern = WP_Block_Patterns_Registry::get_instance()->get_registered( $pattern_name );
	$check( is_array( $pattern ), 'A task-oriented Promotion pattern was not registered: ' . $pattern_name );
	$check(
		$expected_keywords === ( $pattern['keywords'] ?? array() ),
		'A Promotion pattern did not expose its bounded task-oriented search keywords: ' . $pattern_name
	);
}

$block_editor_script = wp_scripts()->registered['npcink-ad-block-editor'] ?? null;
$check( $block_editor_script instanceof _WP_Dependency, 'The block editor script was not registered.' );
$check(
	$block_editor_script instanceof _WP_Dependency && array() === array_intersect( array( 'wp-edit-post', 'wp-editor', 'wp-plugins' ), $block_editor_script->deps ),
	'The block editor script leaked Promotion-only dependencies.'
);
$check( 'npcink-ad' === $block_editor_script->textdomain, 'The block editor script does not use the npcink-ad text domain.' );
$check(
	'' === $block_editor_script->translations_path,
	'The block editor script does not use a bundled language directory.'
);
$page_bar_script = wp_scripts()->registered['npcink-ad-page-bar'] ?? null;
$check( $page_bar_script instanceof _WP_Dependency, 'The page-bar dismissal script was not registered.' );
$check(
	$page_bar_script instanceof _WP_Dependency && array() === $page_bar_script->deps,
	'The page-bar dismissal script unexpectedly depends on the editor or another frontend runtime.'
);
$page_bar_script_source = (string) file_get_contents( WP_PLUGIN_DIR . '/npcink-ad/build/page-bar.js' );
$check( ! str_contains( $page_bar_script_source, 'localStorage' ), 'The page-bar script persisted dismissal in localStorage.' );
$check( ! str_contains( $page_bar_script_source, 'sessionStorage' ), 'The page-bar script persisted dismissal in sessionStorage.' );
$check( ! str_contains( $page_bar_script_source, 'cookie' ), 'The page-bar script wrote or read visitor Cookies.' );
$check( ! str_contains( $page_bar_script_source, 'sendBeacon' ), 'The page-bar script restored visitor-event beacon tracking.' );
$check( ! str_contains( $page_bar_script_source, 'fetch(' ), 'The page-bar script restored a frontend request path.' );
$check( ! str_contains( $page_bar_script_source, 'XMLHttpRequest' ), 'The page-bar script restored an XMLHttpRequest path.' );

Editor::register_assets();
$promotion_editor_script = wp_scripts()->registered['npcink-ad-promotion-editor'] ?? null;
$check( $promotion_editor_script instanceof _WP_Dependency, 'The Promotion editor script was not registered.' );
$check(
	$promotion_editor_script instanceof _WP_Dependency && in_array( 'wp-edit-post', $promotion_editor_script->deps, true ),
	'The Promotion editor script does not declare its WordPress 6.5 edit-post compatibility dependency.'
);
$check( 'npcink-ad' === $promotion_editor_script->textdomain, 'The Promotion editor script does not use the npcink-ad text domain.' );
$check(
	'' === $promotion_editor_script->translations_path,
	'The Promotion editor script does not use a bundled language directory.'
);

$wordpress_org_language_dir  = WP_LANG_DIR . '/plugins';
$wordpress_org_language_pack = $wordpress_org_language_dir . '/npcink-ad-zh_CN.mo';
$check( wp_mkdir_p( $wordpress_org_language_dir ), 'The WordPress.org language-pack directory could not be created.' );
$check( file_exists( $wordpress_org_language_pack ), 'The Simplified Chinese MO catalog was not staged as a WordPress.org language pack.' );

/** @var WP_Textdomain_Registry $wp_textdomain_registry */
global $wp_textdomain_registry;
// Real language packs exist before bootstrap; this late-staged smoke fixture must refresh WordPress 6.5's cached path.
$wp_textdomain_registry->set( 'npcink-ad', 'zh_CN', $wordpress_org_language_dir );

$force_simplified_chinese = static fn ( mixed $locale ): string => 'zh_CN';
add_filter( 'pre_determine_locale', $force_simplified_chinese, PHP_INT_MAX );
unload_textdomain( 'npcink-ad', true );
$check( '推广' === __( 'Promotions', 'npcink-ad' ), 'The WordPress.org PHP language pack did not resolve.' );
$check( '复制为草稿' === __( 'Duplicate as draft', 'npcink-ad' ), 'The duplicate action Simplified Chinese translation did not resolve.' );
$check( 'Y-m-d' === __( 'Y/m/d', 'npcink-ad' ), 'The Promotion-list date format did not match the native Simplified Chinese Date column.' );
$check( 'ag:i' === __( 'g:i a', 'npcink-ad' ), 'The Promotion-list time format did not match the native Simplified Chinese Date column.' );
$check( '%1$s %2$s' === __( '%1$s at %2$s', 'npcink-ad' ), 'The Promotion-list datetime separator did not match the native Simplified Chinese Date column.' );
$check(
	'Npcink Ad 推广' === _x( 'Npcink Ad Promotion', 'block title', 'npcink-ad' ),
	'The translated block metadata title did not resolve.'
);
$block_script_catalog_path = $wordpress_org_language_dir . '/npcink-ad-zh_CN-f082745a2ca724d8e3a123193be09fbe.json';
$block_script_catalog      = json_decode( (string) file_get_contents( $block_script_catalog_path ), true );
$check(
	is_array( $block_script_catalog ) &&
	isset( $block_script_catalog['locale_data']['messages']['Npcink Ad promotion'][0] ) &&
	'Npcink Ad 推广' === $block_script_catalog['locale_data']['messages']['Npcink Ad promotion'][0],
	'The block editor Simplified Chinese JSON catalog has an invalid translation.'
);
$block_script_translations = wp_scripts()->print_translations( 'npcink-ad-block-editor', false );
$check(
	is_string( $block_script_translations ) && str_contains( $block_script_translations, 'Npcink Ad promotion' ),
	'The block editor Simplified Chinese JSON catalog did not resolve.'
);

$promotion_script_catalog_path = $wordpress_org_language_dir . '/npcink-ad-zh_CN-74e2c4c8df75775a490c1e153e6a09cd.json';
$promotion_script_catalog      = json_decode( (string) file_get_contents( $promotion_script_catalog_path ), true );
$check(
	is_array( $promotion_script_catalog ) &&
	isset( $promotion_script_catalog['locale_data']['messages']['Npcink Ad delivery'][0] ) &&
	'Npcink Ad 投放' === $promotion_script_catalog['locale_data']['messages']['Npcink Ad delivery'][0],
	'The Promotion editor Simplified Chinese JSON catalog has an invalid translation.'
);
$promotion_script_translations = wp_scripts()->print_translations( 'npcink-ad-promotion-editor', false );
$check(
	is_string( $promotion_script_translations ) && str_contains( $promotion_script_translations, 'Npcink Ad delivery' ),
	'The Promotion editor Simplified Chinese JSON catalog did not resolve.'
);
remove_filter( 'pre_determine_locale', $force_simplified_chinese, PHP_INT_MAX );
unload_textdomain( 'npcink-ad', true );
wp_delete_file( $wordpress_org_language_pack );

do_action( 'wp_enqueue_scripts' );
$check(
	wp_style_is( 'npcink-ad-frontend', 'enqueued' ),
	'The frontend device stylesheet was not enqueued before content rendering.'
);
$check( ! wp_script_is( 'npcink-ad-page-bar', 'enqueued' ), 'The page-bar script loaded before a page bar rendered.' );
$check( 0 < has_action( 'wp_body_open' ), 'Top page-bar delivery did not register on wp_body_open.' );
$check( 0 < has_action( 'wp_footer' ), 'Bottom page-bar delivery did not register on wp_footer.' );

wp_set_current_user( 1 );

Preview_Page::register();
global $_registered_pages;
$check(
	isset( $_registered_pages['admin_page_npcink-ad-preview'] ),
	'The hidden preview route was not registered as an accessible admin page.'
);
$check(
	str_contains( Preview_Page::url(), '/wp-admin/admin.php?page=npcink-ad-preview' ),
	'The editor preview URL does not target the registered hidden admin page.'
);

$page_a_id = wp_insert_post(
	array(
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_title'   => 'Promotion target A',
		'post_content' => '<p>Page A body</p>',
	),
	true
);
$check( ! is_wp_error( $page_a_id ), 'Could not create target page A.' );

$page_b_id = wp_insert_post(
	array(
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_title'   => 'Promotion target B',
		'post_content' => '<p>Page B body</p>',
	),
	true
);
$check( ! is_wp_error( $page_b_id ), 'Could not create target page B.' );

$post_taxonomies = get_object_taxonomies( 'post' );
$check( in_array( 'category', $post_taxonomies, true ), 'Standard posts did not expose the Core category taxonomy.' );
$check( in_array( 'post_tag', $post_taxonomies, true ), 'Standard posts did not expose the Core post-tag taxonomy.' );
$check( array() === get_object_taxonomies( 'page' ), 'Standard pages unexpectedly exposed category or tag taxonomies.' );

$category_result = wp_insert_term(
	'Promotion scope category',
	'category',
	array( 'slug' => 'npcink-ad-scope-category' )
);
$check( ! is_wp_error( $category_result ), 'Could not create the editorial-scope category.' );
$category_id = absint( is_array( $category_result ) ? ( $category_result['term_id'] ?? 0 ) : 0 );
$check( 0 < $category_id, 'The editorial-scope category did not return a term ID.' );

$child_category_result = wp_insert_term(
	'Promotion scope child category',
	'category',
	array(
		'slug'   => 'npcink-ad-scope-child-category',
		'parent' => $category_id,
	)
);
$check( ! is_wp_error( $child_category_result ), 'Could not create the editorial-scope child category.' );
$child_category_id = absint( is_array( $child_category_result ) ? ( $child_category_result['term_id'] ?? 0 ) : 0 );
$check( 0 < $child_category_id, 'The editorial-scope child category did not return a term ID.' );

$tag_result = wp_insert_term(
	'Promotion scope tag',
	'post_tag',
	array( 'slug' => 'npcink-ad-scope-tag' )
);
$check( ! is_wp_error( $tag_result ), 'Could not create the editorial-scope tag.' );
$tag_id = absint( is_array( $tag_result ) ? ( $tag_result['term_id'] ?? 0 ) : 0 );
$check( 0 < $tag_id, 'The editorial-scope tag did not return a term ID.' );

$category_post_id = wp_insert_post(
	array(
		'post_type'     => 'post',
		'post_status'   => 'publish',
		'post_title'    => 'Category scope target',
		'post_content'  => '<p>Category target body</p>',
		'post_category' => array( $category_id ),
	),
	true
);
$check( ! is_wp_error( $category_post_id ), 'Could not create the category target post.' );
$category_post_id = absint( $category_post_id );

$child_category_post_id = wp_insert_post(
	array(
		'post_type'     => 'post',
		'post_status'   => 'publish',
		'post_title'    => 'Child category scope target',
		'post_content'  => '<p>Child category target body</p>',
		'post_category' => array( $child_category_id ),
	),
	true
);
$check( ! is_wp_error( $child_category_post_id ), 'Could not create the child-category target post.' );
$child_category_post_id = absint( $child_category_post_id );

$tag_post_id = wp_insert_post(
	array(
		'post_type'    => 'post',
		'post_status'  => 'publish',
		'post_title'   => 'Tag scope target',
		'post_content' => '<p>Tag target body</p>',
	),
	true
);
$check( ! is_wp_error( $tag_post_id ), 'Could not create the tag target post.' );
$tag_post_id = absint( $tag_post_id );
$tag_assignment = wp_set_post_terms( $tag_post_id, array( $tag_id ), 'post_tag', false );
$check( ! is_wp_error( $tag_assignment ), 'Could not assign the editorial-scope tag.' );

$plain_post_id = wp_insert_post(
	array(
		'post_type'    => 'post',
		'post_status'  => 'publish',
		'post_title'   => 'Plain scope target',
		'post_content' => '<p>Plain target body</p>',
	),
	true
);
$check( ! is_wp_error( $plain_post_id ), 'Could not create the plain target post.' );
$plain_post_id = absint( $plain_post_id );

$check(
	in_array( $category_id, wp_get_post_categories( $category_post_id ), true ),
	'The category target did not retain its direct category relationship.'
);
$check(
	array( $tag_id ) === wp_get_post_terms( $tag_post_id, 'post_tag', array( 'fields' => 'ids' ) ),
	'The tag target did not retain its direct tag relationship.'
);
$check( array() === wp_get_post_terms( $plain_post_id, 'post_tag', array( 'fields' => 'ids' ) ), 'A plain post unexpectedly received a tag.' );
$check( array() !== wp_get_post_categories( $plain_post_id ), 'A published standard post did not receive a default category.' );

$original_timezone_string = get_option( 'timezone_string' );
$original_gmt_offset      = get_option( 'gmt_offset' );
try {
	update_option( 'timezone_string', 'Asia/Shanghai' );
	update_option( 'gmt_offset', 8 );

	$repository         = new Repository();
	$shanghai_timestamp = $repository->datetime_to_timestamp( '2026-01-15 08:00:00' );
	$check(
		gmmktime( 0, 0, 0, 1, 15, 2026 ) === $shanghai_timestamp,
		'The repository did not parse a WordPress-local datetime in the configured site timezone.'
	);

	$boundary_promotion = array(
		'status'      => 'publish',
		'content'     => '<p>Schedule boundary creative</p>',
		'content_scope' => 'all',
		'include_ids' => array(),
		'exclude_ids' => array(),
		'start_at'    => $shanghai_timestamp,
		'end_at'      => $shanghai_timestamp + HOUR_IN_SECONDS,
	);
	$evaluator          = new Eligibility_Evaluator();
	$at_start           = $evaluator->assess_readiness( $boundary_promotion, $boundary_promotion['start_at'] );
	$before_end         = $evaluator->assess_readiness( $boundary_promotion, $boundary_promotion['end_at'] - 1 );
	$at_end             = $evaluator->assess_readiness( $boundary_promotion, $boundary_promotion['end_at'] );
	$check( $at_start['ready'], 'The shared evaluator did not treat the start boundary as inclusive.' );
	$check( $before_end['ready'], 'The shared evaluator rejected the final second before the end boundary.' );
	$check( ! $at_end['ready'], 'The shared evaluator did not treat the end boundary as exclusive.' );
	$check(
		in_array( 'promotion_expired', $at_end['reasons'], true ),
		'The shared evaluator omitted the expired reason at the end boundary.'
	);
} finally {
	update_option( 'timezone_string', $original_timezone_string );
	update_option( 'gmt_offset', $original_gmt_offset );
}
$check( get_option( 'timezone_string' ) === $original_timezone_string, 'The smoke did not restore the site timezone string.' );
$check( get_option( 'gmt_offset' ) === $original_gmt_offset, 'The smoke did not restore the site GMT offset.' );

$promotion_id = wp_insert_post(
	array(
		'post_type'    => 'npcink_promotion',
		'post_status'  => 'publish',
		'post_title'   => 'Playground smoke promotion',
		'post_content' => '<!-- wp:paragraph --><p>Playground smoke creative</p><!-- /wp:paragraph -->',
	),
	true
);
$check( ! is_wp_error( $promotion_id ), 'Could not create the smoke promotion.' );
$subscriber_id = wp_insert_user(
	array(
		'user_login' => 'npcink_ad_smoke_subscriber',
		'user_pass'  => wp_generate_password( 24, true, true ),
		'user_email' => 'npcink-ad-smoke@example.test',
		'role'       => 'subscriber',
	)
);
$check( ! is_wp_error( $subscriber_id ), 'Could not create the low-privilege smoke user.' );
$subscriber_id = absint( $subscriber_id );
$check(
	'content_after' === get_post_meta( $promotion_id, Post_Types::LOCATION_META, true ),
	'A new promotion did not default to after-content delivery.'
);
$default_location_ids = ( new Repository() )->find_published_automatic_catalog()['location_ids']['content_after'];
$check(
	in_array( $promotion_id, $default_location_ids, true ),
	'A promotion using the registered default location was omitted from automatic delivery.'
);

update_post_meta( $promotion_id, Post_Types::LOCATION_META, 'block' );
update_post_meta( $promotion_id, Post_Types::CONTENT_SCOPE_META, 'selected' );
update_post_meta( $promotion_id, Post_Types::INCLUDE_IDS_META, array( $page_a_id ) );
update_post_meta( $promotion_id, Post_Types::EXCLUDE_IDS_META, array() );
update_post_meta( $promotion_id, Post_Types::DEVICE_META, 'mobile' );
update_post_meta(
	$promotion_id,
	Post_Types::START_AT_META,
	current_datetime()->modify( '-1 hour' )->format( 'Y-m-d H:i:s' )
);
update_post_meta(
	$promotion_id,
	Post_Types::END_AT_META,
	current_datetime()->modify( '+1 hour' )->format( 'Y-m-d H:i:s' )
);

$candidate_ids = array_merge( array( $page_a_id, $page_a_id, 0, -1, '2.5' ), range( 1000, 1060 ) );
update_post_meta( $promotion_id, Post_Types::INCLUDE_IDS_META, $candidate_ids );
$sanitized_ids = get_post_meta( $promotion_id, Post_Types::INCLUDE_IDS_META, true );
$check( is_array( $sanitized_ids ), 'The include IDs value was not stored as an array.' );
$check( 50 === count( $sanitized_ids ), 'The include IDs value was not bounded to 50 unique IDs.' );
$check( 50 === count( array_unique( $sanitized_ids ) ), 'The include IDs value was not deduplicated.' );
$check( $page_a_id === $sanitized_ids[0], 'The first valid include ID was not preserved.' );
$check( ! in_array( -1, $sanitized_ids, true ), 'A negative include ID was accepted.' );
$check( ! in_array( 0, $sanitized_ids, true ), 'A zero include ID was accepted.' );
update_post_meta( $promotion_id, Post_Types::INCLUDE_IDS_META, array( $page_a_id ) );
update_post_meta( $promotion_id, Post_Types::CATEGORY_IDS_META, array( $category_id ) );
update_post_meta( $promotion_id, Post_Types::TAG_IDS_META, array( $tag_id ) );
update_post_meta( $promotion_id, Post_Types::PARAGRAPH_NUMBER_META, 7 );

$revision_original_content = '<!-- wp:paragraph --><p>Revision baseline creative</p><!-- /wp:paragraph -->';
$revision_original_meta    = array(
	Post_Types::LOCATION_META         => 'block',
	Post_Types::CONTENT_SCOPE_META    => 'selected',
	Post_Types::INCLUDE_IDS_META      => array( $page_a_id ),
	Post_Types::EXCLUDE_IDS_META      => array( $page_b_id ),
	Post_Types::CATEGORY_IDS_META     => array( $category_id ),
	Post_Types::TAG_IDS_META          => array( $tag_id ),
	Post_Types::DEVICE_META           => 'mobile',
	Post_Types::START_AT_META         => '2026-08-01 09:00:00',
	Post_Types::END_AT_META           => '2026-08-02 09:00:00',
	Post_Types::PARAGRAPH_NUMBER_META => 5,
);
$revision_promotion_id    = wp_insert_post(
	array(
		'post_type'    => Post_Types::PROMOTION_POST_TYPE,
		'post_status'  => 'draft',
		'post_title'   => 'Revision baseline title',
		'post_content' => $revision_original_content,
	),
	true
);
$check( ! is_wp_error( $revision_promotion_id ), 'Could not create the native revision fixture.' );
$revision_promotion_id = absint( $revision_promotion_id );
foreach ( $revision_original_meta as $meta_key => $meta_value ) {
	update_post_meta( $revision_promotion_id, $meta_key, $meta_value );
}
$revision_id = wp_save_post_revision( $revision_promotion_id );
$check( is_int( $revision_id ) && 0 < $revision_id, 'WordPress did not save the Promotion baseline revision.' );

$revision_update = wp_update_post(
	array(
		'ID'           => $revision_promotion_id,
		'post_title'   => 'Revision changed title',
		'post_content' => '<!-- wp:paragraph --><p>Revision changed creative</p><!-- /wp:paragraph -->',
	),
	true
);
$check( ! is_wp_error( $revision_update ), 'Could not change the Promotion revision fixture.' );
$revision_changed_meta = array(
	Post_Types::LOCATION_META         => 'content_before',
	Post_Types::CONTENT_SCOPE_META    => 'all',
	Post_Types::INCLUDE_IDS_META      => array(),
	Post_Types::EXCLUDE_IDS_META      => array(),
	Post_Types::CATEGORY_IDS_META     => array(),
	Post_Types::TAG_IDS_META          => array(),
	Post_Types::DEVICE_META           => 'desktop',
	Post_Types::START_AT_META         => '',
	Post_Types::END_AT_META           => '',
	Post_Types::PARAGRAPH_NUMBER_META => 11,
);
foreach ( $revision_changed_meta as $meta_key => $meta_value ) {
	update_post_meta( $revision_promotion_id, $meta_key, $meta_value );
}

$restored_revision_post_id = wp_restore_post_revision( $revision_id );
$check(
	$revision_promotion_id === $restored_revision_post_id,
	'WordPress did not restore the selected Promotion revision.'
);
$restored_revision_post = get_post( $revision_promotion_id );
$check( $restored_revision_post instanceof WP_Post, 'The restored Promotion revision could not be reloaded.' );
$check(
	$restored_revision_post instanceof WP_Post && 'Revision baseline title' === $restored_revision_post->post_title,
	'The native revision restore did not restore the Promotion title.'
);
$check(
	$restored_revision_post instanceof WP_Post && $revision_original_content === $restored_revision_post->post_content,
	'The native revision restore did not restore the Promotion block content.'
);
foreach ( $revision_original_meta as $meta_key => $meta_value ) {
	$restored_meta_value = get_post_meta( $revision_promotion_id, $meta_key, true );
	if ( Post_Types::PARAGRAPH_NUMBER_META === $meta_key ) {
		$restored_meta_value = (int) $restored_meta_value;
	}
	$check(
		$meta_value === $restored_meta_value,
		'The native revision restore did not restore typed Promotion metadata: ' . $meta_key
	);
}
$check( false !== wp_delete_post( $revision_promotion_id, true ), 'The native revision fixture could not be removed.' );

$source_meta_before_copy = array(
	Post_Types::LOCATION_META         => get_post_meta( $promotion_id, Post_Types::LOCATION_META, true ),
	Post_Types::CONTENT_SCOPE_META    => get_post_meta( $promotion_id, Post_Types::CONTENT_SCOPE_META, true ),
	Post_Types::INCLUDE_IDS_META      => get_post_meta( $promotion_id, Post_Types::INCLUDE_IDS_META, true ),
	Post_Types::EXCLUDE_IDS_META      => get_post_meta( $promotion_id, Post_Types::EXCLUDE_IDS_META, true ),
	Post_Types::CATEGORY_IDS_META     => get_post_meta( $promotion_id, Post_Types::CATEGORY_IDS_META, true ),
	Post_Types::TAG_IDS_META          => get_post_meta( $promotion_id, Post_Types::TAG_IDS_META, true ),
	Post_Types::DEVICE_META           => get_post_meta( $promotion_id, Post_Types::DEVICE_META, true ),
	Post_Types::PARAGRAPH_NUMBER_META => get_post_meta( $promotion_id, Post_Types::PARAGRAPH_NUMBER_META, true ),
);
update_post_meta( $promotion_id, '_npcink_ad_unknown_copy_fixture', 'must not copy' );
$duplicate_action = new Promotion_Duplicate_Action();
$original_request_method = $_SERVER['REQUEST_METHOD'] ?? null;
$original_post_request    = $_POST;
$original_request         = $_REQUEST;
try {
	$_SERVER['REQUEST_METHOD'] = 'GET';
	$_POST                     = array();
	$_REQUEST                  = array();
	$check(
		405 === $expect_wp_die( array( $duplicate_action, 'handle' ) ),
		'The Promotion duplicate handler accepted a non-POST request.'
	);

	$_SERVER['REQUEST_METHOD'] = 'POST';
	$_POST                     = array( 'promotion_id' => 0 );
	$_REQUEST                  = $_POST;
	$check(
		400 === $expect_wp_die( array( $duplicate_action, 'handle' ) ),
		'The Promotion duplicate handler accepted an invalid source ID.'
	);

	$_POST = array(
		'promotion_id' => $promotion_id,
		'_wpnonce'     => wp_create_nonce( Promotion_Duplicate_Action::nonce_action( $promotion_id + 1 ) ),
	);
	$_REQUEST = $_POST;
	$check(
		403 === $expect_wp_die( array( $duplicate_action, 'handle' ) ),
		'The Promotion duplicate handler accepted a nonce bound to another source.'
	);

	$_POST = array(
		'promotion_id' => $page_a_id,
		'_wpnonce'     => wp_create_nonce( Promotion_Duplicate_Action::nonce_action( $page_a_id ) ),
	);
	$_REQUEST = $_POST;
	$check(
		404 === $expect_wp_die( array( $duplicate_action, 'handle' ) ),
		'The Promotion duplicate handler accepted a non-Promotion source.'
	);

	wp_set_current_user( $subscriber_id );
	$_POST = array(
		'promotion_id' => $promotion_id,
		'_wpnonce'     => wp_create_nonce( Promotion_Duplicate_Action::nonce_action( $promotion_id ) ),
	);
	$_REQUEST = $_POST;
	$check(
		403 === $expect_wp_die( array( $duplicate_action, 'handle' ) ),
		'The Promotion duplicate handler accepted a low-privilege user.'
	);
} finally {
	wp_set_current_user( 1 );
	if ( null === $original_request_method ) {
		unset( $_SERVER['REQUEST_METHOD'] );
	} else {
		$_SERVER['REQUEST_METHOD'] = $original_request_method;
	}
	$_POST    = $original_post_request;
	$_REQUEST = $original_request;
}
$duplicate_method = new ReflectionMethod( $duplicate_action, 'duplicate_promotion' );
$duplicate_result = $duplicate_method->invoke( $duplicate_action, get_post( $promotion_id ) );
$check( ! is_wp_error( $duplicate_result ), 'The safe Promotion copy failed in a real WordPress environment.' );
$duplicate_id   = absint( $duplicate_result );
$duplicate_post = get_post( $duplicate_id );
$check( $duplicate_post instanceof WP_Post, 'The safe Promotion copy did not create a native post.' );
$check( 'npcink_promotion' === $duplicate_post->post_type, 'The safe Promotion copy used the wrong post type.' );
$check( 'draft' === $duplicate_post->post_status, 'The safe Promotion copy was not a draft.' );
$check( 1 === (int) $duplicate_post->post_author, 'The safe Promotion copy was not assigned to the current user.' );
$check( 'Playground smoke promotion — Copy' === $duplicate_post->post_title, 'The safe Promotion copy title is incorrect.' );
$check(
	'<!-- wp:paragraph --><p>Playground smoke creative</p><!-- /wp:paragraph -->' === $duplicate_post->post_content,
	'The safe Promotion copy changed the native block content.'
);
foreach ( $source_meta_before_copy as $meta_key => $meta_value ) {
	$check(
		get_post_meta( $duplicate_id, $meta_key, true ) === $meta_value,
		'The safe Promotion copy changed an allowlisted metadata value: ' . $meta_key
	);
}
$check( '' === get_post_meta( $duplicate_id, Post_Types::START_AT_META, true ), 'The safe Promotion copy retained its start time.' );
$check( '' === get_post_meta( $duplicate_id, Post_Types::END_AT_META, true ), 'The safe Promotion copy retained its end time.' );
$check( ! metadata_exists( 'post', $duplicate_id, '_npcink_ad_unknown_copy_fixture' ), 'The safe Promotion copy included unknown metadata.' );
$check( 'publish' === get_post_status( $promotion_id ), 'The safe Promotion copy changed the source status.' );
$check( 'must not copy' === get_post_meta( $promotion_id, '_npcink_ad_unknown_copy_fixture', true ), 'The safe Promotion copy changed the source metadata.' );
$check( false !== wp_delete_post( $duplicate_id, true ), 'The successful copy fixture could not be removed.' );

$promotion_count_before_failed_copy = array_sum( array_map( 'intval', get_object_vars( wp_count_posts( 'npcink_promotion' ) ) ) );
$fail_duplicate_meta = static function ( mixed $check_value, int $object_id, string $meta_key ): mixed {
	unset( $object_id );

	return Post_Types::DEVICE_META === $meta_key ? false : $check_value;
};
add_filter( 'update_post_metadata', $fail_duplicate_meta, 10, 3 );
try {
	$failed_duplicate_result = $duplicate_method->invoke( $duplicate_action, get_post( $promotion_id ) );
} finally {
	remove_filter( 'update_post_metadata', $fail_duplicate_meta, 10 );
}
$check( is_wp_error( $failed_duplicate_result ), 'A failed metadata copy did not return WP_Error.' );
$check(
	'npcink_ad_duplicate_meta_failed' === $failed_duplicate_result->get_error_code(),
	'A failed metadata copy returned the wrong error code.'
);
$promotion_count_after_failed_copy = array_sum( array_map( 'intval', get_object_vars( wp_count_posts( 'npcink_promotion' ) ) ) );
$check(
	$promotion_count_before_failed_copy === $promotion_count_after_failed_copy,
	'A failed metadata copy left an incomplete Promotion record.'
);

$set_main_singular = static function ( int $post_id ): void {
	global $post, $wp_query, $wp_the_query;

	// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- The smoke intentionally creates a main singular request context.
	$post = get_post( $post_id );
	// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- The smoke intentionally creates a main singular request context.
	$wp_query                  = new WP_Query();
	$wp_query->post            = $post;
	$wp_query->posts           = array( $post );
	$wp_query->post_count      = 1;
	$wp_query->queried_object  = $post;
	$wp_query->queried_object_id = $post_id;
	$wp_query->is_singular     = true;
	$wp_query->is_page         = 'page' === $post->post_type;
	$wp_query->is_single       = 'post' === $post->post_type;
	$wp_query->in_the_loop     = true;
	// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Required for is_main_query() in the integration fixture.
	$wp_the_query = $wp_query;
	setup_postdata( $post );
};

$set_main_singular( $page_a_id );
wp_set_current_user( 0 );

$shortcode_output = do_shortcode( '[npcink_ad promotion="' . $promotion_id . '"]' );
$check( str_contains( $shortcode_output, 'Playground smoke creative' ), 'The shortcode did not server-render the promotion.' );
$check( str_contains( $shortcode_output, 'data-npcink-ad-promotion="' . $promotion_id . '"' ), 'The shortcode does not reference the promotion directly.' );
$check( str_contains( $shortcode_output, 'npcink-ad-device-mobile' ), 'The shortcode omitted the cache-stable mobile CSS class.' );

$block_output = do_blocks( '<!-- wp:npcink-ad/promotion {"promotionId":' . $promotion_id . '} /-->' );
$check( str_contains( $block_output, 'Playground smoke creative' ), 'The dynamic block did not server-render the promotion.' );
$check( str_contains( $block_output, 'wp-block-npcink-ad-promotion' ), 'The dynamic block wrapper is missing.' );

$delivery = new Delivery( new Repository(), new Eligibility_Evaluator(), new Renderer() );
$normal_mobile_target = $delivery->render_promotion( $promotion_id, 'block', 0, null, $page_a_id );
$preview_mobile       = $delivery->render_promotion( $promotion_id, 'block', 0, 'mobile', $page_a_id );
$preview_desktop      = $delivery->render_promotion( $promotion_id, 'block', 0, 'desktop', $page_a_id );
$wrong_page           = $delivery->render_promotion( $promotion_id, 'block', 0, 'mobile', $page_b_id );
$check( str_contains( $normal_mobile_target, 'Playground smoke creative' ), 'Normal delivery incorrectly server-filtered a mobile promotion.' );
$check( str_contains( $preview_mobile, 'Playground smoke creative' ), 'Matching mobile preview did not render.' );
$check( '' === $preview_desktop, 'Desktop preview rendered a mobile-only promotion.' );
$check( '' === $wrong_page, 'A selected-content Promotion rendered on a non-included page.' );

wp_set_current_user( 1 );
update_post_meta( $promotion_id, Post_Types::LOCATION_META, 'content_before' );
wp_set_current_user( 0 );
$set_main_singular( $page_a_id );
$before_delivery = new Delivery( new Repository(), new Eligibility_Evaluator(), new Renderer() );
$before_output   = $before_delivery->filter_content( '<p>Page body</p>' );
$check( str_contains( $before_output, 'Playground smoke creative' ), 'Automatic before-content delivery did not render.' );
$check(
	strpos( $before_output, 'Playground smoke creative' ) < strpos( $before_output, 'Page body' ),
	'Automatic before-content delivery rendered in the wrong order.'
);
$before_repeated = $before_delivery->filter_content( $before_output );
$check(
	1 === substr_count( $before_repeated, 'data-npcink-ad-promotion="' . $promotion_id . '"' ),
	'Repeated the_content filtering duplicated an automatic promotion.'
);
$set_main_singular( $page_b_id );
$check( ! str_contains( $before_delivery->filter_content( '<p>Page body</p>' ), 'Playground smoke creative' ), 'Automatic delivery ignored selected-content scope.' );

wp_set_current_user( 1 );
update_post_meta( $promotion_id, Post_Types::LOCATION_META, 'content_after' );
update_post_meta( $promotion_id, Post_Types::CONTENT_SCOPE_META, 'all' );
update_post_meta( $promotion_id, Post_Types::EXCLUDE_IDS_META, array( $page_b_id ) );
wp_set_current_user( 0 );
$set_main_singular( $page_a_id );
$after_delivery = new Delivery( new Repository(), new Eligibility_Evaluator(), new Renderer() );
$after_output   = $after_delivery->filter_content( '<p>Page body</p>' );
$check( str_contains( $after_output, 'Playground smoke creative' ), 'Automatic after-content delivery did not render.' );
$check(
	strpos( $after_output, 'Playground smoke creative' ) > strpos( $after_output, 'Page body' ),
	'Automatic after-content delivery rendered in the wrong order.'
);
$set_main_singular( $page_b_id );
$check( ! str_contains( $after_delivery->filter_content( '<p>Page body</p>' ), 'Playground smoke creative' ), 'Automatic delivery ignored page exclusion.' );

wp_set_current_user( 1 );
update_post_meta( $promotion_id, Post_Types::LOCATION_META, 'bar_top' );
update_post_meta( $promotion_id, Post_Types::CONTENT_SCOPE_META, 'all' );
update_post_meta( $promotion_id, Post_Types::EXCLUDE_IDS_META, array() );
wp_set_current_user( 0 );
$set_main_singular( $page_a_id );
$top_bar_delivery = new Delivery( new Repository(), new Eligibility_Evaluator(), new Renderer() );
ob_start();
$top_bar_delivery->render_top_page_bar();
$top_bar_output = (string) ob_get_clean();
ob_start();
$top_bar_delivery->render_bottom_page_bar();
$wrong_bar_output = (string) ob_get_clean();
$check( str_contains( $top_bar_output, 'Playground smoke creative' ), 'Top page-bar delivery did not render.' );
$check( str_contains( $top_bar_output, 'npcink-ad-page-bar--top' ), 'Top page-bar output omitted its bounded presentation class.' );
$check( str_contains( $top_bar_output, 'data-npcink-ad-dismiss' ), 'Top page-bar output omitted its accessible dismissal control.' );
$check( '' === $wrong_bar_output, 'A top page-bar Promotion also rendered at the bottom hook.' );
$check( wp_script_is( 'npcink-ad-page-bar', 'enqueued' ), 'Rendering a page bar did not enqueue its small dismissal script.' );

wp_set_current_user( 1 );
update_post_meta( $promotion_id, Post_Types::LOCATION_META, 'bar_bottom' );
wp_set_current_user( 0 );
$bottom_bar_delivery = new Delivery( new Repository(), new Eligibility_Evaluator(), new Renderer() );
ob_start();
$bottom_bar_delivery->render_bottom_page_bar();
$bottom_bar_output = (string) ob_get_clean();
$check( str_contains( $bottom_bar_output, 'Playground smoke creative' ), 'Bottom page-bar delivery did not render.' );
$check( str_contains( $bottom_bar_output, 'npcink-ad-page-bar--bottom' ), 'Bottom page-bar output omitted its bounded presentation class.' );

wp_set_current_user( 1 );
update_post_meta( $promotion_id, Post_Types::LOCATION_META, 'content_after_paragraph' );
update_post_meta( $promotion_id, Post_Types::PARAGRAPH_NUMBER_META, 2 );
update_post_meta( $promotion_id, Post_Types::CONTENT_SCOPE_META, 'all' );
update_post_meta( $promotion_id, Post_Types::EXCLUDE_IDS_META, array() );
$second_paragraph_promotion_id = wp_insert_post(
	array(
		'post_type'    => Post_Types::PROMOTION_POST_TYPE,
		'post_status'  => 'publish',
		'post_title'   => 'Second paragraph smoke promotion',
		'post_content' => '<!-- wp:paragraph --><p>Second paragraph smoke creative</p><!-- /wp:paragraph -->',
	),
	true
);
$check( ! is_wp_error( $second_paragraph_promotion_id ), 'Could not create the second paragraph Promotion.' );
$second_paragraph_promotion_id = absint( $second_paragraph_promotion_id );
update_post_meta( $second_paragraph_promotion_id, Post_Types::LOCATION_META, 'content_after_paragraph' );
update_post_meta( $second_paragraph_promotion_id, Post_Types::PARAGRAPH_NUMBER_META, 2 );
wp_set_current_user( 0 );
$set_main_singular( $page_a_id );

$block_paragraph_source = implode(
	'',
	array(
		'<!-- wp:paragraph --><p>Top paragraph one</p><!-- /wp:paragraph -->',
		'<!-- wp:group --><div class="wp-block-group"><!-- wp:paragraph --><p>Nested paragraph</p><!-- /wp:paragraph --></div><!-- /wp:group -->',
		'<!-- wp:paragraph --><p>Top paragraph two</p><!-- /wp:paragraph -->',
		'<!-- wp:paragraph --><p>Top paragraph three</p><!-- /wp:paragraph -->',
	)
);
$paragraph_delivery     = new Delivery( new Repository(), new Eligibility_Evaluator(), new Renderer() );
$prepared_blocks        = $paragraph_delivery->prepare_content( $block_paragraph_source );
$paragraph_output       = $paragraph_delivery->filter_content( do_blocks( $prepared_blocks ) );
$first_paragraph_ad     = strpos( $paragraph_output, 'data-npcink-ad-promotion="' . $promotion_id . '"' );
$second_paragraph_ad    = strpos( $paragraph_output, 'data-npcink-ad-promotion="' . $second_paragraph_promotion_id . '"' );
$top_paragraph_two      = strpos( $paragraph_output, 'Top paragraph two' );
$top_paragraph_three    = strpos( $paragraph_output, 'Top paragraph three' );
$check(
	is_int( $top_paragraph_two ) && is_int( $first_paragraph_ad ) && $top_paragraph_two < $first_paragraph_ad,
	'Gutenberg paragraph delivery counted a nested paragraph or rendered before the requested top-level anchor.'
);
$check(
	is_int( $top_paragraph_three ) && is_int( $second_paragraph_ad ) && $second_paragraph_ad < $top_paragraph_three,
	'Gutenberg paragraph delivery rendered after the wrong top-level paragraph.'
);
$check( $first_paragraph_ad < $second_paragraph_ad, 'Promotions sharing one paragraph anchor did not preserve repository order.' );
$check( ! str_contains( $paragraph_output, 'npcink-ad-paragraph-anchor-' ), 'An internal paragraph marker leaked into Gutenberg output.' );
$check( ! str_contains( $paragraph_output, 'npcink-ad-block-content-' ), 'The internal block-content sentinel leaked into Gutenberg output.' );

$duplicated_paragraph_marker = false;
$duplicate_marker_filter     = static function ( string $content ) use ( &$duplicated_paragraph_marker ): string {
	$marker_start = strpos( $content, '<!-- npcink-ad-paragraph-anchor-' );
	if ( false === $marker_start ) {
		return $content;
	}

	$marker_end = strpos( $content, ' -->', $marker_start );
	if ( false === $marker_end ) {
		return $content;
	}

	$marker                      = substr( $content, $marker_start, $marker_end + 4 - $marker_start );
	$duplicated_paragraph_marker = true;

	return substr_replace( $content, $marker . $marker, $marker_start, strlen( $marker ) );
};
add_filter( 'the_content', $duplicate_marker_filter, 9 );
try {
	$filtered_block_paragraph_output = apply_filters( 'the_content', $block_paragraph_source );
} finally {
	remove_filter( 'the_content', $duplicate_marker_filter, 9 );
}
$check( $duplicated_paragraph_marker, 'The priority 9 smoke filter did not observe a prepared paragraph marker.' );
$check(
	1 === substr_count( $filtered_block_paragraph_output, 'data-npcink-ad-promotion="' . $promotion_id . '"' ),
	'The real the_content filter chain rendered the first Promotion more than once after a marker was copied.'
);
$check(
	1 === substr_count( $filtered_block_paragraph_output, 'data-npcink-ad-promotion="' . $second_paragraph_promotion_id . '"' ),
	'The real the_content filter chain rendered the second Promotion more than once after a marker was copied.'
);
$check(
	! str_contains( $filtered_block_paragraph_output, 'npcink-ad-paragraph-anchor-' ),
	'A copied internal paragraph marker leaked from the real the_content filter chain.'
);
$check(
	! str_contains( $filtered_block_paragraph_output, 'npcink-ad-block-content-' ),
	'The internal block-content sentinel leaked from the real the_content filter chain.'
);

wp_set_current_user( 1 );
update_post_meta( $promotion_id, Post_Types::PARAGRAPH_NUMBER_META, 4 );
update_post_meta( $second_paragraph_promotion_id, Post_Types::PARAGRAPH_NUMBER_META, 4 );
wp_set_current_user( 0 );
$missing_paragraph_delivery = new Delivery( new Repository(), new Eligibility_Evaluator(), new Renderer() );
$missing_prepared_blocks    = $missing_paragraph_delivery->prepare_content( $block_paragraph_source );
$missing_paragraph_output   = $missing_paragraph_delivery->filter_content( do_blocks( $missing_prepared_blocks ) );
$check(
	! str_contains( $missing_paragraph_output, 'data-npcink-ad-promotion=' ),
	'A missing Gutenberg paragraph anchor silently fell back to another content position.'
);

$classic_token_source = '<!-- comment </p> -->'
	. '<script>window.fake = "</p>";</script>'
	. '<style>.fake::after{content:"</p>"}</style>'
	. '<template><p>Template paragraph</p></template>'
	. '<div data-fake="</p>">Attribute value</div>'
	. '<p data-order="kept">Real one</P >'
	. '<p>Real two</p >';
$classic_token_ad       = '<aside data-token-aware-insertion="true">Inserted</aside>';
$classic_token_expected = str_replace( '</p >', '</p >' . $classic_token_ad, $classic_token_source );
$classic_token_inserter = new Paragraph_Inserter();
$classic_token_output   = $classic_token_inserter->insert_after_classic_paragraphs(
	$classic_token_source,
	array( 2 => $classic_token_ad )
);
$check(
	array( 1, 2 ) === $classic_token_inserter->available_classic_anchors( $classic_token_source, array( 1, 2, 3 ) ),
	'Classic paragraph discovery counted a comment, raw-text, template, or attribute lookalike.'
);
$check(
	$classic_token_expected === $classic_token_output,
	'Classic paragraph insertion did not preserve source HTML bytes around the real closing token.'
);

wp_set_current_user( 1 );
update_post_meta( $promotion_id, Post_Types::PARAGRAPH_NUMBER_META, 2 );
update_post_meta( $second_paragraph_promotion_id, Post_Types::PARAGRAPH_NUMBER_META, 2 );
wp_set_current_user( 0 );
$classic_paragraph_delivery = new Delivery( new Repository(), new Eligibility_Evaluator(), new Renderer() );
$classic_source             = wpautop( "Classic paragraph one\n\nClassic paragraph two\n\nClassic paragraph three" );
$classic_paragraph_output   = $classic_paragraph_delivery->filter_content( $classic_source );
$classic_second             = strpos( $classic_paragraph_output, 'Classic paragraph two' );
$classic_first_ad           = strpos( $classic_paragraph_output, 'data-npcink-ad-promotion="' . $promotion_id . '"' );
$classic_third              = strpos( $classic_paragraph_output, 'Classic paragraph three' );
$check(
	is_int( $classic_second ) && is_int( $classic_first_ad ) && $classic_second < $classic_first_ad,
	'Classic paragraph delivery rendered before the requested rendered paragraph.'
);
$check(
	is_int( $classic_third ) && $classic_first_ad < $classic_third,
	'Classic paragraph delivery rendered after the wrong rendered paragraph.'
);
wp_delete_post( $second_paragraph_promotion_id, true );
$check( null === get_post( $second_paragraph_promotion_id ), 'The second paragraph Promotion fixture was not removed.' );

wp_set_current_user( 1 );
update_post_meta( $promotion_id, Post_Types::LOCATION_META, 'block' );
update_post_meta( $promotion_id, Post_Types::CONTENT_SCOPE_META, 'selected' );
update_post_meta( $promotion_id, Post_Types::INCLUDE_IDS_META, array( $page_a_id ) );
update_post_meta( $promotion_id, Post_Types::EXCLUDE_IDS_META, array() );
update_post_meta( $promotion_id, Post_Types::DEVICE_META, 'mobile' );
update_post_meta( $promotion_id, Post_Types::START_AT_META, '' );
update_post_meta( $promotion_id, Post_Types::END_AT_META, '' );
$set_main_singular( $page_a_id );

$preview_action = 'npcink_ad_preview_' . $promotion_id;

wp_set_current_user( $subscriber_id );
$subscriber_preview_nonce = wp_create_nonce( $preview_action );
$_GET                    = array(
	'promotion' => (string) $promotion_id,
	'target'    => (string) $page_a_id,
	'device'    => 'mobile',
	'_wpnonce'  => $subscriber_preview_nonce,
);
$check(
	403 === $expect_wp_die(
		static function (): void {
			Preview_Page::render();
		}
	),
	'A subscriber accessed the authenticated preview page.'
);

wp_set_current_user( 1 );
$wrong_promotion_nonce = wp_create_nonce( 'npcink_ad_preview_' . ( $promotion_id + 1 ) );
$_GET                  = array(
	'promotion' => (string) $promotion_id,
	'target'    => (string) $page_a_id,
	'device'    => 'mobile',
	'_wpnonce'  => $wrong_promotion_nonce,
);
$check(
	403 === $expect_wp_die(
		static function (): void {
			Preview_Page::render();
		}
	),
	'The preview page accepted a nonce bound to another promotion.'
);

$preview_nonce = wp_create_nonce( $preview_action );
$_GET          = array(
	'promotion' => (string) $promotion_id,
	'target'    => (string) $page_a_id,
	'device'    => 'mobile',
	'_wpnonce'  => $preview_nonce,
);
ob_start();
Preview_Page::render();
$preview_page_html = (string) ob_get_clean();
$check( str_contains( $preview_page_html, 'npcink_ad_preview=' . $promotion_id ), 'A manager could not render the bound real-page preview URL.' );
$check(
	str_contains( $preview_page_html, 'Uses the live delivery policy, including blocked verdicts. Desktop: 782px and above. Mobile: 781px and below in a representative 390px canvas.' ),
	'The preview page omitted its compact policy and device explanation.'
);
$check( str_contains( $preview_page_html, 'aria-label="Preview controls"' ), 'The preview page controls were not exposed as navigation.' );
$check( 1 === substr_count( $preview_page_html, 'aria-current="page"' ), 'The preview page did not expose exactly one current device.' );
$check(
	1 === preg_match( '#<a[^>]*aria-current="page"[^>]*>Mobile</a>#', $preview_page_html ),
	'The mobile preview link did not expose its current state programmatically.'
);

wp_set_current_user( $subscriber_id );
$_GET = array(
	'npcink_ad_preview'        => (string) $promotion_id,
	'npcink_ad_preview_device' => 'mobile',
	'npcink_ad_preview_nonce'  => $subscriber_preview_nonce,
);
$subscriber_preview_request = new Preview_Request(
	new Delivery( new Repository(), new Eligibility_Evaluator(), new Renderer() ),
	new Repository()
);
$check(
	403 === $expect_wp_die(
		static function () use ( $subscriber_preview_request ): void {
			$subscriber_preview_request->activate();
		}
	),
	'A subscriber activated a real-page preview request.'
);

wp_set_current_user( 1 );
$_GET = array(
	'npcink_ad_preview'        => (string) $promotion_id,
	'npcink_ad_preview_device' => 'mobile',
	'npcink_ad_preview_nonce'  => $wrong_promotion_nonce,
);
$invalid_preview_request = new Preview_Request(
	new Delivery( new Repository(), new Eligibility_Evaluator(), new Renderer() ),
	new Repository()
);
$check(
	403 === $expect_wp_die(
		static function () use ( $invalid_preview_request ): void {
			$invalid_preview_request->activate();
		}
	),
	'The real-page preview request accepted a nonce bound to another promotion.'
);

$_GET          = array(
	'npcink_ad_preview'        => (string) $promotion_id,
	'npcink_ad_preview_device' => 'mobile',
	'npcink_ad_preview_nonce'  => $preview_nonce,
);

$block_preview_delivery = new Delivery( new Repository(), new Eligibility_Evaluator(), new Renderer() );
$block_preview_request  = new Preview_Request( $block_preview_delivery, new Repository() );
$block_preview_request->activate();
$check( false === apply_filters( 'show_admin_bar', true ), 'An authorized real-page preview did not hide the front-end admin bar.' );
$check(
	defined( 'DONOTCACHEPAGE' ) && true === DONOTCACHEPAGE,
	'An authorized real-page preview did not disable full-page caching.'
);
$positioned_block = ( new Blocks( $block_preview_delivery ) )->render(
	array(
		'promotionId' => $promotion_id,
		'preview'     => true,
	)
);
$positioned_page = $block_preview_request->filter_content( $positioned_block );
$check(
	1 === substr_count( $positioned_page, 'data-npcink-ad-promotion="' . $promotion_id . '"' ),
	'A real-page block preview duplicated the target promotion.'
);
$check( str_contains( $positioned_page, 'npcink-ad-preview-verdict' ), 'The real-position block preview omitted its verdict.' );
$check(
	! str_contains( $positioned_page, 'Manual block preview is shown after the page content.' ),
	'A page containing the target block also rendered the fallback preview.'
);
remove_filter( 'the_content', array( $block_preview_request, 'filter_content' ), 999 );

$fallback_delivery = new Delivery( new Repository(), new Eligibility_Evaluator(), new Renderer() );
$fallback_request  = new Preview_Request( $fallback_delivery, new Repository() );
$fallback_request->activate();
$fallback_page = $fallback_request->filter_content( '<p>Page body</p>' );
$check(
	1 === substr_count( $fallback_page, 'data-npcink-ad-promotion="' . $promotion_id . '"' ),
	'A block preview fallback did not render exactly one promotion.'
);
$check(
	str_contains( $fallback_page, 'Manual block preview is shown after the page content.' ),
	'A page without the target block omitted the fallback explanation.'
);
remove_filter( 'the_content', array( $fallback_request, 'filter_content' ), 999 );
$_GET = array();

$set_editorial_scope = static function (
	string $scope,
	array $category_ids = array(),
	array $tag_ids = array(),
	array $include_ids = array(),
	array $exclude_ids = array(),
	string $location = 'content_after'
) use ( $promotion_id ): void {
	wp_set_current_user( 1 );
	update_post_meta( $promotion_id, Post_Types::LOCATION_META, $location );
	update_post_meta( $promotion_id, Post_Types::CONTENT_SCOPE_META, $scope );
	update_post_meta( $promotion_id, Post_Types::CATEGORY_IDS_META, $category_ids );
	update_post_meta( $promotion_id, Post_Types::TAG_IDS_META, $tag_ids );
	update_post_meta( $promotion_id, Post_Types::INCLUDE_IDS_META, $include_ids );
	update_post_meta( $promotion_id, Post_Types::EXCLUDE_IDS_META, $exclude_ids );
	update_post_meta( $promotion_id, Post_Types::DEVICE_META, 'all' );
	update_post_meta( $promotion_id, Post_Types::START_AT_META, '' );
	update_post_meta( $promotion_id, Post_Types::END_AT_META, '' );
};
$normal_scope_output = static function ( int $target_id, string $location = 'content_after' ) use ( $promotion_id ): string {
	wp_set_current_user( 0 );

	return ( new Delivery( new Repository(), new Eligibility_Evaluator(), new Renderer() ) )
		->render_promotion( $promotion_id, $location, 0, null, $target_id );
};
$preview_scope_output = static function ( int $target_id, string $location = 'content_after' ) use ( $promotion_id ): string {
	wp_set_current_user( 1 );

	return ( new Delivery( new Repository(), new Eligibility_Evaluator(), new Renderer() ) )
		->render_preview( $promotion_id, $location, 'desktop', $target_id );
};
$list_status = static function () use ( $promotion_id ): string {
	$promotion = ( new Repository() )->find_promotion( $promotion_id );
	if ( null === $promotion ) {
		return '';
	}

	$list   = new Promotion_List( new Repository(), new Eligibility_Evaluator(), new Overlap_Detector() );
	$method = new ReflectionMethod( $list, 'status_label' );

	return (string) $method->invoke( $list, $promotion );
};
$resume_blocking_reason = static function () use ( $promotion_id ): ?string {
	$status_action = new Promotion_Status_Action( new Repository(), new Eligibility_Evaluator() );
	$method        = new ReflectionMethod( $status_action, 'resume_blocking_reason' );

	return $method->invoke( $status_action, $promotion_id );
};

$set_editorial_scope( 'all' );
$check(
	str_contains( $normal_scope_output( $category_post_id ), 'Playground smoke creative' ),
	'The all scope did not render on a standard post.'
);
$check(
	str_contains( $normal_scope_output( $page_a_id ), 'Playground smoke creative' ),
	'The all scope did not render on a standard page.'
);

$set_editorial_scope( 'posts' );
$check(
	str_contains( $normal_scope_output( $category_post_id ), 'Playground smoke creative' ),
	'The posts scope did not render on a standard post.'
);
$check( '' === $normal_scope_output( $page_a_id ), 'The posts scope rendered on a standard page.' );

$set_editorial_scope( 'pages' );
$check(
	str_contains( $normal_scope_output( $page_a_id ), 'Playground smoke creative' ),
	'The pages scope did not render on a standard page.'
);
$check( '' === $normal_scope_output( $category_post_id ), 'The pages scope rendered on a standard post.' );

$set_editorial_scope( 'terms', array( $category_id ) );
$category_output = $normal_scope_output( $category_post_id );
$category_preview = $preview_scope_output( $category_post_id );
$check( str_contains( $category_output, 'Playground smoke creative' ), 'A direct category match did not render normally.' );
$check( ! str_contains( $category_preview, 'is-blocked' ), 'A direct category match was blocked in real-page preview.' );
$check( __( 'Rule ready', 'npcink-ad' ) === $list_status(), 'The list did not report a valid category rule as ready.' );
$check( '' === $normal_scope_output( $tag_post_id ), 'A category rule matched a tag-only post.' );
$check( '' === $normal_scope_output( $page_a_id ), 'A category rule matched a standard page.' );
$check(
	'' === $normal_scope_output( $child_category_post_id ),
	'A parent category rule implicitly expanded to a child-only relationship.'
);

$set_main_singular( $category_post_id );
wp_set_current_user( 0 );
$automatic_term_output = ( new Delivery( new Repository(), new Eligibility_Evaluator(), new Renderer() ) )
	->filter_content( '<p>Category target body</p>' );
$check(
	str_contains( $automatic_term_output, 'Playground smoke creative' ),
	'The automatic the_content path did not use the direct category relationship.'
);

$removed_category = wp_remove_object_terms( $category_post_id, $category_id, 'category' );
$check( ! is_wp_error( $removed_category ), 'Could not remove the direct category relationship.' );
$check( '' === $normal_scope_output( $category_post_id ), 'A removed category relationship remained eligible.' );
$check(
	str_contains( $preview_scope_output( $category_post_id ), 'npcink-ad-preview-verdict is-blocked' ),
	'Real-page preview did not immediately report the removed category relationship.'
);
$check(
	__( 'Rule ready', 'npcink-ad' ) === $list_status(),
	'The list incorrectly treated a valid dynamic term rule as a configuration error.'
);
$restored_category = wp_set_post_terms( $category_post_id, array( $category_id ), 'category', false );
$check( ! is_wp_error( $restored_category ), 'Could not restore the direct category relationship.' );
$check(
	str_contains( $normal_scope_output( $category_post_id ), 'Playground smoke creative' ),
	'A restored category relationship did not become immediately eligible.'
);

$set_editorial_scope( 'terms', array(), array( $tag_id ) );
$check(
	str_contains( $normal_scope_output( $tag_post_id ), 'Playground smoke creative' ),
	'A direct tag match did not render normally.'
);
$check( '' === $normal_scope_output( $plain_post_id ), 'A tag rule matched a post with no tags.' );

$set_editorial_scope(
	'selected',
	array( $category_id ),
	array( $tag_id ),
	array( $page_a_id )
);
$check(
	str_contains( $normal_scope_output( $page_a_id ), 'Playground smoke creative' ),
	'The selected scope did not render on its explicit public page.'
);
$check(
	'' === $normal_scope_output( $category_post_id ),
	'The selected scope combined stale category metadata with explicit IDs.'
);
$set_editorial_scope(
	'selected',
	array(),
	array(),
	array( $page_a_id ),
	array( $page_a_id )
);
$check( '' === $normal_scope_output( $page_a_id ), 'An explicit exclusion did not override selected inclusion.' );

$set_editorial_scope( 'terms', array( 999999999 ), array(), array(), array(), 'block' );
$check(
	str_contains( $normal_scope_output( $page_a_id, 'block' ), 'Playground smoke creative' ),
	'A manual block treated an advanced automatic scope as a term requirement.'
);
$set_editorial_scope( 'terms', array( 999999999 ), array(), array(), array( $page_a_id ), 'block' );
$check( '' === $normal_scope_output( $page_a_id, 'block' ), 'A manual block ignored its explicit ID exclusion.' );

$set_editorial_scope( 'terms' );
$check( __( 'Needs completion', 'npcink-ad' ) === $list_status(), 'The list did not fail closed for an empty term scope.' );
$check( '' === $normal_scope_output( $category_post_id ), 'An empty term scope rendered normally.' );
$check(
	str_contains( $preview_scope_output( $category_post_id ), 'npcink-ad-preview-verdict is-blocked' ),
	'Real-page preview did not expose the empty term scope as blocked.'
);
$check(
	'promotion_targets_empty' === $resume_blocking_reason(),
	'Resume preflight did not reject an empty term scope.'
);

$set_editorial_scope( 'terms', array( 999999999 ) );
$invalid_promotion = ( new Repository() )->find_promotion( $promotion_id );
$check(
	is_array( $invalid_promotion ) && false === $invalid_promotion['terms_valid'],
	'The repository did not fail closed for a missing category ID.'
);
$check( __( 'Needs completion', 'npcink-ad' ) === $list_status(), 'The list did not fail closed for an invalid term scope.' );
$check( '' === $normal_scope_output( $category_post_id ), 'An invalid term scope rendered normally.' );
$check(
	str_contains( $preview_scope_output( $category_post_id ), 'npcink-ad-preview-verdict is-blocked' ),
	'Real-page preview did not expose the invalid term scope as blocked.'
);
$check(
	'promotion_terms_invalid' === $resume_blocking_reason(),
	'Resume preflight did not reject an invalid term scope.'
);

$set_editorial_scope( 'terms', array(), array( $tag_id ) );
$check( null === $resume_blocking_reason(), 'Resume preflight rejected a valid direct-tag scope.' );

$set_editorial_scope( 'terms', array( $category_id ) );
$check(
	str_contains( $normal_scope_output( $category_post_id ), 'Playground smoke creative' ),
	'The category fixture was not eligible before term deletion.'
);
$deleted_category = wp_delete_term( $category_id, 'category' );
$check( true === $deleted_category, 'Could not delete the non-default configured category.' );
$check( '' === $normal_scope_output( $category_post_id ), 'A deleted configured category widened or retained delivery.' );
$check( __( 'Needs completion', 'npcink-ad' ) === $list_status(), 'The list did not expose a deleted configured category.' );
$check(
	'promotion_terms_invalid' === $resume_blocking_reason(),
	'Resume preflight did not reject a deleted configured category.'
);

wp_set_current_user( 1 );
update_post_meta( $promotion_id, Post_Types::LOCATION_META, 'block' );
update_post_meta( $promotion_id, Post_Types::CONTENT_SCOPE_META, 'selected' );
update_post_meta( $promotion_id, Post_Types::INCLUDE_IDS_META, array( $page_a_id ) );
update_post_meta( $promotion_id, Post_Types::EXCLUDE_IDS_META, array() );
update_post_meta( $promotion_id, Post_Types::DEVICE_META, 'mobile' );
update_post_meta(
	$promotion_id,
	Post_Types::START_AT_META,
	current_datetime()->modify( '+1 hour' )->format( 'Y-m-d H:i:s' )
);
wp_set_current_user( 0 );
$check( '' === do_shortcode( '[npcink_ad promotion="' . $promotion_id . '"]' ), 'A not-started promotion rendered anonymously.' );

wp_set_current_user( 1 );
update_post_meta( $promotion_id, Post_Types::START_AT_META, '' );
update_post_meta(
	$promotion_id,
	Post_Types::END_AT_META,
	current_datetime()->modify( '-1 hour' )->format( 'Y-m-d H:i:s' )
);
wp_set_current_user( 0 );
$check( '' === do_shortcode( '[npcink_ad promotion="' . $promotion_id . '"]' ), 'An expired promotion rendered anonymously.' );

wp_set_current_user( 1 );
update_post_meta( $promotion_id, Post_Types::END_AT_META, '' );
wp_update_post(
	array(
		'ID'          => $promotion_id,
		'post_status' => 'draft',
	)
);
wp_set_current_user( 0 );
$check( '' === do_shortcode( '[npcink_ad promotion="' . $promotion_id . '"]' ), 'A draft promotion rendered anonymously.' );

$rest_server = rest_get_server();
if ( 0 === did_action( 'rest_api_init' ) ) {
	do_action( 'rest_api_init', $rest_server );
}

$custom_plugin_rest_routes = array_values(
	array_filter(
		array_keys( $rest_server->get_routes() ),
		static fn ( string $route ): bool => str_starts_with( $route, '/npcink-ad/' ) || str_starts_with( $route, '/magick-ad/' )
	)
);
$check(
	array() === $custom_plugin_rest_routes,
	'The native Promotion workflow restored a custom Npcink Ad or legacy Magick AD REST namespace.'
);

wp_set_current_user( 1 );
$check( current_user_can( 'manage_npcink_ads' ), 'The REST write smoke did not run as the current administrator.' );
$write_promotion_via_rest = static function ( ?int $promotion_id, array $params ): WP_REST_Response {
	$route   = '/wp/v2/npcink_promotion' . ( null === $promotion_id ? '' : '/' . $promotion_id );
	$request = new WP_REST_Request( 'POST', $route );
	$request->set_body_params( $params );

	return rest_do_request( $request );
};
$assert_preflight_error  = static function (
	WP_REST_Response $response,
	string $expected_reason,
	string $message
) use ( $check ): void {
	$data    = $response->get_data();
	$reasons = is_array( $data ) && isset( $data['data']['reasons'] ) && is_array( $data['data']['reasons'] )
		? $data['data']['reasons']
		: array();

	$check( 400 === $response->get_status(), $message . ' The REST response was not HTTP 400.' );
	$check(
		is_array( $data ) && 'npcink_ad_promotion_not_ready' === ( $data['code'] ?? '' ),
		$message . ' The REST response omitted the preflight error code.'
	);
	$check( in_array( $expected_reason, $reasons, true ), $message . ' The REST response omitted the expected reason.' );
};

$blank_draft_response = $write_promotion_via_rest(
	null,
	array(
		'status'  => 'draft',
		'title'   => 'REST preflight smoke promotion',
		'content' => '',
	)
);
$blank_draft_data     = $blank_draft_response->get_data();
$rest_promotion_id    = is_array( $blank_draft_data ) ? absint( $blank_draft_data['id'] ?? 0 ) : 0;
$check( 201 === $blank_draft_response->get_status(), 'Core REST did not allow an empty Promotion draft to be created.' );
$check( 0 < $rest_promotion_id, 'Core REST did not return the created Promotion draft ID.' );
$check( 'draft' === get_post_status( $rest_promotion_id ), 'The empty REST Promotion was not saved as a draft.' );

$empty_publish_response = $write_promotion_via_rest(
	$rest_promotion_id,
	array(
		'status' => 'publish',
	)
);
$assert_preflight_error(
	$empty_publish_response,
	'promotion_content_empty',
	'Core REST allowed an empty Promotion to publish.'
);
$check( 'draft' === get_post_status( $rest_promotion_id ), 'A rejected empty publish changed the Promotion status.' );

$future_date           = current_datetime()->modify( '+1 day' );
$future_date_gmt       = $future_date->setTimezone( new DateTimeZone( 'UTC' ) );
$empty_future_response = $write_promotion_via_rest(
	$rest_promotion_id,
	array(
		'status'   => 'future',
		'date'     => $future_date->format( 'Y-m-d\TH:i:s' ),
		'date_gmt' => $future_date_gmt->format( 'Y-m-d\TH:i:s' ),
	)
);
$assert_preflight_error(
	$empty_future_response,
	'promotion_content_empty',
	'Core REST allowed an empty Promotion to bypass preflight through future scheduling.'
);
$check( 'draft' === get_post_status( $rest_promotion_id ), 'A rejected future schedule changed the Promotion status.' );

$source_less_video_response = $write_promotion_via_rest(
	$rest_promotion_id,
	array(
		'status'  => 'publish',
		'content' => '<!-- wp:video --><figure class="wp-block-video"><video controls></video></figure><!-- /wp:video -->',
	)
);
$assert_preflight_error(
	$source_less_video_response,
	'promotion_video_source_missing',
	'Core REST allowed a source-less Core Video block to publish.'
);
$check( 'draft' === get_post_status( $rest_promotion_id ), 'A rejected source-less video publish changed the Promotion status.' );

$nested_source_only_response = $write_promotion_via_rest(
	$rest_promotion_id,
	array(
		'status'  => 'publish',
		'content' => '<video controls><source src="/wp-content/uploads/promotion.mp4" type="video/mp4"></video>',
	)
);
$assert_preflight_error(
	$nested_source_only_response,
	'promotion_video_source_missing',
	'Core REST accepted a nested source that the frontend KSES contract removes.'
);
$check( 'draft' === get_post_status( $rest_promotion_id ), 'A rejected nested-source video publish changed the Promotion status.' );

$unsafe_video_response = $write_promotion_via_rest(
	$rest_promotion_id,
	array(
		'status'  => 'publish',
		'content' => '<!-- wp:video --><figure class="wp-block-video"><video controls src="javascript:alert(1)"></video></figure><!-- /wp:video -->',
	)
);
$assert_preflight_error(
	$unsafe_video_response,
	'promotion_video_source_missing',
	'Core REST treated an unsafe video source as playable creative.'
);
$check( 'draft' === get_post_status( $rest_promotion_id ), 'A rejected unsafe video publish changed the Promotion status.' );

$obfuscated_unsafe_video_response = $write_promotion_via_rest(
	$rest_promotion_id,
	array(
		'status'  => 'publish',
		'content' => '<video src="jav&#x0D;ascript:alert(1)"></video>',
	)
);
$assert_preflight_error(
	$obfuscated_unsafe_video_response,
	'promotion_video_source_missing',
	'Core REST allowed a control-character entity to conceal an unsafe video scheme.'
);
$check( 'draft' === get_post_status( $rest_promotion_id ), 'A rejected obfuscated video source changed the Promotion status.' );

$double_encoded_unsafe_video_response = $write_promotion_via_rest(
	$rest_promotion_id,
	array(
		'status'  => 'publish',
		'content' => '<video src="java&amp;#x73;cript:alert(1)"></video>',
	)
);
$assert_preflight_error(
	$double_encoded_unsafe_video_response,
	'promotion_video_source_missing',
	'Core REST allowed a double-encoded unsafe video scheme.'
);
$check( 'draft' === get_post_status( $rest_promotion_id ), 'A rejected double-encoded video source changed the Promotion status.' );

$valid_video_content  = '<!-- wp:video --><figure class="wp-block-video"><video controls muted playsinline preload="metadata" poster="/wp-content/uploads/promotion-poster.jpg" src="/wp-content/uploads/promotion.mp4"></video></figure><!-- /wp:video -->';
$valid_video_response = $write_promotion_via_rest(
	$rest_promotion_id,
	array(
		'status'  => 'publish',
		'content' => $valid_video_content,
	)
);
$check( 200 === $valid_video_response->get_status(), 'Core REST rejected a Core Video block with a usable same-origin source.' );
$check( 'publish' === get_post_status( $rest_promotion_id ), 'Core REST did not publish the valid video Promotion.' );
$video_promotion = ( new Repository() )->find_promotion( $rest_promotion_id );
$check( is_array( $video_promotion ), 'The published video Promotion could not be reloaded.' );
$rendered_video = is_array( $video_promotion ) ? ( new Renderer() )->render( $video_promotion ) : '';
$check( str_contains( $rendered_video, '<video' ), 'The Renderer omitted the published Core Video element.' );
$check( str_contains( $rendered_video, 'src="/wp-content/uploads/promotion.mp4"' ), 'The Renderer omitted the usable site-controlled video source.' );
$check( str_contains( $rendered_video, 'poster="/wp-content/uploads/promotion-poster.jpg"' ), 'The Renderer omitted the Core Video poster.' );
$check( 1 === preg_match( '/<video\b[^>]*\bcontrols\b/', $rendered_video ), 'The Renderer omitted Core Video controls.' );
$check( 1 === preg_match( '/<video\b[^>]*\bmuted\b/', $rendered_video ), 'The Renderer omitted the muted Core Video attribute.' );
$check( 1 === preg_match( '/<video\b[^>]*\bplaysinline\b/', $rendered_video ), 'The Renderer omitted the playsinline Core Video attribute.' );
$check( str_contains( $rendered_video, 'preload="metadata"' ), 'The Renderer omitted the Core Video preload policy.' );
$source_only_kses = wp_kses_post( '<video controls><source src="/wp-content/uploads/promotion.mp4" type="video/mp4"></video>' );
$check( ! str_contains( $source_only_kses, '<source' ), 'Core post KSES unexpectedly retained the unsupported source child.' );
$unsafe_video_kses = wp_kses_post( '<video src="javascript:alert(1)" onplay="alert(2)"><script>alert(3)</script></video>' );
$check( ! str_contains( $unsafe_video_kses, 'javascript:' ), 'Core post KSES retained an executable video source.' );
$check( ! str_contains( $unsafe_video_kses, 'onplay=' ), 'Core post KSES retained a video event handler.' );
$check( ! str_contains( $unsafe_video_kses, '<script' ), 'Core post KSES retained a script inside video markup.' );
wp_update_post(
	array(
		'ID'          => $rest_promotion_id,
		'post_status' => 'draft',
	)
);
$check( 'draft' === get_post_status( $rest_promotion_id ), 'The valid video fixture could not return to draft for later preflight checks.' );

$invalid_terms_response = $write_promotion_via_rest(
	$rest_promotion_id,
	array(
		'status'  => 'publish',
		'content' => '<!-- wp:paragraph --><p>REST editorial-scope creative</p><!-- /wp:paragraph -->',
		'meta'    => array(
			Post_Types::LOCATION_META      => 'content_after',
			Post_Types::CONTENT_SCOPE_META => 'terms',
			Post_Types::CATEGORY_IDS_META  => array( $category_id ),
			Post_Types::TAG_IDS_META       => array(),
			Post_Types::EXCLUDE_IDS_META   => array(),
		),
	)
);
$assert_preflight_error(
	$invalid_terms_response,
	'promotion_terms_invalid',
	'Core REST allowed a Promotion referencing a deleted category to publish.'
);
$check( 'draft' === get_post_status( $rest_promotion_id ), 'A rejected invalid-term publish changed the Promotion status.' );

$empty_terms_response = $write_promotion_via_rest(
	$rest_promotion_id,
	array(
		'status'  => 'publish',
		'content' => '<!-- wp:paragraph --><p>REST editorial-scope creative</p><!-- /wp:paragraph -->',
		'meta'    => array(
			Post_Types::LOCATION_META      => 'content_after',
			Post_Types::CONTENT_SCOPE_META => 'terms',
			Post_Types::CATEGORY_IDS_META  => array(),
			Post_Types::TAG_IDS_META       => array(),
			Post_Types::EXCLUDE_IDS_META   => array(),
		),
	)
);
$assert_preflight_error(
	$empty_terms_response,
	'promotion_targets_empty',
	'Core REST allowed a term scope with no configured terms to publish.'
);
$check( 'draft' === get_post_status( $rest_promotion_id ), 'A rejected empty-term publish changed the Promotion status.' );

$valid_terms_response = $write_promotion_via_rest(
	$rest_promotion_id,
	array(
		'status'  => 'publish',
		'content' => '<!-- wp:paragraph --><p>REST editorial-scope creative</p><!-- /wp:paragraph -->',
		'meta'    => array(
			Post_Types::LOCATION_META      => 'content_after',
			Post_Types::CONTENT_SCOPE_META => 'terms',
			Post_Types::CATEGORY_IDS_META  => array(),
			Post_Types::TAG_IDS_META       => array( $tag_id ),
			Post_Types::EXCLUDE_IDS_META   => array(),
		),
	)
);
$check( 200 === $valid_terms_response->get_status(), 'Core REST rejected a valid direct-tag scope.' );
$check( 'publish' === get_post_status( $rest_promotion_id ), 'Core REST did not publish the valid direct-tag scope.' );
$check(
	array( $tag_id ) === get_post_meta( $rest_promotion_id, Post_Types::TAG_IDS_META, true ),
	'Core REST did not persist the valid direct-tag scope.'
);
wp_update_post(
	array(
		'ID'          => $rest_promotion_id,
		'post_status' => 'draft',
	)
);
$check( 'draft' === get_post_status( $rest_promotion_id ), 'The valid term fixture could not return to draft for later preflight checks.' );

$non_public_target_id = wp_insert_post(
	array(
		'post_type'   => 'page',
		'post_status' => 'draft',
		'post_title'  => 'Non-public REST preflight target',
	),
	true
);
$check( ! is_wp_error( $non_public_target_id ), 'Could not create the non-public REST preflight target.' );
$invalid_targets_response = $write_promotion_via_rest(
	$rest_promotion_id,
	array(
		'status'  => 'publish',
		'content' => '<!-- wp:paragraph --><p>REST preflight creative</p><!-- /wp:paragraph -->',
		'meta'    => array(
			Post_Types::CONTENT_SCOPE_META => 'selected',
			Post_Types::INCLUDE_IDS_META => array( absint( $non_public_target_id ), 999999999 ),
			Post_Types::EXCLUDE_IDS_META => array(),
		),
	)
);
$assert_preflight_error(
	$invalid_targets_response,
	'promotion_targets_empty',
	'Core REST allowed a selected Promotion with no public target to publish.'
);
$check( 'draft' === get_post_status( $rest_promotion_id ), 'A rejected target publish changed the Promotion status.' );

$invalid_schedule_response = $write_promotion_via_rest(
	$rest_promotion_id,
	array(
		'status'  => 'publish',
		'content' => '<!-- wp:paragraph --><p>REST preflight creative</p><!-- /wp:paragraph -->',
		'meta'    => array(
			Post_Types::CONTENT_SCOPE_META => 'all',
			Post_Types::INCLUDE_IDS_META => array(),
			Post_Types::EXCLUDE_IDS_META => array(),
			Post_Types::START_AT_META    => '2026-01-15 09:00:00',
			Post_Types::END_AT_META      => '2026-01-15 09:00:00',
		),
	)
);
$assert_preflight_error(
	$invalid_schedule_response,
	'promotion_schedule_invalid',
	'Core REST allowed a Promotion with an invalid schedule to publish.'
);
$check( 'draft' === get_post_status( $rest_promotion_id ), 'A rejected schedule publish changed the Promotion status.' );

$invalid_paragraph_draft_response = $write_promotion_via_rest(
	$rest_promotion_id,
	array(
		'status'  => 'draft',
		'content' => '<!-- wp:paragraph --><p>REST paragraph preflight creative</p><!-- /wp:paragraph -->',
		'meta'    => array(
			Post_Types::LOCATION_META         => 'content_after_paragraph',
			Post_Types::PARAGRAPH_NUMBER_META => 0,
			Post_Types::CONTENT_SCOPE_META    => 'all',
			Post_Types::INCLUDE_IDS_META      => array(),
			Post_Types::EXCLUDE_IDS_META      => array(),
		),
	)
);
$check( 200 === $invalid_paragraph_draft_response->get_status(), 'Core REST did not preserve an incomplete paragraph Promotion draft.' );
$check(
	0 === (int) get_post_meta( $rest_promotion_id, Post_Types::PARAGRAPH_NUMBER_META, true ),
	'Core REST silently replaced the invalid draft paragraph number with its default.'
);
$invalid_paragraph_publish_response = $write_promotion_via_rest(
	$rest_promotion_id,
	array( 'status' => 'publish' )
);
$assert_preflight_error(
	$invalid_paragraph_publish_response,
	'promotion_paragraph_invalid',
	'Core REST allowed an invalid paragraph number to publish.'
);
$check( 'draft' === get_post_status( $rest_promotion_id ), 'A rejected paragraph publish changed the Promotion status.' );

$invalid_calendar_response = $write_promotion_via_rest(
	$rest_promotion_id,
	array(
		'status'  => 'publish',
		'content' => '<!-- wp:paragraph --><p>REST preflight creative</p><!-- /wp:paragraph -->',
		'meta'    => array(
			Post_Types::LOCATION_META    => 'block',
			Post_Types::CONTENT_SCOPE_META => 'selected',
			Post_Types::INCLUDE_IDS_META => array( $page_a_id ),
			Post_Types::EXCLUDE_IDS_META => array(),
			Post_Types::DEVICE_META      => 'all',
			Post_Types::START_AT_META    => '2026-02-31 12:00:00',
			Post_Types::END_AT_META      => '2026-03-02 12:00:00',
		),
	)
);
$assert_preflight_error(
	$invalid_calendar_response,
	'promotion_schedule_invalid',
	'Core REST allowed a Promotion with a formatted but impossible calendar date to publish.'
);
$check( 'draft' === get_post_status( $rest_promotion_id ), 'A rejected invalid calendar date changed the Promotion status.' );

$valid_start = current_datetime()->modify( '-1 hour' )->format( 'Y-m-d H:i:s' );
$valid_end   = current_datetime()->modify( '+1 hour' )->format( 'Y-m-d H:i:s' );
$valid_publish_response = $write_promotion_via_rest(
	$rest_promotion_id,
	array(
		'status'  => 'publish',
		'content' => '<!-- wp:paragraph --><p>REST preflight creative</p><!-- /wp:paragraph -->',
		'meta'    => array(
			Post_Types::LOCATION_META    => 'block',
			Post_Types::CONTENT_SCOPE_META => 'selected',
			Post_Types::INCLUDE_IDS_META => array( $page_a_id ),
			Post_Types::EXCLUDE_IDS_META => array(),
			Post_Types::DEVICE_META      => 'all',
			Post_Types::START_AT_META    => $valid_start,
			Post_Types::END_AT_META      => $valid_end,
		),
	)
);
$check( 200 === $valid_publish_response->get_status(), 'Core REST rejected a complete and valid Promotion configuration.' );
$check( 'publish' === get_post_status( $rest_promotion_id ), 'Core REST did not publish the valid Promotion.' );
$check(
	array( $page_a_id ) === get_post_meta( $rest_promotion_id, Post_Types::INCLUDE_IDS_META, true ),
	'Core REST did not persist the valid public target.'
);

wp_delete_post( absint( $non_public_target_id ), true );
wp_delete_post( $rest_promotion_id, true );
$check( null === get_post( $rest_promotion_id ), 'The REST preflight fixture was not removed before later smoke assertions.' );

$future_fixture_date     = current_datetime()->modify( '+2 days' );
$future_fixture_date_gmt = $future_fixture_date->setTimezone( new DateTimeZone( 'UTC' ) );
$future_fixture_start    = $future_fixture_date->modify( '+1 hour' )->format( 'Y-m-d H:i:s' );
$future_fixture_end      = $future_fixture_date->modify( '+1 day' )->format( 'Y-m-d H:i:s' );
$valid_future_response   = $write_promotion_via_rest(
	null,
	array(
		'status'   => 'future',
		'title'    => 'Valid future REST preflight promotion',
		'content'  => '<!-- wp:paragraph --><p>Scheduled REST preflight creative</p><!-- /wp:paragraph -->',
		'date'     => $future_fixture_date->format( 'Y-m-d\TH:i:s' ),
		'date_gmt' => $future_fixture_date_gmt->format( 'Y-m-d\TH:i:s' ),
		'meta'     => array(
			Post_Types::LOCATION_META    => 'block',
			Post_Types::CONTENT_SCOPE_META => 'selected',
			Post_Types::INCLUDE_IDS_META => array( $page_a_id ),
			Post_Types::EXCLUDE_IDS_META => array(),
			Post_Types::DEVICE_META      => 'mobile',
			Post_Types::START_AT_META    => $future_fixture_start,
			Post_Types::END_AT_META      => $future_fixture_end,
		),
	)
);
$valid_future_data       = $valid_future_response->get_data();
$future_promotion_id     = is_array( $valid_future_data ) ? absint( $valid_future_data['id'] ?? 0 ) : 0;
$future_promotion        = get_post( $future_promotion_id );
$check( 201 === $valid_future_response->get_status(), 'Core REST rejected a complete and valid future Promotion.' );
$check( $future_promotion instanceof WP_Post, 'Core REST did not create the valid future Promotion.' );
$check( 'future' === $future_promotion->post_status, 'The valid future Promotion was not scheduled.' );
$check(
	$future_fixture_date->format( 'Y-m-d H:i:s' ) === $future_promotion->post_date,
	'The valid future Promotion did not persist its local schedule date.'
);
$check(
	$future_fixture_date_gmt->format( 'Y-m-d H:i:s' ) === $future_promotion->post_date_gmt,
	'The valid future Promotion did not persist its GMT schedule date.'
);
$check(
	'block' === get_post_meta( $future_promotion_id, Post_Types::LOCATION_META, true ),
	'The valid future Promotion did not persist its location.'
);
$check(
	'selected' === get_post_meta( $future_promotion_id, Post_Types::CONTENT_SCOPE_META, true ),
	'The valid future Promotion did not persist its content scope.'
);
$check(
	array( $page_a_id ) === get_post_meta( $future_promotion_id, Post_Types::INCLUDE_IDS_META, true ),
	'The valid future Promotion did not persist its public target.'
);
$check(
	'mobile' === get_post_meta( $future_promotion_id, Post_Types::DEVICE_META, true ),
	'The valid future Promotion did not persist its device rule.'
);
$check(
	get_post_meta( $future_promotion_id, Post_Types::START_AT_META, true ) === $future_fixture_start,
	'The valid future Promotion did not persist its custom start time.'
);
$check(
	get_post_meta( $future_promotion_id, Post_Types::END_AT_META, true ) === $future_fixture_end,
	'The valid future Promotion did not persist its custom end time.'
);
wp_delete_post( $future_promotion_id, true );
$check( null === get_post( $future_promotion_id ), 'The valid future fixture was not removed before later smoke assertions.' );

$admin_collection_request = new WP_REST_Request( 'GET', '/wp/v2/npcink_promotion' );
$admin_collection_request->set_query_params(
	array(
		'context' => 'edit',
		'status'  => 'draft',
		'include' => array( $promotion_id ),
	)
);
$admin_collection_response = rest_do_request( $admin_collection_request );
$admin_collection_body     = (string) wp_json_encode( $admin_collection_response->get_data() );
$check( 200 === $admin_collection_response->get_status(), 'An administrator could not access the protected REST collection.' );
$check( str_contains( $admin_collection_body, 'Playground smoke creative' ), 'The authorized REST collection omitted the promotion.' );

$admin_single_request = new WP_REST_Request( 'GET', '/wp/v2/npcink_promotion/' . $promotion_id );
$admin_single_request->set_param( 'context', 'edit' );
$admin_single_response = rest_do_request( $admin_single_request );
$admin_single_body     = (string) wp_json_encode( $admin_single_response->get_data() );
$check( 200 === $admin_single_response->get_status(), 'An administrator could not access the protected REST promotion.' );
$check( str_contains( $admin_single_body, 'Playground smoke creative' ), 'The authorized REST promotion omitted its creative.' );

wp_set_current_user( $subscriber_id );
$subscriber_collection_response = rest_do_request( new WP_REST_Request( 'GET', '/wp/v2/npcink_promotion' ) );
$subscriber_single_response     = rest_do_request( new WP_REST_Request( 'GET', '/wp/v2/npcink_promotion/' . $promotion_id ) );
$check( 403 === $subscriber_collection_response->get_status(), 'A subscriber accessed the protected REST collection.' );
$check( 403 === $subscriber_single_response->get_status(), 'A subscriber accessed the protected REST promotion.' );

wp_set_current_user( 0 );
$collection_response = rest_do_request( new WP_REST_Request( 'GET', '/wp/v2/npcink_promotion' ) );
$collection_status   = $collection_response->get_status();
$collection_body     = (string) wp_json_encode( $collection_response->get_data() );
$check(
	in_array( $collection_status, array( 401, 403 ), true ),
	'Anonymous REST collection access returned HTTP ' . $collection_status . ' instead of 401/403.'
);
$check( ! str_contains( $collection_body, 'Playground smoke creative' ), 'Anonymous REST collection access exposed promotion content.' );

$single_response = rest_do_request( new WP_REST_Request( 'GET', '/wp/v2/npcink_promotion/' . $promotion_id ) );
$single_status   = $single_response->get_status();
$single_body     = (string) wp_json_encode( $single_response->get_data() );
$check(
	in_array( $single_status, array( 401, 403 ), true ),
	'Anonymous REST single-promotion access returned HTTP ' . $single_status . ' instead of 401/403.'
);
$check( ! str_contains( $single_body, 'Playground smoke creative' ), 'Anonymous REST single-promotion access exposed promotion content.' );

$placement_response = rest_do_request( new WP_REST_Request( 'GET', '/wp/v2/npcink_ad_placement' ) );
$check( 404 === $placement_response->get_status(), 'The removed placement REST collection still exists.' );

global $wpdb;
$plugin_options = $wpdb->get_col(
	$wpdb->prepare(
		'SELECT option_name FROM %i WHERE option_name LIKE %s',
		$wpdb->options,
		$wpdb->esc_like( 'npcink_ad_' ) . '%'
	)
);
$check( array() === $plugin_options, 'The single-promotion workflow created plugin options.' );

$plugin_tables = $wpdb->get_col(
	$wpdb->prepare(
		'SHOW TABLES LIKE %s',
		$wpdb->esc_like( $wpdb->prefix . 'npcink_ad' ) . '%'
	)
);
$check( array() === $plugin_tables, 'The single-promotion workflow created custom tables.' );

$legacy_background_hooks = array(
	'magick_ad_flush_stats_cache',
	'magick_ad_cleanup_stats_dim',
	'magick_ad_cleanup_diagnostics_log',
);
foreach ( $legacy_background_hooks as $legacy_background_hook ) {
	$check( false === has_action( $legacy_background_hook ), 'A legacy statistics or diagnostics Cron callback was restored.' );
	$check( false === wp_next_scheduled( $legacy_background_hook ), 'A legacy statistics or diagnostics Cron event was scheduled.' );
}
$plugin_cron_events = array();
foreach ( _get_cron_array() as $cron_hooks ) {
	foreach ( array_keys( $cron_hooks ) as $cron_hook ) {
		if ( str_starts_with( $cron_hook, 'npcink_ad_' ) || str_starts_with( $cron_hook, 'magick_ad_' ) ) {
			$plugin_cron_events[] = $cron_hook;
		}
	}
}
$check( array() === array_values( array_unique( $plugin_cron_events ) ), 'The single-Promotion workflow scheduled a plugin background event.' );

$placement_records = get_posts(
	array(
		'post_type'      => 'npcink_ad_placement',
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'fields'         => 'ids',
	)
);
$check( array() === $placement_records, 'The single-promotion workflow created placement records.' );

$unrelated_meta_post_id = wp_insert_post(
	array(
		'post_title'  => 'Unrelated uninstall metadata fixture',
		'post_status' => 'publish',
		'post_type'   => 'post',
	)
);
$check( 0 < $unrelated_meta_post_id, 'Could not create the unrelated uninstall metadata fixture.' );
update_post_meta( $unrelated_meta_post_id, Post_Types::CONTENT_SCOPE_META, 'all' );

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	define( 'WP_UNINSTALL_PLUGIN', 'npcink-ad/npcink-ad.php' );
}
require WP_PLUGIN_DIR . '/npcink-ad/uninstall.php';
$check( null === get_post( $promotion_id ), 'Explicit uninstall did not delete the promotion.' );
$check( ! get_role( 'administrator' )->has_cap( 'manage_npcink_ads' ), 'Explicit uninstall did not remove the management capability.' );
$check( ! get_role( 'editor' )->has_cap( 'manage_npcink_ads' ), 'Explicit uninstall did not remove the editor capability.' );
$check( 'all' === get_post_meta( $unrelated_meta_post_id, Post_Types::CONTENT_SCOPE_META, true ), 'Uninstall removed metadata from an unrelated post.' );

$result = array(
	'status'    => 'NPCINK_AD_SMOKE_OK',
	'model'     => 'single-promotion',
	'wordpress' => get_bloginfo( 'version' ),
	'php'       => PHP_VERSION,
	'plugin'    => defined( 'NPCINK_AD_VERSION' ) ? NPCINK_AD_VERSION : null,
);
$result_json = wp_json_encode( $result, JSON_PRETTY_PRINT );
$result_dir  = WP_CONTENT_DIR . '/npcink-ad-smoke-results';

$check( wp_mkdir_p( $result_dir ), 'Could not create the smoke result directory.' );
$check( false !== file_put_contents( $result_dir . '/result.json', $result_json ), 'Could not persist the smoke result.' );

// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI smoke protocol emits its generated JSON verbatim.
echo 'NPCINK_AD_SMOKE_OK ' . $result_json;
