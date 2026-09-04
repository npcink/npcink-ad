<?php
/**
 * Namespaced WordPress stubs for real-page preview request tests.
 *
 * @package NpcinkAd
 */

namespace Npcink\Ad\Frontend;

use PreviewRequestWpDieException;

if ( ! function_exists( __NAMESPACE__ . '\\wp_unslash' ) ) {
	/**
	 * Return already unslashed fixture input.
	 *
	 * @param mixed $value Input value.
	 */
	function wp_unslash( mixed $value ): mixed {
		return $value;
	}
}

if ( ! function_exists( __NAMESPACE__ . '\\wp_verify_nonce' ) ) {
	/**
	 * Validate the deterministic preview nonce.
	 *
	 * @param string $nonce  Submitted nonce.
	 * @param string $action Expected action.
	 */
	function wp_verify_nonce( string $nonce, string $action ): bool {
		return 'nonce:' . $action === $nonce;
	}
}

if ( ! function_exists( __NAMESPACE__ . '\\esc_html__' ) ) {
	/**
	 * Return untranslated test text.
	 *
	 * @param string $text Source text.
	 */
	function esc_html__( string $text ): string {
		return $text;
	}
}

if ( ! function_exists( __NAMESPACE__ . '\\wp_die' ) ) {
	/**
	 * Convert a WordPress termination into an inspectable exception.
	 *
	 * @param string               $message Error message.
	 * @param string               $title   Error title.
	 * @param array<string, mixed> $args    Response arguments.
	 * @throws PreviewRequestWpDieException Always.
	 */
	function wp_die( string $message, string $title = '', array $args = array() ): never {
		unset( $title );
		// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Test stub captures the production-escaped message.
		throw new PreviewRequestWpDieException( $message, (int) ( $args['response'] ?? 500 ) );
	}
}

if ( ! function_exists( __NAMESPACE__ . '\\get_queried_object_id' ) ) {
	/**
	 * Return the current fixture post ID.
	 */
	function get_queried_object_id(): int {
		return (int) ( $GLOBALS['npcink_ad_test_preview_target_id'] ?? 0 );
	}
}

if ( ! function_exists( __NAMESPACE__ . '\\get_post_type' ) ) {
	/**
	 * Return the current fixture post type.
	 *
	 * @param int $post_id Queried post ID.
	 */
	function get_post_type( int $post_id ): string|false {
		$GLOBALS['npcink_ad_test_get_post_type_calls'][] = $post_id;
		$post = $GLOBALS['npcink_ad_test_posts'][ $post_id ] ?? null;
		if ( is_object( $post ) && is_string( $post->post_type ?? null ) ) {
			return $post->post_type;
		}
		if ( isset( $GLOBALS['npcink_ad_test_preview_target_type'] ) ) {
			return $GLOBALS['npcink_ad_test_preview_target_type'];
		}

		$post_type = $GLOBALS['npcink_ad_test_singular_post_type'] ?? false;

		return is_string( $post_type ) && '' !== $post_type ? $post_type : false;
	}
}

if ( ! function_exists( __NAMESPACE__ . '\\get_post_status' ) ) {
	/**
	 * Return the current fixture post status.
	 *
	 * @param int $post_id Queried post ID.
	 */
	function get_post_status( int $post_id ): string|false {
		unset( $post_id );
		$status = $GLOBALS['npcink_ad_test_preview_target_status'] ?? 'publish';

		return is_string( $status ) ? $status : false;
	}
}

if ( ! function_exists( __NAMESPACE__ . '\\is_post_publicly_viewable' ) ) {
	/**
	 * Return whether the current fixture target is publicly viewable.
	 *
	 * @param int $post_id Queried post ID.
	 */
	function is_post_publicly_viewable( int $post_id ): bool {
		unset( $post_id );

		return (bool) ( $GLOBALS['npcink_ad_test_preview_target_public'] ?? true );
	}
}

if ( ! function_exists( __NAMESPACE__ . '\\nocache_headers' ) ) {
	/**
	 * Record that preview responses disabled caching.
	 */
	function nocache_headers(): void {
		$GLOBALS['npcink_ad_test_preview_nocache'] = true;
	}
}

if ( ! function_exists( __NAMESPACE__ . '\\header' ) ) {
	/**
	 * Capture a preview response header without sending real CLI headers.
	 *
	 * @param string $header        Header value.
	 * @param bool   $replace       Whether to replace an existing header.
	 * @param int    $response_code Optional response code.
	 */
	function header( string $header, bool $replace = true, int $response_code = 0 ): void {
		unset( $replace, $response_code );
		$GLOBALS['npcink_ad_test_preview_headers'][] = $header;
	}
}

if ( ! function_exists( __NAMESPACE__ . '\\remove_filter' ) ) {
	/**
	 * Record removal of normal automatic delivery.
	 *
	 * @param string $hook_name Filter hook.
	 * @param mixed  $callback  Filter callback.
	 * @param int    $priority  Filter priority.
	 */
	function remove_filter( string $hook_name, mixed $callback, int $priority = 10 ): bool {
		$GLOBALS['npcink_ad_test_preview_filters'][] = 'remove:' . $hook_name;
		$GLOBALS['npcink_ad_test_preview_removed_callbacks'][] = array(
			'method'   => is_array( $callback ) && isset( $callback[1] ) ? (string) $callback[1] : '',
			'priority' => $priority,
		);

		return true;
	}
}

if ( ! function_exists( __NAMESPACE__ . '\\add_filter' ) ) {
	/**
	 * Record installation of forced preview delivery.
	 *
	 * @param string $hook_name    Filter hook.
	 * @param mixed  $callback     Filter callback.
	 * @param int    $priority     Filter priority.
	 * @param int    $accepted_args Accepted argument count.
	 */
	function add_filter( string $hook_name, mixed $callback, int $priority = 10, int $accepted_args = 1 ): bool {
		unset( $callback, $accepted_args );
		$GLOBALS['npcink_ad_test_preview_filters'][] = 'add:' . $hook_name . ':' . $priority;

		return true;
	}
}
