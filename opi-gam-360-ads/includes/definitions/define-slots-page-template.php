<?php 
// Set the default ad configuration if none is set
if ( empty( $this->ad_configuration ) ) {
    $this->ad_configuration = $this->get_default_ad_configuration();
}

// Get all of the page type key values
$all_page_types = $this->get_archive_page_types();
array_push( $all_page_types, 'ros' );
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
            // Print all of the definition calls for the current page template
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