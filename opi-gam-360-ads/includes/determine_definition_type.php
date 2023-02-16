<?php
    // Based on the current page load the specific add slots
    if ( is_home() ) {
        // Include the homepage ad slot definitions
        include( plugin_dir_path( __FILE__ ) . "definitions/define-slots-homepage.php" );
    } else if ( is_single() ) {
        $post_type = get_post_type();
        // Define different slots for each post type
        if ( $post_type == 'post' ) {
            // Include the single ad slot definitions
            include( plugin_dir_path( __FILE__ ) . "definitions/define-slots-article.php" );
        }
    } else if ( is_archive() ) {
        // Include the archive slot definitions
        include( plugin_dir_path( __FILE__) . "definitions/define-slots-standard-1.php" );
    } else if ( is_page() ) {
        // Attempt to get the configuration from the DB
        $this->ad_configuration = $this->get_ad_configuration_for_page();

        // Check if the ad configuration is disabled for this page
        if ( empty( $this->ad_configuration['disable_ads'] ) ) {
            // Include the page slot definitions
            include( plugin_dir_path( __FILE__ ) . "definitions/define-slots-page-template.php" );
        }
    } else if ( is_404() ) { 
        // Include the 404 page definitions
        include( plugin_dir_path( __FILE__ ) . "definitions/define-slots-standard-1.php" );
    } else {
        // Include ad slot definitions for the general case
    }