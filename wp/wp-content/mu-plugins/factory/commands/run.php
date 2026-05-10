<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Factory_Run_Command {

	public function __invoke( array $args = [], array $assoc_args = [] ): void {

		$file = $args[0] ?? 'latest';

		$file = $args[0] ?? 'latest';

		if ( 'latest' === $file ) {
			$file = factory_get_latest_run_name();

			if ( ! $file ) {
				WP_CLI::error( 'Latest run not found.' );
			}
		}

		$data = factory_get_run_manifest( $file );

		if ( ! is_array( $data ) ) {
			WP_CLI::error( "Run file not found or invalid: {$file}" );
		}

		if ( ! $file ) {
			WP_CLI::error(
				'Provide run filename.'
			);
		}

		$path = WP_CONTENT_DIR .
			'/uploads/factory-runs/' .
			$file;

		if ( ! file_exists( $path ) ) {
			WP_CLI::error(
				"Run file not found: {$file}"
			);
		}

		$data = json_decode(
			file_get_contents( $path ),
			true
		);

		if ( ! is_array( $data ) ) {
			WP_CLI::error(
				'Invalid run manifest JSON.'
			);
		}

		WP_CLI::log( '' );
		WP_CLI::log( 'Factory Run' );
		WP_CLI::log( '' );

		WP_CLI::log(
			'File: ' . $file
		);

		WP_CLI::log(
			'Timestamp: ' .
			( $data['timestamp'] ?? '-' )
		);

		WP_CLI::log(
			'Status: ' .
			( $data['status'] ?? '-' )
		);

		WP_CLI::log(
			'Preset: ' .
			( $data['preset'] ?? '-' )
		);

		WP_CLI::log(
			'Prompt: ' .
			( $data['prompt'] ?? '-' )
		);

		WP_CLI::log( '' );

		$plan = $data['plan']['summary'] ?? [];

		WP_CLI::log( 'Plan Summary' );

		WP_CLI::log(
			'+ Create: ' .
			( $plan['create'] ?? 0 )
		);

		WP_CLI::log(
			'~ Update: ' .
			( $plan['update'] ?? 0 )
		);

		WP_CLI::log(
			'= Skip: ' .
			( $plan['skip'] ?? 0 )
		);

		WP_CLI::log(
			'! Warning: ' .
			( $plan['warning'] ?? 0 )
		);

		WP_CLI::log(
			'x Error: ' .
			( $plan['error'] ?? 0 )
		);

		WP_CLI::log( '' );

		$checks = $data['validation']['checks'] ?? [];

		WP_CLI::log(
			'Validation checks: ' .
			count( $checks )
		);

		WP_CLI::log( '' );

		foreach ( $checks as $check ) {

			$status = $check['status'] ?? 'unknown';
			$message = $check['message'] ?? '';

			$icon = match ( $status ) {
				'ok'      => '✓',
				'warning' => '!',
				'error'   => 'x',
				default   => '-',
			};

			WP_CLI::log(
				"{$icon} {$message}"
			);
		}
	}
}
