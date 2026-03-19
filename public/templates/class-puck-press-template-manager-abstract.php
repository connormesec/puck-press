<?php
abstract class Puck_Press_Template_Manager {

	protected $templates = array();

	/**
	 * Per-request cache of registered template arrays, keyed by option prefix.
	 * Prevents repeat glob/require/option-read work when the same manager type
	 * and ID is instantiated more than once in a single page load.
	 */
	private static array $template_cache = array();

	abstract protected function get_template_dir(): string;
	abstract protected function get_option_prefix(): string;
	abstract protected function get_current_template_option(): string;
	protected static $external_script_registry = array(
		'glider-js' => array(
			'src'       => 'https://cdn.jsdelivr.net/npm/glider-js@1/glider.min.js',
			'deps'      => array(),
			'ver'       => '1.0.0',
			'in_footer' => true,
		),
		'moment-js' => array(
			'src'       => 'https://cdn.jsdelivr.net/npm/moment@2.29.1/min/moment.min.js',
			'deps'      => array(),
			'ver'       => '2.29.1',
			'in_footer' => true,
		),
		// ^moment is just an example, you can add more external scripts here
		// More...
	);

	public function __construct() {
		require_once plugin_dir_path( __FILE__ ) . 'class-puck-press-template-abstract.php';
		$this->register_templates();
	}

	protected function register_templates() {
		// Use the option prefix as a unique key per class+id combination.
		// Concrete managers set their ID before calling parent::__construct(),
		// so get_option_prefix() is already correct at this point.
		$cache_key = $this->get_option_prefix();

		if ( isset( self::$template_cache[ $cache_key ] ) ) {
			$this->templates = self::$template_cache[ $cache_key ];
			if ( ! is_admin() ) {
				$this->enqueue_current_template_assets();
			}
			return;
		}

		$template_files = glob( $this->get_template_dir() . '/*_template.php' );

		foreach ( $template_files as $template_file ) {
			require_once $template_file;

			$filename    = basename( $template_file, '.php' );
			$class_parts = explode( '_', $filename );
			$class_name  = implode( '', array_map( 'ucfirst', $class_parts ) );

			if ( class_exists( $class_name ) && is_subclass_of( $class_name, 'PuckPressTemplate' ) ) {
				$key                     = $class_name::get_key();
				$this->templates[ $key ] = $class_name;

				$defaults_data = $class_name::get_default_colors();
				$force_reset   = $class_name::forceResetColors();
				$this->register_template_colors(
					$key,
					$defaults_data ?? array(),
					$force_reset ?? false
				);

				$this->register_template_fonts( $key, $class_name::get_default_fonts() );
			}
		}

		self::$template_cache[ $cache_key ] = $this->templates;

		$this->ensure_default_template_selected();

		if ( ! is_admin() ) {
			$this->enqueue_current_template_assets();
		}
	}

	public function get_all_templates() {
		$instances = array();
		foreach ( $this->templates as $key => $class_name ) {
			$instances[ $key ] = new $class_name();
		}
		return $instances;
	}

	protected function ensure_default_template_selected() {
		$current = $this->get_current_template_key();
		if ( empty( $current ) || ! isset( $this->templates[ $current ] ) ) {
			$keys = array_keys( $this->templates );
			if ( ! empty( $keys ) ) {
				$this->set_current_template_key( $keys[0] );
			}
		}
	}

	public function enqueue_current_template_assets( $handle_prefix = 'puck-press' ) {
		$current_key = $this->get_current_template_key();
		if ( ! empty( $current_key ) ) {
			$this->enqueue_template_assets( $current_key, $handle_prefix );
		}
	}

	public function enqueue_all_template_assets( $handle_prefix = 'puck-press' ) {
		foreach ( $this->templates as $key => $_ ) {
			$this->enqueue_template_assets( $key, $handle_prefix );
		}
	}

	public static function get_external_script_registry() {
		return static::$external_script_registry;
	}

	public function enqueue_template_assets( $template_key, $handle_prefix = 'puck-press' ) {
		if ( ! isset( $this->templates[ $template_key ] ) ) {
			return;
		}

		$class_name = $this->templates[ $template_key ];
		$css_url    = $class_name::get_css_url();
		$js_url     = $class_name::get_js_url();
		$css_path   = $class_name::get_css_path();
		$js_path    = $class_name::get_js_path();

		// Google Fonts — enqueue any saved font for this template
		$template_fonts = $class_name::get_template_fonts();
		foreach ( $template_fonts as $font_key => $font_name ) {
			if ( ! empty( $font_name ) ) {
				$font_url_name = urlencode( $font_name );
				wp_enqueue_style(
					"$handle_prefix-template-$template_key-gf-$font_key",
					"https://fonts.googleapis.com/css2?family={$font_url_name}:wght@400;600;700;800&display=swap",
					array(),
					null
				);
			}
		}

		// CSS enqueue
		if ( file_exists( $css_path ) ) {
			wp_enqueue_style(
				"$handle_prefix-template-$template_key",
				$css_url,
				array(),
				filemtime( $css_path )
			);

			if ( method_exists( $class_name, 'get_inline_css' ) ) {
				$inline = $class_name::get_inline_css();
				if ( $inline ) {
					wp_add_inline_style( "$handle_prefix-template-$template_key", $inline );
				}
			}
		}

		// JS enqueue
		if ( file_exists( $js_path ) ) {
			// Default dependency is jQuery
			$dependencies = array( 'jquery' );

			// Check if the class defines additional dependencies
			if ( method_exists( $class_name, 'get_js_dependencies' ) ) {
				$dependencies = $class_name::get_js_dependencies();
			}

			// Get external script registry
			$registry = $this->get_external_script_registry();

			// Register (and enqueue) all external dependencies; filter out
			// any non-external deps that aren't registered on this page
			// (e.g. pp-player-detail is only registered on frontend roster pages).
			$resolved_deps = array();
			foreach ( $dependencies as $dep ) {
				if ( isset( $registry[ $dep ] ) ) {
					wp_enqueue_script(
						$dep,
						$registry[ $dep ]['src'],
						$registry[ $dep ]['deps'],
						$registry[ $dep ]['ver'],
						$registry[ $dep ]['in_footer']
					);
					$resolved_deps[] = $dep;
				} elseif ( wp_script_is( $dep, 'registered' ) ) {
					$resolved_deps[] = $dep;
				}
				// If not registered and not external, skip it so this template's
				// JS still loads (e.g. standard.js on admin pages without pp-player-detail).
			}

			// Enqueue the main JS file, now that all dependencies are registered/enqueued
			wp_enqueue_script(
				"$handle_prefix-template-$template_key",
				$js_url,
				$resolved_deps,
				filemtime( $js_path ),
				true
			);
		}
	}

	public function get_current_template_key() {
		return get_option( $this->get_current_template_option(), '' );
	}

	public function set_current_template_key( $key ) {
		if ( ! isset( $this->templates[ $key ] ) ) {
			return false;
		}
		return update_option( $this->get_current_template_option(), $key );
	}

	public function get_current_template() {
		$key = $this->get_current_template_key();
		if ( empty( $key ) || ! isset( $this->templates[ $key ] ) ) {
			$keys = array_keys( $this->templates );
			$key  = $keys[0] ?? null;
			if ( ! $key ) {
				return null;
			}
			$this->set_current_template_key( $key );
		}
		return new $this->templates[ $key ]();
	}


	/**
	 * Registers default color values for a given template key.
	 *
	 * - If the option does not exist, it will be created with the provided defaults.
	 * - If the option exists but is missing some keys, only the missing keys will be added (non-destructive).
	 * - If $force_reset is true, the existing option will be completely overwritten with the new defaults.
	 *
	 * Use $force_reset when:
	 * - You’ve changed or corrected the default values in your code and want to push the updated defaults,
	 *   even if the option already exists.
	 * - You’re setting up a new template and need to reset the color settings to match the intended baseline.
	 *
	 * @param string $key Template key to identify the option.
	 * @param array  $defaults Default color values.
	 * @param bool   $force_reset Whether to overwrite all existing values with defaults.
	 */
	protected function register_template_colors( $key, $defaults, $force_reset = false ) {

		$option_name = $this->get_option_prefix() . $key;
		$existing    = get_option( $option_name, array() );
		if ( ! is_array( $existing ) ) {
			// Optional: delete_option($option_name); // or log it
			$existing = array();
		}

		// Reset to defaults if flag is passed
		if ( $force_reset ) {
			update_option( $option_name, $defaults );
			return;
		}

		$has_new = false;
		foreach ( $defaults as $k => $v ) {
			if ( ! isset( $existing[ $k ] ) ) {
				$existing[ $k ] = $v;
				$has_new        = true;
			}
		}

		if ( empty( $existing ) ) {
			update_option( $option_name, $defaults );
		} elseif ( $has_new ) {
			update_option( $option_name, $existing );
		}
	}

	public function get_all_template_colors() {
		$colors = array();
		foreach ( $this->templates as $key => $class_name ) {
			$colors[ $key ] = $class_name::get_template_colors();
		}
		return $colors;
	}

	public function get_all_template_color_labels() {
		$labels = array();
		foreach ( $this->templates as $key => $class_name ) {
			$labels[ $key ] = $class_name::get_color_labels();
		}
		return $labels;
	}

	// -------------------------------------------------------------------------
	// Font management (parallel to color management)
	// -------------------------------------------------------------------------

	protected function get_fonts_option_prefix(): string {
		return str_replace( '_colors_', '_fonts_', $this->get_option_prefix() );
	}

	protected function register_template_fonts( string $key, array $defaults ): void {
		$option   = $this->get_fonts_option_prefix() . $key;
		$existing = get_option( $option );

		if ( $existing === false ) {
			update_option( $option, $defaults );
			return;
		}

		if ( ! is_array( $existing ) ) {
			$existing = array();
		}

		$has_new = false;
		foreach ( $defaults as $k => $v ) {
			if ( ! array_key_exists( $k, $existing ) ) {
				$existing[ $k ] = $v;
				$has_new        = true;
			}
		}

		if ( $has_new ) {
			update_option( $option, $existing );
		}
	}

	public function get_all_template_fonts(): array {
		$fonts = array();
		foreach ( $this->templates as $key => $class_name ) {
			$fonts[ $key ] = $class_name::get_template_fonts();
		}
		return $fonts;
	}

	public function get_all_template_font_labels(): array {
		$labels = array();
		foreach ( $this->templates as $key => $class_name ) {
			$labels[ $key ] = $class_name::get_font_labels();
		}
		return $labels;
	}

	public function save_template_fonts( string $key, array $fonts ): bool {
		if ( ! isset( $this->templates[ $key ] ) ) {
			return false;
		}
		return (bool) update_option( $this->get_fonts_option_prefix() . $key, $fonts );
	}

	public function save_template_colors( $key, $colors ) {
		$option = $this->get_option_prefix() . $key;
		if ( ! isset( $this->templates[ $key ] ) ) {
			return false;
		}

		$defaults = $this->templates[ $key ]::get_default_colors();

		foreach ( $defaults as $k => $v ) {
			if ( ! isset( $colors[ $k ] ) ) {
				$colors[ $k ] = $v;
			}
		}

		return update_option( $option, $colors );
	}

	public function reset_template_colors( $key ) {
		if ( ! isset( $this->templates[ $key ] ) ) {
			return false;
		}
		$defaults = $this->templates[ $key ]::get_default_colors();
		return update_option( $this->get_option_prefix() . $key, $defaults );
	}

	public function get_template( $key ) {
		if ( isset( $this->templates[ $key ] ) ) {
			return new $this->templates[ $key ]();
		}

		$first_class = reset( $this->templates );
		return $first_class ? new $first_class() : null;
	}

	public function get_registered_templates_metadata() {
		$templates_array = array();

		foreach ( $this->templates as $key => $class_name ) {
			$templates_array[ $key ] = array(
				'name'           => $class_name::get_label(),
				'css'            => $class_name::get_css_url(),
				'js'             => $class_name::get_js_url(),
				'file'           => $class_name::get_template_path(),
				'default_colors' => $class_name::get_default_colors(),
			);
		}

		return $templates_array;
	}
}
