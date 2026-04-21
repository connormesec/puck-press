<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CarouselTemplate extends PuckPressTemplate {

    public static function get_key(): string {
        return 'carousel';
    }

    public static function get_label(): string {
        return 'Carousel';
    }

    protected static function get_directory(): string {
        return 'post-slider-templates';
    }

    public static function forceResetColors(): bool {
        return false;
    }

    public static function get_default_colors(): array {
        return array(
            'footer_bg'    => '#1a3a2a',
            'footer_title' => '#ffffff',
            'nav_text'     => '#ffffff',
            'nav_line'     => '#ffffff',
            'btn_bg'       => '#c8102e',
            'btn_text'     => '#ffffff',
        );
    }

    public static function get_color_labels(): array {
        return array(
            'footer_bg'    => 'Footer Background',
            'footer_title' => 'Title Text',
            'nav_text'     => 'Prev/Next Label',
            'nav_line'     => 'Decorative Line',
            'btn_bg'       => 'Button Background',
            'btn_text'     => 'Button Text',
        );
    }

    public function render_with_options( array $posts, array $options ): string {
        $colors     = self::get_template_colors();
        $inline_css = self::get_inline_css( ':root', $colors );
        $css_block  = $inline_css ? '<style>' . $inline_css . '</style>' : '';

        $more_text = esc_html( $options['more_text'] ?? 'More Info' );

        ob_start();
        ?>
        <div class="carousel_post_slider_container">
            <div class="pp-cr-wrap">
                <div class="pp-cr-slides">
                    <?php foreach ( $posts as $index => $post ) : ?>
                        <?php
                        $post_id  = $post instanceof WP_Post ? $post->ID : (int) $post;
                        $post_url = get_permalink( $post_id );
                        $title    = get_the_title( $post_id );
                        $img      = get_the_post_thumbnail_url( $post_id, 'large' );
                        ?>
                        <div class="pp-cr-slide<?php echo 0 === $index ? ' pp-cr-slide--active' : ''; ?>"
                             data-url="<?php echo esc_url( $post_url ); ?>"
                             data-title="<?php echo esc_attr( $title ); ?>">
                            <div class="pp-cr-img-blur"<?php if ( $img ) : ?> style="background-image:url('<?php echo esc_url( $img ); ?>')"<?php endif; ?>></div>
                            <div class="pp-cr-img"<?php if ( $img ) : ?> style="background-image:url('<?php echo esc_url( $img ); ?>')"<?php endif; ?>></div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="pp-cr-footer">
                    <button class="pp-cr-nav pp-cr-nav--prev" aria-label="Previous">
                        <span class="pp-cr-nav-label">PREV</span>
                        <span class="pp-cr-nav-line"></span>
                    </button>
                    <div class="pp-cr-info">
                        <div class="pp-cr-title"></div>
                        <?php if ( $more_text ) : ?>
                            <a href="#" class="pp-cr-btn"><?php echo $more_text; ?></a>
                        <?php endif; ?>
                    <button class="pp-cr-nav pp-cr-nav--next" aria-label="Next">
                        <span class="pp-cr-nav-line"></span>
                        <span class="pp-cr-nav-label">NEXT</span>
                    </button>
                </div>
            </div>
        </div>
        <?php
        return $css_block . ob_get_clean();
    }
}
