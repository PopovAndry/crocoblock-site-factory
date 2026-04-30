<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const FACTORY_BLUEPRINT_PATH   = '/var/www/blueprints/real-estate.json';
const FACTORY_BLUEPRINT_OPTION = 'factory_blueprint';

require_once __DIR__ . '/adapters/plugin-adapter.php';
require_once __DIR__ . '/adapters/wp-core-adapter.php';
require_once __DIR__ . '/adapters/theme-adapter.php';
require_once __DIR__ . '/adapters/jetengine-adapter.php';
require_once __DIR__ . '/adapters/jetengine-listing-adapter.php';
require_once __DIR__ . '/adapters/render-adapter.php';
require_once __DIR__ . '/adapters/single-adapter.php';
require_once __DIR__ . '/adapters/taxonomy-adapter.php';

require_once __DIR__ . '/blueprint/blueprint-normalizer.php';
require_once __DIR__ . '/blueprint/blueprint-preset-manager.php';
require_once __DIR__ . '/ai/blueprint-generator.php';

function factory_get_blueprint(): array {
	$blueprint = get_option( FACTORY_BLUEPRINT_OPTION );

	if ( is_array( $blueprint ) ) {
		return $blueprint;
	}

	if ( ! file_exists( FACTORY_BLUEPRINT_PATH ) ) {
		return [];
	}

	$decoded = json_decode( file_get_contents( FACTORY_BLUEPRINT_PATH ), true );

	return is_array( $decoded ) ? $decoded : [];
}

function factory_get_adapters(): array {
	return [
		new Factory_Plugin_Adapter(),
		new Factory_Theme_Adapter(),
		new Factory_Taxonomy_Adapter(),
		new Factory_WP_Core_Adapter(),
		new Factory_JetEngine_Adapter(),
		new Factory_JetEngine_Listing_Adapter(),
		new Factory_Render_Adapter(),
		new Factory_Single_Adapter(),
	];
}

add_action( 'init', function () {
	$blueprint = factory_get_blueprint();

	foreach ( factory_get_adapters() as $adapter ) {
		$adapter->register( $blueprint );
	}
} );

function factory_apply_blueprint( array $blueprint ): void {
	update_option( FACTORY_BLUEPRINT_OPTION, $blueprint );

	$permalink = $blueprint['site']['permalink'] ?? '/%postname%/';
	update_option( 'permalink_structure', $permalink );

	foreach ( factory_get_adapters() as $adapter ) {
		$adapter->apply( $blueprint );
	}

	flush_rewrite_rules();
}

function factory_validate_blueprint_state( array $blueprint, bool $cli_output = true ): array {
	$report = [
		'timestamp' => current_time( 'mysql' ),
		'status'    => 'ok',
		'checks'    => [],
	];

	foreach ( factory_get_adapters() as $adapter ) {
		if ( ! method_exists( $adapter, 'validate' ) ) {
			continue;
		}

		$results = $adapter->validate( $blueprint );

		foreach ( $results as $line ) {
			$report['checks'][] = $line;

			if ( $line['status'] === 'error' ) {
				$report['status'] = 'error';
			}

			if ( ! $cli_output || ! defined( 'WP_CLI' ) || ! WP_CLI ) {
				continue;
			}

			if ( $line['status'] === 'ok' ) {
				WP_CLI::success( $line['message'] );
			} elseif ( $line['status'] === 'warning' ) {
				WP_CLI::warning( $line['message'] );
			} else {
				WP_CLI::error( $line['message'], false );
			}
		}
	}

	$upload_dir = wp_upload_dir();
	$file_path  = $upload_dir['basedir'] . '/factory-report.json';

	file_put_contents(
		$file_path,
		json_encode( $report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE )
	);

	if ( $cli_output && defined( 'WP_CLI' ) && WP_CLI ) {
		WP_CLI::log( "Report saved: {$file_path}" );
	}

	return $report;
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {

	// 🔥 AI
	WP_CLI::add_command( 'factory ai', function ( $args ) {
		$prompt = $args[0] ?? '';

		if ( ! $prompt ) {
			WP_CLI::error( 'Provide prompt.' );
		}

		try {
			$generator = new Factory_AI_Blueprint_Generator();

			$generator->generate_from_prompt( $prompt );

			WP_CLI::success( 'AI blueprint generated.' );
			WP_CLI::log( 'Saved to: ' . $generator->get_target_path() );
			WP_CLI::log( 'Next: wp factory apply ' . $generator->get_target_path() );
		} catch ( Throwable $e ) {
			WP_CLI::error( $e->getMessage() );
		}
	} );

	// MOCK
	WP_CLI::add_command( 'factory generate', function () {
		try {
			$generator = new Factory_AI_Blueprint_Generator();

			$generator->generate();

			WP_CLI::success( 'Blueprint generated.' );
			WP_CLI::log( 'Saved to: ' . $generator->get_target_path() );
			WP_CLI::log( 'Next: wp factory apply ' . $generator->get_target_path() );
		} catch ( Throwable $e ) {
			WP_CLI::error( $e->getMessage() );
		}
	} );

	// APPLY
	WP_CLI::add_command( 'factory apply', function ( $args ) {
		$path = $args[0] ?? FACTORY_BLUEPRINT_PATH;

		if ( ! file_exists( $path ) ) {
			WP_CLI::error( "Blueprint file not found: {$path}" );
		}

		$blueprint = json_decode( file_get_contents( $path ), true );

		if ( ! is_array( $blueprint ) ) {
			WP_CLI::error( 'Invalid blueprint JSON.' );
		}

		factory_apply_blueprint( $blueprint );



		WP_CLI::success( "Factory blueprint applied: {$path}" );
	} );

	// VALIDATE
	WP_CLI::add_command( 'factory validate', function () {
		$blueprint = factory_get_blueprint();

		$report = [
			'timestamp' => current_time( 'mysql' ),
			'status'    => 'ok',
			'checks'    => [],
		];

		foreach ( factory_get_adapters() as $adapter ) {
			if ( ! method_exists( $adapter, 'validate' ) ) {
				continue;
			}

			$results = $adapter->validate( $blueprint );

			foreach ( $results as $line ) {
				$report['checks'][] = $line;

				if ( $line['status'] === 'ok' ) {
					WP_CLI::success( $line['message'] );
				} elseif ( $line['status'] === 'warning' ) {
					WP_CLI::warning( $line['message'] );
				} else {
					WP_CLI::error( $line['message'], false );
					$report['status'] = 'error';
				}
			}
		}

		$upload_dir = wp_upload_dir();
		$file_path  = $upload_dir['basedir'] . '/factory-report.json';

		file_put_contents(
			$file_path,
			json_encode( $report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE )
		);

		WP_CLI::log( "Report saved: {$file_path}" );
		WP_CLI::success( 'Validation complete.' );
	} );

	// RESET
	WP_CLI::add_command( 'factory reset', function () {
		$blueprint = factory_get_blueprint();

		foreach ( $blueprint['cpt'] ?? [] as $cpt ) {
			$posts = get_posts( [
				'post_type'   => $cpt['slug'],
				'post_status' => 'any',
				'numberposts' => -1,
			] );

			foreach ( $posts as $post ) {
				wp_delete_post( $post->ID, true );
				WP_CLI::log( "Deleted: {$post->post_title}" );
			}
		}

		delete_option( FACTORY_BLUEPRINT_OPTION );
		flush_rewrite_rules();

		WP_CLI::success( 'Factory reset complete.' );
	} );

	// FIX
	WP_CLI::add_command( 'factory fix', function () {
		$upload_dir = wp_upload_dir();
		$file_path  = $upload_dir['basedir'] . '/factory-report.json';

		if ( ! file_exists( $file_path ) ) {
			WP_CLI::error( 'Report not found. Run validate first.' );
		}

		$report = json_decode( file_get_contents( $file_path ), true );

		if ( ! is_array( $report ) ) {
			WP_CLI::error( 'Invalid report format.' );
		}

		if ( ( $report['status'] ?? 'error' ) === 'ok' ) {
			WP_CLI::success( 'No issues found. Nothing to fix.' );
			return;
		}

		WP_CLI::log( 'Fixing issues based on blueprint...' );

		$blueprint = factory_get_blueprint();

		foreach ( factory_get_adapters() as $adapter ) {
			$adapter->apply( $blueprint );
		}

		WP_CLI::success( 'Fix attempt completed.' );
	} );

	WP_CLI::add_command( 'factory preset', function ( $args ) {
	$preset = $args[0] ?? '';

	if ( ! $preset ) {
		WP_CLI::error( 'Provide preset slug.' );
	}

	try {
		$manager   = new Factory_Blueprint_Preset_Manager();
		$blueprint = $manager->load_preset( $preset );
		$path      = $manager->save_generated( $preset, $blueprint );

		WP_CLI::success( "Preset generated: {$preset}" );
		WP_CLI::log( "Saved to: {$path}" );
		WP_CLI::log( "Next: wp factory apply {$path}" );
	} catch ( Throwable $e ) {
		WP_CLI::error( $e->getMessage() );
	}
	} );

	WP_CLI::add_command( 'factory build', function ( $args ) {
	$prompt = $args[0] ?? '';

	if ( ! $prompt ) {
		WP_CLI::error( 'Provide prompt.' );
	}

	try {
		WP_CLI::log( 'Generating blueprint...' );

		$generator = new Factory_AI_Blueprint_Generator();
		$blueprint = $generator->generate_from_prompt( $prompt );

		if ( $generator->was_loaded_from_cache() ) {
			WP_CLI::log( 'Blueprint loaded from cache.' );
		} else {
			WP_CLI::log( 'Blueprint generated via AI and saved to cache.' );
		}

		WP_CLI::log( 'Applying blueprint...' );

		factory_apply_blueprint( $blueprint );

		WP_CLI::log( 'Validating...' );

		$report = factory_validate_blueprint_state( $blueprint );

		if ( ( $report['status'] ?? 'error' ) === 'ok' ) {
			WP_CLI::success( 'Build complete. State is valid.' );
			return;
		}

		WP_CLI::warning( 'Validation failed. Running deterministic fix...' );

		foreach ( factory_get_adapters() as $adapter ) {
			$adapter->apply( $blueprint );
		}

		flush_rewrite_rules();

		WP_CLI::log( 'Re-validating after fix...' );

		$second_report = factory_validate_blueprint_state( $blueprint );

		if ( ( $second_report['status'] ?? 'error' ) === 'ok' ) {
			WP_CLI::success( 'Build complete after fix. State is valid.' );
			return;
		}

		WP_CLI::error( 'Build finished, but validation still has errors.' );

	} catch ( Throwable $e ) {
		WP_CLI::error( $e->getMessage() );
	}
} );
}