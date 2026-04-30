<?php
/**
 * Single Property Template
 */

get_header();

if ( have_posts() ) :
	while ( have_posts() ) :
		the_post();

		$price    = get_post_meta( get_the_ID(), 'price', true );
		$address  = get_post_meta( get_the_ID(), 'address', true );
		$bedrooms = get_post_meta( get_the_ID(), 'bedrooms', true );
		?>

		<main class="site-main" style="padding: 80px 24px;">
			<article <?php post_class( 'property-single' ); ?> style="max-width: 1040px; margin: 0 auto;">

				<header style="margin-bottom: 40px;">
					<p style="margin-bottom: 12px; color: #666;">
						<?php echo esc_html( $address ); ?>
					</p>

					<h1 style="font-size: clamp(40px, 6vw, 72px); line-height: 1.05; margin: 0;">
						<?php the_title(); ?>
					</h1>
				</header>

				<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 48px;">

					<div style="border: 1px solid #e5e5e5; border-radius: 16px; padding: 24px;">
						<div style="color: #777; margin-bottom: 8px;">Price</div>
						<strong style="font-size: 28px;">
							<?php echo $price ? '$' . number_format( (float) $price ) : '—'; ?>
						</strong>
					</div>

					<div style="border: 1px solid #e5e5e5; border-radius: 16px; padding: 24px;">
						<div style="color: #777; margin-bottom: 8px;">Bedrooms</div>
						<strong style="font-size: 28px;">
							<?php echo $bedrooms ? esc_html( $bedrooms ) : '—'; ?>
						</strong>
					</div>

					<div style="border: 1px solid #e5e5e5; border-radius: 16px; padding: 24px;">
						<div style="color: #777; margin-bottom: 8px;">Address</div>
						<strong style="font-size: 22px;">
							<?php echo $address ? esc_html( $address ) : '—'; ?>
						</strong>
					</div>

				</div>

				<div style="font-size: 20px; line-height: 1.7;">
					<?php the_content(); ?>
				</div>

			</article>
		</main>

		<?php
	endwhile;
endif;

get_footer();