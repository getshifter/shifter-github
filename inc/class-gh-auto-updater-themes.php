<?php
require_once(__DIR__ . '/class-gh-auto-updater-base.php' );

class GH_Auto_Updater_Themes extends GH_Auto_Updater_Base
{
    /**
     * Activate automatic update with GitHub API.
     *
     * @param string $slug        The base name of the like `my-theme/theme.php`.
     * @param string $gh_user     The user name of the theme on GitHub.
     * @param string $gh_repo     The repository name of the theme on GitHub.
     * @param string $gh_token    GitHub token (option).
     */
    public function __construct( $slug, $gh_user, $gh_repo, $gh_token = null )
    {
        $this->type = 'themes';
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
        add_filter( 'pre_set_site_transient_update_themes', [$this, 'filter_pre_set_site_transient_update'] );
        add_filter( 'site_transient_update_themes', [$this, 'filter_site_transient_update'] );
        add_filter( 'themes_api', [$this, 'filter_api'], 10, 3 );
    }

    /**
     * Filters the object of the theme which need to be upgraded.
     *
     * @param object $transient The object of the transient.
     *
     * @return object The transient value that contains the new version of the theme.
     */
    public function filter_pre_set_site_transient_update( $transient )
    {
        if ( empty( $transient->checked ) ) {
            return $transient;
        }

        return $this->get_newer_version( $transient );
    }

    /**
     * Filters the object of the theme which need to be upgraded.
     *
     * @param object $obj The object of the themes-api.
     * @param string $action The type of information being requested from the Themes Install API.
     * @param object $arg The arguments for the themes-api.
     * @return object The object of the themes-api which is gotten from GitHub API.
     */
    public function filter_api( $obj, $action, $arg )
    {
        if ( ( 'query_themes' === $action || 'theme_information' === $action ) &&
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
        return $value;
    }

    public function install( $title, $parent_file, $submenu_file, $upgrader_args = [] )
    {
        return parent::install( $title, $parent_file, $submenu_file, $upgrader_args );
    }

    /**
     * Returns theme information
     *
     * @return Object
     */
    protected function get_current_version()
    {
        if (! function_exists('wp_get_theme')) {
            require_once(ABSPATH.'wp-admin/theme.php');
        }
        $theme = wp_get_theme( $this->slug );
        $info = New \stdClass();
        $info->name = $theme->title;
        $info->slug = $this->slug;
        $info->version = $theme->version;
        return $info;
    }

    /**
     * Filters the object of the theme which need to be upgraded.
     *
     * @param object $remote_version  The object of the theme which is gotten from the GitHub.
     * @param object $current_version The object of the theme.
     * @return object The object of the theme-api which is merged.
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
        $obj->theme_name = esc_html( $current_version->name );
        $obj->author = sprintf(
            '<a href="%1$s" target="_blank">%2$s</a>',
            esc_url( $remote_version->author->html_url ),
            esc_html( $remote_version->author->login )
        );
        $obj->homepage = esc_url( $this->github_repo_url );
        $obj->version = $remote_version->tag_name;
        $obj->last_updated = $remote_version->published_at;

        $parsedown = new \Parsedown();
        $changelog = $parsedown->text( $remote_version->body );
        $readme_file = get_theme_root() . '/' . dirname( $this->slug ) . '/README.md';
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
     * Filters the object of the theme which need to be upgraded.
     *
     * @param object $transient The object of the transient.
     *
     * @return object The transient value that contains the new version of the theme.
     */
    protected function get_newer_version( $transient )
    {
        if ( is_wp_error( $this->remote_version ) || is_wp_error( $this->current_version ) ) {
            return $transient;
        }
        $current_version = $this->current_version;
        $remote_version  = $this->remote_version;

        if ( version_compare( $current_version->version, $remote_version->tag_name, '<' ) ) {
            $array = [];
            $array['slug'] = $this->slug;
            $array['theme'] = $this->slug;
            $array['new_version'] = $remote_version->tag_name;
            $array['url'] = $remote_version->html_url;
            $array['package'] = $this->download_url;
            $transient->response[ $this->slug ] = $array;
        }

        return $transient;
    }
}
