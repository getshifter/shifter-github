<?php
/**
 * Plugin Name:     Shifter Github Plugin/Theme Installer
 * Plugin URI:      PLUGIN SITE HERE
 * Description:     Shifter Github Plugin/Theme Installer
 * Author:          Shifter Team
 * Author URI:      https://getshifter.io
 * Text Domain:     shifter-github-pull
 * Domain Path:     /languages
 * Version:         nightly
 * License:         GPLv2 or later
 *
 * @package         Gh_Auto_Updater_Example
 */
require_once(__DIR__ . '/inc/Parsedown.php');
require_once(__DIR__ . '/inc/class-gh-auto-updater.php');
require_once(__DIR__ . '/inc/class-gh-installer.php');

new Shifter_GH_Installer(untrailingslashit(plugin_dir_path(__FILE__)).'/tmp/');