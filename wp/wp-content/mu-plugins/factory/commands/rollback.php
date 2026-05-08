<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Factory_Rollback_Command {

	public function latest( array $args = [], array $assoc_args = [] ): void {

		$upload_dir = wp_upload_dir();

		$base_dir = trailingslashit( $upload_dir['basedir'] ) . 'factory-snapshots';

		if ( ! is_dir( $base_dir ) ) {
			WP_CLI::error( 'Snapshots directory not found.' );
		}

		$items = scandir( $base_dir, SCANDIR_SORT_DESCENDING );

		$latest = null;

		foreach ( $items as $item ) {

			if ( in_array( $item, [ '.', '..' ], true ) ) {
				continue;
			}

			$path = trailingslashit( $base_dir ) . $item;

			if ( is_dir( $path ) ) {
				$latest = $path;
				break;
			}
		}

		if ( ! $latest ) {
			WP_CLI::error( 'No snapshots found.' );
		}

		$blueprint_path = trailingslashit( $latest ) . 'blueprint.json';

		if ( ! file_exists( $blueprint_path ) ) {
			WP_CLI::error( 'Snapshot blueprint.json not found.' );
		}

		$blueprint = json_decode(
			file_get_contents( $blueprint_path ),
			true
		);

		if ( ! is_array( $blueprint ) ) {
			WP_CLI::error( 'Invalid snapshot blueprint JSON.' );
		}

		WP_CLI::log( 'Rolling back from snapshot...' );

		factory_reset_diff_report();

		factory_apply_blueprint( $blueprint );

		factory_log_diff_report();

		WP_CLI::log( 'Validating rollback state...' );

		$report = factory_validate_blueprint_state( $blueprint );

		if ( ( $report['status'] ?? 'error' ) === 'ok' ) {
			WP_CLI::success( 'Rollback completed successfully.' );
			return;
		}

		WP_CLI::warning( 'Rollback applied, but validation has errors.' );
	}
}