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

	private function register_taxonomy( array $tax ): void {

		$slug       = $tax['slug'];
		$post_type  = $tax['post_type'];
		$label      = $tax['label'] ?? ucfirst( $slug );
		$singular   = $tax['singular'] ?? ucfirst( $slug );

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
	}

	private function sync_terms( array $tax ): void {

		$slug = $tax['slug'];

		foreach ( $tax['terms'] ?? [] as $term_name ) {

			if ( term_exists( $term_name, $slug ) ) {
				continue;
			}

			wp_insert_term( $term_name, $slug );
		}
	}
}