<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Factory_Dry_Run_Command {

	public function __invoke( array $args ): void {
		$path = $args[0] ?? FACTORY_BLUEPRINT_PATH;

		if ( ! file_exists( $path ) ) {
			WP_CLI::error( "Blueprint file not found: {$path}" );
		}

		$blueprint = json_decode( file_get_contents( $path ), true );

		if ( ! is_array( $blueprint ) ) {
			WP_CLI::error( 'Invalid blueprint JSON.' );
		}

		WP_CLI::log( 'Factory dry-run v2 started.' );
		WP_CLI::log( "Blueprint: {$path}" );
		WP_CLI::log( 'No changes will be applied.' );
		WP_CLI::log( '--- PLAN ---' );

		$has_changes = false;

		foreach ( factory_get_adapters() as $adapter ) {

        if ( method_exists( $adapter, 'plan' ) ) {
	$class = get_class( $adapter );
	$plan  = $adapter->plan( $blueprint );

	WP_CLI::log( $class );

	foreach ( $plan as $item ) {
		$action  = $item['action'] ?? 'unknown';
		$message = $item['message'] ?? '';

		if ( $action === 'create' ) {
			$has_changes = true;
			WP_CLI::log( "  + {$message}" );
			continue;
		}

		if ( $action === 'update' ) {
			$has_changes = true;
			WP_CLI::log( "  ~ {$message}" );
			continue;
		}

		WP_CLI::log( "  = {$message}" );
	}

	WP_CLI::log( '---' );
	continue;
}

			if ( ! method_exists( $adapter, 'validate' ) ) {
				continue;
			}

			$class   = get_class( $adapter );
			$results = $adapter->validate( $blueprint );

			WP_CLI::log( $class );

			foreach ( $results as $line ) {
				$status  = $line['status'] ?? 'unknown';
				$message = $line['message'] ?? '';

				if ( $status === 'ok' ) {
					WP_CLI::log( "  = {$message}" );
					continue;
				}

				if ( $status === 'warning' ) {
					WP_CLI::warning( "  ! {$message}" );
					continue;
				}

				$has_changes = true;
				WP_CLI::log( "  ~ WOULD FIX: {$message}" );
			}

			WP_CLI::log( '---' );
		}

		if ( $has_changes ) {
			WP_CLI::warning( 'Dry-run completed. Changes would be required.' );
			return;
		}

		WP_CLI::success( 'Dry-run completed. No changes required.' );
	}
}