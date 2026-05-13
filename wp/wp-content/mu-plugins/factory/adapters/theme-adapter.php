<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Factory_Theme_Adapter {

	public function register( array $blueprint ): void {
		// нічого
	}

	public function apply( array $blueprint ): void {

		if ( empty( $blueprint['theme'] ) ) {
			return;
		}

		$config = $blueprint['theme'];

		$slug = $config['slug'] ?? '';
		$path = $config['path'] ?? '';

		if ( ! $slug ) {
			return;
		}

		if ( wp_get_theme()->get_stylesheet() === $slug ) {
			$this->log( "Theme already active: {$slug}" );
			return;
		}

		if ( ! wp_get_theme( $slug )->exists() ) {

			if ( $path && file_exists( $path ) ) {
				$this->log( "Installing theme: {$slug}" );

				WP_CLI::runcommand(
					'theme install ' . escapeshellarg( $path ),
					[ 'launch' => false ]
				);
			} else {
				$this->warn( "Theme not found: {$slug}" );
				return;
			}
		}

		$this->log( "Activating theme: {$slug}" );

		WP_CLI::runcommand(
			'theme activate ' . escapeshellarg( $slug ),
			[ 'launch' => false ]
		);
	}

	public function plan( array $blueprint ): array {
		if ( empty( $blueprint['theme'] ) ) {
			return [];
		}

		$config = $blueprint['theme'];
		$slug   = $config['slug'] ?? '';
		$path   = $config['path'] ?? '';

		if ( ! $slug ) {
			return [
				[
					'action'  => 'error',
					'type'    => 'theme',
					'entity'  => '',
					'message' => 'Theme slug is missing.',
					'diff'    => [],
				],
			];
		}

		if ( wp_get_theme()->get_stylesheet() === $slug ) {
			return [
				[
					'action'  => 'skip',
					'type'    => 'theme',
					'entity'  => $slug,
					'message' => "Theme active: {$slug}",
					'diff'    => [],
				],
			];
		}

		if ( wp_get_theme( $slug )->exists() ) {
			return [
				[
					'action'  => 'update',
					'type'    => 'theme',
					'entity'  => $slug,
					'message' => "Activate theme: {$slug}",
					'diff'    => [
						'active_theme' => [
							'old' => wp_get_theme()->get_stylesheet(),
							'new' => $slug,
						],
					],
				],
			];
		}

		if ( $path && file_exists( $path ) ) {
			return [
				[
					'action'  => 'create',
					'type'    => 'theme',
					'entity'  => $slug,
					'message' => "Install theme: {$slug}",
					'diff'    => [
						'installed' => [
							'old' => false,
							'new' => true,
						],
						'source'    => [
							'value' => $path,
						],
					],
				],
			];
		}

		return [
			[
				'action'  => 'error',
				'type'    => 'theme',
				'entity'  => $slug,
				'message' => "Theme missing: {$slug}",
				'diff'    => [],
			],
		];
	}

	public function validate( array $blueprint ): array {

		$checks = [];

		if ( empty( $blueprint['theme'] ) ) {
			return $checks;
		}

		$slug = $blueprint['theme']['slug'] ?? '';

		if ( ! $slug ) {
			return $checks;
		}

		if ( wp_get_theme()->get_stylesheet() === $slug ) {
			$checks[] = [
				'status'  => 'ok',
				'message' => "Theme active: {$slug}",
			];
		} else {
			$checks[] = [
				'status'  => 'error',
				'message' => "Theme NOT active: {$slug}",
			];
		}

		return $checks;
	}

	private function log( $msg ) {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			WP_CLI::log( $msg );
		}
	}

	private function warn( $msg ) {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			WP_CLI::warning( $msg );
		}
	}
}
