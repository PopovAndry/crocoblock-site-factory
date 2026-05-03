<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Factory_Content_Adapter {

	public function register( array $blueprint ): void {
		// Content is handled during apply/validate only.
	}

	public function apply( array $blueprint ): void {
		foreach ( $blueprint['content'] ?? [] as $post_type => $items ) {
			foreach ( $items as $item ) {
				$this->sync_post( $post_type, $item );
			}
		}
	}

	public function validate( array $blueprint ): array {
		$results = [];

		foreach ( $blueprint['content'] ?? [] as $post_type => $items ) {
			foreach ( $items as $item ) {
				$title = $item['title'] ?? '';

				if ( ! $title ) {
					continue;
				}

				$post = $this->find_post( $post_type, $item );

				if ( ! $post ) {
					$results[] = [
						'status'  => 'error',
						'message' => "Missing content item: {$post_type} → {$title}",
					];

					continue;
				}

				$results[] = [
					'status'  => 'ok',
					'message' => "Content exists: {$post_type} → {$title}",
				];

				foreach ( $item['meta'] ?? [] as $key => $expected_value ) {
					$actual_value = get_post_meta( $post->ID, $key, true );

					$results[] = [
						'status'  => (string) $actual_value === (string) $expected_value ? 'ok' : 'error',
						'message' => (string) $actual_value === (string) $expected_value
							? "Meta value ok: {$title}.{$key} = {$expected_value}"
							: "Meta mismatch: {$title}.{$key}. Expected {$expected_value}, got {$actual_value}",
					];
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

	private function sync_post( string $post_type, array $item ): void {
		if ( empty( $item['title'] ) ) {
			return;
		}

		$post = $this->find_post( $post_type, $item );

		$target_state = $this->get_target_post_state( $item );

		if ( ! $post ) {
			$post_id = $this->create_post( $post_type, $item );

			if ( $post_id ) {
				$this->sync_post_meta( $post_id, $item );
				$this->sync_post_terms( $post_id, $item );
				$this->log_success( "Created: {$item['title']}" );
			}

			return;
		}

		$current_state = $this->get_current_post_state( $post, $item );

		$diff = factory_diff_arrays( $current_state, $target_state );

		if ( empty( $diff ) ) {
			$this->log( "Post up-to-date: {$item['title']}" );
			return;
		}

		$this->update_post( $post->ID, $post_type, $item );
		$this->sync_post_meta( $post->ID, $item );
		$this->sync_post_terms( $post->ID, $item );

		$this->log_success( "Updated: {$item['title']}" );
	}

	private function create_post( string $post_type, array $item ): int {
		$post_id = wp_insert_post( [
			'post_type'    => $post_type,
			'post_title'   => $item['title'],
			'post_content' => $item['content'] ?? '',
			'post_status'  => 'publish',
		], true );

		if ( is_wp_error( $post_id ) ) {
			$this->warn( $post_id->get_error_message() );
			return 0;
		}

		update_post_meta( $post_id, '_factory_source_key', $this->get_source_key( $post_type, $item ) );

		return (int) $post_id;
	}

	private function update_post( int $post_id, string $post_type, array $item ): void {
		$result = wp_update_post( [
			'ID'           => $post_id,
			'post_type'    => $post_type,
			'post_title'   => $item['title'],
			'post_content' => $item['content'] ?? '',
			'post_status'  => 'publish',
		], true );

		if ( is_wp_error( $result ) ) {
			$this->warn( $result->get_error_message() );
			return;
		}

		update_post_meta( $post_id, '_factory_source_key', $this->get_source_key( $post_type, $item ) );
	}

	private function sync_post_meta( int $post_id, array $item ): void {
		foreach ( $item['meta'] ?? [] as $key => $value ) {
			update_post_meta( $post_id, $key, $value );
		}
	}

	private function sync_post_terms( int $post_id, array $item ): void {
		foreach ( $item['terms'] ?? [] as $taxonomy => $terms ) {
			if ( ! taxonomy_exists( $taxonomy ) ) {
				continue;
			}

			if ( ! is_array( $terms ) ) {
				$terms = [ $terms ];
			}

			wp_set_object_terms( $post_id, $terms, $taxonomy, false );
		}
	}

	private function find_post( string $post_type, array $item ): ?WP_Post {
		$source_key = $this->get_source_key( $post_type, $item );

		$posts = get_posts( [
			'post_type'   => $post_type,
			'post_status' => 'any',
			'meta_key'    => '_factory_source_key',
			'meta_value'  => $source_key,
			'numberposts' => 1,
		] );

		return $posts[0] ?? null;
	}

	private function get_current_post_state( WP_Post $post, array $item ): array {
		return [
			'title'   => $post->post_title,
			'content' => $post->post_content,
			'meta'    => $this->get_current_meta_state( $post->ID, $item['meta'] ?? [] ),
			'terms'   => $this->get_current_terms_state( $post->ID, $item['terms'] ?? [] ),
		];
	}

	private function get_target_post_state( array $item ): array {
		return [
			'title'   => $item['title'] ?? '',
			'content' => $item['content'] ?? '',
			'meta'    => $this->normalize_meta_state( $item['meta'] ?? [] ),
			'terms'   => $this->normalize_terms_state( $item['terms'] ?? [] ),
		];
	}

	private function get_current_meta_state( int $post_id, array $expected_meta ): array {
		$result = [];

		foreach ( $expected_meta as $key => $value ) {
			$result[ $key ] = get_post_meta( $post_id, $key, true );
		}

		return $this->normalize_meta_state( $result );
	}

	private function normalize_meta_state( array $meta ): array {
		$result = [];

		foreach ( $meta as $key => $value ) {
			$result[ $key ] = is_scalar( $value ) ? (string) $value : $value;
		}

		ksort( $result );

		return $result;
	}

	private function get_current_terms_state( int $post_id, array $expected_terms ): array {
		$result = [];

		foreach ( $expected_terms as $taxonomy => $terms ) {
			if ( ! taxonomy_exists( $taxonomy ) ) {
				continue;
			}

			$actual_terms = wp_get_object_terms( $post_id, $taxonomy, [
				'fields' => 'names',
			] );

			if ( is_wp_error( $actual_terms ) ) {
				continue;
			}

			sort( $actual_terms );

			$result[ $taxonomy ] = $actual_terms;
		}

		ksort( $result );

		return $result;
	}

	private function normalize_terms_state( array $terms_config ): array {
		$result = [];

		foreach ( $terms_config as $taxonomy => $terms ) {
			if ( ! is_array( $terms ) ) {
				$terms = [ $terms ];
			}

			sort( $terms );

			$result[ $taxonomy ] = $terms;
		}

		ksort( $result );

		return $result;
	}

	private function get_source_key( string $post_type, array $item ): string {
		return sanitize_title( $post_type . '-' . ( $item['title'] ?? '' ) );
	}

	private function log( string $message ): void {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			WP_CLI::log( $message );
		}
	}

	private function log_success( string $message ): void {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			WP_CLI::success( $message );
		}
	}

	private function warn( string $message ): void {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			WP_CLI::warning( $message );
		}
	}
}