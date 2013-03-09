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

/*
 * Plugin Name: Nearby Biker Meets
 * Plugin URI: http://davidkeen.github.com/nearby-bikermeets/
 * Description: Display a list of nearby meets from http://bikermeets.cc.
 * Version: 1.0
 * Author: David Keen
 * Author URI: http://davidkeen.com
*/

// Constants
define('BIKERMEETS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('BIKERMEETS_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Includes
include_once BIKERMEETS_PLUGIN_DIR . 'includes/Bikermeets.php';

// The main plugin class
$bikermeets = new Bikermeets();

// Actions
add_action('wp_enqueue_scripts', array($bikermeets, 'wp_enqueue_scripts'));
add_action('admin_menu', array($bikermeets, 'admin_menu'));
add_action('admin_init', array($bikermeets, 'admin_init'));

// Filters
add_filter('plugin_action_links_' . BIKERMEETS_PLUGIN_BASENAME, array($bikermeets, 'add_settings_link'));

// Shortcodes
add_shortcode('bikermeets', array($bikermeets, 'bikermeets_shortcode'));
