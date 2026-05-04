<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Factory_Diff_Report {

	private array $report = [];

	public function add( string $type, string $entity, array $diff ): void {
		if ( empty( $diff ) ) {
			return;
		}

		if ( ! isset( $this->report[ $type ] ) ) {
			$this->report[ $type ] = [];
		}

		$this->report[ $type ][ $entity ] = $diff;
	}

	public function get(): array {
		return $this->report;
	}

	public function has_changes(): bool {
		return ! empty( $this->report );
	}

	public function log(): void {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {

			if ( empty( $this->report ) ) {
				\WP_CLI::log( 'Diff report: no changes' );
				return;
			}

			\WP_CLI::log( '--- DIFF REPORT ---' );

			foreach ( $this->report as $type => $entities ) {

				\WP_CLI::log( strtoupper( $type ) );

				foreach ( $entities as $entity => $diff ) {

					\WP_CLI::log( "  {$entity}:" );

					foreach ( $diff as $field => $change ) {

						$action = $change['action'] ?? 'update';
						$from   = $change['from'] ?? 'null';
						$to     = $change['to'] ?? 'null';

						\WP_CLI::log( "    {$field}: {$action} ({$from} → {$to})" );
					}
				}
			}
		}
	}
}