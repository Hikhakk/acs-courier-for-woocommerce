<?php
/**
 * WordPress constants the stub packages do not define. Analysis only.
 *
 * @package AcsCourier
 */

define( 'ABSPATH', '/' );
define( 'ARRAY_A', 'ARRAY_A' );
define( 'ARRAY_N', 'ARRAY_N' );
define( 'OBJECT', 'OBJECT' );

define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );

/** @param array<mixed> $args */
function as_has_scheduled_action( string $hook, $args = null, string $group = '' ): bool {}
/** @param array<mixed> $args */
function as_schedule_recurring_action( int $timestamp, int $interval, string $hook, array $args = array(), string $group = '' ): int {}
