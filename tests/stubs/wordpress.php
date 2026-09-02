<?php
/**
 * Minimal WordPress function stubs so PHPStan can analyse WP-facing code
 * without pulling in all of WordPress. Never loaded at runtime.
 *
 * @package AcsCourier
 */

/** @param mixed $default @return mixed */
function get_option(string $option, $default = false) {}
/** @param mixed $value */
function add_option(string $option, $value = '', string $deprecated = '', string $autoload = 'yes'): bool {}
function delete_option(string $option): bool {}
