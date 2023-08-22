<?php

/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       https://wordpress.org/plugins/azure-app-service-migration/
 * @since      1.0.0
 *
 * @package    Azure_app_service_migration
 * @subpackage Azure_app_service_migration/includes
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
 * @package    Azure_app_service_migration
 * @subpackage Azure_app_service_migration/includes
 * @author     Microsoft <wordpressdev@microsoft.com>
 */
class Azure_app_service_migration
{

    /**
     * The loader that's responsible for maintaining and registering all hooks that power
     * the plugin.
     *
     * @since    1.0.0
     * @access   protected
     * @var      Azure_app_service_migration_Loader    $loader    Maintains and registers all hooks for the plugin.
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
        if (defined('AZURE_APP_SERVICE_MIGRATION_VERSION')) {
            $this->version = AZURE_APP_SERVICE_MIGRATION_VERSION;
        } else {
            $this->version = '1.0.0';
        }
        $this->plugin_name = 'azure_app_service_migration';

        $this->load_dependencies();
        $this->set_locale();
        $this->define_admin_hooks();
        $this->define_public_hooks();

    }

    /**
     * Load the required dependencies for this plugin.
     *
     * Include the following files that make up the plugin:
     *
     * - Azure_app_service_migration_Loader. Orchestrates the hooks of the plugin.
     * - Azure_app_service_migration_i18n. Defines internationalization functionality.
     * - Azure_app_service_migration_Export_Controller. Defines all hooks for the admin area.
     * - Azure_app_service_migration_Public. Defines all hooks for the public side of the site.
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
         * Load all vendor (3rd party) libraries (azure blob storage)
         */
        require_once plugin_dir_path(dirname(__FILE__)) . "vendor/autoload.php"; 

        /**
         * The class responsible for orchestrating the actions and filters of the
         * core plugin.
         */
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-azure_app_service_migration-loader.php';

        /**
         * The class responsible for defining internationalization functionality
         * of the plugin.
         */
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-azure_app_service_migration-i18n.php';

        /**
         * The class responsible for defining all actions that occur in the admin area.
         */
        require_once plugin_dir_path(dirname(__FILE__)) . 'admin/engines/class-azure_app_service_migration-export-controller.php';

        /**
         * The class responsible for defining all actions that occur in the public-facing
         * side of the site.
         */
        require_once plugin_dir_path(dirname(__FILE__)) . 'public/class-azure_app_service_migration-public.php';

        /**
         * The class responsible for handling the export ajax calls
         */
        require_once plugin_dir_path(dirname(__FILE__)) . 'admin/engines/export/class-azure_app_service_migration-export-ajaxhandler.php';
        /**
         * The class responsible for handling the export filebackup
         */
        require_once plugin_dir_path(dirname(__FILE__)) . 'admin/engines/export/class-azure_app_service_migration-export-filebackup-handler.php';

        /**
                * The class responsible for calling all actions for Import.
                */
        require_once plugin_dir_path(dirname(__FILE__)) . 'admin/engines/class-azure_app_service_migration-import-controller.php';

        /**
        +        * The class responsible for calling all actions for Export.
        +        */
        +require_once plugin_dir_path(dirname(__FILE__)) . 'admin/engines/class-azure_app_service_migration-export.php';

        /**
        +        * The class responsible for defining actions for wp-content import.
        +        */
        +require_once plugin_dir_path(dirname(__FILE__)) . 'admin/engines/import/class-azure_app_service_migration-import-content.php';

        /**
        +        * The class responsible for defining actions for database import.
        +        */
        +require_once plugin_dir_path(dirname(__FILE__)) . 'admin/engines/import/class-azure_app_service_migration-import-database.php';

        /**
        +        * The class responsible for defining actions for zip encryption.
        +        */
        +require_once plugin_dir_path(dirname(__FILE__)) . 'admin/engines/import/class-decrypt_zip_file.php';

        /**
        +        * The class responsible for defining actions for blob storage.
        +        */
        +require_once plugin_dir_path(dirname(__FILE__)) . 'admin/engines/import/class-blob_storage_client.php';
        /**
         * The class responsible for handling the import ajax calls
         */
        require_once plugin_dir_path(dirname(__FILE__)) . 'admin/engines/import/class-azure_app_service_migration-import-ajaxhandler.php';
        /**
         * The class responsible for handling the import filebackup
         */require_once plugin_dir_path(dirname(__FILE__)) . 'admin/engines/import/class-azure_app_service_migration-import-filebackup-handler.php';

        /**
        +        * The class responsible for defining database helper functions.
        +        */
        +require_once plugin_dir_path(dirname(__FILE__)) . 'utils/class-database_manager.php';

        /**
        +        * The class defining zip extractor helper functions
        +        */
        +require_once plugin_dir_path(dirname(__FILE__)) . 'utils/class-zip_extractor.php';

        /**
        +        * The class defining zip extractor helper functions
        +        */
        +require_once plugin_dir_path(dirname(__FILE__)) . 'utils/class-common_utils.php';
        
        /**
        +        * The class defining plugin constants
        +        */
        +require_once plugin_dir_path(dirname(__FILE__)) . 'constants.php';

        /**
        +        * The class defining plugin exceptions
        +        */
        +require_once plugin_dir_path(dirname(__FILE__)) . 'exceptions.php';

        /**
        +        * The class defining log helper functions
        +        */
        +require_once plugin_dir_path(dirname(__FILE__)) . 'utils/class-custom-logger.php';

        $this->loader = new Azure_app_service_migration_Loader();

    }

    /**
     * Define the locale for this plugin for internationalization.
     *
     * Uses the Azure_app_service_migration_i18n class in order to set the domain and to register the hook
     * with WordPress.
     *
     * @since    1.0.0
     * @access   private
     */
    private function set_locale()
    {

        $plugin_i18n = new Azure_app_service_migration_i18n();

        $this->loader->add_action('plugins_loaded', $plugin_i18n, 'load_plugin_textdomain');

    }

    /**
     * Register all of the hooks related to the admin area functionality
     * of the plugin.
     *
     * @since    1.0.0
     * @access   private
     */
    private function define_admin_hooks()
    {
        $plugin_admin = new Azure_app_service_migration_Export_Controller($this->get_plugin_name(), $this->get_version());

        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_styles');
        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts');
        
        // action hook for admin menu
        $this->loader->add_action('admin_menu', $plugin_admin, 'azure_app_service_migration_menu');
        
        // Register the AJAX handler
        $ajaxHandler = new Azure_app_service_migration_Export_AjaxHandler();
        $this->loader->add_action('wp_ajax_admin_ajax_request', $ajaxHandler, 'handle_ajax_requests_admin');

        $importaxHandler = new Azure_app_service_migration_Import_FileBackupHandler();
        
        add_action('wp_ajax_handle_upload_chunk', 'Azure_app_service_migration_Import_FileBackupHandler::handle_upload_chunk');

        add_action('wp_ajax_handle_combine_chunks', 'Azure_app_service_migration_Import_FileBackupHandler::handle_combine_chunks');

        add_action('wp_ajax_delete_chunks', 'Azure_app_service_migration_Import_FileBackupHandler::delete_chunks');

        // Register status update AJAX handler
        $statusUpdateHandler = new Azure_app_service_migration_Import_AjaxHandler();
        $this->loader->add_action('wp_ajax_get_migration_status', $statusUpdateHandler , 'get_migration_status');

        // register import ajax handler
        add_action('wp_ajax_aasm_import','Azure_app_service_migration_Import_Controller::import');
        add_action('wp_ajax_nopriv_aasm_import', 'Azure_app_service_migration_Import_Controller::import');

        // register export ajax handler
        add_action('wp_ajax_aasm_export','Azure_app_service_migration_Export::export');
        add_action('wp_ajax_nopriv_aasm_export', 'Azure_app_service_migration_Export::export');

        // register function hooks for import
        add_filter( 'aasm_import', 'Azure_app_service_migration_Import_FileBackupHandler::handle_combine_chunks', 5 );
		add_filter( 'aasm_import', 'Azure_app_service_migration_Import_Content::import_content', 10 );
        add_filter( 'aasm_import', 'Azure_app_service_migration_Import_Database::import_database', 20 );

        // register function hooks for export
        add_filter( 'aasm_export', 'Azure_app_service_migration_Export_FileBackupHandler::handle_wp_filebackup', 5 );
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

        $plugin_public = new Azure_app_service_migration_Public($this->get_plugin_name(), $this->get_version());

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
     * @return    Azure_app_service_migration_Loader    Orchestrates the hooks of the plugin.
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

}
