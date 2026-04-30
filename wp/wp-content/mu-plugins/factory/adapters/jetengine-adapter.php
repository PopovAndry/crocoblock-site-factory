<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Factory_JetEngine_Adapter {

	private string $option_name = 'jet_engine_meta_boxes';

	public function register( array $blueprint ): void {
		// Runtime registration not needed here.
	}

	public function apply( array $blueprint ): void {
		if ( ! function_exists( 'jet_engine' ) ) {
			$this->log( 'JetEngine not active. Skipping.' );
			return;
		}

		$boxes = get_option( $this->option_name, [] );

		if ( ! is_array( $boxes ) ) {
			$boxes = [];
		}

		$boxes = $this->remove_old_factory_boxes( $boxes );

		foreach ( $blueprint['cpt'] ?? [] as $cpt ) {
			if ( empty( $cpt['slug'] ) ) {
				continue;
			}

			$boxes = $this->upsert_meta_box( $boxes, $cpt );
		}

		update_option( $this->option_name, $boxes );

		$this->log( 'JetEngine meta boxes synced.' );
	}

	public function validate( array $blueprint ): array {
		$checks = [];

		if ( ! function_exists( 'jet_engine' ) ) {
			return [
				[
					'status'  => 'warning',
					'message' => 'JetEngine not active',
				],
			];
		}

		$checks[] = [
			'status'  => 'ok',
			'message' => 'JetEngine active',
		];

		$boxes = get_option( $this->option_name, [] );

		foreach ( $blueprint['cpt'] ?? [] as $cpt ) {
			$slug   = $cpt['slug'] ?? '';
			$box_id = $this->get_box_id( $slug );

			$box = $this->find_box( $boxes, $box_id );

			if ( ! $box ) {
				$checks[] = [
					'status'  => 'error',
					'message' => "JetEngine meta box missing: {$box_id}",
				];

				continue;
			}

			$checks[] = [
				'status'  => 'ok',
				'message' => "JetEngine meta box exists: {$box_id}",
			];

			foreach ( $cpt['meta'] ?? [] as $meta ) {
				$key = $meta['key'] ?? '';

				if ( ! $key ) {
					continue;
				}

				$exists = false;

				foreach ( $box['meta_fields'] ?? [] as $field ) {
					if ( ( $field['name'] ?? '' ) === $key ) {
						$exists = true;
						break;
					}
				}

				$checks[] = [
					'status'  => $exists ? 'ok' : 'error',
					'message' => $exists
						? "JetEngine field exists: {$slug}.{$key}"
						: "JetEngine field missing: {$slug}.{$key}",
				];
			}
		}

		return $checks;
	}

	private function upsert_meta_box( array $boxes, array $cpt ): array {
		$post_type = $cpt['slug'];
		$box_id    = $this->get_box_id( $post_type );

		$box_index = null;

		foreach ( $boxes as $index => $box ) {
			if ( ( $box['id'] ?? '' ) === $box_id ) {
				$box_index = $index;
				break;
			}
		}

		$meta_fields = [];

		foreach ( $cpt['meta'] ?? [] as $meta ) {
			if ( empty( $meta['key'] ) ) {
				continue;
			}

			$meta_fields[] = [
				'name'        => $meta['key'],
				'title'       => $meta['label'] ?? $meta['key'],
				'type'        => $this->map_field_type( $meta['type'] ?? 'text' ),
				'object_type' => 'field',
				'width'       => '100%',
			];
		}

		$new_box = [
			'id'          => $box_id,
			'title'       => 'Factory: ' . ( $cpt['label'] ?? $post_type ),
			'args'        => [
				'object_type'       => 'post',
				'allowed_post_type' => [ $post_type ],
			],
			'meta_fields' => $meta_fields,
		];

		if ( $box_index === null ) {
			$boxes[] = $new_box;
			$this->log( "JetEngine meta box created: {$box_id}" );
		} else {
			$boxes[ $box_index ] = $new_box;
			$this->log( "JetEngine meta box updated: {$box_id}" );
		}

		return $boxes;
	}

	private function remove_old_factory_boxes( array $boxes ): array {
		return array_values(
			array_filter(
				$boxes,
				function ( $box ) {
					$title = $box['title'] ?? '';

					return ! str_starts_with( $title, 'Factory:' ) || isset( $box['args'] );
				}
			)
		);
	}

	private function find_box( array $boxes, string $box_id ): ?array {
		foreach ( $boxes as $box ) {
			if ( ( $box['id'] ?? '' ) === $box_id ) {
				return $box;
			}
		}

		return null;
	}

	private function get_box_id( string $post_type ): string {
		return 'factory_' . $post_type;
	}

	private function map_field_type( string $type ): string {
		return match ( $type ) {
			'number'  => 'number',
			'boolean' => 'switcher',
			'date'    => 'date',
			default   => 'text',
		};
	}

	private function log( string $message ): void {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			WP_CLI::log( $message );
		}
	}
}