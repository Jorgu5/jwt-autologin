<?php

/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       https://sobolew.ski
 * @since      1.0.0
 *
 * @package    Jwt_Autologin
 * @subpackage Jwt_Autologin/includes
 */

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.0
 * @package    Jwt_Autologin
 * @subpackage Jwt_Autologin/includes
 * @author     Codeable - Sobolew.ski <tomek@sobolew.ski>
 */
class Jwt_Autologin
{

	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      Jwt_Autologin_Loader    $loader    Maintains and registers all hooks for the plugin.
	 */
	protected $loader;

	/**
	 * The unique identifier of this plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $plugin_name    The string used to uniquely identify this plugin.
	 */
	protected $plugin_name;

	/**
	 * The current version of the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $version    The current version of the plugin.
	 */
	protected $version;

	/**
	 * Define the core functionality of the plugin.
	 *
	 * Set the plugin name and the plugin version that can be used throughout the plugin.
	 * Load the dependencies, define the locale, and set the hooks for the admin area and
	 * the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function __construct()
	{
		if (defined('JWT_AUTOLOGIN_VERSION')) {
			$this->version = JWT_AUTOLOGIN_VERSION;
		} else {
			$this->version = '1.0.0';
		}
		$this->plugin_name = 'jwt-autologin';

		$this->load_dependencies();
		$this->define_public_hooks();

		add_action('rest_api_init', array($this, 'create_custom_endpoint'));
		add_action('init', array($this, 'redirect_on_load'));
	}

	/**
	 * Load the required dependencies for this plugin.
	 *
	 * Include the following files that make up the plugin:
	 *
	 * - Jwt_Autologin_Loader. Orchestrates the hooks of the plugin.
	 * - Jwt_Autologin_i18n. Defines internationalization functionality.
	 * - Jwt_Autologin_Admin. Defines all hooks for the admin area.
	 * - Jwt_Autologin_Public. Defines all hooks for the public side of the site.
	 *
	 * Create an instance of the loader which will be used to register the hooks
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function load_dependencies()
	{

		/**
		 * The class responsible for orchestrating the actions and filters of the
		 * core plugin.
		 */
		require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-jwt-autologin-loader.php';

		/**
		 * The class responsible for defining all actions that occur in the public-facing
		 * side of the site.
		 */
		require_once plugin_dir_path(dirname(__FILE__)) . 'public/class-jwt-autologin-public.php';

		$this->loader = new Jwt_Autologin_Loader();
	}

	/**
	 * Register all of the hooks related to the public-facing functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_public_hooks()
	{

		$plugin_public = new Jwt_Autologin_Public($this->get_plugin_name(), $this->get_version());

		$this->loader->add_action('wp_enqueue_scripts', $plugin_public, 'enqueue_styles');
		$this->loader->add_action('wp_enqueue_scripts', $plugin_public, 'enqueue_scripts');
	}

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 *
	 * @since    1.0.0
	 */
	public function run()
	{
		$this->loader->run();
	}

	/**
	 * The name of the plugin used to uniquely identify it within the context of
	 * WordPress and to define internationalization functionality.
	 *
	 * @since     1.0.0
	 * @return    string    The name of the plugin.
	 */
	public function get_plugin_name()
	{
		return $this->plugin_name;
	}

	/**
	 * The reference to the class that orchestrates the hooks with the plugin.
	 *
	 * @since     1.0.0
	 * @return    Jwt_Autologin_Loader    Orchestrates the hooks of the plugin.
	 */
	public function get_loader()
	{
		return $this->loader;
	}

	/**
	 * Retrieve the version number of the plugin.
	 *
	 * @since     1.0.0
	 * @return    string    The version number of the plugin.
	 */
	public function get_version()
	{
		return $this->version;
	}

	/**
	 * Create REST API Route for POSTing JWT token
	 */

	public function create_custom_endpoint()
	{
		register_rest_route('jwt-autologin', '/token', array(
			'methods' => ['POST'],
			'callback' => array($this, 'custom_endpoint_response')
		));
	}

	/**
	 * Autologin feature
	 * Login automatically if user exists. 
	 */

	public function login_user($user_id)
	{
		wp_set_current_user($user_id);
		wp_set_auth_cookie($user_id, true);
	}

	/**
	 * Register user in the backround
	 */

	public function register_user(object $user_data)
	{

		// Hash password
		$pass = hash('md5', $user_data->first_name . $user_data->entity_uuid, false, []);

		// Wrap Entity UUIDS
		array_unshift($user_data->member_entity_uuids, $user_data->entity_uuid);

		// Prepare date format
		$birthdate = date('F j, Y', strtotime($user_data->dob));

		$user_data_array = array(
			'user_pass' => $pass,
			'user_login' => $user_data->username,
			'user_email' => $user_data->email,
			'display_name' => $user_data->first_name . $user_data->last_name,
			'first_name' => $user_data->first_name,
			'last_name' => $user_data->last_name,
			'role' => 'subscriber',
		);

		// Create Use
		$user = wp_insert_user($user_data_array);

		// Additional User Meta in BuddyBoss
		if (!is_wp_error($user)) {

			// Add Entity UUIDS to custom meta
			update_user_meta($user, 'entity_uuids', $user_data->member_entity_uuids);
			// Add Member Usernames to custom meta
			update_user_meta($user, 'member_usernames', $user_data->member_usernames);

			if (function_exists('xprofile_set_field_data')) {
				// Set Birthdate date in xProfile BuddyBoss
				xprofile_set_field_data(4, $user, $birthdate, false);
				// Sync fields with BuddyBoss
				bp_xprofile_sync_bp_profile($user);
			}

			$this->login_user($user);
		}

		return true;
	}

	/**
	 * WP Rest API callback
	 */

	public function custom_endpoint_response(WP_REST_REQUEST $request)
	{
		$response['encoded_token'] = $request['encoded_token'];

		$res = new WP_REST_Response($response);
		$res->set_status(200);

		// Decode JWT

		if (!empty($response['encoded_token'])) {

			$user_data = json_decode(base64_decode(str_replace('_', '/', str_replace('-', '+', explode('.', $response['encoded_token'])[1]))));

			// Redirect if response is empty or there is no email
			if (empty($user_data->email)) {
				$this->redirect_back('https://app.uptogether.org/');
				$res->set_status(302);
				return;
			}

			// Register user

			$user_registered = $this->register_user($user_data);

			// Get ID of user

			$user = get_user_by('email', $user_data->email);

			// Login if exists
			if ($user_registered) {
				$this->login_user($user->ID);
			}

			return $user_data;
		}

		$this->redirect_back('https://app.uptogether.org/');
		$res->set_status(302);
		return;
	}

	public function redirect_back($url)
	{
		wp_redirect($url);
		exit;
	}

	public function redirect_on_load()
	{
		if (!is_user_logged_in()) {
			$this->redirect_back('https://app.uptogether.org/');
		}
	}
}
