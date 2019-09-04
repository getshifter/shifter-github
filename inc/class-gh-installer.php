<?php
require_once(__DIR__ . '/class-gh-auto-updater-plugins.php' );
require_once(__DIR__ . '/class-gh-auto-updater-themes.php' );

class Shifter_GH_Installer
{
    const   OPTION_NAME = 'shifter_gh_pull_targets';
    const   GITHUB_URL_PATTERN = '#^(https://github\.com/|git@github\.com:)([^/]+)/([^/]+)/?.*$#';

    private $options;

    public function __construct()
    {
        $this->options = get_option(self::OPTION_NAME);
        if (!is_array($this->options)) {
            $this->options = [
                'plugins' => [],
                'themes' => [],
            ];
            update_option(self::OPTION_NAME, $this->options);
        }
    }

    public function add_hooks()
    {
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
        $updater = [];

        if (isset($this->options['plugins'])) {
            $installed = [];
            foreach ($this->options['plugins'] as $slug => $github_info) {
                foreach ($this->get_plugins() as $key => $plugin_info) {
                    if ($slug === $key) {
                        $updater[$slug] = new GH_Auto_Updater_Plugins(
                            $slug,
                            $github_info['gh_user'],
                            $github_info['gh_repo'],
                            isset($github_info['gh_token']) ? $github_info['gh_token'] : null
                        );
                        $installed[$slug] = $github_info;
                        break;
                    }
                }
            }
            $slug = 'shifter-wp-git/install-from-github.php';
            if (file_exists(WP_PLUGIN_DIR.'/'.$slug) && !isset($this->options['plugins'][$slug])) {
                $installed[$slug] = [
                    'gh_user'  => 'getshifter',
                    'gh_repo'  => 'shifter-install-helper',
                ];
                $updater[$slug] = new GH_Auto_Updater_Plugins(
                    $slug,
                    $installed[$slug]['gh_user'],
                    $installed[$slug]['gh_repo'],
                    null
                );
            }
            if (count($installed) !== count($this->options['plugins'])) {
                $this->options['plugins'] = $installed;
                update_option(self::OPTION_NAME, $this->options);
            }
        }

        if (isset($this->options['themes'])) {
            $installed = [];
            foreach ($this->options['themes'] as $slug => $github_info) {
                foreach ($this->get_themes() as $key => $theme_info) {
                    if ($slug === $key) {
                        $updater[$slug] = new GH_Auto_Updater_Themes(
                            $slug,
                            $github_info['gh_user'],
                            $github_info['gh_repo'],
                            isset($github_info['gh_token']) ? $github_info['gh_token'] : null
                        );
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
        //  Echo the style for plugin's detail popup screen.
        add_action( 'admin_head', function(){
            echo '<style>#plugin-information .section img{ max-width: 100%; height: auto; }</style>';
        } );

        add_action('update-custom_gh-upload-plugin', [$this, 'plugin_upload']);
        add_action('update-custom_gh-upload-theme',  [$this, 'theme_upload']);
    }

    public function admin_menu()
    {
        add_submenu_page('plugins.php', 'Add New from GitHub', 'Add New from GitHub', 'upload_plugins', 'upload-plugins-from-github', [$this, 'install_plugins']);
        add_submenu_page('themes.php',  'Add New from GitHub', 'Add New from GitHub', 'upload_themes',  'upload-themes-from-github',  [$this, 'install_themes']);
    }

    public function install_plugins()
    {
        $form_action = self_admin_url('update.php?action=gh-upload-plugin');;
        $submit_button_id = 'install-plugin-gh-submit';
        ?>
<div class="wrap">
	<h2><?php _e( 'Shifter Github Plugin Installer' ); ?></h2>
	<p class="install-help"><?php _e( 'If you have a plugin from GitHub, you may input GitHub repo URL.' ); ?></p>
	<form method="post" class="shifter-upload-form" action="<?php echo $form_action; ?>">
		<?php wp_nonce_field( 'plugin-upload' ); ?>
		<table class="form-table">
		<tbody>
			<tr>
				<th scope="row"><label for="ghrepo"><?php _e( 'GitHub repo URL' ); ?></label></th>
				<td><input type="text" id="ghrepo" name="ghrepo" class="regular-text" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="ghtoken"><?php _e( 'GitHub token (option)' ); ?></label></th>
				<td><input type="text" id="ghtoken" name="ghtoken" class="regular-text" /></td>
			</tr>
		</tbody>
		</table>
		<?php submit_button( __( 'Install Now' ), 'primary', $submit_button_id, false ); ?>
	</form>
<?php
        $this->notice_comment();
    }

    public function install_themes()
    {
        $form_action = self_admin_url('update.php?action=gh-upload-theme');;
        $submit_button_id = 'install-theme-gh-submit';
        ?>
<div class="wrap">
	<h2><?php _e( 'Shifter Github Theme Installer' ); ?></h2>
	<p class="install-help"><?php _e( 'If you have a theme from GitHub, you may input GitHub repo URL.' ); ?></p>
	<form method="post" class="shifter-upload-form" action="<?php echo $form_action; ?>">
		<?php wp_nonce_field( 'theme-upload' ); ?>
		<table class="form-table">
		<tbody>
			<tr>
				<th scope="row"><label for="ghrepo"><?php _e( 'GitHub repo URL' ); ?></label></th>
				<td><input type="text" id="ghrepo" name="ghrepo" class="regular-text" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="ghtoken"><?php _e( 'GitHub token (option)' ); ?></label></th>
				<td><input type="text" id="ghtoken" name="ghtoken" class="regular-text" /></td>
			</tr>
		</tbody>
		</table>
		<?php submit_button( __( 'Install Now' ), 'primary', $submit_button_id, false ); ?>
	</form>
</div>
<?php
        $this->notice_comment();
    }

    private function notice_comment()
    {
?>
	<div class="how-to-update">
			  <h2>How to update your plugin.</h2>
			  <p>To release the new version, please do as follows:</p>
			  <h3>Tag and push to GitHub, then upload the package by one of the ways as follows.</h3>
			  <pre><code>$ git tag 1.1.0
 $ git push origin 1.1.0
</code>
			  </pre>
			  <ul>
				<li><code>1.1.0</code> is a version number, it has to be same with version number in your WordPress plugin.</li>
				<li>You have to commit <code>vendor</code> directory in your plugin.</li>
			  </ul>
			  <h3>A. Manually release the new version.</h3>
			  <ol>
				<li>Please visit "releases" in your GitHub repository.</li>
				<li>Choose a tag.</li>
				<li>Fill out the release note and title.</li>
				<li>Upload your plugin which is comporessed with zip. (Optional)</li>
				<li>Press "Publish release".</li>
			  </ol>
			  <h3>B. Automated release the new version with GitHub Actions.</h3>
			  
			  <p>You can upload the package automat GutHub Actions.</p>
			  
			  <p>As for now, GitHub Actions service is in beta, so you have to <a href="https://github.com/features/actions">sign up the Beta here</a>
			  </p>
			  <p>When the service is ready you will find the Actions tab on the top page of your repository, then follow the steps below:</p>
			  <ol>
				<li>Then, copy the .github directory on the below url to the root directory of your local repository.<br><a href="https://github.com/getshifter/shifter-github-hosting-plugin-sample">https://github.com/getshifter/shifter-github-hosting-plugin-sample</a></li>
				<li>It must be look like:
/path_to_your_repository/.github/workflows/release.yml</li>
				<li>Open the release.yml file with your favorite editor and change some lines:
					<ul>
						<li>You MUST change the PACKAGE_NAME to your package name.</li>
						<li>Also, if you need:
						  <ul>
							<li>change FILES_TO_ARCIVE</li>
							<li>comment out the composer install section</li>
							<li>uncomment npm install section</li>
						  </ul>
						</li>
					</ul>
				</li>
				<li>After changed some lines, commit and push it to your GitHub repository.</li>
			</ol>
			  <p>Now when you push your tag to GitHub, the release package will be created automatically.</p>
			  
			  <h3>C. Automated release the new version with Travis.</h3>
			  <p>Also, you can use <a href="https://docs.travis-ci.com/user/deployment/releases/" rel="nofollow">automatic release</a> with Travis.</p>
			  <p>Following is an example of the <code>.travis.yml</code> for automatic release.</p>
			  <p><a href="https://github.com/miya0001/miya-gallery/blob/master/.travis.yml">https://github.com/miya0001/miya-gallery/blob/master/.travis.yml</a></p>
			  <p>You can generate <code>deploy:</code> section by <a href="https://github.com/travis-ci/travis.rb">The Travis Client</a> like following.</p>
			  <pre><code>$ travis setup releases</code></pre>
			  <h4>Example Projects</h4>
			  <p>Please install old version of following projects, then you can see update notice.</p>
			  <ul>
				<li><a href="https://github.com/miya0001/self-hosted-wordpress-plugin-on-github">https://github.com/miya0001/self-hosted-wordpress-plugin-on-github</a></li>
				<li><a href="https://github.com/miya0001/miya-gallery">https://github.com/miya0001/miya-gallery</a></li>
			  </ul>
			  <p>These projects deploy new releases automatically with Travis CI.</p>
			  <pre><code>$ travis setup releases</code></pre>
			  <p>Please check <code>.travis.yml</code> and <a href="https://docs.travis-ci.com/user/deployment/releases/" rel="nofollow">documentation</a>.</p>
	</div>

</div>
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

        // get input value
        $gh_repo_url = sanitize_text_field($_POST['ghrepo']);
        if (! preg_match(self::GITHUB_URL_PATTERN, $gh_repo_url)) {
            wp_die('GitHub url is not correct.');
        }
        $gh_user = preg_replace(self::GITHUB_URL_PATTERN, '$2', $gh_repo_url);
        $gh_repo = preg_replace(self::GITHUB_URL_PATTERN, '$3', $gh_repo_url);
        $gh_repo = preg_replace('#\.git$#', '', $gh_repo);
        $gh_token = isset($_POST['ghtoken']) ? sanitize_text_field($_POST['ghtoken']) : null;

        // install plugin file
        $installer = new GH_Auto_Updater_Plugins($gh_repo, $gh_user, $gh_repo, $gh_token );
        $upgrader_args = [];
        $upgrader_args['title'] = sprintf(
            __('Installing Plugin from github: %s'),
            esc_html($gh_user.'/'.$gh_repo)
        );
        $upgrader_args['url'] = add_query_arg(
            ['action' => 'upload-plugin', 'plugin' => urlencode($gh_repo)],
            'update.php'
        );
        $result = $installer->install( $title, $parent_file, $submenu_file, $upgrader_args );
        if (is_wp_error($result)) {
            $errormsg  = 'Error: ' . $result . '<br>';
            $errormsg .= 'Error code: ' . $result->get_error_codes()[0] . '<br>';
            $errormsg .= 'Error message: ' . $result->get_error_message()->message . "\n";
            wp_die($result);
        }
        $plugin_dir = $gh_repo;

        // get plugin info
        $plugin_slug = $plugin_dir;
        foreach ($this->get_plugins() as $key => $info) {
            if (preg_match('#^'.preg_quote($plugin_dir).'#', $key)) {
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
        if (! preg_match(self::GITHUB_URL_PATTERN, $gh_repo_url)) {
            wp_die('GitHub url is not correct.');
        }
        $gh_user = preg_replace(self::GITHUB_URL_PATTERN, '$2', $gh_repo_url);
        $gh_repo = preg_replace(self::GITHUB_URL_PATTERN, '$3', $gh_repo_url);
        $gh_repo = preg_replace('#\.git$#', '', $gh_repo);
        $gh_token = isset($_POST['ghtoken']) ? sanitize_text_field($_POST['ghtoken']) : null;

        // install theme file
        $installer = new GH_Auto_Updater_Themes($gh_repo, $gh_user, $gh_repo, $gh_token );
        $upgrader_args = [];
        $upgrader_args['title'] = sprintf(
            __('Installing Theme from github: %s'),
            esc_html($gh_user.'/'.$gh_repo)
        );
        $upgrader_args['url'] = add_query_arg(
            ['action' => 'upload-theme', 'package' => urlencode($gh_repo)],
            'update.php'
        );
        $result = $installer->install( $title, $parent_file, $submenu_file, $upgrader_args );
        if (is_wp_error($result)) {
            $errormsg  = 'Error: ' . $result . '<br>';
            $errormsg .= 'Error code: ' . $result->get_error_codes()[0] . '<br>';
            $errormsg .= 'Error message: ' . $result->get_error_message()->message . "\n";
            wp_die($result);
        }
        $theme_dir = $gh_repo;

        // get theme info
        $theme_slug = $theme_dir;
        $this->options['themes'][$theme_slug] = [
            'gh_user'  => $gh_user,
            'gh_repo'  => $gh_repo,
            'gh_token' => $gh_token,
        ];
        update_option(self::OPTION_NAME, $this->options);
    }
}