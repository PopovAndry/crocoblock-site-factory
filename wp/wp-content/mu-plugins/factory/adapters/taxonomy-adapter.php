<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! empty( $item['terms'] ) ) {
	foreach ( $item['terms'] as $taxonomy => $terms ) {

		if ( is_string( $terms ) ) {
			$terms = [ $terms ];
		}

		wp_set_object_terms(
			$post_id,
			$terms,
			$taxonomy,
			false // ❗ overwrite
		);
	}
}

class Factory_Taxonomy_Adapter {

	public function register( array $blueprint ): void {

		foreach ( $blueprint['taxonomies'] ?? [] as $tax ) {

			if ( empty( $tax['slug'] ) || empty( $tax['post_type'] ) ) {
				continue;
			}

			$this->register_taxonomy( $tax );
		}
	}

    public function apply( array $blueprint ): void {

        foreach ( $blueprint['taxonomies'] ?? [] as $tax ) {

            if ( empty( $tax['slug'] ) || empty( $tax['post_type'] ) ) {
                continue;
            }

            $this->register_taxonomy( $tax );
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
			$name = is_array( $term ) ? ( $term['name'] ?? '' ) : $term;

			if ( ! $name ) {
				continue;
			}

			$existing = get_term_by( 'name', $name, $slug );

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

			foreach ( $tax['terms'] ?? [] as $term_name ) {

				$term = get_term_by( 'name', $term_name, $slug );

				$checks[] = [
					'status'  => $term ? 'ok' : 'error',
					'message' => $term
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

	$terms = get_terms( [
		'taxonomy'   => $taxonomy,
		'hide_empty' => false,
		'fields'     => 'names',
	] );

	if ( is_wp_error( $terms ) || ! is_array( $terms ) ) {
		return [];
	}

	sort( $terms );

	return $terms;
}

	private function register_taxonomy( array $tax ): void {

		$slug       = $tax['slug'];
		$post_type  = $tax['post_type'];
		$label      = $tax['label'] ?? ucfirst( $slug );
		$singular   = $tax['singular'] ?? ucfirst( $slug );

        $current = $this->get_current_taxonomy_state( $slug );

        $target = [
            'slug'         => $slug,
            'label'        => $label,
            'hierarchical' => true,
            'show_in_rest' => true,
        ];

        $diff = factory_diff_arrays( $current, $target );

        if ( empty( $current ) || ! empty( $diff ) ) {

            // if ( defined('WP_CLI') && WP_CLI ) {
            //     WP_CLI::log("Applying taxonomy: {$slug}");
            // }

            register_taxonomy( $slug, $post_type, [
                'label'        => $label,
                'labels'       => [
                    'name'          => $label,
                    'singular_name' => $singular,
                ],
                'public'       => true,
                'show_in_rest' => true,
                'hierarchical' => true,
            ] );

        } else {
            if ( defined('WP_CLI') && WP_CLI ) {
                WP_CLI::log("Taxonomy up-to-date: {$slug}");
            }
        }
	}

	private function sync_terms( array $tax ): void {

		$slug = $tax['slug'];

        $current_terms = $this->get_current_terms_state( $slug );
        $target_terms  = $tax['terms'] ?? [];

        sort( $target_terms );

        $term_diff = factory_diff_arrays( $current_terms, $target_terms );

        if ( ! empty( $term_diff ) && defined( 'WP_CLI' ) && WP_CLI ) {
            WP_CLI::log( "Terms diff detected: {$slug}" );
        }

        if ( empty( $term_diff ) ) {
            if ( defined('WP_CLI') && WP_CLI ) {
                WP_CLI::log("Terms up-to-date: {$slug}");
            }
            return;
        }

        if ( defined('WP_CLI') && WP_CLI ) {
            WP_CLI::log("Syncing terms: {$slug}");
        }
	}
}