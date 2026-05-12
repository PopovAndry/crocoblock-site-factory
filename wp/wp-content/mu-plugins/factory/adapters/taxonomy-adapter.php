<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Factory_Taxonomy_Adapter {

	public function register( array $blueprint ): void {

		foreach ( $blueprint['taxonomies'] ?? [] as $tax ) {

			if ( empty( $tax['slug'] ) || empty( $tax['post_type'] ) ) {
				continue;
			}

			$this->register_taxonomy( $tax, false );
		}
	}

	public function apply( array $blueprint ): void {

		foreach ( $blueprint['taxonomies'] ?? [] as $tax ) {

			if ( empty( $tax['slug'] ) || empty( $tax['post_type'] ) ) {
				continue;
			}

			$this->register_taxonomy( $tax, true );
			$this->sync_terms( $tax );
		}
	}

	public function plan( array $blueprint ): array {
		$plan = [];

		foreach ( $blueprint['taxonomies'] ?? [] as $taxonomy ) {
			$slug = $taxonomy['slug'] ?? '';

			if ( ! $slug ) {
				continue;
			}

			if ( ! taxonomy_exists( $slug ) ) {
				$plan[] = [
					'action'  => 'create',
					'type'    => 'taxonomy',
					'entity'  => $slug,
					'message' => "Create taxonomy: {$slug}",
				];

				continue;
			}

			$plan[] = [
				'action'  => 'skip',
				'type'    => 'taxonomy',
				'entity'  => $slug,
				'message' => "Taxonomy exists: {$slug}",
			];

			foreach ( $taxonomy['terms'] ?? [] as $term ) {
				$name = $this->normalize_term_name( $term );

				if ( ! $name ) {
					continue;
				}

				$existing = get_term_by(
					'name',
					$name,
					$slug
				);

				if ( ! $existing ) {
					$plan[] = [
						'action'  => 'create',
						'type'    => 'term',
						'entity'  => "{$slug} → {$name}",
						'message' => "Create term: {$slug} → {$name}",
					];

					continue;
				}

				$plan[] = [
					'action'  => 'skip',
					'type'    => 'term',
					'entity'  => "{$slug} → {$name}",
					'message' => "Term exists: {$slug} → {$name}",
				];
			}
		}

		return $plan;
	}

	public function validate( array $blueprint ): array {

		$checks = [];

		foreach ( $blueprint['taxonomies'] ?? [] as $tax ) {

			$slug = $tax['slug'] ?? '';

			if ( ! $slug ) {
				continue;
			}

			if ( taxonomy_exists( $slug ) ) {
				$checks[] = [
					'status'  => 'ok',
					'message' => "Taxonomy exists: {$slug}",
				];
			} else {
				$checks[] = [
					'status'  => 'error',
					'message' => "Taxonomy missing: {$slug}",
				];

				continue;
			}

			foreach ( $tax['terms'] ?? [] as $term ) {
				$term_name = $this->normalize_term_name( $term );

				if ( ! $term_name ) {
					continue;
				}

				$existing = get_term_by(
					'name',
					$term_name,
					$slug
				);

				$checks[] = [
					'status'  => $existing ? 'ok' : 'error',
					'message' => $existing
						? "Term exists: {$slug} → {$term_name}"
						: "Term missing: {$slug} → {$term_name}",
				];
			}
		}

		return $checks;
	}

	private function get_current_taxonomy_state( string $slug ): array {

		if ( ! taxonomy_exists( $slug ) ) {
			return [];
		}

		$object = get_taxonomy( $slug );

		if ( ! $object ) {
			return [];
		}

		return [
			'slug'         => $slug,
			'label'        => $object->label ?? '',
			'hierarchical' => (bool) ( $object->hierarchical ?? false ),
			'show_in_rest' => (bool) ( $object->show_in_rest ?? false ),
		];
	}

	private function get_current_terms_state( string $taxonomy ): array {

		if ( ! taxonomy_exists( $taxonomy ) ) {
			return [];
		}

		$terms = get_terms(
			[
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
				'fields'     => 'names',
			]
		);

		if ( is_wp_error( $terms ) || ! is_array( $terms ) ) {
			return [];
		}

		sort( $terms );

		return $terms;
	}

	private function register_taxonomy( array $tax, bool $log = false ): void {

		$slug      = $tax['slug'];
		$post_type = $tax['post_type'];
		$label     = $tax['label'] ?? ucfirst( $slug );
		$singular  = $tax['singular'] ?? ucfirst( $slug );

		$current = $this->get_current_taxonomy_state( $slug );

		$target = [
			'slug'         => $slug,
			'label'        => $label,
			'hierarchical' => true,
			'show_in_rest' => true,
		];

		$diff = factory_diff_arrays(
			$current,
			$target
		);

		if ( empty( $current ) || ! empty( $diff ) ) {
			register_taxonomy(
				$slug,
				$post_type,
				[
					'label'        => $label,
					'labels'       => [
						'name'          => $label,
						'singular_name' => $singular,
					],
					'public'       => true,
					'show_in_rest' => true,
					'hierarchical' => true,
				]
			);

			if ( $log && defined( 'WP_CLI' ) && WP_CLI ) {
				WP_CLI::log( "Taxonomy registered: {$slug}" );
			}

			return;
		}

		if ( $log && defined( 'WP_CLI' ) && WP_CLI ) {
			WP_CLI::log( "Taxonomy up-to-date: {$slug}" );
		}
	}

	private function sync_terms( array $tax ): void {

		$slug = $tax['slug'];

		if ( ! taxonomy_exists( $slug ) ) {
			return;
		}

		$current_terms = $this->get_current_terms_state( $slug );

		$target_terms = array_values(
			array_filter(
				array_map(
					[ $this, 'normalize_term_name' ],
					$tax['terms'] ?? []
				)
			)
		);

		sort( $target_terms );

		$term_diff = factory_diff_arrays(
			$current_terms,
			$target_terms
		);

		if ( empty( $term_diff ) ) {
			if ( defined( 'WP_CLI' ) && WP_CLI ) {
				WP_CLI::log( "Terms up-to-date: {$slug}" );
			}

			return;
		}

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			WP_CLI::log( "Syncing terms: {$slug}" );
		}

		foreach ( $target_terms as $term_name ) {
			$existing = get_term_by(
				'name',
				$term_name,
				$slug
			);

			if ( $existing ) {
				continue;
			}

			$result = wp_insert_term(
				$term_name,
				$slug
			);

			if ( is_wp_error( $result ) ) {
				if ( defined( 'WP_CLI' ) && WP_CLI ) {
					WP_CLI::warning(
						"Failed to create term {$slug} → {$term_name}: " .
						$result->get_error_message()
					);
				}

				continue;
			}

			if ( defined( 'WP_CLI' ) && WP_CLI ) {
				WP_CLI::log(
					"Term created: {$slug} → {$term_name}"
				);
			}
		}
	}

	private function normalize_term_name( $term ): string {

		if ( is_array( $term ) ) {
			$term = $term['name'] ?? '';
		}

		if ( ! is_string( $term ) ) {
			return '';
		}

		return trim( $term );
	}
}