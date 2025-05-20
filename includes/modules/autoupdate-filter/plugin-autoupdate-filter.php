<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define module path constant if needed
if ( ! defined( 'ATLANTIS_AUTOUPDATE_FILTER_PATH' ) ) {
	define( 'ATLANTIS_AUTOUPDATE_FILTER_PATH', __DIR__ . '/' );
}

// main plugin functionality
require_once __DIR__ . '/class-plugin-autoupdate-filter.php';

// handles updating of the plugin itself
require_once __DIR__ . '/class-plugin-autoupdate-filter-self-update.php';
