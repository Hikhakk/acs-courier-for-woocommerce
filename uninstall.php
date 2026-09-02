<?php
/**
 * Removes everything the plugin stored, on explicit uninstall only.
 *
 * @package AcsCourier
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

delete_option( 'acs_wc_settings' );

// Any lock left behind by an interrupted request.
$acs_wc_locks = $wpdb->get_col(
	"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'acs\\_wc\\_lock\\_order\\_%'"
);
foreach ( (array) $acs_wc_locks as $acs_wc_lock ) {
	delete_option( $acs_wc_lock );
}

// Order meta written by this plugin, on both storage backends.
$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key IN ('_acs_wc_voucher_no', '_acs_wc_created_at')" );

$acs_wc_orders_meta = $wpdb->prefix . 'wc_orders_meta';
if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $acs_wc_orders_meta ) ) === $acs_wc_orders_meta ) {
	// A table name cannot be a prepared placeholder; it is built from $wpdb->prefix, which is trusted.
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$wpdb->query( "DELETE FROM {$acs_wc_orders_meta} WHERE meta_key IN ('_acs_wc_voucher_no', '_acs_wc_created_at')" );
}
