<?php
/**
 * Remove all Npcink Ad data.
 *
 * @package NpcinkAd
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Remove Npcink Ad data from the current site in the network.
 */
function npcink_ad_uninstall_site_data(): void {
	$npcink_ad_post_ids = get_posts(
		array(
			'post_type'      => 'npcink_promotion',
			'post_status'    => array( 'publish', 'future', 'draft', 'pending', 'private', 'trash', 'auto-draft', 'inherit' ),
			'posts_per_page' => -1,
			'fields'         => 'ids',
		)
	);
	$npcink_ad_meta_keys = array(
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
		'_npcink_ad_first_publish_completed',
		'_npcink_ad_page_scope',
	);
	foreach ( $npcink_ad_post_ids as $npcink_ad_post_id ) {
		foreach ( $npcink_ad_meta_keys as $npcink_ad_meta_key ) {
			delete_post_meta( (int) $npcink_ad_post_id, $npcink_ad_meta_key );
		}
		wp_delete_post( (int) $npcink_ad_post_id, true );
	}

	$npcink_ad_roles = wp_roles();
	foreach ( $npcink_ad_roles->role_objects as $npcink_ad_role ) {
		if ( $npcink_ad_role->has_cap( 'manage_npcink_ads' ) ) {
			$npcink_ad_role->remove_cap( 'manage_npcink_ads' );
		}
	}
}

if ( is_multisite() ) {
	$npcink_ad_site_ids = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);
	foreach ( $npcink_ad_site_ids as $npcink_ad_site_id ) {
		switch_to_blog( (int) $npcink_ad_site_id );
		try {
			npcink_ad_uninstall_site_data();
		} finally {
			restore_current_blog();
		}
	}
} else {
	npcink_ad_uninstall_site_data();
}
