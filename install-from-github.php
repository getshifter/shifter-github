<?php
/**
 * Plugin Name:     Shifter Github Plugin/Theme Installer
 * Plugin URI:      https://github.com/getshifter/shifter-install-helper
 * Description:     Shifter Github Plugin/Theme Installer
 * Author:          Shifter Team
 * Author URI:      https://getshifter.io
 * Text Domain:     shifter-github-pull
 * Domain Path:     /languages
 * Version:         0.0.2
 * License:         GPLv2 or later
 *
 * @package         Gh_Auto_Updater_Example
 */
require_once(__DIR__ . '/vendor/autoload.php' );
require_once(__DIR__ . '/inc/class-gh-auto-updater.php');
require_once(__DIR__ . '/inc/class-gh-installer.php');

$work_dir = trailingslashit(sys_get_temp_dir());
new Shifter_GH_Installer($work_dir);