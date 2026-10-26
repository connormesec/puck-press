<?php

/**
 * Updates Puck Press from GitHub releases.
 *
 * Runs on every request (not only wp-admin) so that WordPress auto-updates
 * (cron) and `wp plugin update` over WP-CLI can see new releases.
 */
class PDUpdater {
	const CACHE_KEY = 'puck_press_github_release';
	const CACHE_TTL = 6 * HOUR_IN_SECONDS;

	private $file;
	private $plugin;
	private $basename;
	private $active;
	private $username;
	private $repository;
	private $authorize_token;
	private $github_response;

	public function __construct( $file ) {
		$this->file     = $file;
		$this->basename = plugin_basename( $file );

		return $this;
	}

	/**
	 * Loads plugin header data on first use. get_plugin_data() lives in
	 * wp-admin, so it isn't loaded on cron or front-end requests.
	 */
	public function set_plugin_properties() {
		if ( ! empty( $this->plugin ) ) {
			return;
		}
		if ( ! function_exists( 'get_plugin_data' ) || ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$this->plugin = get_plugin_data( $this->file, false, false );
		$this->active = is_plugin_active( $this->basename );
	}

	public function set_username( $username ) {
		$this->username = $username;
	}

	public function set_repository( $repository ) {
		$this->repository = $repository;
	}

	public function authorize( $token ) {
		$this->authorize_token = $token;
	}

	/**
	 * Fetches the latest published release (never drafts or prereleases).
	 * Cached because every site shares one server IP and GitHub limits
	 * unauthenticated requests to 60 per hour.
	 */
	private function get_repository_info() {
		if ( ! is_null( $this->github_response ) ) {
			return;
		}

		$cached = get_site_transient( self::CACHE_KEY );
		if ( is_array( $cached ) ) {
			$this->github_response = $cached;
			return;
		}

		$request_uri = sprintf( 'https://api.github.com/repos/%s/%s/releases/latest', $this->username, $this->repository );
		$args        = array( 'headers' => array( 'Accept' => 'application/vnd.github+json' ) );
		if ( $this->authorize_token ) {
			$args['headers']['Authorization'] = 'Bearer ' . $this->authorize_token;
		}

		$raw      = wp_remote_get( $request_uri, $args );
		$response = json_decode( wp_remote_retrieve_body( $raw ), true );

		if ( 200 === wp_remote_retrieve_response_code( $raw ) && is_array( $response ) && isset( $response['tag_name'] ) ) {
			set_site_transient( self::CACHE_KEY, $response, self::CACHE_TTL );
			$this->github_response = $response;
		} else {
			// Don't cache failures; try again on the next update check.
			$this->github_response = array();
		}
	}

	/**
	 * Clears the cached release, so "Check again" in wp-admin and
	 * `wp plugin update` always see the newest tag.
	 */
	public function clear_cache() {
		delete_site_transient( self::CACHE_KEY );
		$this->github_response = null;
	}

	public function initialize() {
		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'modify_transient' ), 10, 1 );
		add_filter( 'plugins_api', array( $this, 'plugin_popup' ), 10, 3 );
		add_filter( 'upgrader_post_install', array( $this, 'after_install' ), 10, 3 );
		add_action( 'delete_site_transient_update_plugins', array( $this, 'clear_cache' ) );
	}

	public function modify_transient( $transient ) {
		if ( ! is_object( $transient ) || empty( $transient->checked ) ) {
			return $transient;
		}

		$checked = $transient->checked;
		if ( ! isset( $checked[ $this->basename ] ) ) {
			return $transient;
		}

		$this->set_plugin_properties();
		$this->get_repository_info();

		if ( empty( $this->github_response['tag_name'] ) ) {
			return $transient;
		}

		$slug   = dirname( $this->basename );
		$plugin = (object) array(
			'id'          => $this->basename,
			'plugin'      => $this->basename,
			'slug'        => $slug,
			'url'         => $this->plugin['PluginURI'],
			'package'     => $this->github_response['zipball_url'],
			'new_version' => $this->github_response['tag_name'],
		);

		if ( version_compare( $this->github_response['tag_name'], $checked[ $this->basename ], 'gt' ) ) {
			$transient->response[ $this->basename ] = $plugin;
		} else {
			// Listing it as "no update" is what makes WordPress show the
			// "Enable auto-updates" link on the Plugins screen.
			$transient->no_update[ $this->basename ] = $plugin;
		}

		return $transient;
	}

	public function plugin_popup( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || empty( $args->slug ) ) {
			return $result;
		}

		if ( $args->slug !== dirname( $this->basename ) ) {
			return $result;
		}

		$this->set_plugin_properties();
		$this->get_repository_info();

		if ( empty( $this->github_response['tag_name'] ) ) {
			return $result;
		}

		$plugin = array(
			'name'              => $this->plugin['Name'],
			'slug'              => $this->basename,
			'requires'          => '5.3',
			'tested'            => '5.4',
			'version'           => $this->github_response['tag_name'],
			'author'            => $this->plugin['AuthorName'],
			'author_profile'    => $this->plugin['AuthorURI'],
			'last_updated'      => $this->github_response['published_at'],
			'homepage'          => $this->plugin['PluginURI'],
			'short_description' => $this->plugin['Description'],
			'sections'          => array(
				'Description' => $this->plugin['Description'],
				'Updates'     => $this->github_response['body'],
			),
			'download_link'     => $this->github_response['zipball_url'],
		);

		return (object) $plugin;
	}

	/**
	 * GitHub zips unpack to a folder like "connormesec-puck-press-abc123".
	 * Move it back to the plugin's own folder. Only for Puck Press: this
	 * filter runs for every plugin and theme install.
	 */
	public function after_install( $response, $hook_extra, $result ) {
		global $wp_filesystem;

		if ( empty( $hook_extra['plugin'] ) || $hook_extra['plugin'] !== $this->basename ) {
			return $response;
		}

		$this->set_plugin_properties();

		$install_directory = plugin_dir_path( $this->file );
		$wp_filesystem->move( $result['destination'], $install_directory );
		$result['destination'] = $install_directory;

		if ( $this->active ) {
			activate_plugin( $this->basename );
		}

		return $result;
	}
}
