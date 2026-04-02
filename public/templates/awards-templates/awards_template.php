<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AwardsTemplate extends PuckPressTemplate {

    public static function get_key(): string {
        return 'awards';
    }

    public static function get_label(): string {
        return 'Standard Awards';
    }

    protected static function get_directory(): string {
        return 'awards-templates';
    }

    public static function forceResetColors(): bool {
        return false;
    }

    public static function get_default_colors(): array {
        return array(
            'page_bg'        => '#FFFFFF',
            'card_bg'        => '#FFFFFF',
            'card_text'      => '#1A1A1A',
            'secondary_text' => '#666666',
            'section_title'  => '#1A1A1A',
            'section_border' => '#E0E0E0',
            'group_title'    => '#1A1A1A',
        );
    }

    public static function get_color_labels(): array {
        return array(
            'page_bg'        => 'Page / Wrap Background',
            'card_bg'        => 'Card Background',
            'card_text'      => 'Player Name',
            'secondary_text' => 'Position / Team Text',
            'section_title'  => 'Section Title Text',
            'section_border' => 'Section Title Border',
            'group_title'    => 'Group Title Text',
        );
    }

    public static function get_default_fonts(): array {
        return array( 'awards_font' => '' );
    }

    public static function get_font_labels(): array {
        return array( 'awards_font' => 'Awards Font' );
    }

    public function render_with_options( array $data, array $options ): string {
        return '';
    }
}
