<?php
abstract class Puck_Press_Template_Manager
{
    protected $templates = [];

    abstract protected function get_template_dir(): string;
    abstract protected function get_option_prefix(): string;
    abstract protected function get_current_template_option(): string;
    protected static $external_script_registry = [
        'glider-js' => [
            'src' => 'https://cdn.jsdelivr.net/npm/glider-js@1/glider.min.js',
            'deps' => [],
            'ver' => '1.0.0',
            'in_footer' => true
        ],
        'moment-js' => [
            'src' => 'https://cdn.jsdelivr.net/npm/moment@2.29.1/min/moment.min.js',
            'deps' => [],
            'ver' => '2.29.1',
            'in_footer' => true
        ],
        // ^moment is just an example, you can add more external scripts here
        // More...
    ];

    public function __construct()
    {
        require_once plugin_dir_path(__FILE__) . 'class-puck-press-template-abstract.php';
        $this->register_templates();
    }

    protected function register_templates()
    {
        $template_files = glob($this->get_template_dir() . '/*_template.php');

        foreach ($template_files as $template_file) {
            require_once $template_file;

            $filename = basename($template_file, '.php');
            $class_parts = explode('_', $filename);
            $class_name = implode('', array_map('ucfirst', $class_parts));

            if (class_exists($class_name) && is_subclass_of($class_name, 'PuckPressTemplate')) {
                $key = $class_name::get_key();
                $this->templates[$key] = $class_name;

                $defaults_data = $class_name::get_default_colors();
                $force_reset = $class_name::forceResetColors();
                $this->register_template_colors(
                    $key,
                    $defaults_data ?? [],
                    $force_reset ?? false
                );
            }
        }

        $this->ensure_default_template_selected();

        if (!is_admin()) {
            $this->enqueue_current_template_assets();
        }
    }

    public function get_all_templates()
    {
        $instances = [];
        foreach ($this->templates as $key => $class_name) {
            $instances[$key] = new $class_name();
        }
        return $instances;
    }

    protected function ensure_default_template_selected()
    {
        $current = $this->get_current_template_key();
        if (empty($current) || !isset($this->templates[$current])) {
            $keys = array_keys($this->templates);
            if (!empty($keys)) {
                $this->set_current_template_key($keys[0]);
            }
        }
    }

    public function enqueue_current_template_assets($handle_prefix = 'puck-press')
    {
        $current_key = $this->get_current_template_key();
        if (!empty($current_key)) {
            $this->enqueue_template_assets($current_key, $handle_prefix);
        }
    }

    public function enqueue_all_template_assets($handle_prefix = 'puck-press')
    {
        foreach ($this->templates as $key => $_) {
            $this->enqueue_template_assets($key, $handle_prefix);
        }
    }

    public static function get_external_script_registry()
    {
        return static::$external_script_registry;
    }

    public function enqueue_template_assets($template_key, $handle_prefix = 'puck-press')
    {
        if (!isset($this->templates[$template_key])) return;

        $class_name = $this->templates[$template_key];
        $css_url = $class_name::get_css_url();
        $js_url = $class_name::get_js_url();
        $css_path = $class_name::get_css_path();
        $js_path = $class_name::get_js_path();

        // CSS enqueue
        if (file_exists($css_path)) {
            wp_enqueue_style(
                "$handle_prefix-template-$template_key",
                $css_url,
                [],
                filemtime($css_path)
            );

            if (method_exists($class_name, 'get_inline_css')) {
                $inline = $class_name::get_inline_css();
                if ($inline) {
                    wp_add_inline_style("$handle_prefix-template-$template_key", $inline);
                }
            }
        }

        // JS enqueue
        if (file_exists($js_path)) {
            // Default dependency is jQuery
            $dependencies = ['jquery'];

            // Check if the class defines additional dependencies
            if (method_exists($class_name, 'get_js_dependencies')) {
                $dependencies = $class_name::get_js_dependencies();
            }

            // Get external script registry
            $registry = $this->get_external_script_registry();

            // Register (and enqueue) all external dependencies
            foreach ($dependencies as $dep) {
                if (isset($registry[$dep])) {
                    // Use wp_enqueue_script instead of wp_register_script for safety
                    wp_enqueue_script(
                        $dep,
                        $registry[$dep]['src'],
                        $registry[$dep]['deps'],
                        $registry[$dep]['ver'],
                        $registry[$dep]['in_footer']
                    );
                }
            }

            // Enqueue the main JS file, now that all dependencies are registered/enqueued
            wp_enqueue_script(
                "$handle_prefix-template-$template_key",
                $js_url,
                $dependencies,
                filemtime($js_path),
                true
            );
        }
    }

    public function get_current_template_key()
    {
        return get_option($this->get_current_template_option(), '');
    }

    public function set_current_template_key($key)
    {
        if (!isset($this->templates[$key])) return false;
        return update_option($this->get_current_template_option(), $key);
    }

    public function get_current_template()
    {
        $key = $this->get_current_template_key();
        if (empty($key) || !isset($this->templates[$key])) {
            $keys = array_keys($this->templates);
            $key = $keys[0] ?? null;
            if (!$key) return null;
            $this->set_current_template_key($key);
        }
        return new $this->templates[$key]();
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
     * @param array $defaults Default color values.
     * @param bool $force_reset Whether to overwrite all existing values with defaults.
     */
    protected function register_template_colors($key, $defaults, $force_reset = false)
    {;
        $option_name = $this->get_option_prefix() . $key;
        $existing = get_option($option_name, []);
        if (!is_array($existing)) {
            // Optional: delete_option($option_name); // or log it
            $existing = [];
        }

        // Reset to defaults if flag is passed
        if ($force_reset) {
            update_option($option_name, $defaults);
            return;
        }

        $has_new = false;
        foreach ($defaults as $k => $v) {
            if (!isset($existing[$k])) {
                $existing[$k] = $v;
                $has_new = true;
            }
        }

        if (empty($existing)) {
            update_option($option_name, $defaults);
        } elseif ($has_new) {
            update_option($option_name, $defaults);
        }
    }

    public function get_all_template_colors()
    {
        $colors = [];
        foreach ($this->templates as $key => $class_name) {
            $colors[$key] = $class_name::get_template_colors();
        }
        return $colors;
    }

    public function save_template_colors($key, $colors)
    {
        $option = $this->get_option_prefix() . $key;
        if (!isset($this->templates[$key])) return false;

        $defaults = $this->templates[$key]::get_default_colors();

        foreach ($defaults as $k => $v) {
            if (!isset($colors[$k])) $colors[$k] = $v;
        }

        return update_option($option, $colors);
    }

    public function reset_template_colors($key)
    {
        if (!isset($this->templates[$key])) return false;
        $defaults = $this->templates[$key]::get_default_colors();
        return update_option($this->get_option_prefix() . $key, $defaults);
    }

    public function get_template($key)
    {
        if (isset($this->templates[$key])) {
            return new $this->templates[$key]();
        }

        $first_class = reset($this->templates);
        return $first_class ? new $first_class() : null;
    }

    public function get_registered_templates_metadata()
    {
        $templates_array = [];

        foreach ($this->templates as $key => $class_name) {
            $templates_array[$key] = [
                'name' => $class_name::get_label(),
                'css' => $class_name::get_css_url(),
                'js' => $class_name::get_js_url(),
                'file' => $class_name::get_template_path(),
                'default_colors' => $class_name::get_default_colors()
            ];
        }

        return $templates_array;
    }
}
