<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Factory_Snapshot_Command {

	public function create( array $args = [], array $assoc_args = [] ): void {

		$upload_dir = wp_upload_dir();

		$base_dir = trailingslashit( $upload_dir['basedir'] ) . 'factory-snapshots';

		if ( ! is_dir( $base_dir ) ) {
			wp_mkdir_p( $base_dir );
		}

		$timestamp = gmdate( 'Ymd-His' );

		$snapshot_dir = trailingslashit( $base_dir ) . $timestamp;

		wp_mkdir_p( $snapshot_dir );

		$this->store_blueprint( $snapshot_dir );
		$this->store_report( $snapshot_dir );
		$this->store_metadata( $snapshot_dir, $timestamp );

		WP_CLI::success( "Snapshot created: {$timestamp}" );
	}

	public function list( array $args = [], array $assoc_args = [] ): void {

		$upload_dir = wp_upload_dir();

		$base_dir = trailingslashit( $upload_dir['basedir'] ) . 'factory-snapshots';

		if ( ! is_dir( $base_dir ) ) {
			WP_CLI::warning( 'No snapshots found.' );
			return;
		}

		$items = scandir( $base_dir );

		$snapshots = [];

		foreach ( $items as $item ) {

			if ( in_array( $item, [ '.', '..' ], true ) ) {
				continue;
			}

			$path = trailingslashit( $base_dir ) . $item;

			if ( ! is_dir( $path ) ) {
				continue;
			}

			$metadata_path = trailingslashit( $path ) . 'metadata.json';

			$created = $item;

			if ( file_exists( $metadata_path ) ) {

				$metadata = json_decode(
					file_get_contents( $metadata_path ),
					true
				);

				if ( is_array( $metadata ) && ! empty( $metadata['created_at'] ) ) {
					$created = $metadata['created_at'];
				}
			}

			$snapshots[] = [
				'id'      => $item,
				'created' => $created,
			];
		}

		if ( empty( $snapshots ) ) {
			WP_CLI::warning( 'No snapshots found.' );
			return;
		}

		WP_CLI\Utils\format_items(
			'table',
			$snapshots,
			[ 'id', 'created' ]
		);
	}

	private function store_blueprint( string $snapshot_dir ): void {

		$source = '/var/www/blueprints/generated/ai-blueprint.json';

		if ( ! file_exists( $source ) ) {
			return;
		}

		copy(
			$source,
			trailingslashit( $snapshot_dir ) . 'blueprint.json'
		);
	}

	private function store_report( string $snapshot_dir ): void {

		$upload_dir = wp_upload_dir();

		$source = trailingslashit( $upload_dir['basedir'] ) . 'factory-report.json';

		if ( ! file_exists( $source ) ) {
			return;
		}

		copy(
			$source,
			trailingslashit( $snapshot_dir ) . 'report.json'
		);
	}

	private function store_metadata(
		string $snapshot_dir,
		string $timestamp
	): void {

		$metadata = [
			'created_at'    => $timestamp,
			'wp_version'    => get_bloginfo( 'version' ),
			'php_version'   => PHP_VERSION,
			'active_theme'  => wp_get_theme()->get( 'Name' ),
			'active_plugins'=> array_values(
				wp_get_active_and_valid_plugins()
			),
		];

		file_put_contents(
			trailingslashit( $snapshot_dir ) . 'metadata.json',
			json_encode(
				$metadata,
				JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
			)
		);
	}
}