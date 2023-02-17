<?php
/**
 * Plugin Name:       OPI GAM 360 Ads
 * Plugin URI:        https://oahupublications.com/
 * Description:       Provides a UI and plugin based method to implement GAM 360 Ads on a site. 
 * Version:           1.0.3
 * Author:            Oahu Publications Inc.
 * Author URI:        https://oahupublications.com/
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

require 'plugin-update-checker/plugin-update-checker.php';
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$myUpdateChecker = PucFactory::buildUpdateChecker(
	'https://github.com/JordanNg/opi-gam-360-plugin/',
	__FILE__,
	'opi-gam-360-ads'
);

// //Set the branch that contains the stable release.
// $myUpdateChecker->setBranch('production');

// //Optional: If you're using a private repository, specify the access token like this:
// $myUpdateChecker->setAuthentication('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');

/**
 * Require the core class for ad functionality
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-opi-gam-360-ads.php';

/**
 * Create a new object using the core class
 */
function run_opi_gam_360_ads() {
    $plugin = new OPI_GAM_360_Ads();
}
run_opi_gam_360_ads();