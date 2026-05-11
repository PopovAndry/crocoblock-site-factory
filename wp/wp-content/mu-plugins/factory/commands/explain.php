<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Factory_Explain_Command {

	public function __invoke(
		array $args = [],
		array $assoc_args = []
	): void {

		$file = $args[0] ?? 'latest';

		if ( 'latest' === $file ) {
			$file = factory_get_latest_run_name();

			if ( ! $file ) {
				WP_CLI::error(
					'Latest run not found.'
				);
			}
		}

		$run = factory_get_run_manifest( $file );

		if ( ! is_array( $run ) ) {
			WP_CLI::error(
				"Run file not found or invalid: {$file}"
			);
		}

		$blueprint = $run['blueprint'] ?? [];

		WP_CLI::log( '' );
		WP_CLI::log( 'Factory Explain' );
		WP_CLI::log( '' );

		$site_name =
			$blueprint['site']['name'] ?? 'Unnamed Site';

		WP_CLI::log(
			"This factory run generated:"
		);

		WP_CLI::log( '' );

		WP_CLI::log(
			"- {$site_name}"
		);

		$cpts = $blueprint['cpt'] ?? [];

		foreach ( $cpts as $cpt ) {

			$slug = $cpt['slug'] ?? '';

			if ( ! $slug ) {
				continue;
			}

			WP_CLI::log(
				"- CPT: {$slug}"
			);

			$meta = $cpt['meta'] ?? [];

			if ( ! empty( $meta ) ) {

				WP_CLI::log(
					'  Meta fields:'
				);

				foreach ( $meta as $field ) {

					$key = $field['key'] ?? '';

					if ( ! $key ) {
						continue;
					}

					WP_CLI::log(
						"    - {$key}"
					);
				}
			}
		}

		$taxonomies =
			$blueprint['taxonomies'] ?? [];

		if ( ! empty( $taxonomies ) ) {

			WP_CLI::log( '' );
			WP_CLI::log( 'Taxonomies:' );

			foreach ( $taxonomies as $taxonomy ) {

				$slug =
					$taxonomy['slug'] ?? '';

				if ( ! $slug ) {
					continue;
				}

				WP_CLI::log(
					"- {$slug}"
				);
			}
		}

		$listings =
			$blueprint['listings'] ?? [];

		if ( ! empty( $listings ) ) {

			WP_CLI::log( '' );
			WP_CLI::log( 'Listings:' );

			foreach ( $listings as $listing ) {

				$title =
					$listing['title'] ?? '';

				if ( ! $title ) {
					continue;
				}

				WP_CLI::log(
					"- {$title}"
				);
			}
		}

		$archive =
			$blueprint['pages']['archive'] ?? [];

		if ( ! empty( $archive ) ) {

			$slug =
				$archive['slug'] ?? '';

			if ( $slug ) {

				WP_CLI::log( '' );
				WP_CLI::log(
					'Archive page:'
				);

				WP_CLI::log(
					"- /{$slug}/"
				);
			}
		}

		$content =
			$blueprint['content'] ?? [];

		if ( ! empty( $content ) ) {

			WP_CLI::log( '' );
			WP_CLI::log(
				'Demo content:'
			);

			foreach ( $content as $items ) {

				if ( ! is_array( $items ) ) {
					continue;
				}

				foreach ( $items as $item ) {

					$title =
						$item['title'] ?? '';

					if ( ! $title ) {
						continue;
					}

					WP_CLI::log(
						"- {$title}"
					);
				}
			}
		}
	}
}