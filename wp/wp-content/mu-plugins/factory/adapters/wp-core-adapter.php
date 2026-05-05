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
	}

	public function plan( array $blueprint ): array {
	$plan = [];

	foreach ( $blueprint['cpt'] ?? [] as $cpt ) {
		$slug = $cpt['slug'] ?? '';

		if ( ! $slug ) {
			$plan[] = [
				'action'  => 'error',
				'type'    => 'cpt',
				'entity'  => 'unknown',
				'message' => 'CPT slug is missing.',
			];

			continue;
		}

		if ( ! post_type_exists( $slug ) ) {
			$plan[] = [
				'action'  => 'create',
				'type'    => 'cpt',
				'entity'  => $slug,
				'message' => "Create CPT: {$slug}",
			];
		} else {
			$plan[] = [
				'action'  => 'skip',
				'type'    => 'cpt',
				'entity'  => $slug,
				'message' => "CPT up-to-date: {$slug}",
			];
		}

		foreach ( $cpt['meta'] ?? [] as $meta ) {
			$key = $meta['key'] ?? '';

			if ( ! $key ) {
				$plan[] = [
					'action'  => 'warning',
					'type'    => 'meta',
					'entity'  => $slug,
					'message' => "Meta key missing for CPT: {$slug}",
				];

				continue;
			}

			$plan[] = [
				'action'  => 'skip',
				'type'    => 'meta',
				'entity'  => "{$slug}.{$key}",
				'message' => "Meta declared: {$slug}.{$key}",
			];
		}
	}

	return $plan;
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

			foreach ( $cpt['meta'] ?? [] as $meta ) {
				$key = $meta['key'] ?? '';

				$results[] = [
					'status'  => $key ? 'ok' : 'warning',
					'message' => $key
						? "Meta declared: {$slug}.{$key}"
						: "Meta key missing for CPT: {$slug}",
				];
			}
		}

		return $results;
	}

	private function get_current_cpt_state( string $slug ): array {
		if ( ! post_type_exists( $slug ) ) {
			return [];
		}

		$object = get_post_type_object( $slug );

		if ( ! $object ) {
			return [];
		}

		return [
			'slug'     => $slug,
			'label'    => $object->label ?? '',
			'supports' => get_all_post_type_supports( $slug ),
		];
	}

	private function register_cpt( array $cpt ): void {
		if ( empty( $cpt['slug'] ) ) {
			return;
		}

		$slug = $cpt['slug'];

		$current = $this->get_current_cpt_state( $slug );

		$target = [
			'slug'     => $slug,
			'label'    => $cpt['label'] ?? ucfirst( $slug ),
			'supports' => array_fill_keys( $cpt['supports'] ?? [ 'title', 'editor' ], true ),
		];

		$diff = factory_diff_arrays( $current, $target );

		register_post_type( $slug, [
			'label'              => $cpt['label'] ?? ucfirst( $slug ),
			'public'             => true,
			'show_in_rest'       => true,
			'supports'           => $cpt['supports'] ?? [ 'title', 'editor' ],
			'menu_icon'          => 'dashicons-admin-home',
			'has_archive'        => true,
			'rewrite'            => [
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
}