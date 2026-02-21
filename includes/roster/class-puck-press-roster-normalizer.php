<?php

/**
 * Centralised term-mapping for roster field values.
 *
 * All source systems (ACHA, USPHL, CSV) deliver inconsistently formatted
 * strings. This class converts them to canonical values before the display
 * table is read by templates, so templates never need per-variant checks.
 *
 * To add a new accepted variant, add a lowercase entry to the relevant map.
 */
class Puck_Press_Roster_Normalizer
{
    /**
     * Lookup tables keyed by lowercased input value.
     * Canonical output is always the array value.
     */
    const MAPS = [
        'position' => [
            // Generic forward
            'f'          => 'F',
            'forward'    => 'F',
            'forwards'   => 'F',
            'fwd'        => 'F',
            // Left wing
            'lw'         => 'LW',
            'left wing'  => 'LW',
            'left-wing'  => 'LW',
            'leftwing'   => 'LW',
            // Right wing
            'rw'         => 'RW',
            'right wing' => 'RW',
            'right-wing' => 'RW',
            'rightwing'  => 'RW',
            // Centre
            'c'          => 'C',
            'center'     => 'C',
            'centre'     => 'C',
            // Generic defence
            'd'          => 'D',
            'defense'    => 'D',
            'defence'    => 'D',
            'defenceman' => 'D',
            'defenseman' => 'D',
            'defender'   => 'D',
            // Left defence
            'ld'             => 'LD',
            'left defense'   => 'LD',
            'left defence'   => 'LD',
            'left d'         => 'LD',
            'left-defense'   => 'LD',
            'left-defence'   => 'LD',
            // Right defence
            'rd'             => 'RD',
            'right defense'  => 'RD',
            'right defence'  => 'RD',
            'right d'        => 'RD',
            'right-defense'  => 'RD',
            'right-defence'  => 'RD',
            // Goalie
            'g'            => 'G',
            'goalie'       => 'G',
            'goaltender'   => 'G',
            'goaltenders'  => 'G',
            'goalkeeper'   => 'G',
        ],
        'shoots' => [
            'r'     => 'R',
            'right' => 'R',
            'righty' => 'R',
            'r/h'   => 'R',
            'rh'    => 'R',
            'l'     => 'L',
            'left'  => 'L',
            'lefty' => 'L',
            'l/h'   => 'L',
            'lh'    => 'L',
        ],
    ];

    /**
     * Normalize a position string.
     * Returns the canonical value (e.g. 'LW') or the original if unrecognised.
     */
    public static function normalize_position(string $value): string
    {
        $key = strtolower(trim($value));
        return self::MAPS['position'][$key] ?? $value;
    }

    /**
     * Normalize a shoots/handedness string to 'R' or 'L'.
     * Returns the original if unrecognised.
     */
    public static function normalize_shoots(string $value): string
    {
        $key = strtolower(trim($value));
        return self::MAPS['shoots'][$key] ?? $value;
    }

    /**
     * Normalize a height string to the canonical format: 5'11"
     *
     * Handles common variants:
     *   5'11"  5'11  5-11  5 11  511  5ft11in  5ft 11in  5ft11  5'11''
     *
     * Returns the original string if it cannot be parsed.
     */
    public static function normalize_height(string $value): ?string
    {
        $v = trim($value);
        if ($v === '') {
            return $value;
        }

        $feet   = null;
        $inches = null;

        // Already in target format: 5'11" or 5'11'' or 5'11
        if (preg_match("/^(\d)\s*['\u{2019}]\s*(\d{1,2})\s*(?:\"|\"|'')?$/u", $v, $m)) {
            $feet   = (int) $m[1];
            $inches = (int) $m[2];
        }
        // Hyphen or space separated: 5-11  5 11
        elseif (preg_match('/^(\d)[\s\-](\d{1,2})$/', $v, $m)) {
            $feet   = (int) $m[1];
            $inches = (int) $m[2];
        }
        // Plain digits: 511  510  600
        elseif (preg_match('/^(\d)(\d{2})$/', $v, $m)) {
            $feet   = (int) $m[1];
            $inches = (int) $m[2];
        }
        // Verbose: 5ft11in  5ft 11in  5ft11  5 ft 11 in
        elseif (preg_match('/^(\d)\s*ft\.?\s*(\d{1,2})\s*(?:in\.?)?$/i', $v, $m)) {
            $feet   = (int) $m[1];
            $inches = (int) $m[2];
        }

        if ($feet === null || $inches === null || $inches > 11) {
            return $value; // unrecognised — leave as-is
        }

        // 0'0" means no data was provided
        if ($feet === 0 && $inches === 0) {
            return null;
        }

        return "{$feet}'{$inches}\"";
    }

    /**
     * Normalize a weight value to a plain integer string.
     * Strips units (lbs, lb, kg, etc.) and whitespace.
     * Returns null if the value is zero (placeholder) or empty.
     * Returns the original if stripping leaves nothing numeric.
     */
    public static function normalize_weight(string $value): ?string
    {
        $stripped = trim(preg_replace('/[^0-9]/', '', $value));
        if ($stripped === '' || (int) $stripped === 0) {
            return null;
        }
        return $stripped;
    }
}
