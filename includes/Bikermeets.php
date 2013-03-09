<?php

/*
 * Copyright 2013 David Keen <david@davidkeen.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

require_once 'Venue.php';

class Bikermeets
{

    // Default values for all plugin options.
    // To add a new option just add it to this array.
    private $defaultOptions = array(
        'radius' => 35,
        'limit' => 5);
    private $options;

    public function __construct() {

        // Set up the options array
        $this->options = get_option('bikermeets_options');
        if (!is_array($this->options)) {

            // We don't have any options set yet.
            $this->options = $this->defaultOptions;

            // Save them to the DB.
            update_option('bikermeets_options', $this->options);
        } else if (count(array_diff_key($this->defaultOptions, $this->options)) > 0) {

            // The option was set but we don't have all the option values.
            foreach ($this->defaultOptions as $key => $val) {
                if (!isset($this->options[$key]) ) {
                    $this->options[$key] = $this->defaultOptions[$key];
                }
            }

            // Save them to the DB.
            update_option('bikermeets_options', $this->options);
        }
    }

    /**
     * The wp_enqueue_scripts action callback.
     * This is the hook to use when enqueuing items that are meant to appear on the front end.
     * Despite the name, it is used for enqueuing both scripts and styles.
     */
    public function wp_enqueue_scripts() {

        // Styles
        wp_register_style('bikermeets-css', plugins_url('css/bikermeets.css', dirname(__FILE__)));
        wp_enqueue_style('bikermeets-css');
    }

    /**
     * The [bikermeets] shortcode handler.
     *
     * This shortcode inserts a list of nearby meets.
     * The 'radius' parameter should be used to give the search radius (default 35).
     * The 'limit' parameter should be used to limit the number of results returned (default 5).
     * Eg: [bikermeets radius=35 limit=5]
     *
     * @param string $atts an associative array of attributes.
     * @return string the shortcode output to be inserted into the post body in place of the shortcode itself.
     */
    public function bikermeets_shortcode($atts) {

        // Extract the shortcode arguments into local variables named for the attribute keys (setting defaults as required)
        $defaults = array(
            'radius' => $this->options['radius'],
            'limit' => $this->options['limit']);
        extract(shortcode_atts($defaults, $atts));

        // Create a div to show the links.
        $ret = '<div id="bikermeets">&#160;</div>';

        // Get the lat/long for this post.
        // First use the WPGeo data
        $latitude = get_post_meta($post->ID, '_wp_geo_latitude', true);
        $longitude = get_post_meta($post->ID, '_wp_geo_longitude', true);

        // TODO: What if we don't have WPGeo? Post properties?
        $ret .= '<ul>';
        foreach ($this->getMeets($latitude, $longitude, $this->options['radius'], $this->options['limit']) as $meet) {
            $ret .= '<li>' . $meet->name . '</li>';
        }
        $ret .= '</ul>';

        return $ret;
    }

    /**
     * admin_init action callback.
     */
    public function admin_init() {

        // Register a setting and its sanitization callback.
        // Parameters are:
        // $option_group - A settings group name. Must exist prior to the register_setting call. (settings_fields() call)
        // $option_name - The name of an option to sanitize and save.
        // $sanitize_callback - A callback function that sanitizes the option's value.
        register_setting('bikermeets-options', 'bikermeets_options', array($this, 'validate_options'));

        // Add the 'General Settings' section to the options page.
        // Parameters are:
        // $id - String for use in the 'id' attribute of tags.
        // $title - Title of the section.
        // $callback - Function that fills the section with the desired content. The function should echo its output.
        // $page - The type of settings page on which to show the section (general, reading, writing, media etc.)
        add_settings_section('general', 'General Settings', array($this, 'general_section_content'), 'bikermeets');


        // Register the options
        // Parameters are:
        // $id - String for use in the 'id' attribute of tags.
        // $title - Title of the field.
        // $callback - Function that fills the field with the desired inputs as part of the larger form.
        //             Name and id of the input should match the $id given to this function. The function should echo its output.
        // $page - The type of settings page on which to show the field (general, reading, writing, ...).
        // $section - The section of the settings page in which to show the box (default or a section you added with add_settings_section,
        //            look at the page in the source to see what the existing ones are.)
        // $args - Additional arguments
        add_settings_field('radius', 'Search radius (in miles)', array($this, 'radius_input'), 'bikermeets', 'general');
        add_settings_field('limit', 'Number of results', array($this, 'limit_input'), 'bikermeets', 'general');
    }

    /**
     * Filter callback to add a link to the plugin's settings.
     *
     * @param $links
     * @return array
     */
    public function add_settings_link($links) {
        $settings_link = '<a href="options-general.php?page=bikermeets">' . __("Settings", "Nearby Bikermeets") . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }

    /**
     * admin_menu action callback.
     */
    public function admin_menu() {
        add_options_page('Nearby Bikermeets Options', 'Bikermeets', 'manage_options', 'bikermeets', array($this, 'options_page'));
    }

    /**
     * Creates the plugin options page.
     * See: http://ottopress.com/2009/wordpress-settings-api-tutorial/
     * And: http://codex.wordpress.org/Settings_API
     */
    public function options_page() {

        // Authorised?
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        // Start the settings form.
        echo '
            <div class="wrap">
            <h2>Bikermeets Settings</h2>
            <form method="post" action="options.php">';

        // Display the hidden fields and handle security.
        settings_fields('bikermeets-options');

        // Print out all settings sections.
        do_settings_sections('bikermeets');

        // Finish the settings form.
        echo '
            <input class="button-primary" name="Submit" type="submit" value="Save Changes" />
            </form>
            </div>';
    }

    /**
     * Fills the section with the desired content. The function should echo its output.
     */
    public function general_section_content() {
        // Nothing to see here.
    }

    /**
     * Fills the field with the desired inputs as part of the larger form.
     * Name and id of the input should match the $id given to this function. The function should echo its output.
     *
     * Name value must start with the same as the id used in register_setting.
     */
    public function radius_input() {
        echo "<input id='radius' name='bikermeets_options[radius]' type='text' value='{$this->options['radius']}' />";
    }

    public function limit_input() {
        echo "<input id='limit' name='bikermeets_options[limit]' type='text' value='{$this->options['limit']}' />";
    }

    public function validate_options($input) {

        // TODO: Do we need to list all options here or only those that we want to validate?

        // Validate radius and limit
        if ($input['radius'] < 0) {
            $this->options['radius'] = 0;
        } else {
            $this->options['radius'] = $input['radius'];
        }

        if ($input['limit'] < 0) {
            $this->options['limit'] = 0;
        } else {
            $this->options['limit'] = $input['limit'];
        }

        return $this->options;
    }

    private function getMeets($latitude, $longitude, $radius, $limit) {

        $url = "http://bikermeets.cc/Svc/Venues/-json?lat=$latitude&lon=$longitude&rad=$radius&lim=$limit";

        $ch = curl_init($url);
        $curlOptions = array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => array('Accept: application/json')
        );

        curl_setopt_array($ch, $curlOptions );

        $json = json_decode(curl_exec($ch));

        $meets = array();
        foreach ($json->venues as $venue) {
            $meets[] = new Venue($venue->Id, $venue->Name, 'http://bikermeets.cc/Home/Venue/' . $venue->Id);
        }

        return $meets;
    }
}


