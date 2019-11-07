<?php
require_once(__DIR__ . '/class-gh-auto-updater-base.php' );

class GH_Auto_Updater_Plugins extends GH_Auto_Updater_Base
{
    /**
     * Activate automatic update with GitHub API.
     *
     * @param string $slug        The base name of the like `my-plugin/plugin.php`.
     * @param string $gh_user     The user name of the plugin on GitHub.
     * @param string $gh_repo     The repository name of the plugin on GitHub.
     * @param string $gh_token    GitHub token (option).
     */
    public function __construct( $slug, $gh_user, $gh_repo, $gh_token = null )
    {
        $this->type = 'plugins';
        parent::__construct( $slug, $gh_user, $gh_repo, $gh_token );
        $this->add_filters();
    }

    public function __get( $key )
    {
        switch ($key) {
        case 'current_version':
            $value = $this->get_current_version();
            break;

        default:
            $value = parent::__get( $key );
            break;
        }
        return $value;
    }
    
    public function add_filters()
    {
        parent::add_filters();
        add_filter( 'pre_set_site_transient_update_plugins', [$this, 'filter_pre_set_site_transient_update'] );
        add_filter( 'site_transient_update_plugins', [$this, 'filter_site_transient_update'] );
        add_filter( 'plugins_api', [$this, 'filter_api'], 10, 3 );

//        //  Echo the style for plugin's detail popup screen.
//        add_action( 'admin_head', function(){
//            echo '<style>#plugin-information .section img{ max-width: 100%; height: auto; }</style>';
//        } );
    }

    /**
     * Filters the object of the plugin which need to be upgraded.
     *
     * @param object $transient The object of the transient.
     *
     * @return object The transient value that contains the new version of the plugin.
     */
    public function filter_pre_set_site_transient_update( $transient )
    {
        if ( empty( $transient->checked ) ) {
            return $transient;
        }

        return $this->get_newer_version( $transient );
    }

    /**
     * Filters the object of the plugin which need to be upgraded.
     *
     * @param object $obj The object of the plugins-api.
     * @param string $action The type of information being requested from the Plugin Install API.
     * @param object $arg The arguments for the plugins-api.
     *
     * @return object The object of the plugins-api which is gotten from GitHub API.
     */
    public function filter_api( $obj, $action, $arg )
    {
        if ( ( 'query_plugins' === $action || 'plugin_information' === $action ) &&
            isset( $arg->slug ) && $arg->slug === $this->slug ) {

            return $this->get_api_object( $obj );
        }

        return $obj;
    }

    /**
     * Disable update from WordPress.org
     *
     * @param $value
     *
     * @return mixed
     */
    public function filter_site_transient_update( $value )
    {
        if ( ! empty( $value->response[ $this->slug ] ) ) {
            $plugin = $value->response[ $this->slug ];
            if ( ! empty( $plugin->package ) ) {
                if ( 0 !== strpos( $plugin->package, 'https://github.com' ) ) {
                    unset( $value->response[ $this->slug ] );
                }
            }
        }
        return $value;
    }

    public function install( $title, $parent_file, $submenu_file, $upgrader_args = [] )
    {
        return parent::install( $title, $parent_file, $submenu_file, $upgrader_args );
    }

    /**
     * Returns plugin information
     *
     * @return Object
     */
    protected function get_current_version()
    {
        if (! function_exists('get_plugin_data')) {
            require_once(ABSPATH.'wp-admin/includes/plugin.php');
        }
        $plugin = get_plugin_data( WP_PLUGIN_DIR . '/' . $this->slug );
        $info = New \stdClass();
        $info->name = isset($plugin['Name']) ? $plugin['Name'] : null;
        $info->slug = $this->slug;
        $info->version = isset($plugin['Version']) ? $plugin['Version'] : null;
        return $info;
    }

    /**
     * Filters the object of the plugin which need to be upgraded.
     *
     * @param object $default
     *
     * @return object The object of the plugins-api which is merged.
     */
    protected function get_api_object( $default )
    {
        if ( is_wp_error( $this->remote_version ) || is_wp_error( $this->current_version ) ) {
            return $default;
        }
        $current_version = $this->current_version;
        $remote_version  = $this->remote_version;

        $obj = new \stdClass();
        $obj->slug = $this->slug;
        $obj->name = esc_html( $current_version->name );
        $obj->plugin_name = esc_html( $current_version->name );
        $obj->author = sprintf(
            '<a href="%1$s" target="_blank">%2$s</a>',
            esc_url( $remote_version->author->html_url ),
            esc_html( $remote_version->author->login )
        );
        $obj->homepage = esc_url( $this->github_repo_url );
        $obj->version = sprintf(
            '<a href="%1$s" target="_blank">%2$s</a>',
            $remote_version->html_url,
            $remote_version->tag_name
        );
        $obj->last_updated = $remote_version->published_at;

        $parsedown = new \Parsedown();
        $changelog = $parsedown->text( $remote_version->body );
        $readme_file = WP_PLUGIN_DIR . '/' . dirname( $this->slug ) . '/README.md';
        $readme = '';
        if ( is_file( $readme_file ) ) {
            $readme = $parsedown->text( file_get_contents( $readme_file ) );
        }
        $obj->sections = array(
            'readme' => $readme,
            'changelog' => $changelog
        );

        $obj->download_link = esc_url( $this->download_url );

        return $obj;
    }

    /**
     * Filters the object of the plugin which need to be upgraded.
     *
     * @param object $transient The object of the transient.
     *
     * @return object The transient value that contains the new version of the plugin.
     */
    protected function get_newer_version( $transient )
    {
        if ( is_wp_error( $this->remote_version ) || is_wp_error( $this->current_version ) ) {
            return $transient;
        }
        $current_version = $this->current_version;
        $remote_version  = $this->remote_version;

        if ( version_compare( $current_version->version, $remote_version->tag_name, '<' ) ) {
            $obj = new \stdClass();
            $obj->slug = $this->slug;
            $obj->plugin = $this->slug;
            $obj->new_version = $remote_version->tag_name;
            $obj->url = $remote_version->html_url;
            $obj->package = $this->download_url;
            $transient->response[ $this->slug ] = $obj;
        }

        return $transient;
    }
}
