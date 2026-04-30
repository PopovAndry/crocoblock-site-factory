<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Factory_Single_Adapter {

	public function register( array $blueprint ): void {
		add_filter( 'template_include', [ $this, 'override_single_template' ], 99 );
	}

	public function apply( array $blueprint ): void {
		// Runtime rendering only.
	}

	public function validate( array $blueprint ): array {
		$checks = [];

		foreach ( $blueprint['single'] ?? [] as $post_type => $config ) {
			$checks[] = [
				'status'  => post_type_exists( $post_type ) ? 'ok' : 'error',
				'message' => post_type_exists( $post_type )
					? "Single template registered for: {$post_type}"
					: "Single template post type missing: {$post_type}",
			];
		}

		return $checks;
	}

	public function override_single_template( string $template ): string {
		if ( ! is_singular() ) {
			return $template;
		}

		$post_type = get_post_type();

		$blueprint = factory_get_blueprint();

		if ( empty( $blueprint['single'][ $post_type ] ) ) {
			return $template;
		}

		$factory_template = __DIR__ . '/../templates/single-factory.php';

		return file_exists( $factory_template ) ? $factory_template : $template;
	}

	public function render_current(): string {
		if ( ! is_singular() ) {
			return '';
		}

		$post_type = get_post_type();
		$blueprint = factory_get_blueprint();
		$config    = $blueprint['single'][ $post_type ] ?? [];

		if ( empty( $config ) ) {
			return '';
		}

		$layout = $config['layout'] ?? [];

			if ( empty( $layout ) && ! empty( $config['fields'] ) ) {
				$layout = array_map(
					function ( $field ) {
						return $field === 'content'
							? [ 'type' => 'content' ]
							: [
								'type'  => 'meta',
								'key'   => $field,
								'label' => ucfirst( $field ),
							];
					},
					$config['fields']
				);
			}

		ob_start();
		?>

		<main class="factory-single-wrap" style="max-width: 1120px; margin: 80px auto; padding: 0 24px;">
			<article <?php post_class( 'factory-single' ); ?>>

				<header style="margin-bottom: 48px;">
					<?php
					$address = get_post_meta( get_the_ID(), 'address', true );

					if ( $address ) :
						?>
						<p style="margin-bottom: 12px; color: #666;">
							<?php echo esc_html( $address ); ?>
						</p>
					<?php endif; ?>

					<h1 style="font-size: clamp(44px, 7vw, 82px); line-height: 1.05; margin: 0;">
						<?php the_title(); ?>
					</h1>
				</header>

				<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin-bottom: 48px;">

					<?php foreach ( $layout as $item ) : ?>
						<?php
						if ( ( $item['type'] ?? '' ) !== 'meta' ) {
							continue;
						}

						$key = $item['key'] ?? '';

						if ( ! $key ) {
							continue;
						}

						$value = get_post_meta( get_the_ID(), $key, true );

						if ( $value === '' ) {
							continue;
						}

						$label  = $item['label'] ?? ucfirst( $key );
						$format = $item['format'] ?? '';
						?>

						<div style="border: 1px solid #e5e5e5; border-radius: 18px; padding: 24px;">
							<div style="color: #777; margin-bottom: 8px;">
								<?php echo esc_html( $label ); ?>
							</div>

							<strong style="font-size: 28px;">
								<?php
								if ( $format === 'currency' ) {
									echo '$' . esc_html( number_format( (float) $value ) );
								} else {
									echo esc_html( $value );
								}
								?>
							</strong>
						</div>

					<?php endforeach; ?>

				</div>

				<div style="font-size: 20px; line-height: 1.7;">
					<?php the_content(); ?>
				</div>

			</article>
		</main>

		<?php
		return ob_get_clean();
	}
}

function factory_render_single_template(): void {
	$adapter = new Factory_Single_Adapter();

	echo $adapter->render_current();
}