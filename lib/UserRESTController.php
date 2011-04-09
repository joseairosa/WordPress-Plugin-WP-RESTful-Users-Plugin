<?php
class UserRESTController extends WPAPIRESTController {
	protected function __construct() {}
	
	protected function getUsers() {
		global $wpdb;
		$array = array();
		$users = $wpdb->get_results("SELECT ID FROM ".$wpdb->users." WHERE ID > 0");
		// Check if we only have 1 element
		if(count($users) == 1) {
			return $this->_return($this->getUser($users[0]->ID));
		}
		foreach($users as $user) {
			$array[] = $this->getUser($user->ID);
		}
		return $this->_return($array);
	}
	
	protected function getUser($user = 0) {
		return $this->_return(get_userdata($user));
	}
	
	protected function verify_credentials($userdata) {
		global $current_user,$wpdb;
		wpr_set_defaults($userdata,array('username'=>'','password'=>'','sign_on' => 0,'session_id' => null));
		// Check if we're already signed on
		wp_get_current_user();
		
		if(0 == $current_user->ID) {
			// Check given user credentials
			if ( empty($userdata['username']) || empty($userdata['password']) ) {
				$error = new WP_Error();
				
				if ( empty($$userdata['username']) )
					$error->add('empty_username', __('The username field is empty.'));
		
				if ( empty($userdata['password']) )
					$error->add('empty_password', __('The password field is empty.'));
				
				return $error;
			}
			
			// Check if we provide a session
			if(!is_null($userdata['session_id'])) {
				$sql = "SELECT * FROM ".WPR_USERS_PLUGIN_DB_TABLE." WHERE session_id = ".$userdata['session_id'];
				$session_data = $wpdb->get_row($sql);
				$actual_microtime = microtime(true);
				if(($actual_microtime - $session_data['microtime']) <= (60*60)) {
					return get_userdata($session_data['user_id']);
				} 
			}
			
			$is_authentic = user_pass_ok($userdata['username'],$userdata['password']);
			// If user is authentic
			if($is_authentic) {
				
				$credentials = array('user_login' => $userdata['username'], 'user_password' => $userdata['password'], 'remember' => false);
				$current_user = wp_signon($credentials,false);
				// Send user information to filter system and return
				if($userdata['sign_on']) {
					$session_id = md5(uniqid());
					$sql = "INSERT INTO ".WPR_USERS_PLUGIN_DB_TABLE." (`session_id`,`user_id`,`microtime`) VALUES ('".$session_id."',".$current_user->ID.",".microtime(true).")";
					$wpdb->query($sql);
					$return_array = array('session_id' => $session_id, 'wp_user' => $current_user);
					return $return_array;
				} else
					return $this->_return($current_user);
					
			} else {
				// There was a problem with your credentials
				return new WP_Error('invalid_credentials', __('Invalid credentials'));
			}
		} else {
			// If the user was already signed through the API
			return $this->_return($this->getUser($current_user->ID));
		}
	}
	
	/**
	 * Apply request filter
	 * 
	 * @since 0.1
	 * 
	 * @return (mixed) filtered content
	 */
	private function _return($content) {
		return wpr_filter_content($content,wpr_get_filter("Users"));
	}
}
?>