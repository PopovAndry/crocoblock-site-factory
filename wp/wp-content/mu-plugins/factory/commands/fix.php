<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Factory_Fix_Command {

	public function __invoke(): void {
		WP_CLI::log( 'Running smart fix v2...' );

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

		WP_CLI::warning( 'Detected broken adapters:' );

		foreach ( $failed_adapters as $class => $messages ) {
			WP_CLI::log( "- {$class}" );

			foreach ( $messages as $message ) {
				WP_CLI::log( "  {$message}" );
			}
		}

		WP_CLI::log( 'Applying only affected adapters...' );

		foreach ( $adapters as $adapter ) {
			$class = get_class( $adapter );

			if ( ! isset( $failed_adapters[ $class ] ) ) {
				WP_CLI::log( "Skipping {$class}" );
				continue;
			}

			if ( ! method_exists( $adapter, 'apply' ) ) {
				continue;
			}

			WP_CLI::log( "Fixing via {$class}..." );
			$adapter->apply( $blueprint );
		}

		flush_rewrite_rules();

		WP_CLI::log( 'Re-validating...' );

		$remaining_errors = $this->get_failed_adapters( $adapters, $blueprint );

		if ( empty( $remaining_errors ) ) {
			WP_CLI::success( 'Fix v2 completed. State is now valid.' );
			return;
		}

		WP_CLI::warning( 'Some issues remain after fix:' );

		foreach ( $remaining_errors as $class => $messages ) {
			WP_CLI::log( "- {$class}" );

			foreach ( $messages as $message ) {
				WP_CLI::log( "  {$message}" );
			}
		}
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
}