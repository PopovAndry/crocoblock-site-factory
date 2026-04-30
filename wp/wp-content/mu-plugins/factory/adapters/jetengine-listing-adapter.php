<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Factory_JetEngine_Listing_Adapter {

	public function register( array $blueprint ): void {
		// Listings are created on apply only.
	}

	public function apply( array $blueprint ): void {
		if ( ! function_exists( 'jet_engine' ) ) {
			$this->log( 'JetEngine not active. Listing sync skipped.' );
			return;
		}

		foreach ( $blueprint['listings'] ?? [] as $listing ) {
			$this->upsert_listing( $listing );
		}
	}

	public function validate( array $blueprint ): array {
		$checks = [];

		foreach ( $blueprint['listings'] ?? [] as $listing ) {
			$slug  = $listing['slug'] ?? '';
			$title = $listing['title'] ?? $slug;

			if ( ! $slug ) {
				$checks[] = [
					'status'  => 'error',
					'message' => 'Listing slug is missing.',
				];

				continue;
			}

			$post = $this->find_listing_by_slug( $slug );

			if ( $post ) {
				$checks[] = [
					'status'  => 'ok',
					'message' => "Listing exists: {$title}",
				];
			} else {
				$checks[] = [
					'status'  => 'error',
					'message' => "Listing missing: {$title}",
				];
			}
		}

		return $checks;
	}

	private function upsert_listing( array $listing ): void {
		$slug      = $listing['slug'] ?? '';
		$title     = $listing['title'] ?? $slug;
		$post_type = $listing['post_type'] ?? '';

		if ( ! $slug || ! $post_type ) {
			$this->warn( 'Listing slug or post_type is missing.' );
			return;
		}

		$content = $this->generate_blocks( $listing );

		$existing = $this->find_listing_by_slug( $slug );

		$post_data = [
			'post_type'    => 'jet-engine',
			'post_title'   => $title,
			'post_name'    => $slug,
			'post_status'  => 'publish',
			'post_content' => $content,
		];

		if ( $existing ) {
			$post_data['ID'] = $existing->ID;
			$post_id         = wp_update_post( $post_data, true );
			$action          = 'updated';
		} else {
			$post_id = wp_insert_post( $post_data, true );
			$action  = 'created';
		}

		if ( is_wp_error( $post_id ) ) {
			$this->warn( $post_id->get_error_message() );
			return;
		}

		update_post_meta( $post_id, '_entry_type', 'listing' );
		update_post_meta( $post_id, '_listing_type', 'blocks' );

		update_post_meta( $post_id, '_listing_data', [
			'source'    => 'posts',
			'post_type' => $post_type,
			'tax'       => 'category',
		] );

		update_post_meta( $post_id, '_elementor_page_settings', [
			'listing_source'          => 'posts',
			'listing_post_type'       => $post_type,
			'listing_tax'             => 'category',
			'repeater_source'         => 'jet_engine',
			'repeater_field'          => '',
			'repeater_option'         => '',
			'listing_link'            => '',
			'listing_link_source'     => '',
			'listing_link_object_prop'=> 'post_id',
			'listing_link_custom_url' => '',
			'listing_link_add_query_args' => '',
			'listing_link_query_args' => '',
			'_post_id'                => 'current_id',
		] );

		update_post_meta( $post_id, '_factory_listing_key', $slug );

		$this->log( "Listing {$action}: {$title}" );
	}

	private function generate_blocks( array $listing ): string {
	$blocks = [];

	$layout = $listing['layout'] ?? [];

	if ( empty( $layout ) && ! empty( $listing['fields'] ) ) {
		$layout = $listing['fields'];
	}

	foreach ( $layout as $field ) {
		$type = $field['type'] ?? '';

		if ( $type === 'title' ) {
			$blocks[] = $this->dynamic_title_block();
		}

		if ( $type === 'meta' && ! empty( $field['key'] ) ) {
			$blocks[] = $this->dynamic_meta_field_block( $field['key'] );
		}
	}

	return implode( "\n\n", $blocks );
}

	private function dynamic_title_block(): string {
		return '<!-- wp:jet-engine/dynamic-field {"dynamic_field_source":"post_or_term_object","dynamic_field_post_object":"post_title","crocoblock_styles":{"_uniqueClassName":"factory-title","field_width":"auto","field_alignment":"flex-start","content_alignment":"left"}} /-->';
	}

	private function dynamic_meta_field_block( string $key ): string {
		$class = 'factory-meta-' . sanitize_key( $key );

		return '<!-- wp:jet-engine/dynamic-field {"dynamic_field_source":"meta","dynamic_field_post_meta":"' . esc_attr( $key ) . '","crocoblock_styles":{"_uniqueClassName":"' . esc_attr( $class ) . '","field_width":"auto","field_alignment":"flex-start","content_alignment":"left"}} /-->';
	}

	private function find_listing_by_slug( string $slug ): ?WP_Post {
		$posts = get_posts( [
			'post_type'   => 'jet-engine',
			'post_status' => 'any',
			'name'        => $slug,
			'numberposts' => 1,
		] );

		return $posts[0] ?? null;
	}

	private function log( string $message ): void {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			WP_CLI::log( $message );
		}
	}

	private function warn( string $message ): void {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			WP_CLI::warning( $message );
		}
	}
}