<?php
/**
 * The core OPI GAM 360 plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.0
 * @package    OPI GAM 360 
 * @subpackage OPI GAM 360/includes
 * @author     https://oahupublications.com
 */
class OPI_GAM_360_Ads {
    /**
     * The Google Ad Manager Network ID
     * 
     * @var int $network_id
     */
    public $network_id;

    /**
     * A multidimensional array containing the saved ad taxonomy for this site
     * 
     * @var array $ad_taxonomy
     */
    public $ad_taxonomy;

    /**
     * A multidimensional array containing the saved size mapping configuration
     * 
     * @var array $size_mapping_configuration
     */
    public $size_mapping_configuration;

    /**
     * A multidimensional array containing the saved default ad configuration
     * 
     * @var array $default_ad_configuration
     */
    public $default_ad_configuration;

    /**
     * A multidimensional array containing the current item's ad configuration
     * 
     * @var array $ad_configuration
     */
    public $ad_configuration;
    
    /**
     * A multidimensional array containing the current site's page type mapping
     * 
     * @var array $page_type_mapping
     */
    public $page_type_mapping;
	
    /**
     * Ad refresh key used in GPT targeting calls
     */
    public $refresh_key = 'refresh';

    /**
     * Ad refresh value used in GPT targeting calls
     */
    public $refresh_value = 'true';

    /**
     * Seconds to wait after viewability
     * 
     * The default is set to 30 as this is the minimum as recommended by the GPT 
     * reference documentation.
     */
    public $seconds_to_wait_after_viewability;

    /**
     * Enable Lazy Loading across the site
     * 
     * The default is set to false as only when this is set enabled in the settings 
     * do we want to begin lazy loading
     */
    public $lazy_load_enabled;
    public $intersection_margin;

    /**
     * The class constructor
     * 
     * Pulls the saved plugin settings from the DB and sets the member variables if possible. 
     * It is also responsible for loading all helper scripts and styles along with setting up
     * Wordpress hooks for core plugin functionality.
	 */
	public function __construct() {
        // Get the saved options from the settings page
        $settings = get_option( 'gam_360_ad_options' );
        // Set the settings to their respective member variable
        $this->network_id = !empty( $settings['network_id'] ) ? $settings['network_id'] : null;
        $this->ad_taxonomy = !empty( $settings['ad_taxonomy'] ) ? json_decode( $settings['ad_taxonomy'], true ) : null;
        $this->size_mapping_configuration = !empty( $settings['size_mapping'] ) ? json_decode( $settings['size_mapping'], true ) : null; 
        $this->default_ad_configuration = !empty( $settings['default_ad_configuration'] ) ? json_decode( $settings['default_ad_configuration'], true ) : null; 
        $this->page_type_mapping = !empty( $settings['page_type_mapping'] ) ? json_decode( $settings['page_type_mapping'], true ) : null; 
        $this->seconds_to_wait_after_viewability = !empty( $settings['seconds_to_wait_after_viewability'] ) ? $settings['seconds_to_wait_after_viewability'] : 30;
        $this->lazy_load_enabled = !empty( $settings['lazy_load_enabled'] ) && $settings['lazy_load_enabled'] == 'on' ? true : false;
        $this->intersection_margin = !empty( $settings['intersection_margin'] ) ? $settings['intersection_margin'] : 200; 

        // Run functions based on if this is an admin screen or not
        if ( is_admin() ) {
            /* ---------------------------------- ADMIN --------------------------------- */
            // Register the settings page
            add_action( 'admin_menu', array( $this, 'gam_360_ads_settings_page' ) );
            add_action( 'admin_init', array( $this, 'opi_gam_360_settings_init' ) );
            
            // Load CSS and JS
            add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_files' ) );

            // Register a metabox for individual pages
            add_action( 'add_meta_boxes', array( $this, 'page_meta_box_ad_configuration' ) );
            add_action( 'save_post', array( $this, 'meta_box_ad_configuration_save' ) );
        } else {
            /* --------------------------------- PUBLIC --------------------------------- */
            add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_public_scripts' ) );
            // Add filter when enqueing scripts ad related scripts
            add_filter( 'script_loader_tag', array( $this, 'async_ad_scripts' ), 10, 2 );
            // Hook into wp_head to determine the appropriate ad slot definition
            add_action( 'wp_head', array( $this, 'determine_definition_type' ) );
            // Register the print_display_call function to an action hook
            add_action( 'print_display_call', array( $this, 'print_display_call' ), 10, 5 );
        }
    }

    /**
     * Function to enqueue all admin scripts and styles
     */
    public function enqueue_admin_files( $hook ) {
        global $post;
        // If this is the edit post screen
        if ( $hook == 'post-new.php' || $hook == 'post.php' ) {
            if ( $post->post_type == 'page' ) {
                wp_enqueue_script( 'opi_gam_360_admin_scripts', plugin_dir_url( __DIR__ ) . 'admin/js/gam_360_scripts.js', array( 'jquery', 'jquery-ui-accordion' ), '1.0.3', true );
                wp_enqueue_style( 'jquery_ui_css', '//code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css' );
            }
        }
    }

    /**
     * Function to enqueue all public scripts
     */
    public function enqueue_public_scripts() {
        wp_enqueue_script( 'gpt_script', 'https://securepubads.g.doubleclick.net/tag/js/gpt.js', array(), '1.0.0', false );
    }

    /**
     * Function to add async attribute to ad scripts
     */
    function async_ad_scripts( $tag, $handle ) {
        // Just return the tag normally if this isn't one we want to async
        if ( 'gpt_script' !== $handle ) {
            return $tag;
        }
        return str_replace( ' type', ' async type', $tag );
    }

    /**
     * Register the admin settings page
     */
    public function gam_360_ads_settings_page() {
        add_options_page(
            'GAM 360 Ads',
            'GAM 360 Ads',
            'manage_options',
            'gam_360_ad_options_page',
            array( $this, 'gam_360_ad_options_callback' )
        );
    }

    /**
     * Registers the plugin's settings
     */
    public function opi_gam_360_settings_init() {
        // Register a new setting
        register_setting( 'gam_360_options_group', 'gam_360_ad_options' );

        // Register a Setting Sections
        add_settings_section(
            'gam_360_ad_options_section',
            __( 'GAM 360 Ads', 'wordpress' ),
            array( $this, 'gam_360_ads_section_callback' ),
            'gam_360_ad_options_page'
        );
        
        // Register Setting Fields
        add_settings_field( 'network_id', 'GAM Network ID', array( $this, 'network_id_input_callback' ), 'gam_360_ad_options_page', 'gam_360_ad_options_section' );
        add_settings_field( 'seconds_to_wait_after_viewability', 'Seconds to wait after Ad Viewability', array( $this, 'seconds_to_wait_after_viewability_callback' ), 'gam_360_ad_options_page', 'gam_360_ad_options_section' );
        add_settings_field( 'lazy_load_enabled', 'Lazy Loading Enabled', array( $this, 'lazy_load_enabled_input_callback' ), 'gam_360_ad_options_page', 'gam_360_ad_options_section' );
        add_settings_field( 'intersection_margin', 'Intersection Margin (% of Viewport)', array( $this, 'intersection_margin_input_callback' ), 'gam_360_ad_options_page', 'gam_360_ad_options_section' );
        add_settings_field( 'ad_taxonomy', 'Ad Taxonomy', array( $this, 'ad_taxonomy_input_callback' ), 'gam_360_ad_options_page', 'gam_360_ad_options_section' );
        add_settings_field( 'size_mapping', 'Size Mapping', array( $this, 'size_mapping_input_callback' ), 'gam_360_ad_options_page', 'gam_360_ad_options_section' );
        add_settings_field( 'default_ad_configuration', 'Default Ad Configuration', array( $this, 'default_ad_configuration_input_callback' ), 'gam_360_ad_options_page', 'gam_360_ad_options_section' );
        add_settings_field( 'page_type_mapping', 'Page Type Key Value Mapping', array( $this, 'page_type_mapping_input_callback' ), 'gam_360_ad_options_page', 'gam_360_ad_options_section' );
    }

    /**
     * Callback for the section description
     */
    public function gam_360_ads_section_callback() {
        echo __( 'Site specific settings for GAM 360 Ads', 'wordpress' );
    }

    /**
     * Callback for the network ID input's HTML 
     * 
     * @param array $options    JSON array of plugin settings saved to the DB
     */
    public function network_id_input_callback() {
        ?>
            <input type="number" name="gam_360_ad_options[network_id]" id="network_id" value="<?php echo $this->network_id; ?>" class="gam-360">
        <?php 
    }

    /**
     * Callback for the seconds to wait after viewability input's HTML
     * 
     * @param array $options    JSON array of plugin settings saved to the DB
     */
    public function seconds_to_wait_after_viewability_callback() {
        ?>
            <input type="number" name="gam_360_ad_options[seconds_to_wait_after_viewability]" id="seconds_to_wait_after_viewability" value="<?php echo $this->seconds_to_wait_after_viewability; ?>">
            <div>
                <span style="color: #2271b1;"><em>Recommended by Google Publisher Tag Best practices documentation to not decrease this to lower than 30 seconds. Doing so could cause our ad requests to become throttled.</em></span>
            </div>
        <?php
    }

    /**
     * Callback function to display the enable lazy loading input's HTML 
     * 
     * @param array $options    JSON array of plugin settings saved to the DB
     */
    public function lazy_load_enabled_input_callback() {
        ?>
            <input type="checkbox" name="gam_360_ad_options[lazy_load_enabled]" id="lazy_load_enabled" <?php checked( $this->lazy_load_enabled, true ); ?>>
            <div>
                <span style="color: #2271b1;"><em>Allows lazy loading to be set for ad slots. All ads in the viewport on page load will be fetched using a batch SRA request.</em></span>
            </div>
        <?php
    }

    /**
     * Callback function for the intersection root margin used for lazy loading off screen ad slots
     * 
     * @param array $options    JSON array of plugin settings saved to the DB
     */
    public function intersection_margin_input_callback() {
        ?>
            <input type="text" name="gam_360_ad_options[intersection_margin]" id="intersection_margin" value="<?php echo $this->intersection_margin; ?>">
            <div>
                <span style="color: #2271b1;"><em>Margin from the viewport that ad slots are fetched and rendered. Units are in % of user's current viewport</em></span>
            </div>
        <?php
    }

    /**
     * Callback for the ad taxonomy input's HTML
     * 
     * @param array $options    JSON array of plugin settings saved to the DB
     */
    public function ad_taxonomy_input_callback() {
        ?>
        <textarea name="gam_360_ad_options[ad_taxonomy]" id="ad_taxonomy" style="width: 100%;" rows="25" onclick='this.style.height = "";this.style.height = this.scrollHeight + "px"' oninput='this.style.height = "";this.style.height = this.scrollHeight + "px"'><?php echo json_encode( $this->ad_taxonomy, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT ); ?></textarea>
        <span style="color: #2271b1;"><em>This field is set to take a JSON array of values</em></span>
        <?php
    }

    /**
     * Callback for the size mapping input's HTML
     * 
     * @param array $options    JSON array of plugin settings saved to the DB
     */
    public function size_mapping_input_callback() {
        ?>
        <textarea name="gam_360_ad_options[size_mapping]" id="size_mapping" style="width: 100%;" rows="7" onclick='this.style.height = "";this.style.height = this.scrollHeight + "px"' oninput='this.style.height = "";this.style.height = this.scrollHeight + "px"'><?php echo json_encode( $this->size_mapping_configuration, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT ); ?></textarea>
        <span style="color: #2271b1;"><em>This field is set to take a JSON array of values</em></span>
        <?php
    }

    /**
     * Callback for the default ad configuration input's HTML
     * 
     * @param array $options    JSON array of plugin settings saved to the DB
     */
    public function default_ad_configuration_input_callback() {
        ?>
        <textarea name="gam_360_ad_options[default_ad_configuration]" id="default_ad_configuration" style="width: 100%;" rows="10" onclick='this.style.height = "";this.style.height = this.scrollHeight + "px"' oninput='this.style.height = "";this.style.height = this.scrollHeight + "px"'><?php echo json_encode( $this->default_ad_configuration, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT ); ?></textarea>
        <span style="color: #2271b1;"><em>This field is set to take a JSON array of values</em></span>
        <?php
    }

    /**
     * Callback for the pagetype mapping input's HTML
     */
    public function page_type_mapping_input_callback() {
        // Get the page type mapping property
        $page_type_mapping_array = $this->page_type_mapping;
        // Get all current categories
        $all_categories = get_categories( array( 'hide_empty' => false ) );
        if ( !is_wp_error( $all_categories ) ) {
            // Check all cats for missing values
            foreach( $all_categories as $key => $value ) {
                // If the saved option does not already contain the slug
                if ( empty( $page_type_mapping_array[$value->slug] ) ) {
                    $page_type_mapping_array[$value->slug] = array();
                }
            }
        }
        // Get all tags
        $all_tags = get_tags( array( 'hide_empty' => false ) );
        if ( !is_wp_error( $all_tags ) ) {
            // Check all cats for missing values
            foreach( $all_tags as $key => $value ) {
                // If the saved option does not already contain the slug
                if ( empty( $page_type_mapping_array[$value->slug] ) ) {
                    $page_type_mapping_array[$value->slug] = array();
                }
            }
        }

        ?>
        <textarea name="gam_360_ad_options[page_type_mapping]" id="page_type_mapping" style="width: 100%;" rows="30" onclick='this.style.height = "";this.style.height = this.scrollHeight + "px"' oninput='this.style.height = "";this.style.height = this.scrollHeight + "px"'><?php echo !empty( $page_type_mapping_array ) ? json_encode( $page_type_mapping_array, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT ) : ''; ?></textarea>
        <span style="color: #2271b1;"><em>This field is set to take a JSON array of values</em></span>
        <?php
    }

    /**
     * Function that uses the Settings API to display the plugin's settings on the setting page
     */
    public function gam_360_ad_options_callback() {
        ?>
        <div class="wrap">
            <form action='options.php' method='post'>
        
                <h1>GAM 360 Settings</h1>
        
                <?php
                settings_fields( 'gam_360_options_group' );
                do_settings_sections( 'gam_360_ad_options_page' );
                submit_button();
                ?>
        
            </form>
        </div>
        <?php
    }

    /**
     * Function to register the ad configuration meta box for pages
     */
    public function page_meta_box_ad_configuration() {
        $screens = array( 'page' );
        foreach ( $screens as $screen ) {
            add_meta_box(
                'ad_configuration_meta_box',
                'Page Ad Configuration',
                array( $this, 'ad_configuration_meta_box_html' ),
                $screen
            );
        }
    }

    /**
     * Callback function to display the meta boxes HTML
     * 
     * @param $post     WP_Term Object for the current page
     */
    public function ad_configuration_meta_box_html( $post ) {
        // Include form elements
        $ad_taxonomy = $this->get_configuration();
        $size_mapping_configuration = $this->get_size_mapping_configuration();

        // Get the values
        $gam_ad_configuration = json_decode( get_post_meta( $post->ID, '_gam_ad_configuration', true ), true );

        // Generate nonce fields for the form
        wp_nonce_field( 'gam_ad_configuration_action', 'gam_ad_configuration_name' );

        $counter = 0;

        ?><div id="accordion" style="<?php echo isset( $gam_ad_configuration['disable_ads'] ) && $gam_ad_configuration['disable_ads'] == true ? 'display: none;' : ''; ?>"><?php 
        foreach( $ad_taxonomy as $ad_unit => $data ) :
            ?>
            <h4><?php echo $data['path']; ?></h4>
            <div>
                <div>
                    <p><strong>Sizes: </strong><em><?php echo implode( ', ', $data['sizes'] ); ?></em></p>
                    <!-- Positions -->
                    <p>
                        <span><strong>Positions: </strong></span>
                        <?php
                        if ( !empty( $data['positions'] ) ) {
                            foreach( $data['positions'] as $position ) :
                            ?>
                                <span style="margin-right: 10px;">
                                    <input 
                                        type="checkbox" 
                                        id="<?php echo $ad_unit . '_' . $position; ?>_position" 
                                        name="gam_ad_configuration[<?php echo $ad_unit; ?>][positions][<?php echo $position; ?>]"
                                        value="true"
                                        onclick="update_size_mapping_select_on_position_set('<?php echo $ad_unit; ?>', '<?php echo $position; ?>');update_lazy_load_on_position_set('<?php echo $ad_unit; ?>', '<?php echo $position; ?>');"
                                        <?php echo isset( $gam_ad_configuration['ad_slots'][$ad_unit]['positions'] ) && in_array( $position, $gam_ad_configuration['ad_slots'][$ad_unit]['positions'] ) ? 'checked="checked"' : ''; ?>
                                    >
                                    <label for="<?php echo $ad_unit . '_' . $position; ?>" ><?php echo $position; ?></label>
                                </span>
                            <?php
                            endforeach;
                        } else {
                            ?>
                                <input 
                                    type="checkbox" 
                                    id="<?php echo $ad_unit; ?>_position" 
                                    name="gam_ad_configuration[<?php echo $ad_unit; ?>][positions]" 
                                    value="true"
                                    onclick="update_size_mapping_select_on_position_set( '<?php echo $ad_unit; ?>' );update_lazy_load_on_position_set('<?php echo $ad_unit; ?>');"
                                    <?php echo isset( $gam_ad_configuration['ad_slots'][$ad_unit]['positions'] ) ? 'checked="checked"' : ''; ?> 
                                >
                            <?php
                        }
                        ?>
                    </p>
                    <!-- Size Mappings -->
                    <p>
                        <span><strong>Size Mappings: </strong></span>
                        <?php 
                        // Can remove from request by using disabled
                        if ( !empty( $data['positions'] ) ) {
                            foreach( $data['positions'] as $position ) :
                                ?>
                                <div id="size_mapping_<?php echo $ad_unit . "_" . $position; ?>_div_id" style="<?php echo !isset( $gam_ad_configuration['ad_slots'][$ad_unit]['positions'] ) || !in_array( $position, $gam_ad_configuration['ad_slots'][$ad_unit]['positions'] ) ? 'display:none;' : ''; ?>">
                                    <span><?php echo $position; ?>: </span>
                                    <select id="size_mapping_<?php echo $ad_unit . "_" . $position; ?>_id" name="gam_ad_configuration[<?php echo $ad_unit; ?>][size_mapping][<?php echo $position; ?>]" <?php echo !isset( $gam_ad_configuration['ad_slots'][$ad_unit]['positions'] ) || !in_array( $position, $gam_ad_configuration['ad_slots'][$ad_unit]['positions'] ) ? 'disabled' : ''; ?>>
                                        <option value>--</option>
                                        <?php foreach( $size_mapping_configuration as $mapping_name => $size_mapping ) : ?>
                                        <option value="<?php echo $mapping_name; ?>" <?php echo !empty( $gam_ad_configuration['ad_slots'][$ad_unit]['size_mapping'][$position] ) && $gam_ad_configuration['ad_slots'][$ad_unit]['size_mapping'][$position] == $mapping_name ? 'selected="selected"' : '';?>><?php echo $mapping_name; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <?php
                            endforeach;
                        } else {
                            ?>
                            <div id="size_mapping_<?php echo $ad_unit; ?>_div_id" style="<?php echo !isset( $gam_ad_configuration['ad_slots'][$ad_unit]['positions'] ) ? 'display:none;' : ''; ?>">
                                <select id="size_mapping_<?php echo $ad_unit; ?>_id" name="gam_ad_configuration[<?php echo $ad_unit; ?>][size_mapping]" <?php echo !isset( $gam_ad_configuration['ad_slots'][$ad_unit]['positions'] ) ? 'disabled' : ''; ?>>
                                    <option value>--</option>
                                    <?php foreach( $size_mapping_configuration as $mapping_name => $size_mapping ) : ?>
                                    <option value="<?php echo $mapping_name; ?>" <?php echo !empty( $gam_ad_configuration['ad_slots'][$ad_unit]['size_mapping'] ) && $gam_ad_configuration['ad_slots'][$ad_unit]['size_mapping'] == $mapping_name ? 'selected="selected"' : ''; ?>><?php echo $mapping_name; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <?php
                        }
                        ?>
                    </p>
                    <!-- Out of Page Slots -->
                    <p>  
                        <span><strong>Out Of Page: </strong></span>
                        <input 
                            type="checkbox" 
                            id="<?php echo $ad_unit; ?>_oop" 
                            name="gam_ad_configuration[<?php echo $ad_unit; ?>][out_of_page]" 
                            value="true"
                            <?php isset( $gam_ad_configuration['ad_slots'][$ad_unit]['out_of_page'] ) ? checked( $gam_ad_configuration['ad_slots'][$ad_unit]['out_of_page'], true ) : ''; ?>
                        >
                    </p>
                    <p>
                        <span><strong>Lazy Load: </strong></spam>
                        <?php 
                        // Can remove from request by using disabled
                        if ( !empty( $data['positions'] ) ) {
                            foreach( $data['positions'] as $position ) :
                                ?>
                                <span id="lazy_load_<?php echo $ad_unit . "_" . $position; ?>_span_id" style="margin-right: 10px;<?php echo !isset( $gam_ad_configuration['ad_slots'][$ad_unit]['positions'] ) || !in_array( $position, $gam_ad_configuration['ad_slots'][$ad_unit]['positions'] ) ? 'display:none;' : ''; ?>">
                                    <input 
                                        type="checkbox"
                                        id="lazy_load_<?php echo $ad_unit . "_" . $position; ?>_id" 
                                        name="gam_ad_configuration[<?php echo $ad_unit; ?>][lazy_load][<?php echo $position; ?>]"
                                        <?php checked( ( isset( $gam_ad_configuration['ad_slots'][$ad_unit]['lazy_load'][$position] ) ? $gam_ad_configuration['ad_slots'][$ad_unit]['lazy_load'][$position] : false ), true ); ?>
                                        <?php echo !isset( $gam_ad_configuration['ad_slots'][$ad_unit]['positions'] ) || !in_array( $position, $gam_ad_configuration['ad_slots'][$ad_unit]['positions'] ) ? 'disabled' : ''; ?>
                                    >
                                    <label for="lazy_load_<?php echo $ad_unit . "_" . $position; ?>_id"> <?php echo $position; ?></label>
                                </span>
                                <?php
                            endforeach;
                        } else {
                            ?>
                            <span id="lazy_load_<?php echo $ad_unit; ?>_span_id" style="<?php echo !isset( $gam_ad_configuration['ad_slots'][$ad_unit]['positions'] ) ? 'display:none;' : ''; ?>">
                                <input 
                                    type="checkbox" 
                                    id="lazy_load_<?php echo $ad_unit; ?>_id" 
                                    name="gam_ad_configuration[<?php echo $ad_unit; ?>][lazy_load]" 
                                    <?php checked( $gam_ad_configuration['ad_slots'][$ad_unit]['lazy_load'], true ); ?>
                                    <?php echo !isset( $gam_ad_configuration['ad_slots'][$ad_unit]['positions'] ) ? 'disabled' : ''; ?>
                                >
                            </span>
                            <?php
                        }
                        ?>
                    </p>
                    <!-- Custom Targeting -->
                    <!-- <p>
                        <span><strong>Custom Targeting: </strong></span>
                        <div>
                            <div>
                                <label>Key: </label>
                                <input id="" type="text">
                                <label>Value: </label>
                                <input id="" type="text">
                            </div>
                            <button id="custom_targeting_add_new_button" type="button" class="button">Add New</button>
                        </div>
                    </p> -->
                </div>
            </div>
            <?php
            $counter++;
        endforeach;
        ?>
        </div>
        <!-- Disable ads -->
        <div>
            <p>
                <label for="disable_ads_id" style="color:red;"><strong>Disable Ads: </strong></label>
                <input id="disable_ads_id" name="disable_ads" type="checkbox" value="true" <?php isset( $gam_ad_configuration['disable_ads'] ) ? checked( $gam_ad_configuration['disable_ads'], true ) : ''; ?>>
            </p>
        </div>
        <?php
    }

    /**
     * Function to format and save data from the ad configuration metabox form
     * 
     * @param int $post_id  The post id of the item being saved
     */
    public function meta_box_ad_configuration_save( $post_id ) {
        // Verify the request using the nonce
        if ( wp_verify_nonce( $_POST['gam_ad_configuration_name'], 'gam_ad_configuration_action' ) ) {
            // Format data so that it uses the same ad configuration
            $formatted_config = array(
                'size_mapping_definitions' => array(),
                'ad_slots' => array(), 
                'disable_ads' => ( !empty( $_POST['disable_ads'] ) && $_POST['disable_ads'] === "true" ? true : false ),
            );

            // If the gam_ad_configuration POST data is not empty
            if ( !empty( $_POST['gam_ad_configuration'] ) ) {
                foreach( $_POST['gam_ad_configuration'] as $ad_unit => $data ) {
                    // Check that the positions have been set
                    if ( !empty( $data['positions'] ) ) {
                        // Set the ad unit array's default values
                        $formatted_config['ad_slots'][$ad_unit] = array();
                        $formatted_config['ad_slots'][$ad_unit]['positions'] = array();
                        $formatted_config['ad_slots'][$ad_unit]['size_mapping'] = array();
                        $formatted_config['ad_slots'][$ad_unit]['out_of_page'] = false;
                        $formatted_config['ad_slots'][$ad_unit]['custom_targeting'] = array();
                        $formatted_config['ad_slots'][$ad_unit]['lazy_load'] = array();

                        // Determine if this is a single ad unit or one with multiple positions
                        if ( is_array( $data['positions'] ) ) {
                            // For each position add it to the formatted array's position
                            foreach( $data['positions'] as $position => $value ) {
                                array_push( $formatted_config['ad_slots'][$ad_unit]['positions'], $position );
                            }
                        }

                        // Check if the size mapping data is empty
                        if( !empty( $data['size_mapping'] ) ) {
                            if ( is_array( $data['size_mapping'] ) ) { 
                                foreach( $data['size_mapping'] as $position => $mapping_name ) {
                                    if ( !empty( $mapping_name ) ) {
                                        $formatted_config['ad_slots'][$ad_unit]['size_mapping'][$position] = $mapping_name;

                                        // Check if it is already in the definition array 
                                        if ( !in_array( $mapping_name, $formatted_config['size_mapping_definitions'] ) ) {
                                            array_push( $formatted_config['size_mapping_definitions'], $mapping_name );
                                        }
                                    }
                                }
                            } else {
                                if ( !empty( $data['size_mapping'] ) ) {
                                    $formatted_config['ad_slots'][$ad_unit]['size_mapping'] = $data['size_mapping'];

                                    // Check if it is already in the definition array 
                                    if ( !in_array( $data['size_mapping'], $formatted_config['size_mapping_definitions'] ) ) {
                                        array_push( $formatted_config['size_mapping_definitions'], $data['size_mapping'] );
                                    }
                                }
                            }
                        }

                        // Check if the OOP value is empty
                        if ( !empty( $data['out_of_page'] ) ) {
                            $formatted_config['ad_slots'][$ad_unit]['out_of_page'] = true;
                        }

                        // Check if the lazy loading value is empty
                        if ( !empty( $data['lazy_load'] ) ) {
                            if ( is_array( $data['lazy_load'] ) ) { // Multiple positions
                                foreach( $data['lazy_load'] as $position => $lazy_load_value ) {
                                    if ( $lazy_load_value == 'on' ) {
                                        $formatted_config['ad_slots'][$ad_unit]['lazy_load'][$position] = true;
                                    }
                                }
                            } else {
                                if ( $data['lazy_load'] == 'on') {
                                    $formatted_config['ad_slots'][$ad_unit]['lazy_load'] = true;
                                }
                            }
                        }

                        // TODO: CHECK FOR ANY CUSTOM TARGETING ( Not a priority at the moment )
                    }        
                }
            }

            // Update the post meta, set _gam_ad_configuration as empty if empty
            $result = update_post_meta(
                $post_id,
                '_gam_ad_configuration',
                json_encode( $formatted_config )
            );
        } else {
            return;
        }
    }

    /**
     * Function to determine the corresponding definition file that should be applied
     * 
     * This function uses the definition files found in /includes/definitions to determine the
     * ad slots to define in a pages head. There are also a few standard definition files 
     * that can be used for repetitive definitions.
     */
    public function determine_definition_type() {
        // Include the external definition types file
        include( plugin_dir_path( __FILE__ ) . "/determine_definition_type.php" );
    }

    /**
     * Function to get the current GAM network id
     */
    public function get_GAM_network_id() {
        // Set the network id
        return $this->network_id;
    }

    /**
     * Returns the configuration for a specified level
     * 
     * Users are able to pass in optional parameters to get information about the 
     * current ad configuration. Passing no $ad_type will return the entire 
     * configuration array, passing an $ad_type will return the configuration 
     * for the specified ad_type, and in conjunction with $ad_data with return 
     * the specific data point for the ad slot. This should be used as a source 
     * of truth when determining ad configuration. 
     * 
     * @param string $ad_type   The ad slot that you would like to retrieve information for
     * @param string $ad_data   The information that you would like to retrieve for the ad slot
     * 
     * @return string|array|false   Will return a string when valid $ad_type and $ad_data parameters
     *                              are passed. Will return an array when valid $ad_type parameter is
     *                              passed or no parameters are passed. Will return false on invalid
     *                              parameters. 
     */
    public function get_configuration( $ad_type = null, $ad_data = null ) {
        // Define the configuration array for all possible ads
        $mapping = $this->ad_taxonomy;

        // Return a portion of the configuration based on the parameters passed in
        if ( !empty( $ad_type ) ) {
            // Check before returning data
            if ( empty( $mapping[$ad_type] ) ) {
                return false;
            }
            // Check that the parameter is not false
            if ( !empty( $ad_data ) ) {
                // Check before returning data
                if ( empty( $mapping[$ad_type][$ad_data] ) ) {
                    return false;
                }
                
                return $mapping[$ad_type][$ad_data];
            } else {
                return $mapping[$ad_type];
            }
        } else {
            return $mapping;
        }
    }

    /**
     * Return the mapping configuration for a given mapping name
     * 
     * Defines the configuration for size mapping returning the configuration
     * for valid size mapping names. 
     * 
     * @param string $size_mapping_name     A valid size mapping name to get the configuration for
     * 
     * @return array|boolean                The mapping configuration for the requested mapping name
     *                                      or false if the mapping name does not exist. 
     */
    public function get_size_mapping_configuration( $size_mapping_name = null ) {
        // Create the size mapping configuration
        $size_mapping_configuration = $this->size_mapping_configuration;

        // Check if the size mapping name is not empty
        if ( !empty( $size_mapping_name ) ) {
            // Check if the size mapping name is valid
            if ( !empty( $size_mapping_configuration[ $size_mapping_name ] ) ) {
                return $size_mapping_configuration[$size_mapping_name];
            } else {
                return false;
            }
        } else {
            return $size_mapping_configuration;
        }
    }

    /**
     * Returns the default ad configuration for dynamic ad definitions
     * 
     * Defines the default ad configuration for all ad slot definitions that
     * need to use the default configuration.
     * 
     * @return array $default_ad_configuration     A multidimensional array defining default ads definitions.  
     */
    public function get_default_ad_configuration() {
        return $this->default_ad_configuration;
    }

    /**
     * Function to get the ad configuration for a page. 
     * 
     * Checks the DB for the ad configuration saved to the current page's post meta.
     * If it is found it will decode the JSON array and return it to the page.
     * 
     * @return array $gam_ad_configuration  Array of ad slots and corresponding data for the page
     */
    public function get_ad_configuration_for_page() {
        // Attempt to pull the ad configuration for the current page
        $gam_ad_configuration = get_post_meta( get_the_ID(), '_gam_ad_configuration', true );
        
        // Return the configuration array or an empty array if not set
        return !empty( $gam_ad_configuration ) ?  json_decode( $gam_ad_configuration, true ) : array();
    }

    /**
     * Prints the size mapping definitions
     * 
     * Uses the global $ad_configuration variable to print the definitions
     * of the size mapping names passed if they are valid. 
     * 
     * @param boolean $echo     Whether to echo the definition call or not
     * 
     * @return void|string      The size mapping definition when $echo = false 
     *                          or void on $echo = true
     */
    public function print_size_mapping_definition_calls( $echo = true ) {
        if ( !empty( $this->ad_configuration['size_mapping_definitions'] ) ) {
            foreach( $this->ad_configuration['size_mapping_definitions'] as $key => $mapping_name ) {
                // Attempt to get the size mapping configuration
                $size_mapping_configuration = $this->get_size_mapping_configuration( $mapping_name );
                if ( !empty( $size_mapping_configuration ) ) {
                    $output = "var " . $mapping_name . " = googletag.sizeMapping()\n\t\t\t";
                    foreach( $size_mapping_configuration as $key => $sizes ) {
                        $output .= ".addSize(" . $sizes . ")\n\t\t\t";
                    }

                    // Cap the call with a .build() call
                    $output .= ".build();\n\n\t\t\t";

                    if ( $echo ) {
                        echo $output;
                    } else {
                        return $output;
                    }
                } else {
                    echo "// Size mapping configuration does not exist for " . $mapping_name . "\n\t\t\t";
                }
            }
        }
    }

    /**
     * Function to print all ad definition calls
     * 
     * This function relies on the global $ad_configuration variable to be set 
     * in the template file. If it is set then it will use the configuration to
     * print the ad slot definitions.
     * 
     * @param boolean $echo     If true with echo the ad slot definitions
     */
    public function print_slot_definition_calls( $echo = true ) {
        // Check that the $ad_configuration global has been set
        if ( !empty( $this->ad_configuration['ad_slots'] ) ) {
            $output = '';
            // Create two strings, one for slots with lazy loading, the other for slots without lazy loading
            $lazyLoadedAdSlots = "var lazyLoadedAdSlots = {\n\t\t\t\t";
            $initialAdSlotsRequested = "var initialAdSlotsRequested = [\n\t\t\t\t";

            // Use the ad_configuration set on the template
            foreach( $this->ad_configuration['ad_slots'] as $ad_type => $configuration ) {
                $positions              = !empty( $configuration['positions'] ) ? $configuration['positions'] : null;
                $out_of_page            = !empty( $configuration['out_of_page'] ) ? $configuration['out_of_page'] : null;
                $size_mapping           = !empty( $configuration['size_mapping'] ) ? $configuration['size_mapping'] : null;
                $custom_targeting       = !empty( $configuration['custom_targeting'] ) ? $configuration['custom_targeting'] : null;
                $lazy_load              = !empty( $configuration['lazy_load'] ) ? $configuration['lazy_load'] : null;

                // Get the GAM configuration
                $gam_configuration = $this->get_configuration( $ad_type );

                // Check that the requested ad type exists
                if ( !empty( $gam_configuration ) ) {
                    // If positions have been requested
                    if ( !empty( $positions ) ) {
                        // For each of the requested positions
                        foreach( $positions as $pos ) {
                            // Check the GAM configuration for the position
                            if ( !empty( $gam_configuration['positions'] ) && in_array( $pos, $gam_configuration['positions'] ) ) {
                                // Get the ad slot's div ID 
                                $div_id = $this->get_gpt_div_id( $ad_type, $pos ); 
                                // Get the ad slot definition call
                                $temp_output = $this->print_ad_slot_definition_call( $ad_type, $pos, $out_of_page, $size_mapping, $custom_targeting, $echo );
                                // Add the ad slot to the proper output string
                                $this->lazy_load_enabled == true && $lazy_load[$pos] == true ? $lazyLoadedAdSlots .= "'" . $div_id . "' : " . $temp_output : $initialAdSlotsRequested .= $temp_output;
                            }
                        }
                    } else {
                        // Get the ad slot's div ID 
                        $div_id = $this->get_gpt_div_id( $ad_type ); 
                        // Get the ad slot definition call
                        $temp_output = $this->print_ad_slot_definition_call( $ad_type, '', $out_of_page, $size_mapping, $custom_targeting );
                        // Add the ad slot to the proper output string
                        $lazy_load == true && $this->lazy_load_enabled ? $lazyLoadedAdSlots .= "'" . $div_id . "' : " . $temp_output : $initialAdSlotsRequested .= $temp_output;
                    }
                } else {
                    echo "// Unable to create ad slot definitions due to missing ad type configuration\n\t\t\t";
                }
            }

            // Cap the strings with their appropriate ending
            $lazyLoadedAdSlots .= "};\n\t\t\t";
            $initialAdSlotsRequested .= "];\n\t\t\t";

            // Merge the two strings 
            $output .= $lazyLoadedAdSlots . "\n\t\t\t" . $initialAdSlotsRequested; 
            // If echo is false then return the output
            if ( $echo ) {
                echo $output;
            } else {
                return $output;
            }
        } else {
            // Echo the error as a script comment
            echo "// Unable to create ad slot definitions due to missing configuration array on template\n\t\t\t";
            echo "var lazyLoadedAdSlots = {};\n\t\t\t";
            echo "var initialAdSlotsRequested = [];\n\t\t\t";
        }
    }

    /**
     * Function to print the JS for each individual ad slot definition
     * 
     * @param string $ad_type   The ad type to create a definition for
     */
    public function print_ad_slot_definition_call( $ad_type, $position = '', $out_of_page = false, $size_mapping = '', $custom_targeting = array(), $echo = true ) {
        // This is a valid ad type and a valid position
        $output = "googletag." . ( $out_of_page == true ? 'defineOutOfPageSlot' : 'defineSlot' ) . "('/" . $this->get_GAM_network_id() . $this->get_configuration( $ad_type, 'path' ) . "',"; 
        // If this is not an out of page slot, then add the sizes
        if ( !$out_of_page ) {
            $output .= "[" . implode( ',', $this->get_sizes_for_ad_slot( $ad_type, array(), array(), false ) ) . "],";
        }
        // Add the div GPT ID 
        $output .= " '" . $this->get_gpt_div_id( $ad_type, $position ) . "')";
        // Check the size mapping
        if ( !empty( $size_mapping ) ) {
            if ( !empty( $position ) || $position === '0' ) {
                // If the position is not empty, then get the size mapping for the position
                $size_mapping_name = !empty( $size_mapping[$position] ) ? $size_mapping[$position] : '';
            } else {
                // The position is empty get the size mapping string
                $size_mapping_name = $size_mapping; 
            }

            if ( !empty( $size_mapping_name ) ) {
                // Check that the mapping exists
                $mapped_sizes = $this->get_size_mapping_configuration( $size_mapping_name );
                if ( !empty( $mapped_sizes ) ) {
                    $output .= ".defineSizeMapping(" . $size_mapping_name . ")";
                }
            }
        }
        // Set the addService call
        $output .= ".addService(googletag.pubads())";
        // Set refresh for all ad slots
        $output .= $this->create_key_value_targeting_call( $this->refresh_key, $this->refresh_value );
        // Set the position targeting if it has been provided
        if ( !empty( $position ) || $position === '0' ) {
            $output .= $this->create_key_value_targeting_call( 'position', $position );
        }
        // Set any additional targeting
        if ( !empty( $custom_targeting ) ) {
            // Determine how to pull the custom targeting from the config
            if ( !empty( $position ) || $position === '0' ) {
                $custom_key_values = !empty( $custom_targeting[$position] ) ? $custom_targeting[$position] : array();
            } else {
                $custom_key_values = $custom_targeting;
            }

            foreach( $custom_key_values as $key => $value ) {
                $output .= $this->create_key_value_targeting_call( $key, $value );
            }
        }
        // Cap the call
        $output .= ",";

        // If the output is not empty
        if ( !empty( $output ) ) {
            return $output . "\n\t\t\t\t";
        } else {
            return false;
        }
    }

    /** 
     * Helper function to create the JS call for setting targeting
     */
    public function create_key_value_targeting_call( $key, $value ) {
        if( !empty( $key ) && ( !empty( $value ) || $value === '0' ) ) {
            return ".setTargeting('" . $key . "', " . json_encode( $value ) . ")";
        } else {
            return '';
        }
    }

    /**
     * Function to print the GPT display call
     * 
     * This function takes the ad type and position and uses it to create a display call
     * id for the ad. 
     * 
     * @param string    $ad_type - The type of ad slot to display
     * @param string    $position (Optional) - A specific position for the ad slot
     * @param string    $classes (Optional) - A string of custom classes to add
     * @param string    $styles (Optional) - A string of custom styles to add
     * @param boolean   $echo (Optional) - If true then return the display call instead of printing 
     */
    public function print_display_call( $ad_type, $position = '', $classes = '', $styles = '', $echo = true ) {
        // If the ad_type or the position are empty, then do not output the display call
        if ( empty( $ad_type ) ) {
            return;
        }

        // If the echo parameter is true
        if ( $echo ) {
        ?>
            <!-- <?php echo $this->get_configuration( $ad_type, 'path' ) . ( !empty( $position ) || $position === '0' ? ' ' . $position : '' ); ?> -->
            <div id="<?php echo $this->get_gpt_div_id( $ad_type, $position ); ?>" <?php echo !empty( $classes ) ? 'class="' . $classes . '"' : ''; ?> <?php echo !empty( $styles ) ? 'style="' . $styles . '"' : ''; ?>>
                <script type="text/javascript">
                    googletag.cmd.push(function() { googletag.display("<?php echo $this->get_gpt_div_id( $ad_type, $position ); ?>"); });
                </script>
            </div>
        <?php
        } else {
            // Set a variable for the display call
            $display_call = '<!-- ' . $this->get_configuration( $ad_type, 'path' ) . ( !empty( $position ) || $position === '0' ? ' ' . $position : '' ) . ' -->
                            <div id="' . $this->get_gpt_div_id( $ad_type, $position ) . '" ' . ( !empty( $classes ) ? 'class="' . $classes . '"' : '' ) . ' ' . ( !empty( $styles ) ? 'style="' . $styles . '"' : '' ) . '>
                                <script type="text/javascript">
                                    googletag.cmd.push(function() { googletag.display("' . $this->get_gpt_div_id( $ad_type, $position ) . '"); });
                                </script>
                            </div>';
            return $display_call;
        }
        return;
    }

    /**
     * Function to print the GPT lazy loading script
     * 
     * Function to print the GPT lazy loading script based on the GPT lazy loading
     * implementation documentation.
     * 
     * @param string $fetch_margin_percent
     * @param string $render_margin_percent
     * @param string $mobile_scaling     
     */
    public function print_lazy_loading( $echo = true ) {
        $output = '';
        // Check that lazy loading is enabled
        if ( $this->lazy_load_enabled == true ) {
            // Threshold statically defined at 0.0 since we want ads to start loading as soon as it is intersected. 
            $threshold = '0.0';

            $output .= "
            /* -------------------------------------------------------------------------- */
            /*                                LAZY LOADING                                */
            /* -------------------------------------------------------------------------- */
            if ( document.readyState !== 'loading' ) {
                // Document is already ready, call the lazy loading ad script
                lazyLoadAdScript();
            } else {
                document.addEventListener(\"DOMContentLoaded\", () => {
                    // Document is now ready, call the lazy loading ad script
                    lazyLoadAdScript();
                });
            }

            // Function to handle lazy loading and loading ads currently in the viewport
            function lazyLoadAdScript() {
                // Define what is considered above the fold
                const topOfViewPort = window.scrollY;
                const bottomOfViewPort = window.scrollY + window.innerHeight;
                const rootMarginPadding = ((" . $this->intersection_margin . "/100) * window.innerHeight);

                // Define the Intersection Observer options
                const options = {
                    root: null, // the viewport is the root by default
                    rootMargin: '" . $this->intersection_margin . "%', // no margin to the root
                    threshold: " . $threshold . " // trigger when " . $threshold . "% of the ad container is within the the root margin of the viewport
                };

                // Create the Intersection Observer instance
                const observer = new IntersectionObserver(onIntersection, options);

                // For each of the defined ad slots above
                for (const key in lazyLoadedAdSlots) {
                    // Get a reference to the ad slot
                    var adSlot = document.getElementById(key);

                    if ( adSlot != null ) {
                        var adSlotBoundingRect = adSlot.getBoundingClientRect();

                        // If the slot is in the viewport, add it to the initial SRA batch 
                        // request. Otherwise observe it for lazy loading
                        if (((topOfViewPort + adSlotBoundingRect.bottom) - (topOfViewPort - rootMarginPadding) > 0) && ((bottomOfViewPort + rootMarginPadding) - (adSlotBoundingRect.top + topOfViewPort) > 0)) {
                            initialAdSlotsRequested.push(lazyLoadedAdSlots[key]);
                        } else {
                            observer.observe(adSlot);
                        }
                    }
                }

                // Intersection Observer callback function
                function onIntersection(entries, observer) {
                    entries.forEach(entry => {
                        // If the ad container is in view, load the GPT ad
                        if (entry.isIntersecting) {
                            // Stop observing the current ad slot
                            observer.unobserve(entry.target);
                            
                            // Load the GPT ad using the appropriate API
                            googletag.pubads().refresh([lazyLoadedAdSlots[entry.target.id]]);
                        }
                    });
                }

                // Immediately load all ads above the fold
                if ( initialAdSlotsRequested.length !== 0 ) {
                    googletag.pubads().refresh( initialAdSlotsRequested );
                }
            }
            /* ---------------------------- END LAZY LOADING ---------------------------- */\n";
        } else {
            $output .= "
            // Immediately load all ads above the fold
            googletag.pubads().refresh( initialAdSlotsRequested );\n";
        }
        
        if ( $echo ) {
            echo $output;
        }

        return $output;
    }

    /**
     * Function to print the GPT ad refresh slot variables
     * 
     * The SECONDS_TO_WAIT_AFTER_VIEWABILITY value can be set in the Settings page.
     * 
     * @param boolean $echo     If true will output the string otherwise it will be returned
     * 
     * @return string @output   The ad refresh variables
     */
    public function print_ad_slot_refresh_variables( $echo = true ) {
        $output = '';
        $output .= 'var REFRESH_KEY = "' . $this->refresh_key . '";' . "\n\t\t\t";
        $output .= 'var REFRESH_VALUE = "' . $this->refresh_value . '";' . "\n\t\t\t";
        $output .= 'var SECONDS_TO_WAIT_AFTER_VIEWABILITY = ' . $this->seconds_to_wait_after_viewability . ';' . "\n\t\t\t";

        if ( $echo ) {
            echo $output;
        }

        return $output;
    }

    /**
     * Function to print the GPT impressionViewable event listener
     * 
     * @param boolean $echo     If true will output the string otherwise it will be returned
     * 
     * @return string @output   The ad refresh variables
     */
    public function print_impression_viewable_event_listener( $echo = true ) {
        $output = '';
        $output .= "googletag.pubads().addEventListener('impressionViewable', function(event) {" . "\n\t\t\t\t";
        $output .= "var slot = event.slot;" . "\n\t\t\t\t";
        // If the google_console query parameter is set output console logs for debugging
        if ( !empty( $_GET['google_console'] ) && $_GET['google_console'] == 1 ) {
            $output .= "console.log( 'IMPRESSION VIEWABLE FOR AD SLOT: ' + slot.getSlotElementId() );" . "\n\t\t\t\t";
        }
        $output .= "if (slot.getTargeting(REFRESH_KEY).indexOf(REFRESH_VALUE) > -1) {" . "\n\t\t\t\t\t";
        $output .= "setTimeout(function() {" . "\n\t\t\t\t\t\t";
        $output .= "googletag.pubads().refresh([slot]);" . "\n\t\t\t\t\t";
        // If the google_console query parameter is set output console logs for debugging
        if ( !empty( $_GET['google_console'] ) && $_GET['google_console'] == 1 ) {
            $output .= "console.log( 'REFRESHED AD SLOT: ' + slot.getSlotElementId() );" . "\n\t\t\t\t\t";
        }
        $output .= "}, SECONDS_TO_WAIT_AFTER_VIEWABILITY * 1000);" . "\n\t\t\t\t";
        $output .= "}" . "\n\t\t\t";
        $output .= "});" . "\n\t\t\t\t";

        if ( $echo ) {
            echo $output;
        }

        return $output;
    }

    /**
     * Prints the div id for a GPT div
     * 
     * @param string    $ad_type
     * @param string    $position (Optional)
     * 
     * @return string   The GPT div id
     */
    public function get_gpt_div_id( $ad_type, $position = '' ) {
        // At the very least you need the ad type
        if ( empty( $ad_type ) ) {
            return;
        }
        
        return "div-gpt-ad-" . $ad_type . ( !empty( $position ) || $position === '0' ? '-' . $position : '' );
    }

    /**
     * Get the ad sizes for a given ad slot
     * 
     * Uses a predefined array of slot sizes to determine which sizes to add for a given slot.
     * Updating ad slots are extremely simple only requiring updates in one location
     * 
     * @see array_merge()
     * 
     * @param string    $slot_type  String to define the slot sizes you wish to pull
     * @param array     $includes   Array of sizes to include
     * @param array     $excludes   Array of sizes to exclude
     * @param bool      $echo       Whether to echo the the sizes or return an array of sizes
     */
    public function get_sizes_for_ad_slot( $slot_type, $includes = array(), $excludes = array(), $echo = 1 ) {
        // Check that the slot type is not empty
        if ( empty( $slot_type ) ) {
            return array();
        }

        // Get the sizes for the specific ad type
        $sizes = $this->get_configuration( $slot_type, 'sizes' );
        // Add all of the manually included sizes
        $sizes = array_merge( $sizes, $includes );
        // Remove all of the manually excluded sizes
        $sizes = array_merge( array_diff( $sizes, $excludes ), array_diff( $excludes, $sizes ) );
        if ( $echo ) {
            echo implode( ',', $sizes );
        } else {
            return $sizes;
        }
    }

    /**
     * Get all pagetype targeting for the current post
     * 
     * Get all the pagetype key values for slot-level targeting for a given post. 
     * This includes all categories, parent categories, and tags
     * 
     * @see get_terms()
     * 
     * @since 1.0.0
     */
    public function get_post_page_types() {
        // Get all categories for post
        $all_cats_and_tags = get_terms( 
            array( 
                'taxonomy'      => array( 'category', 'post_tag' ),
                'object_ids'    => get_the_ID()
            )
        );

        // Filter the values
        $page_types_array = array();
        if ( !empty( $all_cats_and_tags ) ) {
            // For each of the labels
            foreach( $all_cats_and_tags as $key => $value ) {
                // Get the mapped values by passing the slug
                $page_type_values = $this->page_type_targeting_map( $value->slug );
                if ( !empty( $page_type_values ) ) {
                    // Merge the two arrays
                    $page_types_array = array_merge( $page_types_array, $page_type_values ); 
                }
            }
        }

        // Remove all of the duplicates
        $page_types_array = array_unique( $page_types_array );
        $page_types_array = array_values( $page_types_array );

        return $page_types_array;
    }

    /**
     * Function to get the pagetype key values for archive pages
     * 
     * Gets the key values for archived pages by using the current itme's slug. 
     * After running the value through the mapping function it returns the
     * pagetyle key values. 
     * 
     * @return array    Returns the mapped key values on success and an empty
     *                  array if no key values exist.
     */
    public function get_archive_page_types() {
        $slugs = array();
        // Get the slug based on the archive type
        if ( is_category() ) { // Targeting for a category
            $category = get_category( get_query_var( 'cat' ) );
            if ( !empty( $category ) ) {
                array_push( $slugs, $category->slug ); 
            }
        } else if ( is_tag() ) { // Targeting for a tag
            $tag = get_query_var( 'tag' );
            if ( !empty( $tag ) ) {
                array_push( $slugs, $tag ); 
            }
        } else if ( $this->is_section_front() ) { // Targeting for a section front
            array_push( $slugs, get_query_var( 'pagename' ) ); 
        } else if ( is_page() ) { // Targeting for all other pages
            $page_type_meta = get_post_meta( get_the_ID(), 'pagetype', true );
            if ( !empty( $page_type_meta ) ) {
                $page_type_arr = explode( ',', $page_type_meta );
                $page_type_arr = array_map( 'trim', $page_type_arr );
                if ( !empty( $page_type_arr ) ) {
                    $slugs = $page_type_arr;
                }
            }
        } else {
            // Only add pagetype key values if there was a match
            return array();
        }

        if ( !empty( $slugs ) ) {
            $return_arr = array();
            foreach( $slugs as $key => $slug ) {
                $page_type_values = $this->page_type_targeting_map( $slug );
                if ( !empty( $page_type_values ) ) {
                    $return_arr = array_merge( $return_arr, $page_type_values );
                }
            }

            // Filter out all duplicates from the result array
            $return_arr = array_unique( $return_arr );
            
            return $return_arr;
        } 

        return array();
    }

    /**
     * Function to determine all of the current section fronts
     * 
     * Checks if the current item in the loop is a section front page
     * 
     * @return bool     Whether the current item in the loop is a section front
     */
    public function is_section_front() {
        $section_fronts = array( 'business', 'crave', 'editorial', 'features', 'news', 'sports', 'travel' );
        // Check if the current item is in the section fronts array
        if ( is_page( $section_fronts ) ) {
            return true;
        }
        return false;
    }

        /**
     * Function to determine the pagetype key value
     * 
     * @param $value    The value you wish to get the mapping for
     */
    public function page_type_targeting_map( $value ) {
        // Define the mapping of category/tag to buck for O(1) lookup
        $mapping = $this->page_type_mapping;

        if ( !empty( $mapping[$value] ) ) {
            return $mapping[$value];
        }

        return false;
    }


}