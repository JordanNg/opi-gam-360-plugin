<?php 
// Set the global ad_configuration array
$this->ad_configuration = array( 
    'size_mapping_definitions' => array( 'mappingTopBanner', 'mappingBottomBanner', 'mappingRightRailBox', 'mappingMobileStickyFooter', 'mappingSliding' ),
    'ad_slots' => array(
        'tile' => array(
            'positions'             => array( '1', '2' ),
            'size_mapping'          => array(),
            'out_of_page'           => false,
            'custom_targeting'      => array(),
            'lazy_load'             => array( '1' => true, '2' => true )
        ),
        'int' => array( 
            'positions'             => array(),
            'size_mapping'          => array(),
            'out_of_page'           => true,
            'custom_targeting'      => array(),
            'lazy_load'             => false
        ),
        'sliding' => array( 
            'positions'             => array(),
            'size_mapping'          => 'mappingSliding',
            'out_of_page'           => false,
            'custom_targeting'      => array(),
            'lazy_load'             => true
        ),
        'leaderboard' => array(
            'positions'             => array( '1', '2' ),
            'size_mapping'          => array( '1' => 'mappingTopBanner', '2' => 'mappingBottomBanner' ),
            'out_of_page'           => false,
            'custom_targeting'      => array(),
            'lazy_load'             => array( '1' => true, '2' => true )
        ),
        'native' => array(
            'positions'             => array( '0' ),
            'size_mapping'          => array(),
            'out_of_page'           => false,
            'custom_targeting'      => array(),
            'lazy_load'             => array( '0' => true )
        ),
        'box' => array( 
            'positions'             => array( '1', '2', '3' ),
            'size_mapping'          => array( '1' => 'mappingRightRailBox', '2' => 'mappingRightRailBox', '3' => 'mappingRightRailBox' ),
            'out_of_page'           => false,
            'custom_targeting'      => array(),
            'lazy_load'             => array( '1' => true, '2' => true, '3' => true )
        ),
        'box-tile' => array(
            'positions'             => array( '1', '2', '3', '4', '5', '6' ),
            'size_mapping'          => array(),
            'out_of_page'           => false,
            'custom_targeting'      => array(),
            'lazy_load'             => array( '1' => true, '2' => true, '3' => true, '4' => true, '5' => true, '6' => true )
        ),
        'mobile_sticky_footer' => array(
            'positions'             => array(),
            'size_mapping'          => 'mappingMobileStickyFooter',
            'out_of_page'           => false,
            'custom_targeting'      => array(),
            'lazy_load'             => false
        )
    ),
);

// Get all of the page type key values
$all_page_types = $this->get_archive_page_types();
array_push( $all_page_types, 'homepage' );
?>

    <script type='text/javascript'>
        window.googletag = window.googletag || {cmd: []};

        googletag.cmd.push(function() {
            /* -------------------------------------------------------------------------- */
            /*                              REFRESH KEY VALUE                             */
            /* -------------------------------------------------------------------------- */
            <?php
            // Set ad refresh variables
            $this->print_ad_slot_refresh_variables();
            ?>

            /* -------------------------------------------------------------------------- */
            /*                                SIZE MAPPING                                */
            /* -------------------------------------------------------------------------- */
            <?php
            // Dynamically set any size mapping definitions here
            $this->print_size_mapping_definition_calls();
            ?>

            /* -------------------------------------------------------------------------- */
            /*                             AD SLOT DEFINITIONS                            */
            /* -------------------------------------------------------------------------- */
            <?php
            // Print all of the definition calls
            $this->print_slot_definition_calls();
            ?>

            // Set page-level targeting
            <?php echo !empty( $all_page_types ) ? "googletag.pubads().setTargeting('pagetype'," . json_encode( $all_page_types ) . ");\n" : ""; ?>
            googletag.pubads().setTargeting('hi_site', 'WHT');

            // Site Domain for BlackPress GAM reporting
            googletag.pubads().setTargeting('site', 'westhawaiitoday.com');

            // Set GPT impression viewable event listener
            <?php $this->print_impression_viewable_event_listener(); ?>

            googletag.pubads().collapseEmptyDivs();
            googletag.pubads().setCentering(true);
            googletag.pubads().enableSingleRequest();
            googletag.pubads().disableInitialLoad();
            googletag.enableServices();

            <?php $this->print_lazy_loading(); ?>
        });
    </script>