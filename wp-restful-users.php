<?php
if(in_array("wp-restful/wp-restful.php",get_option('active_plugins'))) {
/*
Plugin Name: WP-RESTful Users Plugin
Plugin URI: http://www.joseairosa.com/2010/05/17/wordpress-plugin-parallel-loading-system/
Description: Plugin to add users component to WP-RESTful plugin
Author: Jos&eacute; P. Airosa
Version: 0.1
Author URI: http://www.joseairosa.com/

Copyright 2010  Jos� P. Airosa  (email : me@joseairosa.com)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License, version 2, as 
published by the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

@session_start();

global $wpdb;

//========================================
// Load WP-RESTful functions
//========================================
require_once WP_PLUGIN_DIR."/wp-restful/wp-restful.php";

//========================================
// Plugin Settings
//========================================
define("WPR_USERS_PLUGIN_DB_TABLE",$wpdb->prefix . "wpr_users_plugin");
define("WPR_USERS_PLUGIN_DB_VERISON","1.0.1");

//========================================
// Load Widget
//========================================
require_once WP_PLUGIN_DIR."/wp-restful-users-plugin/wp-restful-users-widget.php";

//========================================
// Install / Uninstall Plugin
//========================================
function wpr_users_install() {
	global $wpdb;
	
	$db_installed_version = get_option('wpr_users_plugin_db_version');
	// Update database in case this is an update
	if($db_installed_version != WPR_USERS_PLUGIN_DB_VERISON) {
	
		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		
		$sql = "CREATE TABLE ".WPR_USERS_PLUGIN_DB_TABLE." (
			`session_id` varchar(32) NOT NULL,
			`microtime` int(12) NOT NULL,
			`user_id` int(8) NOT NULL
		)";
		
		dbDelta($sql);
		
		update_option("wpr_users_plugin_db_version", WPR_USERS_PLUGIN_DB_VERISON);
	}
	
	$wpr_plugins = get_option("wpr_plugins");
	if(!is_array($wpr_plugins))
		$wpr_plugins = array(); 
	// Add our plugin as active
	$wpr_plugins['users'] = "wp-restful-users-plugin";
	update_option("wpr_plugins",$wpr_plugins);
}
function wpr_users_uninstall() {
	$wpr_plugins = get_option("wpr_plugins");
	if(!is_array($wpr_plugins))
		$wpr_plugins = array(); 
	// Remove this plugin as active
	$wpr_active_plugins = array_diff($wpr_plugins,array("wp-restful-users-plugin"));
	update_option("wpr_plugins",$wpr_active_plugins);
}

//========================================
// Set allowed fields for User requests
//========================================
function wpr_users_fields() {
	return array('Users' => array(
		'ID' => 'User ID',
		'display_name' => 'Display Name',
		'first_name' => 'First Name',
		'last_name' => 'Last Name',
		'user_login' => 'Username',
		'user_email' => 'Email'
	));
}

//========================================
// Overwrite pluggable function wp_get_current_user
//========================================
if ( !function_exists('wp_get_current_user') ) :
function wp_get_current_user() {
	global $current_user,$user_ID,$user_login, $userdata, $user_level, $user_email, $user_url, $user_pass_md5, $user_identity;
	
	if(isset($_SESSION['user_data'])) {
		$current_user = new WP_User($_SESSION['user_data']->ID);
		$user_ID = $_SESSION['user_data']->ID;
		$userdata = $_SESSION['user_data'];
		$user_login	= $_SESSION['user_data']->user_login;
		$user_level	= (int) isset($_SESSION['user_data']->user_level) ? $_SESSION['user_data']->user_level : 0;
		$user_email	= $_SESSION['user_data']->user_email;
		$user_url	= $_SESSION['user_data']->user_url;
		$user_pass_md5	= md5($_SESSION['user_data']->user_pass);
		$user_identity	= $_SESSION['user_data']->display_name;
	} else
		get_currentuserinfo();

	return $current_user;
}
endif;

//========================================
// Overwrite pluggable function get_userdata
//========================================
if ( !function_exists('get_userdata') ) :
function get_userdata( $user_id ) {
	global $wpdb;
	
	$user_id = absint($user_id);
	if ( $user_id == 0 )
		return false;
	if(isset($_SESSION['user_data']) && $user_id == $_SESSION['user_data']->ID) {
		$user = $_SESSION['user_data'];
	} else {
		$user = wp_cache_get($user_id, 'users');

		if ( $user )
			return $user;
	
		if ( !$user = $wpdb->get_row($wpdb->prepare("SELECT * FROM $wpdb->users WHERE ID = %d LIMIT 1", $user_id)) )
			return false;
	
		_fill_user($user);

	}
	return $user;
}
endif;

//========================================
// Make sure our session is cleared when we logout
//========================================
function wpr_user_logout() {
	session_destroy();
	session_unset();
}

//========================================
// Filter comments. WordPress 3.0 specific
//========================================
function wpr_filter_comments() {
	global $current_user;
	if(0 != $current_user->ID)
		$user_identity = $current_user->display_name;
	if(isset($_SESSION['user_data'])) {
		return '<p class="logged-in-as">' . sprintf( __( 'Logged in as %1$s. <a href="%2$s" title="Log out of this account">Log out?</a>' ), $user_identity, wp_logout_url( apply_filters( 'the_permalink', get_permalink( $post_id ) ) ) ) . '</p>';
	} else {
		return '<p class="logged-in-as">' . sprintf( __( 'Logged in as <a href="%1$s">%2$s</a>. <a href="%3$s" title="Log out of this account">Log out?</a>' ), admin_url( 'profile.php' ), $user_identity, wp_logout_url( apply_filters( 'the_permalink', get_permalink( $post_id ) ) ) ) . '</p>';
	}
	
}

//========================================
// WP-RESTful hook to register this plugin
//========================================
wpr_add_plugin('wpr_users_fields');

//========================================
// Set action hooks
//========================================
add_action('wp_logout',"wpr_user_logout");
register_activation_hook(WP_PLUGIN_DIR.'/wp-restful-users-plugin/wp-restful-users.php', 'wpr_users_install');
register_deactivation_hook(WP_PLUGIN_DIR.'/wp-restful-users-plugin/wp-restful-users.php', 'wpr_users_uninstall');

//========================================
// Set filter hooks
//========================================
add_filter('comment_form_logged_in',"wpr_filter_comments");

}
?>