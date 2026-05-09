<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Factory_Status_Command {

	public function __invoke(): void {

		$registry_path = WP_CONTENT_DIR .
			'/uploads/factory-runs/registry.json';

		if ( ! file_exists( $registry_path ) ) {
			WP_CLI::warning(
				'Factory registry not found.'
			);

			return;
		}

		$registry = json_decode(
			file_get_contents( $registry_path ),
			true
		);

		if ( ! is_array( $registry ) ) {
			WP_CLI::error(
				'Invalid registry JSON.'
			);
		}

		$latest = $registry['latest'] ?? '';

		if ( ! $latest ) {
			WP_CLI::warning(
				'No latest run found.'
			);

			return;
		}

		$run_path = WP_CONTENT_DIR .
			'/uploads/factory-runs/' .
			$latest;

		if ( ! file_exists( $run_path ) ) {
			WP_CLI::error(
				"Run file missing: {$latest}"
			);
		}

		$run = json_decode(
			file_get_contents( $run_path ),
			true
		);

		if ( ! is_array( $run ) ) {
			WP_CLI::error(
				'Invalid run manifest.'
			);
		}

		$plan = $run['plan']['summary'] ?? [];

		WP_CLI::log( '' );
		WP_CLI::log( 'Factory Status' );
		WP_CLI::log( '' );

		WP_CLI::log(
			'Latest Run: ' . $latest
		);

		WP_CLI::log(
			'Timestamp: ' .
			( $run['timestamp'] ?? '-' )
		);

		WP_CLI::log(
			'Preset: ' .
			( $run['preset'] ?? '-' )
		);

		WP_CLI::log(
			'Status: ' .
			( $run['status'] ?? '-' )
		);

		WP_CLI::log(
			'Prompt: ' .
			( $run['prompt'] ?? '-' )
		);

		WP_CLI::log( '' );

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

		$validation_status =
			$run['validation']['status'] ?? 'unknown';

		if ( $validation_status === 'ok' ) {

			WP_CLI::success(
				'Validation status: OK'
			);

			return;
		}

		WP_CLI::warning(
			"Validation status: {$validation_status}"
		);
	}
}