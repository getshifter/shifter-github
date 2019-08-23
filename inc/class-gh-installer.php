<?php
class Shifter_GH_Installer
{
    const   OPTION_NAME = 'shifter_gh_pull_targets';
    private $options;
    private $work_dir;

    public function __construct( $work_dir )
    {
        $this->options = get_option(self::OPTION_NAME);
        if (!is_array($this->options)) {
            $this->options = [
                'plugins' => [],
                'themes' => [],
            ];
            update_option(self::OPTION_NAME, $this->options);
        }
        $this->work_dir = trailingslashit($work_dir);

        add_action('init', [$this, 'init']);
        add_action('admin_init', [$this, 'admin_init']);
        add_action('admin_menu', [$this, 'admin_menu'], 102);
    }

    private function get_plugins( $plugin_folder = '' )
    {
        if (! function_exists('get_plugins')) {
            require_once(ABSPATH.'wp-admin/includes/plugin.php');
        }
        return get_plugins($plugin_folder);
    }

    private function get_themes( $args = array() )
    {
        if (! function_exists('wp_get_themes')) {
            require_once(ABSPATH.'wp-admin/theme.php');
        }
        return wp_get_themes($args);
    }

    public function init()
    {
        if (isset($this->options['plugins'])) {
            $installed = [];
            foreach ($this->options['plugins'] as $slug => $info) {
                foreach ($this->get_plugins() as $key => $plugin_info) {
                    if ($slug === $key) {
                        new Miya\WP\GH_Auto_Updater(
                            $slug,
                            $info['gh_user'],
                            $info['gh_repo'],
                            isset($info['gh_token']) ? $info['gh_token'] : null
                        );
                        $installed[$slug] = $info;
                        break;
                    }
                }
            }
            if (count($installed) !== count($this->options['plugins'])) {
                $this->options['plugins'] = $installed;
                update_option(self::OPTION_NAME, $this->options);
            }
        }

        if (isset($this->options['themes'])) {
            $installed = [];
            foreach ($this->options['themes'] as $slug => $info) {
                foreach ($this->get_themes() as $key => $theme_info) {
                    if ($slug === $key) {
/*
                        new Miya\WP\GH_Auto_Updater(
                            $slug,
                            $info['gh_user'],
                            $info['gh_repo'],
                            isset($info['gh_token']) ? $info['gh_token'] : null
                        );
*/
                        $installed[$slug] = $info;
                        break;
                    }
                }
            }
            if (count($installed) !== count($this->options['themes'])) {
                $this->options['themes'] = $installed;
                update_option(self::OPTION_NAME, $this->options);
            }
        }
    }

    public function admin_init()
    {
        add_action('update-custom_gh-upload-plugin', [$this, 'plugin_upload']);
        add_action('update-custom_gh-upload-theme',  [$this, 'theme_upload']);
    }

    public function admin_menu()
    {
        add_submenu_page('plugins.php', 'Add New from GitHub', 'Add New from GitHub', 'upload_plugins', 'upload-plugins-from-github', [$this, 'install_plugins']);
        add_submenu_page('themes.php',  'Add New from GitHub', 'Add New from GitHub', 'upload_themes',  'upload-themes-from-github',  [$this, 'install_themes']);
    }

    private function api_data( $endpoint, $gh_user, $gh_repo, $gh_token = null )
    {
        $url = sprintf(
            'https://api.github.com/repos/%1$s/%2$s%3$s',
            $gh_user,
            $gh_repo,
            $endpoint
        );
        if ($gh_token) {
            $url = add_query_arg( array(
                'access_token' => $gh_token
            ), $url );
        }
        $api_url = esc_url_raw($url, 'https');

        $res = wp_remote_get($api_url);
        if (200 !== wp_remote_retrieve_response_code($res)) {
            return new \WP_Error(wp_remote_retrieve_body($res));
        }
        $body = wp_remote_retrieve_body($res);
        return json_decode($body);
    }

    private function get_download_url($gh_user, $gh_repo, $gh_token)
    {
        $remote_version = $this->api_data('/releases/latest', $gh_user, $gh_repo, $gh_token);
        if (! empty($remote_version->assets[0]) && ! empty($remote_version->assets[0]->browser_download_url)) {
            $download_url = $remote_version->assets[0]->browser_download_url;
        } else {
            $download_url = sprintf(
                'https://github.com/%s/%s/archive/%s.zip',
                $gh_user,
                $gh_repo,
                $remote_version->tag_name
            );
        }
        return $download_url;
    }

    private function wp_filesystem()
    {
        global $wp_filesystem;
        if (empty($wp_filesystem)) {
            require_once(ABSPATH.'wp-admin/includes/file.php');
            WP_Filesystem();
        }
        return $wp_filesystem;
    }

    // get zip ball
    private function get_zip_ball($download_url)
    {
        $wp_filesystem = $this->wp_filesystem();

        if (!$wp_filesystem->is_dir($this->work_dir)) {
            $wp_filesystem->mkdir($this->work_dir);
        }

        $zip_file = $this->work_dir.basename($download_url);
        $res = wp_remote_get($download_url);
        if (200 !== wp_remote_retrieve_response_code($res)) {
            return new \WP_Error(wp_remote_retrieve_body($res));
        }
        $body = wp_remote_retrieve_body($res);
        $wp_filesystem->put_contents($zip_file, $body, defined('FS_CHMOD_FILE') ? FS_CHMOD_FILE : '0755');

        return $zip_file;
    }

    private function delete_file( $file )
    {
        $wp_filesystem = $this->wp_filesystem();
        return $wp_filesystem->delete($file);
    }

    public function install_plugins()
    {
        $form_action = self_admin_url('update.php?action=gh-upload-plugin');;
        $submit_button_id = 'install-plugin-gh-submit';
        ?>
	<p class="install-help"><?php _e( 'If you have a plugin from GitHub, you may input GitHub repo URL.' ); ?></p>
	<form method="post" class="shifter-upload-form" action="<?php echo $form_action; ?>">
		<?php wp_nonce_field( 'plugin-upload' ); ?>
		<label for="ghrepo"><?php _e( 'GitHub repo URL' ); ?></label>
		<input type="text" id="ghrepo" name="ghrepo" /><br>
		<label for="ghtoken"><?php _e( 'GitHub token (option)' ); ?></label>
		<input type="text" id="ghtoken" name="ghtoken" /><br />
		<?php submit_button( __( 'Install Now' ), '', $submit_button_id, false ); ?>
	</form>
<?php
    }

    public function install_themes()
    {
        $form_action = self_admin_url('update.php?action=gh-upload-theme');;
        $submit_button_id = 'install-theme-gh-submit';
        ?>
	<p class="install-help"><?php _e( 'If you have a theme from GitHub, you may input GitHub repo URL.' ); ?></p>
	<form method="post" class="shifter-upload-form" action="<?php echo $form_action; ?>">
		<?php wp_nonce_field( 'theme-upload' ); ?>
		<label for="ghrepo"><?php _e( 'GitHub repo URL' ); ?></label>
		<input type="text" id="ghrepo" name="ghrepo" /><br>
		<label for="ghtoken"><?php _e( 'GitHub token (option)' ); ?></label>
		<input type="text" id="ghtoken" name="ghtoken" /><br />
		<?php submit_button( __( 'Install Now' ), '', $submit_button_id, false ); ?>
	</form>
<?php
    }

    public function plugin_upload()
    {
        if (! current_user_can('upload_plugins')) {
            wp_die(__('Sorry, you are not allowed to install plugins on this site.'));
        }

        check_admin_referer('plugin-upload');

        $title        = __('Upload Plugin');
        $parent_file  = 'plugins.php';
        $submenu_file = 'plugins.php?page=upload-plugins-from-github';
        require_once(ABSPATH.'wp-admin/admin-header.php');

        // get input value
        $gh_repo_url = sanitize_text_field($_POST['ghrepo']);
        if (! preg_match('#^https://github.com/([^/]+)/([^/]+)/?.*$#', $gh_repo_url)) {
            wp_die('GitHub url is not correct.');
        }
        $gh_user = preg_replace('#^https://github.com/([^/]+)/([^/]+)/?.*$#', '$1', $gh_repo_url);
        $gh_repo = preg_replace('#^https://github.com/([^/]+)/([^/]+)/?.*$#', '$2', $gh_repo_url);
        $gh_token = isset($_POST['ghtoken']) ? sanitize_text_field($_POST['ghtoken']) : null;

        // get download URL
        $download_url = $this->get_download_url($gh_user, $gh_repo, $gh_token);
        $plugin_dir = str_replace('.zip', '', preg_replace('/\?.+$/', '', basename($download_url)));

        // install plugin file
        $title = sprintf(
            __('Installing Plugin from github: %s'),
            esc_html($gh_user.'/'.$gh_repo)
        );
        $nonce = 'plugin-upload';
        $url   = add_query_arg(
            ['action' => 'upload-plugin', 'plugin' => urlencode($gh_repo)],
            'update.php'
        );
        $type  = 'upload'; //Install plugin type, From Web or an Upload.
        $upgrader = new Plugin_Upgrader(new Plugin_Installer_Skin(compact('type', 'title', 'nonce', 'url')));

        // get zip ball & install
        $zip_file = $this->get_zip_ball($download_url);
        if (is_wp_error($zip_file)) {
            wp_die($zip_file->get_error_message());
        }
        $result   = $upgrader->install($zip_file);

        // remove zip file
        $this->delete_file($zip_file);

        // get plugin info
        $plugin_slug = $plugin_dir;
        foreach ($this->get_plugins() as $key => $info) {
            if (preg_match('#^'.preg_quote($plugin_dir).'/#', $key)) {
                $plugin_slug = $key;
                break;
            }
        }
        $this->options['plugins'][$plugin_slug] = [
            'gh_user'  => $gh_user,
            'gh_repo'  => $gh_repo,
            'gh_token' => $gh_token,
        ];
        update_option(self::OPTION_NAME, $this->options);

        include(ABSPATH.'wp-admin/admin-footer.php');
    }

    public function theme_upload()
    {
        if (! current_user_can('upload_themes')) {
            wp_die(__('Sorry, you are not allowed to install themes on this site.'));
        }

        check_admin_referer('theme-upload');

        $title        = __('Upload Theme');
        $parent_file  = 'themes.php';
        $submenu_file = 'themes.php?page=upload-themes-from-github';
        require_once(ABSPATH.'wp-admin/admin-header.php');

        // get input value
        $gh_repo_url = sanitize_text_field($_POST['ghrepo']);
        if (! preg_match('#^https://github.com/([^/]+)/([^/]+)/?.*$#', $gh_repo_url)) {
            wp_die('GitHub url is not correct.');
        }
        $gh_user = preg_replace('#^https://github.com/([^/]+)/([^/]+)/?.*$#', '$1', $gh_repo_url);
        $gh_repo = preg_replace('#^https://github.com/([^/]+)/([^/]+)/?.*$#', '$2', $gh_repo_url);
        $gh_token = isset($_POST['ghtoken']) ? sanitize_text_field($_POST['ghtoken']) : null;

        // get download URL
        $download_url = $this->get_download_url($gh_user, $gh_repo, $gh_token);
        $theme_dir = str_replace('.zip', '', preg_replace('/\?.+$/', '', basename($download_url)));

        // install theme file
        $title = sprintf(
            __('Installing Theme from github: %s'),
            esc_html($gh_user.'/'.$gh_repo)
        );
        $nonce = 'theme-upload';
        $url   = add_query_arg(
            ['action' => 'upload-theme', 'package' => urlencode($gh_repo)],
            'update.php'
        );
        $type  = 'upload'; //Install plugin type, From Web or an Upload.
        $upgrader = new Theme_Upgrader(new Theme_Installer_Skin(compact('type', 'title', 'nonce', 'url')));

        // get zip ball & install
        $zip_file = $this->get_zip_ball($download_url);
        if (is_wp_error($zip_file)) {
            wp_die($zip_file->get_error_message());
        }
        $result   = $upgrader->install($zip_file);

        // remove zip file
        $this->delete_file($zip_file);

        // get theme info
        $theme_slug = $theme_dir;
        foreach ($this->get_themes() as $key => $info) {
            if (preg_match('#^'.preg_quote($theme_dir).'#', $key)) {
                $theme_slug = $key;
                break;
            }
        }
        $this->options['themes'][$theme_slug] = [
            'gh_user'  => $gh_user,
            'gh_repo'  => $gh_repo,
            'gh_token' => $gh_token,
        ];
        update_option(self::OPTION_NAME, $this->options);

        include(ABSPATH.'wp-admin/admin-footer.php');
    }
}