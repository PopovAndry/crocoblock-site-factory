<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Factory_Fix_Command {

	public function __invoke(): void {
		WP_CLI::log( 'Running smart fix v3...' );

		$blueprint = factory_get_blueprint();

		if ( empty( $blueprint ) ) {
			WP_CLI::error( 'Blueprint not found.' );
		}

		$adapters = factory_get_adapters();

		$failed_adapters = $this->get_failed_adapters( $adapters, $blueprint );

		if ( empty( $failed_adapters ) ) {
			WP_CLI::success( 'Nothing to fix. State is valid.' );
			return;
		}

		$this->log_failed_adapters( $failed_adapters );

		$adapters_to_apply = $this->expand_dependencies( array_keys( $failed_adapters ) );

		WP_CLI::log( 'Applying affected adapters with dependencies...' );

		foreach ( $adapters as $adapter ) {
			$class = get_class( $adapter );

			if ( ! in_array( $class, $adapters_to_apply, true ) ) {
				WP_CLI::log( "Skipping {$class}" );
				continue;
			}

			if ( ! method_exists( $adapter, 'apply' ) ) {
				continue;
			}

			if ( method_exists( $adapter, 'plan' ) ) {
				$plan = $adapter->plan( $blueprint );

				$needs_apply = false;

				foreach ( $plan as $item ) {
					if ( in_array( $item['action'] ?? '', [ 'create', 'update' ], true ) ) {
						$needs_apply = true;
						break;
					}
				}

				if ( ! $needs_apply ) {
					WP_CLI::log( "Skipping {$class} (dependency only, no changes)" );
					continue;
				}
			}

			WP_CLI::log( "Fixing via {$class}..." );
			$adapter->apply( $blueprint );
		}

		flush_rewrite_rules();

		WP_CLI::log( 'Re-validating...' );

		$remaining_errors = $this->get_failed_adapters( $adapters, $blueprint );

		if ( empty( $remaining_errors ) ) {
			WP_CLI::success( 'Fix v3 completed. State is now valid.' );
			return;
		}

		WP_CLI::warning( 'Some issues remain after fix:' );
		$this->log_failed_adapters( $remaining_errors );
	}

	private function get_failed_adapters( array $adapters, array $blueprint ): array {
		$failed = [];

		foreach ( $adapters as $adapter ) {
			if ( ! method_exists( $adapter, 'validate' ) ) {
				continue;
			}

			$class   = get_class( $adapter );
			$results = $adapter->validate( $blueprint );

			foreach ( $results as $line ) {
				if ( ( $line['status'] ?? '' ) !== 'error' ) {
					continue;
				}

				if ( ! isset( $failed[ $class ] ) ) {
					$failed[ $class ] = [];
				}

				$failed[ $class ][] = $line['message'] ?? 'Unknown error';
			}
		}

		return $failed;
	}

	private function expand_dependencies( array $failed_classes ): array {
		$dependencies = [
			Factory_Content_Adapter::class => [
				Factory_Taxonomy_Adapter::class,
				Factory_WP_Core_Adapter::class,
				Factory_Content_Adapter::class,
			],

			Factory_JetEngine_Adapter::class => [
				Factory_WP_Core_Adapter::class,
				Factory_JetEngine_Adapter::class,
			],

			Factory_JetEngine_Listing_Adapter::class => [
				Factory_WP_Core_Adapter::class,
				Factory_JetEngine_Adapter::class,
				Factory_JetEngine_Listing_Adapter::class,
			],

			Factory_Render_Adapter::class => [
				Factory_JetEngine_Listing_Adapter::class,
				Factory_Render_Adapter::class,
			],

			Factory_Single_Adapter::class => [
				Factory_WP_Core_Adapter::class,
				Factory_Single_Adapter::class,
			],
		];

		$result = [];

		foreach ( $failed_classes as $class ) {
			if ( isset( $dependencies[ $class ] ) ) {
				$result = array_merge( $result, $dependencies[ $class ] );
			} else {
				$result[] = $class;
			}
		}

		return array_values( array_unique( $result ) );
	}

	private function log_failed_adapters( array $failed_adapters ): void {
		WP_CLI::warning( 'Detected broken adapters:' );

		foreach ( $failed_adapters as $class => $messages ) {
			WP_CLI::log( "- {$class}" );

			foreach ( $messages as $message ) {
				WP_CLI::log( "  {$message}" );
			}
		}
	}
}