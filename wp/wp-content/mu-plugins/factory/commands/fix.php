<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Factory_Fix_Command {

	public function __invoke(): void {
		WP_CLI::log( 'Running smart fix...' );

		$blueprint = factory_get_blueprint();

		if ( ! $blueprint ) {
			WP_CLI::error( 'Blueprint not found.' );
			return;
		}

		$adapters = factory_get_adapters();

		$errors = [];

		// 1. Validate first
		foreach ( $adapters as $adapter ) {
			if ( ! method_exists( $adapter, 'validate' ) ) {
				continue;
			}

			$result = $adapter->validate( $blueprint );

			foreach ( $result as $check ) {
				if ( ( $check['status'] ?? '' ) === 'error' ) {
					$errors[] = [
						'adapter' => get_class( $adapter ),
						'message' => $check['message'],
					];
				}
			}
		}

		if ( empty( $errors ) ) {
			WP_CLI::success( 'Nothing to fix. State is valid.' );
			return;
		}

		WP_CLI::warning( 'Detected issues:' );

		foreach ( $errors as $error ) {
			WP_CLI::log( $error['adapter'] . ': ' . $error['message'] );
		}

		// 2. Fix only affected adapters
		foreach ( $adapters as $adapter ) {
			$class = get_class( $adapter );

			foreach ( $errors as $error ) {
				if ( $error['adapter'] === $class ) {
					if ( method_exists( $adapter, 'apply' ) ) {
						WP_CLI::log( "Fixing via {$class}..." );
						$adapter->apply( $blueprint );
					}
					break;
				}
			}
		}

		// 3. Re-validate
		WP_CLI::log( 'Re-validating...' );

		$has_errors = false;

		foreach ( $adapters as $adapter ) {
			if ( ! method_exists( $adapter, 'validate' ) ) {
				continue;
			}

			$result = $adapter->validate( $blueprint );

			foreach ( $result as $check ) {
				if ( ( $check['status'] ?? '' ) === 'error' ) {
					$has_errors = true;
					WP_CLI::error( $check['message'], false );
				}
			}
		}

		if ( ! $has_errors ) {
			WP_CLI::success( 'Fix completed. State is now valid.' );
		} else {
			WP_CLI::warning( 'Some issues remain after fix.' );
		}
	}
}