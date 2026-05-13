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
	string $status = 'success',
	array $execution = []
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
		'results'    => factory_build_manifest_results( $validation ),
		'execution'  => factory_build_manifest_execution( $execution ),
	];

	file_put_contents(
		$path,
		wp_json_encode(
			$data,
			JSON_PRETTY_PRINT |
			JSON_UNESCAPED_UNICODE
		)
	);

	$manifest = $data;
	$manifest['file'] = $path;

	if ( function_exists( 'factory_update_run_registry' ) ) {
		factory_update_run_registry( $manifest );
	}

	return $path;
}

function factory_build_manifest_results( array $validation ): array {
	$results = [
		'version' => 1,
		'source'  => 'validation',
		'summary' => [
			'ok'      => 0,
			'warning' => 0,
			'error'   => 0,
		],
		'items'   => [],
	];

	$checks = $validation['checks'] ?? [];

	if ( ! is_array( $checks ) ) {
		return $results;
	}

	foreach ( $checks as $check ) {
		if ( ! is_array( $check ) ) {
			continue;
		}

		$status = $check['status'] ?? '';

		if ( ! in_array( $status, [ 'ok', 'warning', 'error' ], true ) ) {
			continue;
		}

		$results['summary'][ $status ]++;
		$results['items'][] = [
			'stage'   => 'validation',
			'status'  => $status,
			'type'    => 'validation',
			'entity'  => '',
			'message' => $check['message'] ?? '',
			'details' => [],
		];
	}

	return $results;
}

function factory_build_manifest_execution( array $items ): array {
	return [
		'version' => 1,
		'items'   => array_values( $items ),
	];
}
