<?php
/**
 * Runs when the plugin is deleted from the Plugins screen.
 *
 * By default nothing is removed, so a reinstall can pick up the owner's settings,
 * request counters and stored conversations. Data is deleted only when the owner
 * has explicitly turned on "Delete data on uninstall". Only items created by this
 * plugin, identified by exact name, are touched.
 *
 * @package NicheCleverInteractive\Core
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Remove this plugin's data for the current site when the owner has opted in.
 *
 * @return void
 */
function ncli_core_uninstall_current_site() {
	global $wpdb;

	$settings = get_option( 'ncli_core_settings', array() );
	if ( ! is_array( $settings ) || ! isset( $settings['lifecycle'] ) || ! is_array( $settings['lifecycle'] ) || true !== ( $settings['lifecycle']['delete_on_uninstall'] ?? false ) ) {
		return;
	}

	wp_clear_scheduled_hook( 'ncli_core_purge_conversations' );

	// The table names are built only from the site prefix and fixed suffixes.
	foreach ( array( 'ncli_core_rate_buckets', 'ncli_core_conversation_turns' ) as $suffix ) {
		$table = esc_sql( $wpdb->prefix . $suffix );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Fixed plugin-owned table identifier; no user input; a DROP TABLE has nothing to cache.
		$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
	}

	delete_option( 'ncli_core_settings' );
	delete_option( 'ncli_core_schema_version' );
	delete_option( 'ncli_core_last_failure' );
}

if ( is_multisite() ) {
	$ncli_core_site_ids = get_sites( array( 'fields' => 'ids', 'number' => 0 ) );
	foreach ( $ncli_core_site_ids as $ncli_core_site_id ) {
		switch_to_blog( (int) $ncli_core_site_id );
		ncli_core_uninstall_current_site();
		restore_current_blog();
	}
} else {
	ncli_core_uninstall_current_site();
}
