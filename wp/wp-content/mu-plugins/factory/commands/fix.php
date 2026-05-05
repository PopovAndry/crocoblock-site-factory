<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Factory_Fix_Command {

	public function __invoke( array $args = [], array $assoc_args = [] ): void {
		WP_CLI::log( 'Running smart fix v4 (plan-based)...' );

		$blueprint = factory_get_blueprint();

		if ( empty( $blueprint ) ) {
			WP_CLI::error( 'Blueprint not found.' );
		}

		$only = $assoc_args['only'] ?? null;

		$only_map = [
			'plugins'  => Factory_Plugin_Adapter::class,
			'theme'    => Factory_Theme_Adapter::class,
			'taxonomy' => Factory_Taxonomy_Adapter::class,
			'core'     => Factory_WP_Core_Adapter::class,
			'meta'     => Factory_JetEngine_Adapter::class,
			'listings' => Factory_JetEngine_Listing_Adapter::class,
			'render'   => Factory_Render_Adapter::class,
			'single'   => Factory_Single_Adapter::class,
			'content'  => Factory_Content_Adapter::class,
		];

		if ( $only && isset( $only_map[ $only ] ) ) {
			$only = $only_map[ $only ];
		}

		$adapters = factory_get_adapters();

		$dry_run    = new Factory_Dry_Run_Command();
		$plan_items = $dry_run->get_plan_items( $blueprint );

		$changes = [];

		foreach ( $plan_items as $item ) {
			$is_change = in_array( $item['action'] ?? '', [ 'create', 'update', 'error' ], true );

			if ( ! $is_change ) {
				continue;
			}

			if ( $only && ( $item['adapter_class'] ?? '' ) !== $only ) {
				continue;
			}

			$changes[] = $item;
		}

		if ( empty( $changes ) ) {
			if ( $only ) {
				WP_CLI::success( "Nothing to fix for: {$only}" );
			} else {
				WP_CLI::success( 'Nothing to fix. State is valid.' );
			}

			return;
		}

		$this->log_plan_changes( $changes );

		$changed_adapter_classes = [];

		foreach ( $changes as $item ) {
			if ( ! empty( $item['adapter_class'] ) ) {
				$changed_adapter_classes[] = $item['adapter_class'];
			}
		}

		$adapters_to_apply = $this->expand_dependencies( array_unique( $changed_adapter_classes ) );

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
				$plan        = $adapter->plan( $blueprint );
				$needs_apply = false;

				foreach ( $plan as $item ) {
					if ( in_array( $item['action'] ?? '', [ 'create', 'update', 'error' ], true ) ) {
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

		$remaining_changes = [];

		$plan_items_after_fix = $dry_run->get_plan_items( $blueprint );

		foreach ( $plan_items_after_fix as $item ) {
			if ( in_array( $item['action'] ?? '', [ 'create', 'update', 'error' ], true ) ) {
				$remaining_changes[] = $item;
			}
		}

		if ( empty( $remaining_changes ) ) {
			WP_CLI::success( 'Fix v4 completed. State is now valid.' );
			return;
		}

		WP_CLI::warning( 'Some issues remain after fix:' );
		$this->log_plan_changes( $remaining_changes );
	}

	private function expand_dependencies( array $changed_classes ): array {
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

		foreach ( $changed_classes as $class ) {
			if ( isset( $dependencies[ $class ] ) ) {
				$result = array_merge( $result, $dependencies[ $class ] );
			} else {
				$result[] = $class;
			}
		}

		return array_values( array_unique( $result ) );
	}

	private function log_plan_changes( array $changes ): void {
		WP_CLI::warning( 'Detected changes from plan:' );

		foreach ( $changes as $item ) {
			$adapter = $item['adapter'] ?? ( $item['adapter_class'] ?? 'Unknown adapter' );
			$message = $item['message'] ?? 'Unknown change';

			WP_CLI::log( "- {$adapter}" );
			WP_CLI::log( "  {$message}" );
		}
	}
}