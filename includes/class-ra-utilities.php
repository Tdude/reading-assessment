<?php
/**
 * File: includes/class-ra-utilities.php
 * Get many HSL colors for stats
 */

class Reading_Assessment_Utilities {
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

  /**
   * Generate an array of colors based on Material Design
   * If more colors are needed than base colors available,
   * generate variations using HSL
   *
   * @param int $count Number of colors needed
   * @return array Array of hex color codes
   */

    // Google Material Design color base
    private $material_colors = [
        '#4285F4', // Google Blue
        '#EA4335', // Google Red
        '#FBBC05', // Google Yellow
        '#34A853', // Google Green
        '#FF7043', // Deep Orange
        '#9C27B0', // Purple
        '#00ACC1', // Cyan
        '#FB8C00'  // Orange
    ];
    public function generate_colors($count) {
        // If we have enough material colors, use them
        if ($count <= count($this->material_colors)) {
            return array_slice($this->material_colors, 0, $count);
        }

        // If we need more colors, use material colors as base
        // and generate variations for the remaining
        $colors = $this->material_colors;
        $remaining = $count - count($this->material_colors);

        // For each remaining color needed, create a variation
        // based on the material colors
        for ($i = 0; $i < $remaining; $i++) {
            // Get the base material color to vary from
            $base_color = $this->material_colors[$i % count($this->material_colors)];

            // Convert hex to HSL, modify, and convert back
            $hsl = $this->hex_to_hsl($base_color);

            // Shift the hue slightly and adjust saturation/lightness
            $hsl['h'] += 20 * ($i + 1); // Shift hue by 20 degrees each time
            $hsl['h'] = fmod($hsl['h'], 360); // Keep hue within 0-360
            $hsl['s'] = min(100, $hsl['s'] + ($i * 5)); // Increase saturation slightly
            $hsl['l'] = max(35, min(65, $hsl['l'] + ($i % 3 - 1) * 10)); // Vary lightness

            $colors[] = $this->hsl_to_hex($hsl['h'], $hsl['s'], $hsl['l']);
        }

        return $colors;
    }

    /**
     * Convert hex color to HSL
     *
     * @param string $hex Hex color code
     * @return array HSL values
     */
    private function hex_to_hsl($hex) {
        // Remove # if present
        $hex = ltrim($hex, '#');

        // Convert hex to RGB
        $r = hexdec(substr($hex, 0, 2)) / 255;
        $g = hexdec(substr($hex, 2, 2)) / 255;
        $b = hexdec(substr($hex, 4, 2)) / 255;

        $max = max($r, $g, $b);
        $min = min($r, $g, $b);

        $h = $s = $l = ($max + $min) / 2;

        if ($max == $min) {
            $h = $s = 0;
        } else {
            $d = $max - $min;
            $s = $l > 0.5 ? $d / (2 - $max - $min) : $d / ($max + $min);

            switch ($max) {
                case $r:
                    $h = ($g - $b) / $d + ($g < $b ? 6 : 0);
                    break;
                case $g:
                    $h = ($b - $r) / $d + 2;
                    break;
                case $b:
                    $h = ($r - $g) / $d + 4;
                    break;
            }

            $h = $h / 6;
        }

        return [
            'h' => $h * 360,
            's' => $s * 100,
            'l' => $l * 100
        ];
    }

    /**
     * Convert HSL color values to hexadecimal color code
     *
     * @param float $h Hue (0-360)
     * @param float $s Saturation (0-100)
     * @param float $l Lightness (0-100)
     * @return string Hex color code
     */
    private function hsl_to_hex($h, $s, $l) {
        $s /= 100;
        $l /= 100;

        $c = (1 - abs(2 * $l - 1)) * $s;
        $x = $c * (1 - abs(fmod($h / 60.0, 2) - 1));
        $m = $l - $c / 2;

        if ($h < 60) {
            $r = $c; $g = $x; $b = 0;
        } elseif ($h < 120) {
            $r = $x; $g = $c; $b = 0;
        } elseif ($h < 180) {
            $r = 0; $g = $c; $b = $x;
        } elseif ($h < 240) {
            $r = 0; $g = $x; $b = $c;
        } elseif ($h < 300) {
            $r = $x; $g = 0; $b = $c;
        } else {
            $r = $c; $g = 0; $b = $x;
        }

        $r = round(($r + $m) * 255);
        $g = round(($g + $m) * 255);
        $b = round(($b + $m) * 255);

        return sprintf("#%02x%02x%02x", $r, $g, $b);
    }
}