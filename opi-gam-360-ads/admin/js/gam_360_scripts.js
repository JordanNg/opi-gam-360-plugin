/**
 * Display or hide the size mapping select divs based on the positions selected
 * 
 * @param {*} ad_unit 
 * @param {*} position 
 */
function update_size_mapping_select_on_position_set( ad_unit, position = null ) {
    // Set the id of the select element
    var size_mapping_div_id = "#size_mapping_" + ad_unit + ( position != null ? '_' + position : '' ) + "_div_id";
    var size_mapping_select_id = "#size_mapping_" + ad_unit + ( position != null ? '_' + position : '' ) + "_id";

    // Get references to the elements
    var $select_container = jQuery( size_mapping_div_id );
    var $select = jQuery( size_mapping_select_id );

    // Toggle the display value
    $select_container.toggle();
    // Toggle the disabled attribute
    $select.prop('disabled', (i, v) => !v);
}

/**
 * Display or hide the lazy load checkbox spans based on the positions selected
 * 
 * @param {*} ad_unit 
 * @param {*} position 
 */
function update_lazy_load_on_position_set( ad_unit, position = null ) {
    // Set the id of the select element
    var lazy_load_span_id = "#lazy_load_" + ad_unit + ( position != null ? '_' + position : '' ) + "_span_id";
    var lazy_load_input_id = "#lazy_load_" + ad_unit + ( position != null ? '_' + position : '' ) + "_id";

    // Get references to the elements
    var $input_container = jQuery( lazy_load_span_id );
    var $input = jQuery( lazy_load_input_id );

    // Toggle the display value
    $input_container.toggle();
    // Toggle the disabled attribute
    $input.prop('disabled', (i, v) => !v);
}

jQuery( document ).ready( function() {
    /**
     * Enable the accordion jQuery UI element
     */
    jQuery( function() {
        jQuery( "#accordion" ).accordion({
            heightStyle: "content",
            collapsible: true
        });
    });
    
    /**
     * Toggle the accordion on disable ads click
     */
    jQuery( '#disable_ads_id' ).click(function(){
        jQuery( '#accordion' ).toggle();
    });
});
