<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Factory_Render_Adapter {

	public function register( array $blueprint ): void {
		add_shortcode( 'factory_listing', [ $this, 'render_listing_shortcode' ] );
	}

	public function apply( array $blueprint ): void {
		foreach ( $blueprint['listings'] ?? [] as $listing ) {
			$this->upsert_listing_page( $listing );
		}
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

	private function upsert_listing_page( array $listing ): void {
		$slug      = $listing['slug'] ?? '';
		$post_type = $listing['post_type'] ?? '';

		if ( ! $slug || ! $post_type ) {
			return;
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
				return;
			}

			$post_data       = $target_state;
			$post_data['ID'] = $existing->ID;
			$post_data['post_type'] = 'page';

			wp_update_post( $post_data );
			$this->log( "Render page updated: {$page_slug}" );

			return;
		}

		wp_insert_post( [
			'post_type'    => 'page',
			'post_title'   => $page_title,
			'post_name'    => $page_slug,
			'post_status'  => 'publish',
			'post_content' => $content,
		] );

		$this->log( "Render page created: {$page_slug}" );
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
				return $this->render_listing( $listing );
			}
		}

		return '';
	}

	private function render_listing( array $listing ): string {
		$post_type = $listing['post_type'] ?? '';

		if ( ! $post_type ) {
			return '';
		}

		$query = new WP_Query( [
			'post_type'      => $post_type,
			'post_status'    => 'publish',
			'posts_per_page' => 12,
		] );

		if ( ! $query->have_posts() ) {
			return '<p>No items found.</p>';
		}

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
						<h2 style="font-size: 24px; margin: 0 0 16px;">
							<a href="<?php the_permalink(); ?>" style="text-decoration: none;">
								<?php the_title(); ?>
							</a>
						</h2>

						<?php
						$layout = $listing['layout'] ?? [];

						if ( empty( $layout ) && ! empty( $listing['fields'] ) ) {
							$layout = $listing['fields'];
						}
						?>

						<?php foreach ( $layout as $field ) : ?>
							<?php
							if ( ( $field['type'] ?? '' ) !== 'meta' ) {
								continue;
							}

							$key = $field['key'] ?? '';

							if ( ! $key ) {
								continue;
							}

							$value = get_post_meta( get_the_ID(), $key, true );

							if ( $value === '' ) {
								continue;
							}

							$label  = $field['label'] ?? ucfirst( $key );
							$format = $field['format'] ?? '';
							?>

							<div style="margin-top: 10px;">
								<strong><?php echo esc_html( $label ); ?>:</strong>
								<?php
								if ( $format === 'currency' ) {
									echo '$' . esc_html( number_format( (float) $value ) );
								} else {
									echo esc_html( $value );
								}
								?>
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

	private function get_archive_page_config( string $post_type ): array {
		$blueprint = factory_get_blueprint();

		$archive = $blueprint['pages']['archive'] ?? [];

		if ( ( $archive['post_type'] ?? '' ) === $post_type ) {
			return $archive;
		}

		return [];
	}

	private function log( string $message ): void {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			WP_CLI::log( $message );
		}
	}

		private function get_listing_slug_for_post_type( array $blueprint, string $post_type ): string {
		foreach ( $blueprint['listings'] ?? [] as $listing ) {
			if ( ( $listing['post_type'] ?? '' ) === $post_type ) {
				return $listing['slug'] ?? '';
			}
		}

		return '';
	}
}