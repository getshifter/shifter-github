<?php
/**
 * GH Auto Updater
 *
 * @package   miya/gh-auto-updater
 * @author    Takayuki Miyauchi
 * @license   GPL v2
 * @link      https://github.com/miya0001/gh-auto-updater
 */

class GH_Auto_Updater_Base
{
    /**
     * @var string $type
     */
    private $type = 'plugins';

    /**
     * @var string $gh_user
     */
    private $gh_user;

    /**
     * @var string $gh_repo
     */
    private $gh_repo;

    /**
     * @var string $gh_token
     */
    private $gh_token;

    /**
     * @var string $slug
     */
    private $slug;

    const TRANSIENT_EXPIRES_MIN = 3;

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
        $this->slug     = $slug;
        $this->gh_user  = $gh_user;
        $this->gh_repo  = $gh_repo;
        $this->gh_token = !empty($gh_token) ? $gh_token : (defined('GITHUB_ACCESS_TOKEN') && GITHUB_ACCESS_TOKEN);
    }

    public function __get( $key )
    {
        switch ($key) {
        case 'slug':
            $value = $this->slug;
            break;

        case 'user':
            $value = $this->gh_user;
            break;

        case 'repo':
            $value = $this->gh_repo;
            break;

        case 'token':
            $value = $this->gh_token;
            break;

        case 'current_version':
            $value = $this->get_current_version();
            break;

        case 'remote_version':
            if (empty($this->gh_token)) {
                $value = $this->get_release_from_api_v3();
            } else {
                $value = $this->get_release_from_api_v4();
            }
            break;

        case 'github_repo_url' :
            $value = sprintf(
                'https://github.com/%1$s/%2$s',
                $this->gh_user,
                $this->gh_repo
            );
            break;

        case 'download_url':
            $value = $this->get_download_url();
            break;
        }
        return $value;
    }

    public function __set( $key, $value )
    {
        switch ($key) {
        case 'slug':
            $this->slug = $value;
            break;

        case 'user':
            $this->gh_user = $value;
            break;

        case 'repo':
            $this->gh_repo = $value;
            break;

        case 'token':
            $this->gh_token = $value;
            break;

        case 'type':
            $this->type = $value;
            break;
        }
        return $value;
    }

    protected function add_filters()
    {
        add_filter( 'upgrader_package_options', [$this, 'filter_upgrader_package_options'] );
        add_filter( 'upgrader_source_selection', [$this, 'filter_upgrader_source_selection'], 1 );
    }

    /**
     * Filters the package options before running an update.
     *
     * See also {@see 'upgrader_process_complete'}.
     *
     * @since 4.3.0
     *
     * @param array $options {
     *     Options used by the upgrader.
     *
     *     @type string $package                     Package for update.
     *     @type string $destination                 Update location.
     *     @type bool   $clear_destination           Clear the destination resource.
     *     @type bool   $clear_working               Clear the working resource.
     *     @type bool   $abort_if_destination_exists Abort if the Destination directory exists.
     *     @type bool   $is_multi                    Whether the upgrader is running multiple times.
     *     @type array  $hook_extra {
     *         Extra hook arguments.
     *
     *         @type string $action               Type of action. Default 'update'.
     *         @type string $type                 Type of update process. Accepts 'plugin', 'theme', or 'core'.
     *         @type bool   $bulk                 Whether the update process is a bulk update. Default true.
     *         @type string $plugin               Path to the plugin file relative to the plugins directory.
     *         @type string $theme                The stylesheet or template name of the theme.
     *         @type string $language_update_type The language pack update type. Accepts 'plugin', 'theme',
     *                                            or 'core'.
     *         @type object $language_update      The language pack update offer.
     *     }
     * }
     */
    public function filter_upgrader_package_options( $options )
    {
        if ( isset( $options['package'] ) && preg_match( '#^https://github#i', $options['package'] ) ) {
            $zip_file = $this->get_zip_ball( $options['package'] );
            if ( ! is_wp_error( $zip_file ) ) {
                $options['package'] = $zip_file;
            }
        }
        return $options;
    }

    /**
     * Filters the source file location for the upgrade package.
     *
     * @param  string $source File source location.
     *
     * @return string The new location of the upgrade plugin.
     */
    public function filter_upgrader_source_selection( $source )
    {
        if( strpos( $source, $this->gh_repo ) === false ) {
            return $source;
        }

        $path_parts = pathinfo( $source );
        $newsource = trailingslashit( $path_parts['dirname'] ) . trailingslashit( $this->gh_repo );
        rename( $source, $newsource );
        return $newsource;
    }

    /**
     * Filters the object of the plugin which need to be upgraded.
     *
     * @param object $transient The object of the transient.
     * @return object The transient value that contains the new version of the plugin.
     */
    public function filter_pre_set_site_transient_update( $transient )
    {
        return $transient;
    }

    /**
     * Filters the object of the plugin which need to be upgraded.
     *
     * @param object $obj The object of the plugins-api.
     * @param string $action The type of information being requested from the Plugin Install API.
     * @param object $arg The arguments for the plugins-api.
     * @return object The object of the plugins-api which is gotten from GitHub API.
     */
    public function filter_api( $obj, $action, $arg )
    {
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
        require_once(ABSPATH.'wp-admin/admin-header.php');

        $upgrader_args['nonce'] = $this->type.'-upload-from-github';
        $upgrader_args['type']  = 'upload'; //Install plugin type, From Web or an Upload.
        if ( 'plugins' === $this->type ) {
            $upgrader = new Plugin_Upgrader(new Plugin_Installer_Skin($upgrader_args));
        } else {
            $upgrader = new Theme_Upgrader(new Theme_Installer_Skin($upgrader_args));
        }

        // get zip ball
        if ( is_wp_error( $this->download_url ) ) {
            return $this->download_url;
        }
        $zip_file = $this->get_zip_ball( $this->download_url );
        if ( is_wp_error( $zip_file ) ) {
            return $zip_file;
        }

        // plugin/theme install
        $install_result   = $upgrader->install( $zip_file );
        if ( is_wp_error( $install_result ) ) {
            return $install_result;
        }

        // remove zip file
        $this->delete_file( $zip_file );

        include(ABSPATH.'wp-admin/admin-footer.php');

        return true;
    }

    /**
     * Filters the object of the plugin which need to be upgraded.
     *
     * @return object The transient value that contains the new version of the plugin.
     */
    protected function get_current_version()
    {
        $obj = new \stdClass();
        $obj->name = esc_html( $this->slug );
        return $obj;
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

        $obj = new \stdClass();
        $obj->slug = $this->slug;
        $obj->name = esc_html( $this->current_version->name );
        $obj->author = esc_url( $this->user );
        $obj->homepage = esc_url( $this->github_repo_url );
        if ( ! is_wp_error( $this->download_url ) ) {
            $obj->download_link = esc_url( $this->download_url );
        }
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
        return $transient;
    }

    /**
     * Returns the URL of the plugin to download new verion.
     *
     * @return The URL of the plugin to download.
     */
    private function get_download_url()
    {
        $asset =
            ( ! is_wp_error( $this->remote_version ) && ! empty( $this->remote_version->assets[0] ) )
            ? $this->remote_version->assets[0]
            : null;

        if ( ! empty( $asset->browser_download_url ) ) {
            $download_url = $asset->browser_download_url;
        } else {
            $download_url = sprintf(
                'https://github.com/%s/%s/archive/%s.zip',
                $this->gh_user,
                $this->gh_repo,
                'master'
            );
        }

        return $download_url;
    }

    private function get_download_filename()
    {
        $download_filename = $this->gh_repo . '.zip';

        return $download_filename;
    }

    /**
     * Returns the data from the GitHub API.
     *
     * @param string $endpoint The path to the endpoint of the GitHub API.
     *
     * @return object The object that is gotten from the API.
     */
    private function get_release_from_api_v3()
    {
        $url = sprintf(
            'https://api.github.com/repos/%1$s/%2$s%3$s',
            $this->gh_user,
            $this->gh_repo,
            '/releases/latest'
        );
        $url = esc_url_raw( $url, 'https' );
        $body = $this->remote_get( $url );
        if ( is_wp_error( $body ) ) {
            return $body;
        } else {
            $obj = json_decode( $body );
            if ( !empty($obj->assets[0]->browser_download_url) ) {
                $obj->assets[0]->download_file_name = basename( preg_replace( '/\?.*$/', '', $obj->assets[0]->browser_download_url ) );
            }
            return $obj;
        }
    }

    private function get_release_from_api_v4( $cache = false )
    {
        $transient_key = "GH_Auto_Updater::githubapiv4/{$this->gh_user}/{$this->gh_repo}/release";
        if ( ! $cache || false === ( $res = get_transient( $transient_key ) ) ) {
            $query = '
            {
                repository(owner: "'.$this->gh_user.'", name: "'.$this->gh_repo.'") {
                  releases(last: 1){
                    edges {
                      node {
                        releaseAssets(last: 1) {
                          nodes {
                            url
                            downloadUrl
                            createdAt
                            updatedAt
                          }
                        }
                        url
                        resourcePath
                        tagName
                        isPrerelease
                        publishedAt
                        updatedAt
                        descriptionHTML
                      }
                    }
                  }
                }
              }
            ';
            $options = [
                'method'  => 'POST',
                'headers' => [
                    'Authorization' => 'bearer ' . $this->gh_token,
                    'Content-type'  => 'application/json; charset=UTF-8',
                ],
                'body'    => json_encode(['query' => $query]),
            ];
            $url = esc_url_raw( 'https://api.github.com/graphql', 'https' );
            $res = wp_remote_post( $url, $options);
            if ( $cache ) {
                set_transient( $transient_key, $res, self::TRANSIENT_EXPIRES_MIN * MINUTE_IN_SECONDS );
            }
        }

        if (200 !== wp_remote_retrieve_response_code( $res )) {
            return new \WP_Error(
                wp_remote_retrieve_response_code( $res ),
                wp_remote_retrieve_body( $res )
            );
        }
        $body = json_decode( wp_remote_retrieve_body( $res ) );

        $obj = new \stdClass();
        if ( ! empty($body->data->repository->releases->edges[0]->node->releaseAssets) ) {
            $gh_repo_url = "https://github.com/{$this->gh_user}/{$this->gh_repo}";

            $asset = new \stdClass();
            $release_asset = $body->data->repository->releases->edges[0]->node;
            $asset->browser_download_url = $release_asset->releaseAssets->nodes[0]->url;
            $asset->download_file_name = $this->get_download_filename();
            $asset->html_url = $gh_repo_url;
            $asset->tag_name = $release_asset->tagName;
            $asset->published_at = $release_asset->publishedAt;
            $asset->updated_at = $release_asset->updatedAt;
            $obj->assets = [$asset];

            $obj->author = new \stdClass();
            $obj->author->url = $gh_repo_url;
            $obj->author->login = $this->gh_user;
            $obj->html_url = $gh_repo_url;
            $obj->tag_name = $release_asset->tagName;
            $obj->published_at = $release_asset->publishedAt;
            $obj->updated_at = $release_asset->updatedAt;
            $obj->is_prerelease = $release_asset->isPrerelease;
            $obj->body = $release_asset->descriptionHTML;
        }

        return $obj;
    }

    private function remote_get( $url, $cache = true )
    {
        $transient_key = 'GH_Auto_Updater::'.$url;
        if ( ! $cache || false === ( $res = get_transient( $transient_key ) ) ) {
            $res = wp_remote_get( $url );
            if ( $cache ) {
                set_transient( $transient_key, $res, self::TRANSIENT_EXPIRES_MIN * MINUTE_IN_SECONDS );
            }
        }

        if (200 !== wp_remote_retrieve_response_code( $res )) {
            return new \WP_Error(
                wp_remote_retrieve_response_code( $res ),
                wp_remote_retrieve_body( $res )
            );
        }
        $body = wp_remote_retrieve_body( $res );
        return $body;
    }

    private function filesystem()
    {
        global $wp_filesystem;
        if ( empty( $wp_filesystem ) ) {
            require_once( ABSPATH.'wp-admin/includes/file.php' );
            WP_Filesystem();
        }
        return $wp_filesystem;
    }

    // get zip ball
    private function get_zip_ball( $download_url, $work_dir = null )
    {
        $wp_filesystem = $this->filesystem();

        if ( ! $work_dir ) {
            $work_dir = sys_get_temp_dir();
        }
        if ( ! $wp_filesystem->is_dir( $work_dir ) ) {
            $wp_filesystem->mkdir( $work_dir );
        }

        $zip_file = trailingslashit( $work_dir ) . $this->get_download_filename();
        $body = $this->remote_get( $download_url, false );
        if ( is_wp_error( $body ) ) {
            return $body;
        }
        $wp_filesystem->put_contents( $zip_file, $body, defined('FS_CHMOD_FILE') ? FS_CHMOD_FILE : '0644' );

        return $zip_file;
    }

    private function delete_file( $filename )
    {
        $wp_filesystem = $this->filesystem();
        $wp_filesystem->delete( $filename );
        $dirname = dirname( $filename );
        if ( empty( glob( $dirname.'/*' ) ) ){
            rmdir( $dirname );
        }
    }
}
