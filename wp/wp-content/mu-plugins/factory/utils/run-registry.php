<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function factory_update_run_registry( array $manifest ): void {
	$dir = WP_CONTENT_DIR . '/uploads/factory-runs';

	if ( ! is_dir( $dir ) ) {
		wp_mkdir_p( $dir );
	}

	$registry_path = $dir . '/registry.json';

	$registry = [
		'latest' => null,
		'runs'   => [],
	];

	if ( file_exists( $registry_path ) ) {
		$decoded = json_decode(
			file_get_contents( $registry_path ),
			true
		);

		if ( is_array( $decoded ) ) {
			$registry = $decoded;
		}
	}

	$file = basename( $manifest['file'] ?? '' );

	if ( ! $file ) {
		return;
	}

	$entry = [
		'file'      => $file,
		'timestamp' => $manifest['timestamp'] ?? '',
		'status'    => $manifest['status'] ?? 'unknown',
		'preset'    => $manifest['preset'] ?? null,
		'prompt'    => $manifest['prompt'] ?? '',
	];

	$registry['latest'] = $file;

	array_unshift( $registry['runs'], $entry );

	$registry['runs'] = array_slice( $registry['runs'], 0, 50 );

	file_put_contents(
		$registry_path,
		wp_json_encode(
			$registry,
			JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
		)
	);
}