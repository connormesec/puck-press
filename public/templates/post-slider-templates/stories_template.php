<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class StoriesTemplate extends PuckPressTemplate {

    public static function get_key(): string {
        return 'stories';
    }

    public static function get_label(): string {
        return 'Stories';
    }

    protected static function get_directory(): string {
        return 'post-slider-templates';
    }

    public static function forceResetColors(): bool {
        return false;
    }

    public static function get_default_colors(): array {
        return array(
            'featured_overlay' => '#000000',
            'featured_text'    => '#ffffff',
            'date_text'        => '#aaaaaa',
            'list_title'       => '#111827',
            'inner_bg'         => '#ffffff',
            'more_btn_bg'      => '#1a2a4a',
            'more_btn_text'    => '#ffffff',
            'section_bg'       => '#f9fafb',
        );
    }

    public static function get_color_labels(): array {
        return array(
            'featured_overlay' => 'Featured Image Overlay',
            'featured_text'    => 'Featured Headline',
            'date_text'        => 'Date Text',
            'list_title'       => 'List Headline',
            'inner_bg'         => 'Background',
            'more_btn_bg'      => 'More Button Background',
            'more_btn_text'    => 'More Button Text',
            'section_bg'       => 'Section Background',
        );
    }

    public function render_with_options( array $posts, array $options ): string {
        $colors     = self::get_template_colors();
        $inline_css = self::get_inline_css( ':root', $colors );
        $css_block  = $inline_css ? '<style>' . $inline_css . '</style>' : '';

        $featured   = ! empty( $posts ) ? $posts[0] : null;
        $list_posts = array_slice( $posts, 1, 5 );
        $more_url   = ! empty( $options['more_url'] ) ? esc_url( $options['more_url'] ) : '#';
        $more_text  = esc_html( $options['more_text'] ?? 'More Posts' );

        ob_start();
        ?>
        <div class="stories_post_slider_container">
            <?php if ( $featured ) : ?>
                <?php
                $featured_id    = $featured instanceof WP_Post ? $featured->ID : (int) $featured;
                $featured_url   = get_permalink( $featured_id );
                $featured_title = get_the_title( $featured_id );
                $featured_date  = get_the_date( 'M j', $featured_id );
                $featured_img   = get_the_post_thumbnail_url( $featured_id, 'large' );
                ?>
                <div class="pp-st-inner">
                    <a href="<?php echo esc_url( $featured_url ); ?>" class="pp-st-featured">
                        <div class="pp-st-featured-blur"<?php if ( $featured_img ) : ?> style="background-image:url('<?php echo esc_url( $featured_img ); ?>')"<?php endif; ?>></div>
                        <div class="pp-st-featured-bg"<?php if ( $featured_img ) : ?> style="background-image:url('<?php echo esc_url( $featured_img ); ?>')"<?php endif; ?>></div>
                        <div class="pp-st-featured-overlay"></div>
                        <div class="pp-st-featured-text">
                            <div class="pp-st-featured-date"><?php echo esc_html( $featured_date ); ?></div>
                            <div class="pp-st-featured-title"><?php echo esc_html( $featured_title ); ?></div>
                        </div>
                    </a>

                    <div class="pp-st-list">
                        <?php foreach ( $list_posts as $list_post ) : ?>
                            <?php
                            $list_id    = $list_post instanceof WP_Post ? $list_post->ID : (int) $list_post;
                            $list_url   = get_permalink( $list_id );
                            $list_title = get_the_title( $list_id );
                            $list_img   = get_the_post_thumbnail_url( $list_id, 'medium' );
                            ?>
                            <a href="<?php echo esc_url( $list_url ); ?>" class="pp-st-item">
                                <div class="pp-st-thumb">
                                    <div class="pp-st-thumb-blur"<?php if ( $list_img ) : ?> style="background-image:url('<?php echo esc_url( $list_img ); ?>')"<?php endif; ?>></div>
                                    <div class="pp-st-thumb-bg"<?php if ( $list_img ) : ?> style="background-image:url('<?php echo esc_url( $list_img ); ?>')"<?php endif; ?>></div>
                                </div>
                                <div class="pp-st-item-text">
                                    <div class="pp-st-item-title"><?php echo esc_html( $list_title ); ?></div>
                                </div>
                            </a>
                        <?php endforeach; ?>

                        <?php if ( $more_text ) : ?>
                            <a href="<?php echo $more_url; ?>" class="pp-st-more"><?php echo $more_text; ?></a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else : ?>
                <p style="padding:20px;color:#888;">No posts found.</p>
            <?php endif; ?>
        </div>
        <?php
        return $css_block . ob_get_clean();
    }
}
