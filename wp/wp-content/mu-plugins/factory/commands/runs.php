<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Factory_Runs_Command {

	public function __invoke( array $args = [], array $assoc_args = [] ): void {
		$path = WP_CONTENT_DIR .
			'/uploads/factory-runs/registry.json';

		$format      = $assoc_args['format'] ?? 'table';
		$is_json     = 'json' === $format;
		$latest_only = isset( $assoc_args['latest'] );
		$failed_only = isset( $assoc_args['failed'] );

		if ( ! file_exists( $path ) ) {
			if ( $is_json ) {
				WP_CLI::line(
					wp_json_encode(
						[
							'status'  => 'error',
							'message' => 'Run registry not found.',
							'runs'    => [],
						],
						JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
					)
				);

				return;
			}

			WP_CLI::warning( 'Run registry not found.' );
			return;
		}

		$registry = json_decode(
			file_get_contents( $path ),
			true
		);

		if ( ! is_array( $registry ) ) {
			WP_CLI::error( 'Invalid registry JSON.' );
		}

		$runs = $registry['runs'] ?? [];

		if ( $latest_only ) {
			$latest = $registry['latest'] ?? '';

			$runs = array_values(
				array_filter(
					$runs,
					static fn( $run ) => ( $run['file'] ?? '' ) === $latest
				)
			);
		}

		if ( $failed_only ) {
			$runs = array_values(
				array_filter(
					$runs,
					static fn( $run ) => ( $run['status'] ?? '' ) !== 'ok'
				)
			);
		}

		if ( empty( $runs ) ) {
			if ( $is_json ) {
				WP_CLI::line(
					wp_json_encode(
						[
							'status' => 'ok',
							'runs'   => [],
						],
						JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
					)
				);

				return;
			}

			WP_CLI::warning( 'No runs found.' );
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

		if ( $is_json ) {
			WP_CLI::line(
				wp_json_encode(
					[
						'status' => 'ok',
						'latest' => $registry['latest'] ?? null,
						'runs'   => $rows,
					],
					JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
				)
			);

			return;
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