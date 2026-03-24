<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CardTemplate extends PuckPressTemplate {

    public static function get_key(): string {
        return 'card';
    }

    public static function get_label(): string {
        return 'Card';
    }

    protected static function get_directory(): string {
        return 'league-news-templates';
    }

    public static function forceResetColors(): bool {
        return false;
    }

    public static function get_default_colors(): array {
        return array(
            'card_bg'      => '#ffffff',
            'card_border'  => '#e1e5eb',
            'title_text'   => '#0a2342',
            'date_text'    => '#6c757d',
            'nav_bg'       => '#0a2342',
            'nav_icon'     => '#ffffff',
            'dot_active'   => '#0a2342',
            'dot_inactive' => '#c8d0db',
        );
    }

    public static function get_color_labels(): array {
        return array(
            'card_bg'      => 'Card Background',
            'card_border'  => 'Card Border',
            'title_text'   => 'Title Text',
            'date_text'    => 'Date Text',
            'nav_bg'       => 'Arrow Background',
            'nav_icon'     => 'Arrow Icon',
            'dot_active'   => 'Active Dot',
            'dot_inactive' => 'Inactive Dot',
        );
    }

    public function render_with_options( array $posts, array $options ): string {
        $colors     = self::get_template_colors();
        $inline_css = self::get_inline_css( ':root', $colors );
        $css_block  = $inline_css ? '<style>' . $inline_css . '</style>' : '';

        if ( empty( $posts ) ) {
            return $css_block . '<p>No news available.</p>';
        }

        ob_start();
        ?>
        <div class="pp-ln-card-container">
            <div class="pp-ln-card-track-wrap">
                <div class="pp-ln-card-track">
                    <?php foreach ( $posts as $post ) : ?>
                        <a class="pp-ln-card"
                           href="<?php echo esc_url( $post['link'] ); ?>"
                           target="_blank"
                           rel="noopener noreferrer">
                            <div class="pp-ln-card-image">
                                <?php if ( ! empty( $post['image_url'] ) ) : ?>
                                    <img src="<?php echo esc_url( $post['image_url'] ); ?>"
                                         alt="<?php echo esc_attr( $post['title'] ); ?>"
                                         loading="lazy">
                                <?php else : ?>
                                    <div class="pp-ln-card-no-image"></div>
                                <?php endif; ?>
                            </div>
                            <div class="pp-ln-card-body">
                                <p class="pp-ln-card-date">
                                    <?php echo esc_html( date( 'M j', strtotime( $post['date'] ) ) ); ?>
                                </p>
                                <h3 class="pp-ln-card-title">
                                    <?php echo esc_html( $post['title'] ); ?>
                                </h3>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="pp-ln-card-controls">
                <button class="pp-ln-card-arrow pp-ln-card-arrow--prev" aria-label="Previous">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"></polyline></svg>
                </button>
                <div class="pp-ln-card-dots"></div>
                <button class="pp-ln-card-arrow pp-ln-card-arrow--next" aria-label="Next">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
                </button>
            </div>
        </div>
        <?php
        return $css_block . ob_get_clean();
    }
}
