<?php
/**
 * Plugin Name:       OPI GAM 360 Ads
 * Plugin URI:        https://oahupublications.com/
 * Description:       Provides a UI and plugin based method to implement GAM 360 Ads on a site. 
 * Version:           1.0.1
 * Author:            Oahu Publications Inc.
 * Author URI:        https://oahupublications.com/
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

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