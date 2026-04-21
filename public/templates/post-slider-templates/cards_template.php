<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CardsTemplate extends PuckPressTemplate {

    public static function get_key(): string {
        return 'cards';
    }

    public static function get_label(): string {
        return 'Cards';
    }

    protected static function get_directory(): string {
        return 'post-slider-templates';
    }

    public static function forceResetColors(): bool {
        return false;
    }

    public static function get_default_colors(): array {
        return array(
            'section_bg'      => '#f5f0eb',
            'card_bg'         => '#ffffff',
            'image_bg'        => '#0d1f3c',
            'badge_bg'        => '#b8922a',
            'badge_text'      => '#ffffff',
            'date_color'      => '#4a6080',
            'title_color'     => '#0d1f3c',
            'excerpt_color'   => '#4a4a4a',
            'read_more_color' => '#0d1f3c',
        );
    }

    public static function get_color_labels(): array {
        return array(
            'section_bg'      => 'Section Background',
            'card_bg'         => 'Card Background',
            'image_bg'        => 'Image Placeholder',
            'badge_bg'        => 'Category Badge Background',
            'badge_text'      => 'Category Badge Text',
            'date_color'      => 'Date Text',
            'title_color'     => 'Card Title',
            'excerpt_color'   => 'Excerpt Text',
            'read_more_color' => 'Read More Link',
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

        ob_start();
        ?>
        <div class="cards_post_slider_container">
            <?php if ( ! empty( $posts ) ) : ?>
                <div class="pp-cards-grid">
                    <?php foreach ( $posts as $post ) :
                        $post_id = $post instanceof WP_Post ? $post->ID : (int) $post;
                        $url     = get_permalink( $post_id );
                        $title   = get_the_title( $post_id );
                        $date    = strtoupper( get_the_date( 'M j, Y', $post_id ) );
                        $excerpt = get_the_excerpt( $post_id );
                        $img     = get_the_post_thumbnail_url( $post_id, 'large' );

                        // Category badge: try standard categories, then any taxonomy, then post type label
                        $badge = '';
                        $cats  = get_the_category( $post_id );
                        if ( ! empty( $cats ) ) {
                            $badge = $cats[0]->name;
                        } else {
                            foreach ( get_post_taxonomies( $post_id ) as $tax ) {
                                $terms = get_the_terms( $post_id, $tax );
                                if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
                                    $badge = $terms[0]->name;
                                    break;
                                }
                            }
                        }
                        if ( ! $badge ) {
                            $post_type_obj = get_post_type_object( get_post_type( $post_id ) );
                            if ( $post_type_obj ) {
                                $badge = $post_type_obj->labels->singular_name;
                            }
                        }
                    ?>
                        <a href="<?php echo esc_url( $url ); ?>" class="pp-cards-card">
                            <div class="pp-cards-img-wrap">
                                <div class="pp-cards-img-bg"<?php if ( $img ) : ?> style="background-image:url('<?php echo esc_url( $img ); ?>')"<?php endif; ?>></div>
                                <?php if ( $badge ) : ?>
                                    <span class="pp-cards-badge"><?php echo esc_html( $badge ); ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="pp-cards-body">
                                <div class="pp-cards-date"><?php echo esc_html( $date ); ?></div>
                                <div class="pp-cards-title"><?php echo esc_html( $title ); ?></div>
                                <?php if ( $excerpt ) : ?>
                                    <div class="pp-cards-excerpt"><?php echo esc_html( $excerpt ); ?></div>
                                <?php endif; ?>
                                <span class="pp-cards-read-more">Read More</span>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>

                <?php if ( $more_text ) : ?>
                    <div class="pp-cards-more-wrap">
                        <a href="<?php echo $more_url; ?>" class="pp-cards-more"><?php echo $more_text; ?></a>
                    </div>
                <?php endif; ?>

            <?php else : ?>
                <p style="padding:20px;color:#888;">No posts found.</p>
            <?php endif; ?>
        </div>
        <?php
        return $css_block . ob_get_clean();
    }
}
