<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Factory_WP_Core_Adapter {

	public function register( array $blueprint ): void {
		foreach ( $blueprint['cpt'] ?? [] as $cpt ) {
			$this->register_cpt( $cpt );
			$this->register_meta_fields( $cpt );
		}
	}

	public function apply( array $blueprint ): void {
		$this->register( $blueprint );

		foreach ( $blueprint['content'] ?? [] as $post_type => $items ) {
			foreach ( $items as $item ) {
				$this->create_or_skip_post( $post_type, $item );
			}
		}
	}

	public function validate( array $blueprint ): array {
		$results = [];

		if ( empty( $blueprint ) ) {
			return [
				[
					'status'  => 'error',
					'message' => 'No active blueprint found.',
				],
			];
		}

		foreach ( $blueprint['cpt'] ?? [] as $cpt ) {
			$slug = $cpt['slug'] ?? '';

			if ( ! $slug ) {
				$results[] = [
					'status'  => 'error',
					'message' => 'CPT slug is missing.',
				];
				continue;
			}

			$results[] = [
				'status'  => post_type_exists( $slug ) ? 'ok' : 'error',
				'message' => post_type_exists( $slug )
					? "CPT exists: {$slug}"
					: "CPT missing: {$slug}",
			];

			$count = wp_count_posts( $slug );

			$results[] = [
				'status'  => 'ok',
				'message' => "Published {$slug}: " . ( $count->publish ?? 0 ),
			];

			foreach ( $cpt['meta'] ?? [] as $meta ) {
				$key = $meta['key'] ?? '';

				$results[] = [
					'status'  => $key ? 'ok' : 'warning',
					'message' => $key
						? "Meta declared: {$slug}.{$key}"
						: "Meta key missing for CPT: {$slug}",
				];
			}

			$expected_items = $blueprint['content'][ $slug ] ?? [];

			foreach ( $expected_items as $item ) {
				$title      = $item['title'] ?? '';
				$source_key = sanitize_title( $slug . '-' . $title );

				$posts = get_posts( [
					'post_type'   => $slug,
					'post_status' => 'publish',
					'meta_key'    => '_factory_source_key',
					'meta_value'  => $source_key,
					'numberposts' => 1,
				] );

				if ( empty( $posts ) ) {
					$results[] = [
						'status'  => 'error',
						'message' => "Missing content item: {$slug} → {$title}",
					];

					continue;
				}

				$post = $posts[0];

				$results[] = [
					'status'  => 'ok',
					'message' => "Content exists: {$slug} → {$title}",
				];

				foreach ( $item['meta'] ?? [] as $key => $expected_value ) {
					$actual_value = get_post_meta( $post->ID, $key, true );

					if ( (string) $actual_value === (string) $expected_value ) {
						$results[] = [
							'status'  => 'ok',
							'message' => "Meta value ok: {$title}.{$key} = {$expected_value}",
						];
					} else {
						$results[] = [
							'status'  => 'error',
							'message' => "Meta mismatch: {$title}.{$key}. Expected {$expected_value}, got {$actual_value}",
						];
					}
				}
				foreach ( $item['terms'] ?? [] as $taxonomy => $expected_terms ) {
					if ( ! taxonomy_exists( $taxonomy ) ) {
						$results[] = [
							'status'  => 'error',
							'message' => "Taxonomy missing for content item: {$title}.{$taxonomy}",
						];

						continue;
					}

					if ( ! is_array( $expected_terms ) ) {
						$expected_terms = [ $expected_terms ];
					}

					$actual_terms = wp_get_object_terms( $post->ID, $taxonomy, [
						'fields' => 'names',
					] );

					if ( is_wp_error( $actual_terms ) ) {
						$results[] = [
							'status'  => 'error',
							'message' => "Cannot read terms: {$title}.{$taxonomy}",
						];

						continue;
					}

					sort( $expected_terms );
					sort( $actual_terms );

					$results[] = [
						'status'  => $actual_terms === $expected_terms ? 'ok' : 'error',
						'message' => $actual_terms === $expected_terms
							? "Terms ok: {$title}.{$taxonomy} = " . implode( ', ', $expected_terms )
							: "Terms mismatch: {$title}.{$taxonomy}. Expected " . implode( ', ', $expected_terms ) . ', got ' . implode( ', ', $actual_terms ),
					];
				}
			}
		}

		return $results;
	}

private function register_cpt( array $cpt ): void {

	if ( empty( $cpt['slug'] ) ) {
		return;
	}

	$slug = $cpt['slug'];

	register_post_type( $slug, [
		'label'           => $cpt['label'] ?? ucfirst( $slug ),
		'public'          => true,
		'show_in_rest'    => true,
		'supports'        => $cpt['supports'] ?? [ 'title', 'editor' ],
		'menu_icon'       => 'dashicons-admin-home',
		'has_archive'     => true,
		'rewrite'         => [
			'slug'       => $slug,
			'with_front' => false,
		],

		'publicly_queryable' => true,
		'show_ui'            => true,
	] );
}

	private function register_meta_fields( array $cpt ): void {
		if ( empty( $cpt['slug'] ) ) {
			return;
		}

		foreach ( $cpt['meta'] ?? [] as $meta ) {
			if ( empty( $meta['key'] ) || empty( $meta['type'] ) ) {
				continue;
			}

			register_post_meta( $cpt['slug'], $meta['key'], [
				'type'         => $meta['type'],
				'single'       => true,
				'show_in_rest' => true,
			] );
		}
	}

private function create_or_skip_post( string $post_type, array $item ): void {
	if ( empty( $item['title'] ) ) {
		return;
	}

	$source_key = sanitize_title( $post_type . '-' . $item['title'] );

	$existing = get_posts( [
		'post_type'   => $post_type,
		'post_status' => 'any',
		'meta_key'    => '_factory_source_key',
		'meta_value'  => $source_key,
		'numberposts' => 1,
		'fields'      => 'ids',
	] );

	$post_data = [
		'post_type'    => $post_type,
		'post_title'   => $item['title'],
		'post_content' => $item['content'] ?? '',
		'post_status'  => 'publish',
	];

	if ( $existing ) {
		$post_data['ID'] = $existing[0];
		$post_id = wp_update_post( $post_data, true );
		$action  = 'Updated';
	} else {
		$post_id = wp_insert_post( $post_data, true );
		$action  = 'Created';
	}

	if ( is_wp_error( $post_id ) ) {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			WP_CLI::warning( $post_id->get_error_message() );
		}
		return;
	}

	update_post_meta( $post_id, '_factory_source_key', $source_key );

	foreach ( $item['meta'] ?? [] as $key => $value ) {
		update_post_meta( $post_id, $key, $value );
	}

	foreach ( $item['terms'] ?? [] as $taxonomy => $terms ) {
	if ( ! taxonomy_exists( $taxonomy ) ) {
		continue;
	}

	if ( ! is_array( $terms ) ) {
		$terms = [ $terms ];
	}

	wp_set_object_terms( $post_id, $terms, $taxonomy, false );
}

	if ( defined( 'WP_CLI' ) && WP_CLI ) {
		WP_CLI::success( "{$action}: {$item['title']}" );
	}
}
}