<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function factory_save_run_manifest(
	string $prompt,
	?string $preset,
	array $blueprint,
	array $plan,
	array $validation,
	string $status = 'success'
): string {

	$upload_dir = wp_upload_dir();

	$dir = trailingslashit(
		$upload_dir['basedir']
	) . 'factory-runs';

	if ( ! is_dir( $dir ) ) {
		wp_mkdir_p( $dir );
	}

	$timestamp = current_time( 'Ymd-His' );

	$path = trailingslashit( $dir ) .
		"run-{$timestamp}.json";

	$data = [
		'timestamp'  => current_time( 'mysql' ),
		'prompt'     => $prompt,
		'preset'     => $preset,
		'status'     => $status,

		'blueprint'  => $blueprint,
		'plan'       => $plan,
		'validation' => $validation,
	];

	file_put_contents(
		$path,
		wp_json_encode(
			$data,
			JSON_PRETTY_PRINT |
			JSON_UNESCAPED_UNICODE
		)
	);

	return $path;
}