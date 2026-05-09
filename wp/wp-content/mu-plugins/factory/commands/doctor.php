<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Factory_Doctor_Command {

	public function __invoke( array $args = [], array $assoc_args = [] ): void {
		$registry_path = WP_CONTENT_DIR . '/uploads/factory-runs/registry.json';

		if ( ! file_exists( $registry_path ) ) {
			WP_CLI::warning( 'Run registry not found.' );
			return;
		}

		$registry = json_decode( file_get_contents( $registry_path ), true );

		if ( ! is_array( $registry ) ) {
			WP_CLI::error( 'Invalid registry JSON.' );
		}

		$latest = $registry['latest'] ?? '';

		if ( ! $latest ) {
			WP_CLI::warning( 'Latest run not found.' );
			return;
		}

		$run_path = WP_CONTENT_DIR . '/uploads/factory-runs/' . $latest;

		if ( ! file_exists( $run_path ) ) {
			WP_CLI::error( "Latest run manifest missing: {$latest}" );
		}

		$run = json_decode( file_get_contents( $run_path ), true );

		if ( ! is_array( $run ) ) {
			WP_CLI::error( 'Invalid run manifest JSON.' );
		}

		$blueprint = $run['blueprint'] ?? [];

		WP_CLI::log( '' );
		WP_CLI::log( 'Factory Doctor' );
		WP_CLI::log( '' );
		WP_CLI::log( 'Latest Run: ' . $latest );
		WP_CLI::log( 'Prompt: ' . ( $run['prompt'] ?? '-' ) );
		WP_CLI::log( '' );

		$current = factory_validate_blueprint_state( $blueprint, false );
		$status  = $current['status'] ?? 'error';

		if ( 'ok' === $status ) {
			WP_CLI::success( 'System healthy. All layers are in sync.' );
			return;
		}

		WP_CLI::warning( 'Drift detected.' );
		WP_CLI::log( '' );
		WP_CLI::log( 'Issues:' );

		foreach ( $current['checks'] ?? [] as $check ) {
			if ( ( $check['status'] ?? '' ) === 'ok' ) {
				continue;
			}

			WP_CLI::log( '  - ' . ( $check['message'] ?? '' ) );
		}

		WP_CLI::log( '' );
		WP_CLI::log( 'Suggested action:' );
		WP_CLI::log( '  wp factory fix' );

		if ( isset( $assoc_args['fix'] ) ) {
			WP_CLI::log( '' );
			WP_CLI::log( 'Running auto-fix...' );

			$fix = new Factory_Fix_Command();
			$fix->__invoke( [], [] );
		}
	}
}