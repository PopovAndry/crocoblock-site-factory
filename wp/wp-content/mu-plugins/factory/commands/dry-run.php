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

		$summary = [
			'create'  => 0,
			'update'  => 0,
			'skip'    => 0,
			'warning' => 0,
			'error'   => 0,
		];

		WP_CLI::log( '' );
		WP_CLI::log( 'Factory plan' );
		WP_CLI::log( 'Blueprint: ' . $path );
		WP_CLI::log( 'No changes will be applied.' );
		WP_CLI::log( '' );

		foreach ( factory_get_adapters() as $adapter ) {
			$class = get_class( $adapter );

			if ( method_exists( $adapter, 'plan' ) ) {
				$items = $adapter->plan( $blueprint );
			} elseif ( method_exists( $adapter, 'validate' ) ) {
				$items = $this->convert_validate_to_plan( $adapter->validate( $blueprint ) );
			} else {
				continue;
			}

			if ( empty( $items ) ) {
				continue;
			}

			WP_CLI::log( $this->format_adapter_name( $class ) );

			foreach ( $items as $item ) {
				$action  = $item['action'] ?? 'skip';
				$message = $item['message'] ?? '';

				$summary[ $action ] = ( $summary[ $action ] ?? 0 ) + 1;

				WP_CLI::log( '  ' . $this->format_action( $action ) . ' ' . $message );

                if ( isset( $item['diff'] ) && is_array( $item['diff'] ) ) {
                    foreach ( $item['diff'] as $key => $change ) {
                        if ( is_array( $change ) && isset( $change['old'], $change['new'] ) ) {
                            WP_CLI::log( "      - {$key}: {$change['old']} → {$change['new']}" );
                        } else {
                            WP_CLI::log( "      - {$key} changed" );
                        }
                    }
                }
			}

			WP_CLI::log( '' );

		}

		WP_CLI::log( 'Summary:' );
		WP_CLI::log( "  + {$summary['create']} to create" );
		WP_CLI::log( "  ~ {$summary['update']} to update" );
		WP_CLI::log( "  = {$summary['skip']} unchanged" );

		if ( $summary['warning'] > 0 ) {
			WP_CLI::log( "  ! {$summary['warning']} warnings" );
		}

		if ( $summary['error'] > 0 ) {
			WP_CLI::log( "  x {$summary['error']} errors" );
		}

		if ( $summary['create'] > 0 || $summary['update'] > 0 || $summary['error'] > 0 ) {
			WP_CLI::warning( 'Plan completed. Changes would be required.' );
			return;
		}

		WP_CLI::success( 'Plan completed. No changes required.' );
	}

	private function convert_validate_to_plan( array $results ): array {
		$items = [];

		foreach ( $results as $line ) {
			$status  = $line['status'] ?? 'ok';
			$message = $line['message'] ?? '';

			$items[] = [
				'action'  => $status === 'ok' ? 'skip' : $status,
				'message' => $message,
			];
		}

		return $items;
	}

	private function format_action( string $action ): string {
		return match ( $action ) {
			'create'  => '+',
			'update'  => '~',
			'warning' => '!',
			'error'   => 'x',
			default   => '=',
		};
	}

    private function format_adapter_name( string $class ): string {
	return match ( $class ) {
		'Factory_Plugin_Adapter'             => 'Plugins',
		'Factory_Theme_Adapter'              => 'Theme',
		'Factory_Taxonomy_Adapter'           => 'Taxonomies',
		'Factory_WP_Core_Adapter'            => 'WordPress Core',
		'Factory_JetEngine_Adapter'          => 'JetEngine Meta',
		'Factory_JetEngine_Listing_Adapter'  => 'JetEngine Listings',
		'Factory_Render_Adapter'             => 'Render Pages',
		'Factory_Single_Adapter'             => 'Single Templates',
		'Factory_Content_Adapter'            => 'Content',
		default                              => $class,
	};
}
}