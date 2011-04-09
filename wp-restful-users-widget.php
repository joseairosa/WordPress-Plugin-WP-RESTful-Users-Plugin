<?php
if(in_array("wp-restful-users-plugin/wp-restful-users.php",get_option('active_plugins'))) {
/*
Copyright 2010  José P. Airosa  (email : me@joseairosa.com)

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
	
//========================================
// Create Network User Login Widget
//========================================
class wpr_widget_users_login {
	
	function activate() {
		// Instructions to run when the plugin is activated
		$data = array( 'return_type' => 'xml', 'max_tags' => 3);
	    if ( ! get_option('wpr_widget_users_login')){
	      add_option('wpr_widget_users_login' , $data);
	    } else {
	      update_option('wpr_widget_users_login' , $data);
	    }
	}
	
	function deactivate() {
		// Instructions to run when the plugin is activated
		delete_option('wpr_widget_users_login');
	}
	
	function control() {
		global $wpdb;
		require_once WPR_PLUGIN_FOLDER_PATH.'lib/OAuthStore.php';
		// Init the database connection
		$store = OAuthStore::instance ( 'MySQL', array ('conn' => $wpdb->dbh ) );
		$servers = $store->listServerTokens();
		
		$data = get_option('wpr_widget_users_login'); ?>
		<p>
			<label>Return Type 
				<select name="wpr_widget_users_login_return_type" id="wpr_widget_users_login_return_type">
					<option value="json" <?php echo ($data ['return_type'] == "json" ? 'selected="selected"' : '' )?>>JSON</option>
					<option value="xml" <?php echo ($data ['return_type'] == "xml" ? 'selected="selected"' : '' )?>>XML</option>
				</select>
			</label>
		</p>
		<p>
			<label>Used API Servers
				<select multiple="multiple" size="5" name="wpr_widget_users_login_server[]" style="height: 100px;overflow: auto;">
					<?php 
						foreach($servers as $server): 
						$server_url = str_replace(array("/api"),array(""),$server['server_uri']);
					?>
						<option <?php echo ((!isset($data ['servers']) || !is_array($data ['servers'])) ? '' : ((in_array($server ['consumer_key'],$data ['servers'])) ? 'selected="selected"' : '' ) )?> value="<?php echo $server ['consumer_key']?>"><?php echo $server_url?></option>
					<?php endforeach;?>
				</select>
			</label>
		</p>
		<?php
		if (isset ( $_POST ['wpr_widget_users_login_return_type'] )) {
			$data ['return_type'] = attribute_escape ( $_POST ['wpr_widget_users_login_return_type'] );
			$data ['servers'] = $_POST ['wpr_widget_users_login_server'];
			update_option ( 'wpr_widget_users_login', $data );
		}
	}
	
	function widget($args) {
		global $wpdb,$wp_query,$wpr,$current_user;
		
		$message = "";
		
		$data = get_option('wpr_widget_users_login');
		
		if(!isset($wp_query->query_vars['request']))
			$wp_query->query_vars['request'] = "";
		
		$show_login = true;
		
		require_once WPR_PLUGIN_FOLDER_PATH.'lib/OAuthStore.php';
		require_once WPR_PLUGIN_FOLDER_PATH.'lib/consumer/WP-API.php';
		require_once WPR_PLUGIN_FOLDER_PATH.'lib/consumer/OAuth.php';
		require_once WPR_PLUGIN_FOLDER_PATH.'lib/jsonwrapper/jsonwrapper.php';
		// Init the database connection
		$store = OAuthStore::instance ( 'MySQL', array ('conn' => $wpdb->dbh ) );
		$server_found = false;
		if(isset($_POST['wpr-users-login-submit']) && !isset($_SESSION['user_data'])) {
		
			$server_uri = $_POST['wpr-users-login-server'];
			
			$servers = $store->listServerTokens();
			
			foreach($servers as $server) {
			
				if($server ['server_uri'] == $server_uri) {
					
					$response = "";
					$to = new WPOAuth ( $server ['consumer_key'], $server ['consumer_secret'], $server ['token'], $server ['token_secret'], $server ['server_uri'] );
					
					if(isset($_SESSION['session_id']))
						$request_array = array ('username' => $_POST['wpr-users-login-username'],'password' => $_POST['wpr-users-login-password'],'sign_on' => 1,'session_id' => $_SESSION['session_id']);
					else
						$request_array = array ('username' => $_POST['wpr-users-login-username'],'password' => $_POST['wpr-users-login-password'],'sign_on' => 1);
						
					if($data ['return_type'] == "json") {
						$response = json_decode($to->OAuthRequest ( $to->TO_API_ROOT.'user/verify_credentials.json', $request_array, 'POST' ));
					} elseif($data ['return_type'] == "xml") {
						//$response = json_decode($to->OAuthRequest ( $to->TO_API_ROOT.'user/verify_credentials.json', $request_array, 'POST' ));
					}
					
					if(is_array($response) || is_object($response)) {
						if(isset($response->errors->invalid_credentials[0])) {
							$message = '<span class="error">'.$response->errors->invalid_credentials[0].'</span>';
						} else {
							$user_data = $response->wp_user->data;
							if(isset($user_data->ID))
								$user_id = $user_data->ID;
							else
								$user_id = 0;
							// Save session
							$_SESSION['session_id'] = $response->session_id;
							$_SESSION['user_id'] = $user_id;
							$_SESSION['user_data'] = $user_data;
							$_SESSION['source'] = str_replace(array("/api"),array(""),$to->TO_API_ROOT);
							$show_login = false;
							wp_get_current_user();
						}
					}
					$server_found = true;
				}
			} 
			if(!$server_found) {
				$message = '<span class="error">Server not found</span>';
			}
		} elseif(isset($_SESSION['user_data']) && is_object($_SESSION['user_data'])) {
			// Set user data
			wp_get_current_user();
			$show_login = false;
		}
		
		if($show_login) {
			if(!in_array($wp_query->query_vars['request'],$wpr['reserved_requests'])) {
				echo $args ['before_widget'];
				echo $args ['before_title'] . 'Network Login' . $args ['after_title'];
				$servers = $store->listServerTokens();
				
				?>
				<div id="wpr-users-login-wrapper">
					<div id="wpr-users-login-message"><?php echo $message?></div>
					<form action="" method="post">
						<p>
						<label>Login with:<br/>
							<select name="wpr-users-login-server">
							<?php foreach($servers as $server) : if(is_array($data ['servers']) && in_array($server ['consumer_key'],$data ['servers'])) :?>
							<?php $server_url = str_replace(array("/api"),array(""),$server['server_uri']); ?>
							<option value="<?php echo $server['server_uri'] ?>"><?php echo $server_url ?></option>
							<?php endif;endforeach; ?>
						</select>
						</label>
						</p>
						
						<p>
						<label>Username:<br/>
							<input type="text" name="wpr-users-login-username" id="wpr-users-login-username" />
						</label>
						</p>
						<p>
						<label>Password:<br/>
							<input type="password" name="wpr-users-login-password" id="wpr-users-login-password" />
						</label>
						</p>
						<p>
							<input type="submit" name="wpr-users-login-submit" id="wpr-users-login-submit" />
						</p>
					</form>
				</div>
				<?php
				echo $args ['after_widget'];
			}
		} else {
			echo $args ['before_widget'];
			echo $args ['before_title'] . 'Network Login' . $args ['after_title'];
			echo "<p>You're logged in as ".$current_user->display_name." from ".$_SESSION['source']."</p>";
			echo "<p><a href=\"".wp_logout_url(get_permalink())."\">Logout</a></p>";
			echo $args ['after_widget'];
		}
	}
	
	function register() {
		register_sidebar_widget ( 'Network Login', array ('wpr_widget_users_login', 'widget' ) );
		register_widget_control ( 'Network Login', array ('wpr_widget_users_login', 'control' ) );
	}
}

//========================================
// Tell WordPress to load this widget
//========================================
add_action ( "widgets_init", array ('wpr_widget_users_login', 'register' ) );
register_activation_hook ( __FILE__, array ('wpr_widget_users_login', 'activate' ) );
register_deactivation_hook ( __FILE__, array ('wpr_widget_users_login', 'deactivate' ) );

}
?>