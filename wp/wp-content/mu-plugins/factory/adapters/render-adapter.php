<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Factory_Render_Adapter {

	private array $execution_results = [];

	public function register( array $blueprint ): void {
		add_shortcode( 'factory_listing', [ $this, 'render_listing_shortcode' ] );
	}

	public function apply( array $blueprint ): void {
		$this->execution_results = [];

		foreach ( $blueprint['listings'] ?? [] as $listing ) {
			$result = $this->upsert_listing_page( $listing );

			if ( is_array( $result ) ) {
				$this->execution_results[] = $result;
			}
		}
	}

	public function get_execution_results(): array {
		return $this->execution_results;
	}

	public function plan( array $blueprint ): array {
		$plan = [];

		foreach ( $blueprint['listings'] ?? [] as $listing ) {
			$slug      = $listing['slug'] ?? '';
			$post_type = $listing['post_type'] ?? '';

			if ( ! $slug || ! $post_type ) {
				continue;
			}

			$page_config = $this->get_archive_page_config( $post_type );

			$page_slug  = $page_config['slug'] ?? $post_type . 's';
			$page_title = $page_config['title'] ?? ucwords( str_replace( '-', ' ', $page_slug ) );

			$content = sprintf(
				'[factory_listing slug="%s"]',
				esc_attr( $slug )
			);

			$target_state = [
				'post_title'   => $page_title,
				'post_name'    => $page_slug,
				'post_status'  => 'publish',
				'post_content' => $content,
			];

			$existing = get_page_by_path( $page_slug );

			if ( ! $existing ) {
				$plan[] = [
					'action'  => 'create',
					'type'    => 'render',
					'entity'  => $page_slug,
					'message' => "Create render page: {$page_slug}",
				];

				continue;
			}

			$current_state = [
				'post_title'   => $existing->post_title,
				'post_name'    => $existing->post_name,
				'post_status'  => $existing->post_status,
				'post_content' => $existing->post_content,
			];

			$diff = factory_diff_arrays( $current_state, $target_state );

			if ( empty( $diff ) ) {
				$plan[] = [
					'action'  => 'skip',
					'type'    => 'render',
					'entity'  => $page_slug,
					'message' => "Render page up-to-date: {$page_slug}",
				];

				continue;
			}

			$plan[] = [
				'action'  => 'update',
				'type'    => 'render',
				'entity'  => $page_slug,
				'message' => "Update render page: {$page_slug}",
				'diff'    => $diff,
			];
		}

		return $plan;
	}

	public function validate( array $blueprint ): array {
		$results = [];

		$page = $blueprint['pages']['archive'] ?? null;

		if ( ! $page ) {
			return $results;
		}

		$slug  = $page['slug'] ?? '';
		$title = $page['title'] ?? $slug;

		$existing = get_page_by_path( $slug );

		if ( ! $existing ) {
			$results[] = [
				'status'  => 'error',
				'message' => "Render page missing: {$title}",
			];

			return $results;
		}

		$target_content = sprintf(
			'[factory_listing slug="%s"]',
			esc_attr( $this->get_listing_slug_for_post_type( $blueprint, $page['post_type'] ?? '' ) )
		);

		$current_state = [
			'post_title'   => $existing->post_title,
			'post_content' => $existing->post_content,
		];

		$target_state = [
			'post_title'   => $title,
			'post_content' => $target_content,
		];

		$diff = factory_diff_arrays( $current_state, $target_state );

		if ( ! empty( $diff ) ) {
			$results[] = [
				'status'  => 'error',
				'message' => "Render page out of sync: {$title}",
			];

			return $results;
		}

		$results[] = [
			'status'  => 'ok',
			'message' => "Render page up-to-date: {$title}",
		];

		return $results;
	}

	private function upsert_listing_page( array $listing ): ?array {
		$slug      = $listing['slug'] ?? '';
		$post_type = $listing['post_type'] ?? '';

		if ( ! $slug || ! $post_type ) {
			return null;
		}

		$page_config = $this->get_archive_page_config( $post_type );

		$page_slug  = $page_config['slug'] ?? $post_type . 's';
		$page_title = $page_config['title'] ?? ucwords( str_replace( '-', ' ', $page_slug ) );

		$content = sprintf(
			'[factory_listing slug="%s"]',
			esc_attr( $slug )
		);

		$existing = get_page_by_path( $page_slug );

		$target_state = [
			'post_title'   => $page_title,
			'post_name'    => $page_slug,
			'post_status'  => 'publish',
			'post_content' => $content,
		];

		if ( $existing ) {
			$current_state = [
				'post_title'   => $existing->post_title,
				'post_name'    => $existing->post_name,
				'post_status'  => $existing->post_status,
				'post_content' => $existing->post_content,
			];

			$diff = factory_diff_arrays( $current_state, $target_state );

			if ( empty( $diff ) ) {
				$this->log( "Render page up-to-date: {$page_slug}" );

				return $this->execution_item(
					'ok',
					'skip',
					$page_slug,
					"Render page up-to-date: {$page_slug}"
				);
			}

			$post_data              = $target_state;
			$post_data['ID']        = $existing->ID;
			$post_data['post_type'] = 'page';

			$post_id = wp_update_post( $post_data );
			$this->log( "Render page updated: {$page_slug}" );

			if ( is_wp_error( $post_id ) || ! $post_id ) {
				return $this->execution_item(
					'error',
					'update',
					$page_slug,
					"Render page update failed: {$page_slug}"
				);
			}

			return $this->execution_item(
				'ok',
				'update',
				$page_slug,
				"Render page updated: {$page_slug}"
			);
		}

		$post_id = wp_insert_post( [
			'post_type'    => 'page',
			'post_title'   => $page_title,
			'post_name'    => $page_slug,
			'post_status'  => 'publish',
			'post_content' => $content,
		] );

		$this->log( "Render page created: {$page_slug}" );

		if ( is_wp_error( $post_id ) || ! $post_id ) {
			return $this->execution_item(
				'error',
				'create',
				$page_slug,
				"Render page create failed: {$page_slug}"
			);
		}

		return $this->execution_item(
			'ok',
			'create',
			$page_slug,
			"Render page created: {$page_slug}"
		);
	}

	private function execution_item(
		string $status,
		string $action,
		string $entity,
		string $message
	): array {
		return [
			'status'  => $status,
			'action'  => $action,
			'type'    => 'render',
			'entity'  => $entity,
			'message' => $message,
			'details' => [],
		];
	}

	public function render_listing_shortcode( array $atts ): string {
		$atts = shortcode_atts(
			[
				'slug' => '',
			],
			$atts
		);

		$blueprint = factory_get_blueprint();

		foreach ( $blueprint['listings'] ?? [] as $listing ) {
			if ( ( $listing['slug'] ?? '' ) === $atts['slug'] ) {
				return $this->render_listing( $listing, $blueprint );
			}
		}

		return '';
	}

	private function render_listing( array $listing, array $blueprint ): string {
		$post_type = $listing['post_type'] ?? '';

		if ( ! $post_type ) {
			return '';
		}

		$query = new WP_Query( [
			'post_type'      => $post_type,
			'post_status'    => 'publish',
			'posts_per_page' => 12,
			'orderby'        => 'ID',
			'order'          => 'ASC',
		] );

		if ( ! $query->have_posts() ) {
			return '<p>No items found.</p>';
		}

		$fields = $this->get_render_fields( $listing, $blueprint, $post_type );

		ob_start();
		?>

		<section class="factory-listing-wrap" style="max-width: 1120px; margin: 80px auto; padding: 0 24px;">
			<header style="margin-bottom: 40px;">
				<h1 style="font-size: clamp(40px, 6vw, 72px); line-height: 1.05;">
					<?php echo esc_html( $listing['title'] ?? 'Listing' ); ?>
				</h1>
			</header>

			<div class="factory-listing-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 24px;">
				<?php
				while ( $query->have_posts() ) :
					$query->the_post();
					?>

					<article class="factory-card" style="border: 1px solid #e5e5e5; border-radius: 18px; padding: 24px;">
						<?php if ( has_post_thumbnail() ) : ?>
							<a href="<?php the_permalink(); ?>" style="display: block; margin: -24px -24px 20px; overflow: hidden; border-radius: 18px 18px 0 0;">
								<?php
								echo get_the_post_thumbnail(
									get_the_ID(),
									'medium_large',
									[
										'style' => 'display: block; width: 100%; height: 220px; object-fit: cover;',
									]
								);
								?>
							</a>
						<?php endif; ?>

						<h2 style="font-size: 24px; margin: 0 0 16px;">
							<a href="<?php the_permalink(); ?>" style="text-decoration: none;">
								<?php the_title(); ?>
							</a>
						</h2>

						<?php foreach ( $fields as $field ) : ?>
							<?php
							$key = $field['key'] ?? '';

							if ( ! $key || 'title' === $key ) {
								continue;
							}

							$value = get_post_meta( get_the_ID(), $key, true );

							if ( '' === $value || [] === $value ) {
								continue;
							}

							$label = $field['label'] ?? $this->humanize_key( $key );
							$type  = $field['type'] ?? 'text';
							?>

							<div style="margin-top: 10px;">
								<strong><?php echo esc_html( $label ); ?>:</strong>
								<?php echo esc_html( $this->format_value( $value, $type ) ); ?>
							</div>
						<?php endforeach; ?>
					</article>

				<?php endwhile; ?>
			</div>
		</section>

		<?php
		wp_reset_postdata();

		return ob_get_clean();
	}

	private function get_render_fields(
		array $listing,
		array $blueprint,
		string $post_type
	): array {
		$meta_map = $this->get_cpt_meta_map( $blueprint, $post_type );
		$fields   = [];
		$used     = [];

		if ( ! empty( $listing['layout'] ) && is_array( $listing['layout'] ) ) {
			foreach ( $listing['layout'] as $field ) {
				if ( ! is_array( $field ) || ( $field['type'] ?? '' ) !== 'meta' ) {
					continue;
				}

				$key = $field['key'] ?? '';

				if ( ! $key ) {
					continue;
				}

				$fields[] = [
					'key'   => $key,
					'type'  => $meta_map[ $key ]['type'] ?? 'text',
					'label' => $field['label'] ?? $meta_map[ $key ]['label'] ?? $this->humanize_key( $key ),
				];

				$used[ $key ] = true;
			}
		}

		if ( ! empty( $listing['fields'] ) && is_array( $listing['fields'] ) ) {
			foreach ( $listing['fields'] as $field ) {
				if ( ! is_string( $field ) || 'title' === $field ) {
					continue;
				}

				if ( isset( $used[ $field ] ) ) {
					continue;
				}

				$fields[] = [
					'key'   => $field,
					'type'  => $meta_map[ $field ]['type'] ?? 'text',
					'label' => $meta_map[ $field ]['label'] ?? $this->humanize_key( $field ),
				];

				$used[ $field ] = true;
			}
		}

		if ( empty( $fields ) ) {
			return array_values( $meta_map );
		}

		return $fields;
	}

	private function get_cpt_meta_map( array $blueprint, string $post_type ): array {
		foreach ( $blueprint['cpt'] ?? [] as $cpt ) {
			if ( ( $cpt['slug'] ?? '' ) !== $post_type ) {
				continue;
			}

			$map = [];

			foreach ( $cpt['meta'] ?? [] as $field ) {
				if ( empty( $field['key'] ) ) {
					continue;
				}

				$map[ $field['key'] ] = [
					'key'   => $field['key'],
					'type'  => $field['type'] ?? 'text',
					'label' => $field['label'] ?? $this->humanize_key( $field['key'] ),
				];
			}

			return $map;
		}

		return [];
	}

	private function format_value( $value, string $type ): string {
		if ( is_array( $value ) ) {
			$value = implode( ', ', $value );
		}

		if ( in_array( $type, [ 'boolean', 'checkbox' ], true ) ) {
			return $value ? 'Yes' : 'No';
		}

		if ( 'number' === $type && is_numeric( $value ) ) {
			return number_format( (float) $value );
		}

		return (string) $value;
	}

	private function humanize_key( string $key ): string {
		return ucwords( str_replace( '_', ' ', $key ) );
	}

	private function get_archive_page_config( string $post_type ): array {
		$blueprint = factory_get_blueprint();

		$archive = $blueprint['pages']['archive'] ?? [];

		if ( ( $archive['post_type'] ?? '' ) === $post_type ) {
			return $archive;
		}

		return [];
	}

	private function get_listing_slug_for_post_type( array $blueprint, string $post_type ): string {
		foreach ( $blueprint['listings'] ?? [] as $listing ) {
			if ( ( $listing['post_type'] ?? '' ) === $post_type ) {
				return $listing['slug'] ?? '';
			}
		}

		return '';
	}

	private function log( string $message ): void {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			WP_CLI::log( $message );
		}
	}
}
