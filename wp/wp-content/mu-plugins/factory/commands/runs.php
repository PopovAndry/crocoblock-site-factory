<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Factory_Runs_Command {

	public function __invoke( array $args = [], array $assoc_args = [] ): void {

		$path = WP_CONTENT_DIR .
			'/uploads/factory-runs/registry.json';

		if ( ! file_exists( $path ) ) {
			WP_CLI::warning(
				'Run registry not found.'
			);

			return;
		}

		$registry = json_decode(
			file_get_contents( $path ),
			true
		);

		if ( ! is_array( $registry ) ) {
			WP_CLI::error(
				'Invalid registry JSON.'
			);
		}

		$runs = $registry['runs'] ?? [];

		if ( empty( $runs ) ) {
			WP_CLI::warning(
				'No runs found.'
			);

			return;
		}

		$rows = [];

		foreach ( $runs as $run ) {

			$rows[] = [
				'file'      => $run['file'] ?? '',
				'timestamp' => $run['timestamp'] ?? '',
				'status'    => $run['status'] ?? '',
				'preset'    => $run['preset'] ?? '',
				'prompt'    => $run['prompt'] ?? '',
			];
		}

		WP_CLI\Utils\format_items(
			'table',
			$rows,
			[
				'file',
				'timestamp',
				'status',
				'preset',
				'prompt',
			]
		);
	}
}