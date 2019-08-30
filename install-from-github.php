<?php
/**
 * Plugin Name:     Shifter Github Plugin/Theme Installer
 * Plugin URI:      https://github.com/getshifter/shifter-install-helper
 * Description:     Shifter Github Plugin/Theme Installer
 * Author:          Shifter Team
 * Author URI:      https://getshifter.io
 * Text Domain:     shifter-github-pull
 * Domain Path:     /languages
 * Version:         {release version}
 * License:         GPLv2 or later
 */
require_once(__DIR__ . '/vendor/autoload.php' );
require_once(__DIR__ . '/inc/class-gh-installer.php');

$shifter_gh_installer = new Shifter_GH_Installer();
$shifter_gh_installer->add_hooks();