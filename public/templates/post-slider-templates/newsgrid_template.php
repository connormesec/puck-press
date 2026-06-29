<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NewsgridTemplate extends PuckPressTemplate {

	public static function get_key(): string {
		return 'newsgrid';
	}

	public static function get_label(): string {
		return 'News Grid';
	}

	protected static function get_directory(): string {
		return 'post-slider-templates';
	}

	public static function forceResetColors(): bool {
		return false;
	}

	public static function get_default_colors(): array {
		return array(
			'section_bg'     => '#f0ede8',
			'feature_img_bg' => '#002347',
			'tag_bg'         => '#002347',
			'tag_text'       => '#ffffff',
			'meta_color'     => '#6b7a8d',
			'head_color'     => '#001e3c',
			'excerpt_color'  => '#4a4a4a',
			'list_divider'   => '#d8d4ce',
			'list_tag_color' => '#001e3c',
			'list_date_color'=> '#6b7a8d',
			'list_head_color'=> '#001e3c',
		);
	}

	public static function get_color_labels(): array {
		return array(
			'section_bg'     => 'Background',
			'feature_img_bg' => 'Feature Image Placeholder',
			'tag_bg'         => 'Tag Badge Background',
			'tag_text'       => 'Tag Badge Text',
			'meta_color'     => 'Feature Date Text',
			'head_color'     => 'Feature Headline',
			'excerpt_color'  => 'Feature Excerpt',
			'list_divider'   => 'List Divider',
			'list_tag_color' => 'List Tag Text',
			'list_date_color'=> 'List Date Text',
			'list_head_color'=> 'List Headline',
		);
	}

	public static function get_default_fonts(): array {
		return array(
			'heading_font' => '',
			'body_font'    => '',
		);
	}

	public static function get_font_labels(): array {
		return array(
			'heading_font' => 'Heading Font',
			'body_font'    => 'Body Font',
		);
	}

	public function render_with_options( array $posts, array $options ): string {
		$colors    = self::get_template_colors();
		$fonts     = self::get_template_fonts();
		$inline    = self::get_inline_css( ':root', $colors, $fonts );
		$css_block = $inline ? '<style>' . $inline . '</style>' : '';

		$more_url  = ! empty( $options['more_url'] ) ? esc_url( $options['more_url'] ) : '#';
		$more_text = esc_html( $options['more_text'] ?? 'More Posts' );

		$featured = ! empty( $posts ) ? array_shift( $posts ) : null;
		$list     = array_slice( $posts, 0, 4 );

		ob_start();
		?>
		<div class="newsgrid_post_slider_container">
			<?php if ( $featured ) :
				$f_id      = $featured instanceof WP_Post ? $featured->ID : (int) $featured;
				$f_url     = get_permalink( $f_id );
				$f_title   = get_the_title( $f_id );
				$f_date    = get_the_date( 'M j', $f_id );
				$f_excerpt = get_the_excerpt( $f_id );
				$f_img     = get_the_post_thumbnail_url( $f_id, 'large' );
				$f_badge   = self::get_post_badge( $f_id );
			?>
			<div class="news-grid-v2">

				<article class="news-feature-v2">
					<a href="<?php echo esc_url( $f_url ); ?>" class="news-feature-v2-link">
						<div class="news-feature-v2-img"<?php if ( $f_img ) : ?> style="background-image:url('<?php echo esc_url( $f_img ); ?>')"<?php endif; ?>></div>
						<div class="news-feature-v2-body">
							<div class="news-meta">
								<?php if ( $f_badge ) : ?>
									<span class="news-tag"><?php echo esc_html( $f_badge ); ?></span>
								<?php endif; ?>
								<span class="news-meta-date"><?php echo esc_html( $f_date ); ?></span>
							</div>
							<h3 class="news-feature-v2-head"><?php echo esc_html( $f_title ); ?></h3>
							<?php if ( $f_excerpt ) : ?>
								<p class="news-feature-v2-excerpt"><?php echo esc_html( $f_excerpt ); ?></p>
							<?php endif; ?>
						</div>
					</a>
				</article>

				<ul class="news-list-v2">
					<?php foreach ( $list as $post ) :
						$p_id    = $post instanceof WP_Post ? $post->ID : (int) $post;
						$p_url   = get_permalink( $p_id );
						$p_title = get_the_title( $p_id );
						$p_date  = get_the_date( 'M j', $p_id );
						$p_badge = self::get_post_badge( $p_id );
					?>
					<li class="news-item-v2">
						<a href="<?php echo esc_url( $p_url ); ?>" class="news-item-v2-link">
							<div class="news-item-v2-meta">
								<?php if ( $p_badge ) : ?>
									<span class="news-item-v2-tag"><?php echo esc_html( $p_badge ); ?></span>
								<?php endif; ?>
								<span class="news-item-v2-date"><?php echo esc_html( $p_date ); ?></span>
							</div>
							<h4 class="news-item-v2-head"><?php echo esc_html( $p_title ); ?></h4>
						</a>
					</li>
					<?php endforeach; ?>

					<?php if ( $more_text ) : ?>
						<li class="news-item-v2 news-item-v2--more">
							<a href="<?php echo $more_url; ?>" class="news-more-link"><?php echo $more_text; ?> &rarr;</a>
						</li>
					<?php endif; ?>
				</ul>

			</div>
			<?php else : ?>
				<p style="padding:20px;color:#888;">No posts found.</p>
			<?php endif; ?>
		</div>
		<?php
		return $css_block . ob_get_clean();
	}

	private static function get_post_badge( int $post_id ): string {
		$cats = get_the_category( $post_id );
		if ( ! empty( $cats ) ) {
			return $cats[0]->name;
		}
		foreach ( get_post_taxonomies( $post_id ) as $tax ) {
			$terms = get_the_terms( $post_id, $tax );
			if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
				return $terms[0]->name;
			}
		}
		$obj = get_post_type_object( get_post_type( $post_id ) );
		return $obj ? $obj->labels->singular_name : '';
	}
}
