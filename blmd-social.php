<?php
/*
Plugin Name: BLMD Social
Plugin URI: https://github.com/blmd/blmd-social
Description: Social shares
Author: blmd
Author URI: https://github.com/blmd
Version: 0.9

GitHub Plugin URI: https://github.com/blmd/blmd-social

*/

!defined( 'ABSPATH' ) && die;
define( 'BLMD_SOCIAL_VERSION', '0.9' );
define( 'BLMD_SOCIAL_URL', plugin_dir_url( __FILE__ ) );
define( 'BLMD_SOCIAL_DIR', plugin_dir_path( __FILE__ ) );
define( 'BLMD_SOCIAL_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Shareaholic's Social Share Counts Library
 * https://github.com/shareaholic/social-share-counts
 * @version 1.0.0.0
 */
abstract class ShareaholicShareCount {

  protected $url;
  protected $services;
  protected $options;
  public $raw_response;

  public function __construct($url, $services, $options) {
    // encode the url if needed
    if (!$this->is_url_encoded($url)) {
      $url = urlencode($url);
    }
    $this->url = $url;
    $this->services = $services;
    $this->options = $options;
    $this->raw_response = array();
  }

  public static function get_services_config() {
    return array(
      'facebook' => array(
        'url' => 'https://graph.facebook.com/?id=%s',
        'method' => 'GET',
        'timeout' => 3,  // in number of seconds
        'callback' => 'facebook_count_callback',
      ),
      'twitter' => array(
        // 'url' => 'https://cdn.api.twitter.com/1/urls/count.json?url=%s',
        'url' => get_option('blmd_social_twitter_counts_url', 'http://localhost.invalid/?url=').'%s',
        'method' => 'GET',
        'timeout' => 3,
        'callback' => 'twitter_count_callback',
      ),
      'linkedin' => array(
        'url' => 'https://www.linkedin.com/countserv/count/share?format=json&url=%s',
        'method' => 'GET',
        'timeout' => 3,
        'callback' => 'linkedin_count_callback',
      ),
      'google_plus' => array(
        'url' => 'https://clients6.google.com/rpc?key=AIzaSyCKSbrvQasunBoV16zDH9R33D88CeLr9gQ',
        'method' => 'POST',
        'timeout' => 2,
        'headers' => array('Content-Type' => 'application/json'),
        'body' => NULL,
        'prepare' => 'google_plus_prepare_request',
        'callback' => 'google_plus_count_callback',
      ),
      'delicious' => array(
        'url' => 'http://feeds.delicious.com/v2/json/urlinfo/data?url=%s',
        'method' => 'GET',
        'timeout' => 3,
        'callback' => 'delicious_count_callback',
      ),
      'pinterest' => array(
        'url' => 'https://api.pinterest.com/v1/urls/count.json?url=%s&callback=f',
        'method' => 'GET',
        'timeout' => 3,
        'callback' => 'pinterest_count_callback',
      ),
      'buffer' => array(
        'url' => 'https://api.bufferapp.com/1/links/shares.json?url=%s',
        'method' => 'GET',
        'timeout' => 1,
        'callback' => 'buffer_count_callback',
      ),
      'stumbleupon' => array(
        'url' => 'https://www.stumbleupon.com/services/1.01/badge.getinfo?url=%s',
        'method' => 'GET',
        'timeout' => 1,
        'callback' => 'stumbleupon_count_callback',
      ),
      'reddit' => array(
        'url' => 'https://buttons.reddit.com/button_info.json?url=%s',
        'method' => 'GET',
        'timeout' => 1,
        'callback' => 'reddit_count_callback',
      ),
      'vk' => array(
        'url' => 'http://vk.com/share.php?act=count&url=%s',
        'method' => 'GET',
        'timeout' => 1,
        'callback' => 'vk_count_callback',
      ),
      'odnoklassniki' => array(
        'url' => 'http://ok.ru/dk?st.cmd=extLike&uid=odklcnt0&ref=%s',
        'method' => 'GET',
        'timeout' => 1,
        'callback' => 'odnoklassniki_count_callback',
      ),
      'fancy' => array(
        'url' => 'http://fancy.com/fancyit/count?ItemURL=%s',
        'method' => 'GET',
        'timeout' => 1,
        'callback' => 'fancy_count_callback',
      ),
      'yummly' => array(
        'url' => 'http://www.yummly.com/services/yum-count?url=%s',
        'method' => 'GET',
        'timeout' => 1,
        'callback' => 'yummly_count_callback',
      ),
    );
  }

  /**
   * Check if the url is encoded
   *
   * The check is very simple and will fail if the url is encoded
   * more than once because the check only decodes once
   *
   * @param string $url the url to check if it is encoded
   * @return boolean true if the url is encoded and false otherwise
   */
  public function is_url_encoded($url) {
    $decoded = urldecode($url);
    if (strcmp($url, $decoded) === 0) {
      return false;
    }
    return true;
  }

  /**
   * Check if calling the service returned any type of error
   *
   * @param object $response A response object
   * @return bool true if it has an error or false if it does not
   */
  public function has_http_error($response) {
    if(!$response || !isset($response['response']['code']) || !preg_match('/20*/', $response['response']['code']) || !isset($response['body'])) {
      return true;
    }
    return false;
  }

  /**
   * Get the client's ip address
   *
   * NOTE: this function does not care if the IP is spoofed. This is used
   * only by the google plus count API to separate server side calls in order
   * to prevent usage limits. Under normal conditions, a request from a user's
   * browser to this API should not involve any spoofing.
   *
   * @return {Mixed} An IP address as string or false otherwise
   */
  public function get_client_ip() {
    $ip = NULL;

    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
     //check for ip from share internet
     $ip = $_SERVER['HTTP_CLIENT_IP'];
    }
    elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
     // Check for the Proxy User
     $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    }
    else {
     $ip = $_SERVER['REMOTE_ADDR'];
    }

    return $ip;
  }


  /**
   * Callback function for facebook count API
   * Gets the facebook counts from response
   *
   * @param Array $response The response from calling the API
   * @return mixed The counts from the API or false if error
   */
  public function facebook_count_callback($response) {
    if($this->has_http_error($response)) {
      return false;
    }
    $body = json_decode($response['body'], true);
    return isset($body['shares']) ? intval($body['shares']) : false;
  }


  /**
   * Callback function for twitter count API
   * Gets the twitter counts from response
   *
   * @param Array $response The response from calling the API
   * @return mixed The counts from the API or false if error
   */
  public function twitter_count_callback($response) {
    if($this->has_http_error($response)) {
      return false;
    }
    $body = json_decode($response['body'], true);
    return isset($body['count']) ? intval($body['count']) : false;
  }


  /**
   * Callback function for linkedin count API
   * Gets the linkedin counts from response
   *
   * @param Array $response The response from calling the API
   * @return mixed The counts from the API or false if error
   */
  public function linkedin_count_callback($response) {
    if($this->has_http_error($response)) {
      return false;
    }
    $body = json_decode($response['body'], true);
    return isset($body['count']) ? intval($body['count']) : false;
  }


  /**
   * A preprocess function to be called necessary to prepare
   * the request to the service.
   *
   * One may customize the headers or body to their liking
   * before the request is sent. The customization should
   * update the services config where it will be read by
   * the get_counts() function
   *
   * @param $url The url needed by google_plus to be passed in to the body
   * @param $config The services configuration object to be updated
   */
  public function google_plus_prepare_request($url, &$config) {
    if ($this->is_url_encoded($url)) {
      $url = urldecode($url);
    }
    $post_fields = array(
      array(
        'method' => 'pos.plusones.get',
        'id' => 'p',
        'params' => array(
          'nolog' => true,
          'id' => $url,
          'source' => 'widget',
          'userId' => '@viewer',
          'groupId' => '@self',
        ),
        'jsonrpc' => '2.0',
        'key' => 'p',
        'apiVersion' => 'v1',
      )
    );

    $ip = $this->get_client_ip();
    if ($ip && !empty($ip)) {
      $post_fields[0]['params']['userIp'] = $ip;
    }

    $config['google_plus']['body'] = $post_fields;
  }


  /**
   * Callback function for google plus count API
   * Gets the google plus counts from response
   *
   * @param Array $response The response from calling the API
   * @return mixed The counts from the API or false if error
   */
  public function google_plus_count_callback($response) {
    if($this->has_http_error($response)) {
       return false;
    }
    $body = json_decode($response['body'], true);
    // special case: do not return false if the count is not set because the api can return without counts
    return isset($body[0]['result']['metadata']['globalCounts']['count']) ? intval($body[0]['result']['metadata']['globalCounts']['count']) : 0;
  }


  /**
   * Callback function for delicious count API
   * Gets the delicious counts from response
   *
   * @param Array $response The response from calling the API
   * @return mixed The counts from the API or false if error
   */
  public function delicious_count_callback($response) {
    if($this->has_http_error($response)) {
      return false;
    }
    $body = json_decode($response['body'], true);
    // special case: do not return false if the count is set because the api can return without total posts
    return isset($body[0]['total_posts']) ? intval($body[0]['total_posts']) : 0;
  }


  /**
   * Callback function for pinterest count API
   * Gets the pinterest counts from response
   *
   * @param Array $response The response from calling the API
   * @return mixed The counts from the API or false if error
   */
  public function pinterest_count_callback($response) {
    if($this->has_http_error($response)) {
      return false;
    }
    $response['body'] = substr($response['body'], 2, strlen($response['body']) - 3);
    $body = json_decode($response['body'], true);
    return isset($body['count']) ? intval($body['count']) : false;
  }


  /**
   * Callback function for buffer count API
   * Gets the buffer share counts from response
   *
   * @param Array $response The response from calling the API
   * @return mixed The counts from the API or false if error
   */
  public function buffer_count_callback($response) {
    if($this->has_http_error($response)) {
      return false;
    }
    $body = json_decode($response['body'], true);
    return isset($body['shares']) ? intval($body['shares']) : false;
  }


  /**
   * Callback function for stumbleupon count API
   * Gets the stumbleupon counts from response
   *
   * @param Array $response The response from calling the API
   * @return mixed The counts from the API or false if error
   */
  public function stumbleupon_count_callback($response) {
    if($this->has_http_error($response)) {
      return false;
    }
    $body = json_decode($response['body'], true);
    // special case: do not return false if views is not set because the api can return it not set
    return isset($body['result']['views']) ? intval($body['result']['views']) : 0;
  }


  /**
   * Callback function for reddit count API
   * Gets the reddit counts from response
   *
   * @param Array $response The response from calling the API
   * @return mixed The counts from the API or false if error
   */
  public function reddit_count_callback($response) {
    if($this->has_http_error($response)) {
      return false;
    }
    $body = json_decode($response['body'], true);
    // special case: do not return false if the ups is not set because the api can return it not set
    return isset($body['data']['children'][0]['data']['ups']) ? intval($body['data']['children'][0]['data']['ups']) : 0;
  }


  /**
   * Callback function for vk count API
   * Gets the vk counts from response
   *
   * @param Array $response The response from calling the API
   * @return mixed The counts from the API or false if error
   */
  public function vk_count_callback($response) {
    if($this->has_http_error($response)) {
      return false;
    }

    // This API does not return JSON. Just plain text JS. Example:
    // 'VK.Share.count(0, 3779);'
    // From documentation, need to just grab the 2nd param: http://vk.com/developers.php?oid=-17680044&p=Share
    $matches = array();
    preg_match('/^VK\.Share\.count\(\d, (\d+)\);$/i', $response['body'], $matches);
    return isset($matches[1]) ? intval($matches[1]) : false;
  }


  /**
   * Callback function for odnoklassniki count API
   * Gets the odnoklassniki counts from response
   *
   * @param Array $response The response from calling the API
   * @return mixed The counts from the API or false if error
   */
  public function odnoklassniki_count_callback($response) {
    if($this->has_http_error($response)) {
      return false;
    }

    // Another weird API. Similar to vk, extract the 2nd param from the response:
    // 'ODKL.updateCount('odklcnt0','14198');'
    $matches = array();
    preg_match('/^ODKL\.updateCount\(\'odklcnt0\',\'(\d+)\'\);$/i', $response['body'], $matches);
    return isset($matches[1]) ? intval($matches[1]) : false;
  }

  /**
   * Callback function for Fancy count API
   * Gets the Fancy counts from response
   *
   * @param Array $response The response from calling the API
   * @return mixed The counts from the API or false if error
   */
  public function fancy_count_callback($response) {
    if($this->has_http_error($response)) {
      return false;
    }

    // Fancy always provides a JS callback like this in the response:
    // '__FIB.collectCount({"url": "http://www.google.com", "count": 26, "thing_url": "http://fancy.com/things/263001623/Google%27s-Jim-Henson-75th-Anniversary-logo", "showcount": 1});'
    // strip out the callback and parse the JSON from there
    $response['body'] = str_replace('__FIB.collectCount(', '', $response['body']);
    $response['body'] = substr($response['body'], 0, strlen($response['body']) - 2);

    $body = json_decode($response['body'], true);
    return isset($body['count']) ? intval($body['count']) : false;
  }

  /**
   * Callback function for Yummly count API
   * Gets the Yummly counts from response
   *
   * @param Array $response The response from calling the API
   * @return mixed The counts from the API or false if error
   */
  public function yummly_count_callback($response) {
    if($this->has_http_error($response)) {
      return false;
    }
    $body = json_decode($response['body'], true);
    return isset($body['count']) ? intval($body['count']) : false;
  }
  
  /**
   * The abstract function to be implemented by its children
   * This function should get all the counts for the
   * supported services
   *
   * It should return an associative array with the services as
   * the keys and the counts as the value.
   *
   * Example:
   * array('facebook' => 12, 'google_plus' => 0, 'twitter' => 14, ...);
   *
   * @return Array an associative array of service => counts
   */
  public abstract function get_counts();

};

class ShareaholicCurlMultiShareCount extends ShareaholicShareCount {

  /**
   * This function should get all the counts for the
   * supported services
   *
   * It should return an associative array with the services as
   * the keys and the counts as the value.
   *
   * Example:
   * array('facebook' => 12, 'google_plus' => 0, 'twitter' => 14, ...);
   *
   * @return Array an associative array of service => counts
   */
  public function get_counts() {
    $services_length = count($this->services);
    $config = self::get_services_config();
    $response = array();
    $response['status'] = 200;

    // array of curl handles
    $curl_handles = array();

    // multi handle
    $multi_handle = curl_multi_init();

    for($i = 0; $i < $services_length; $i++) {
      $service = $this->services[$i];

      if(!isset($config[$service])) {
        continue;
      }

      if(isset($config[$service]['prepare'])) {
        $this->{$config[$service]['prepare']}($this->url, $config);
      }

      // Create the curl handle
      $curl_handles[$service] = curl_init();

      // set the curl options to make the request
      $this->curl_setopts($curl_handles[$service], $config, $service);

      // add the handle to curl_multi_handle
      curl_multi_add_handle($multi_handle, $curl_handles[$service]);
    }

    // Run curl_multi only if there are some actual curl handles
    if(count($curl_handles) > 0) {
      // While we're still active, execute curl
      $running = NULL;
      do {
        $mrc = curl_multi_exec($multi_handle, $running);
      } while ($mrc == CURLM_CALL_MULTI_PERFORM);

      while ($running && $mrc == CURLM_OK) {
        // Wait for activity on any curl-connection
        if (curl_multi_select($multi_handle) == -1) {
          usleep(1);
        }

        // Continue to exec until curl is ready to
        // give us more data
        do {
          $mrc = curl_multi_exec($multi_handle, $running);
        } while ($mrc == CURLM_CALL_MULTI_PERFORM);
      }

      // handle the responses
      foreach($curl_handles as $service => $handle) {
        if(curl_errno($handle)) {
          $response['status'] = 500;
        }
        $result = array(
          'body' => curl_multi_getcontent($handle),
          'response' => array(
            'code' => curl_getinfo($handle, CURLINFO_HTTP_CODE)
          ),
        );
        $callback = $config[$service]['callback'];
        $counts = $this->{$callback}($result);
        if(is_numeric($counts)) {
          $response['data'][$service] = $counts;
        }
        $this->raw_response[$service] = $result;
        curl_multi_remove_handle($multi_handle, $handle);
        curl_close($handle);
      }
      curl_multi_close($multi_handle);
    }
    return $response;
  }

  private function curl_setopts($curl_handle, $config, $service) {
    // set the url to make the curl request
    curl_setopt($curl_handle, CURLOPT_URL, str_replace('%s', $this->url, $config[$service]['url']));
    $timeout = isset($this->options['timeout']) ? $this->options['timeout'] : 6;

    // other necessary settings:
    // CURLOPT_HEADER means include header in output, which we do not want
    // CURLOPT_RETURNTRANSER means return output as string or not
    curl_setopt_array($curl_handle, array(
      CURLOPT_HEADER => 0,
      CURLOPT_RETURNTRANSFER => 1,
      CURLOPT_TIMEOUT => $timeout,
      CURLOPT_SSL_VERIFYPEER => false,
      CURLOPT_SSL_VERIFYHOST => false,
    ));

    // set the http method: default is GET
    if($config[$service]['method'] === 'POST') {
      curl_setopt($curl_handle, CURLOPT_POST, 1);
    }

    // set the body and headers
    $headers = isset($config[$service]['headers']) ? $config[$service]['headers'] : array();
    $body = isset($config[$service]['body']) ? $config[$service]['body'] : NULL;

    if(isset($body)) {
      if(isset($headers['Content-Type']) && $headers['Content-Type'] === 'application/json') {
        $data_string = json_encode($body);

        curl_setopt($curl_handle, CURLOPT_HTTPHEADER, array(
          'Content-Type: application/json',
          'Content-Length: ' . strlen($data_string))
        );

        curl_setopt($curl_handle, CURLOPT_POSTFIELDS, $data_string);
      }
    }

    // set the useragent
    $useragent = isset($config[$service]['User-Agent']) ? $config[$service]['User-Agent'] : 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.8; rv:24.0) Gecko/20100101 Firefox/24.0';
    curl_setopt($curl_handle, CURLOPT_USERAGENT, $useragent);
  }


};

class BLMD_Social {

	public static function factory() {
		static $instance = null;
		if ( ! ( $instance instanceof self ) ) {
			$instance = new self;
			$instance->setup_actions();
			$instance->setup_filters();
		}
		return $instance;
	}

	protected function setup_actions() {
		add_action( 'admin_notices', array( $this, 'admin_notices' ) );
		add_action( 'cmb2_admin_init', array( $this, 'cmb2_admin_init' ) );
		add_action( 'blmd_social_buttons', array( $this, 'populate_button_set' ));
		add_action( 'blmd_social_update_counts', array( $this, 'blmd_social_update_counts' ), 10, 3 );
		add_action( 'plugins_loaded', function() {
			if ( ! wp_next_scheduled( 'blmd_social_update_counts' ) ) {
				wp_schedule_event( time(), 'every5minutes', 'blmd_social_update_counts' );
				update_option( 'blmd_social_last_cron', 'setting counts '.date('Y-m-d H:i:s') );
			}

			if ( ! wp_next_scheduled( 'blmd_social_update_counts', array('archive') ) ) {
				wp_schedule_event( time(), 'daily', 'blmd_social_update_counts', array('archive') );
				update_option( 'blmd_social_last_cron', 'setting archived '.date('Y-m-d H:i:s') );
			}
			// wp_clear_scheduled_hook( 'blmd_social_update_counts' );
			// wp_schedule_single_event( time() + 5, 'blmd_social_update_counts', array( 5, 10, 15 ) );

		} );
	}
	
	protected function setup_filters() {
		// add_filter( 'cmb2_meta_box_url', array( $this, 'cmb2_meta_box_url' ));
		add_filter( 'cron_schedules', array( $this, 'cron_schedules' ) );
		add_filter('blmd_social_classes_button', function($classes, $network=null) {
			if ($network == 'twitter') { $classes .= ' native'; }
			return $classes;
		}, 10, 2);
		
	}	
	public function admin_notices() {
		if ( !get_option( 'blmd_social_twitter_counts_url' ) ) {
			echo '<div class="error">';
			echo '<p><strong>BLMD Social error.</strong> For Twitter counts to work, set the option <em>blmd_social_twitter_counts_url</em>.</p>';
			echo '</div>';
		}
	}

	// public function cmb2_meta_box_url( $url ) {
	// 	$pd = ( defined( 'WP_PLUGIN_DIR' ) ? WP_PLUGIN_DIR : WP_CONTENT_DIR . '/plugins' ) . '/cmb2/';
	// 	return is_dir( $pd ) ? WP_PLUGIN_URL . '/cmb2/' : $url;
	// }
	
	public function cmb2_admin_init() {
		$prefix = '_blmd_social_';

		$cmb_box_side = new_cmb2_box( array(
				'id'           => $prefix . 'metabox_side',
				'title'        => __( 'Featured Image (Pinterest)', 'cmb2' ),
				'object_types' => array( 'post', 'page' ),
				'context'      => 'side',
				'priority'     => 'low',
				'show_names'   => false, // Show field names on the left
				// 'closed'       => true,
			) );
		$cmb_box_side->add_field( array(
				'name' => __( 'Pinterest Image', 'cmb2' ),
				'id'   => apply_filters('blmd_social_image_vertical', $prefix.'image_vertical'),
				'type' => 'file',
				'options' => array( 'url' => false, ),
				// 'description' => 'Best size 736 x 1104px',
			) );


		// $cmb_box = new_cmb2_box( array(
		// 		'id'           => $prefix . 'metabox',
		// 		'title'        => __( 'BLMD Social', 'cmb2' ),
		// 		'object_types' => array( 'post', 'page' ),
		// 		'context'      => 'normal',
		// 		'priority'     => 'high',
		// 		'show_names'   => true, // Show field names on the left
		// 		// 'closed'       => true,
		// 	) );

	}
	
	public function get_options() {
		static $options;
		if ( !isset( $options ) ) {
			$options = array_merge( array(
					'networks'     => array('twitter','facebook','pinterest','linkedin','googleplus','stumbleupon'),
					// 'networks'     => array(),
				), get_option( 'blmd_social', array() )
			);
		}
		return $options;
	}

	public function get_option( $name, $default=null ) {
		static $options;
		if ( !isset( $options ) ) { $options = $this->get_options(); }
		return isset( $options[$name] ) ? $options[$name] : $default;
	}
	
	public function populate_button_set( $args ) {
		$defaults   = array(
			'ID'       => null,
			'position' => null,
			'networks' => array(),
			'css_id'   => null,
		);
		$args = wp_parse_args( $args, $defaults );
		global $post;
		$_post = $args['ID'] ? get_post( (int)$args['ID'] ) : $post;
		if ( empty( $_post->ID ) ) { return; }

		$prefix              = apply_filters( 'blmd_social_class_prefix', 'blmd' );
		$prefix_e            = esc_attr( $prefix );
		// $id_main      = apply_filters( 'blmd_social_id_main', '' );
		// $id_main_e    = esc_attr( $id_main );

		$class_main          = "{$prefix}-social";
		// $class_main_e        = esc_attr( $class_main );
		$classes_main        = $args['position'] ? "{$prefix}-{$args['position']}" : "";
		$classes_main       .= apply_filters( 'blmd_social_classes_main', '' );
		// $classes_main_e      = esc_attr( $classes_main );
		$class_button        = "{$prefix}-button";
		// $class_button_e      = esc_attr( $class_button );
		$class_total_count   = "{$prefix}-total-count";
		// $class_total_count_e = esc_attr( $class_total_count );
		
		$shares_arr = get_post_meta( $_post->ID, '_blmd_social_shares', true );
		// lastupdate: 137948238423,
		// networks: ['twitter','facebook','pinterest'],
		// counts: {twitter: 133, facebook: 34}
		// counts_archived: {twitter: 999,}
		$buttons         = array();
		$networks        = apply_filters('blmd_social_display_networks', $this->get_option('networks'));
		$counts          = array();
		$counts_archived = array();

		$total_cnt = 0;
		$count_min = 1000;
		$image     = null;
		$pin_image = null;
		
		if ( current_theme_supports( 'post-thumbnails' ) ) {
			$image = wp_get_attachment_image_src( get_post_thumbnail_id( $_post->ID ), 'full' );
			$image = is_array( $image ) ? $image[0] : null;
			$pin_image = wp_get_attachment_image_src( get_post_thumbnail_id( $_post->ID ), 'post-feature-vertical' );
			// make sure we didnt just get back original, pin_image[3] == false if so
			$pin_image = is_array( $pin_image ) && $pin_image[3]!==false ? $pin_image[0] : null;
		}

		if ( class_exists( 'WPSEO_Meta' ) && $_ = WPSEO_Meta::get_value( 'opengraph-image' ) ) {
			$image = $_;
		}

		if ( $_ = get_post_meta( $_post->ID, '_blmd_social_image_vertical', true ) ) {
			$pin_image = $_;
		}
    
		
		if ( !$image ) {
			$content = $_post->post_content;
			// $content = apply_filters('the_content', $_post->post_content);
			// $content = str_replace(']]>', ']]&gt;', $content);
 
			$output = preg_match_all('/<img[^>]+src=[\'"]([^\'"]+)[\'"][^>]*>/i', $content, $matches);  
		
			if ( $output ) {
				$image = $matches[1][0];
			}
		}
		
		$pin_image = $pin_image ? $pin_image : $image;

		$via = '';
		if ( class_exists( 'WPSEO_Options' ) ) {
			$wpseo_options = WPSEO_Options::get_all();
			$_ = $wpseo_options['twitter_site'];
			if ( $_ && preg_match( '`([A-Za-z0-9_]{1,25})$`', $_, $matches ) ) {
				$via = $matches[1];
				$via = str_replace( '@', '', $via );
			}
		}

		$_ = get_the_author_meta( 'twitter', $_post->post_autho );
		if ( $_ && preg_match( '`([A-Za-z0-9_]{1,25})$`', $_, $matches ) ) {
			if ( $via != $matches[1] ) {
				$via .= ( $via ? ' @' : '' ).$matches[1];
			}
		}
		
		$via       = apply_filters( 'blmd_social_network_twitter_via', $via );
		$pin_id    = apply_filters( 'blmd_social_network_pinterest_id', '' );
		$pin_image = apply_filters( 'blmd_social_network_pinterest_image', $pin_image );
	
		
		$button_template       = '{icon} {count}';
		$button_template_total = '{total-count} Total Shares';
		$permalink                   = get_permalink( $_post->ID );
		$summary               = html_entity_decode(the_title_attribute( array( 'echo'=>false, 'post'=>$_post->ID ) ), ENT_COMPAT, 'UTF-8');
		if ( !empty( $shares_arr['networks'] ) ) { $networks = $shares_arr['networks']; }
		if ( !empty( $shares_arr['counts'] ) ) { $counts = $shares_arr['counts']; }
		// for facebook returning double counts for 301'd url
		if ( !empty( $shares_arr['counts_archived'] ) && count($shares_arr['counts_archived']) > 1 ) {
			$counts_archived = $shares_arr['counts_archived'];
		}

		foreach ( $networks as $network ) {
			$summary = apply_filters( "blmd_social_share_summary", $summary, $network );
			
			$class_network  = apply_filters( 'blmd_social_class_network', $network );
			$classes_button = apply_filters( 'blmd_social_classes_button', '', $network );
			$classes_icon   = apply_filters( 'blmd_social_classes_icon', '' );
			$class_icon     = apply_filters( 'blmd_social_class_icon', 'icon' );
			// $classes_button_e    = esc_attr( $classes_button );
			
			$cnt = !empty( $counts[$network] ) ? (int)$counts[$network] : 0;
			$cnt_a = !empty( $counts_archived[$network] ) ? (int)$counts_archived[$network] : 0;
			$cnt += $cnt_a;
			$total_cnt += $cnt;

			$bs = '<div class="wrap">';
			$bs .= '<a class="%1$s %2$s %3$s" data-network="%4$s" data-count="%5$d" data-count-archived="%6$d" href="%7$s"><span class="icon"><span class="%8$s-%4$s %9$s"></span></span><span class="count">%5$d</span></a>';
			$bs .= '</div>';
			$bs = sprintf( $bs,
				esc_attr( $class_button ),
				esc_attr( "{$network}" ),
				esc_attr( $classes_button ),
				esc_attr( $class_network ),
				(int)$cnt,
				(int)$cnt_a,
				'%1$s',
				esc_attr( $class_icon ),
				esc_attr( $classes_icon )
			);

			switch ( $network ) {
			case 'twitter':
				$url = 'https://twitter.com/intent/tweet?url=%1$s&text=%2$s&via=%3$s';
				$url = sprintf( $url, urlencode( $permalink ), urlencode( $summary ), urlencode( $via ) );
				$buttons[] = sprintf( $bs, esc_url( $url ) );
				break;
			case 'facebook':
				$url = 'https://www.facebook.com/sharer/sharer.php?u=%1$s';
				$url = sprintf( $url, urlencode( $permalink ) );
				$buttons[] = sprintf( $bs, esc_url( $url ) );
				break;
			case 'pinterest':
				$url = 'https://pinterest.com/pin/create/button/?url=%1$s&media=%2$s&description=%3$s&id=%4$s';
				$url = sprintf( $url, urlencode( $permalink ), urlencode( $pin_image ), urlencode( $summary ), urlencode( $pin_id ) );
				$buttons[] = sprintf( $bs, esc_url( $url ) );
				break;
			case 'linkedin':
				$url = 'https://www.linkedin.com/cws/share?url=%1$s&token=&isFramed=true';
				$url = sprintf( $url, urlencode( $permalink ) );
				$buttons[] = sprintf( $bs, esc_url( $url ) );
				break;
			case 'googleplus':
				$url = 'https://plus.google.com/share?url=%1$s';
				$url = sprintf( $url, urlencode( $permalink ) );
				$buttons[] = sprintf( $bs, esc_url( $url ) );
				break;
			case 'stumbleupon':
				$url = 'https://www.stumbleupon.com/badge/?url=%1$s';
				$url = sprintf( $url, urlencode( $permalink ) );
				$buttons[] = sprintf( $bs, esc_url( $url ) );
				break;
			default:
				$buttons[] = str_replace( '%1$s', '', $button_str );
			}
		}
		
		
		$classes_button      = apply_filters( 'blmd_social_classes_button', '', 'total-count' );
		$total_shares_text   = apply_filters( 'blmd_social_total_shares_text', 'Total Shares' );
		$bs = '<div class="wrap">';
		$bs .= '<span class="%1$s %2$s %3$s" data-total-count="%4$d"><span class="count">%4$d</span><span class="text">%5$s</span></span>';
		$bs .= '</div>';
		$bs = sprintf( $bs,
			'',//esc_attr( $class_button ),
			esc_attr( $class_total_count ),
			esc_attr( $classes_button ),
			(int)$total_cnt,
			esc_html( $total_shares_text )
		);
		$buttons[] = $bs;
		
		$buttons_html = join( "\n", $buttons );
		$template = sprintf( '<div id="%1$s" class="%2$s %3$s" data-count-min="%4$d" data-total-count="%5$s">
					%6$s
			</div>',
			esc_attr( $args['css_id'] ), //esc_attr( $id_main ),
			esc_attr( $class_main ),
			esc_attr( $classes_main ),
			(int)$count_min,
			(int)$total_cnt,
			$buttons_html
		);
		if (@$_GET['update_share_counts'] == "yes") {
			add_action( 'pre_get_posts', array( $this, 'posts_in_filter' ) );
			$this->update_share_counts( 2 );
			remove_action( 'pre_get_posts', array( $this, 'posts_in_filter' ) );
			exit;
		}
		add_action('wp_footer', array($this, 'js'));
		echo $template;
		return $buttons_html;
	}

	public function posts_in_filter( $query ) {
		global $post;
		if ( empty( $post->ID ) ) { return; }
		$query->set( 'post__in', array( $post->ID ) );
	}
	
	public function blmd_social_update_counts($type='permalink') {
		// return;
		ob_start();
		if ($type == 'archive') {
			// echo "updating counts archived";
			$this->update_share_counts_archived( );
			// echo "updating counts > last 5 posts";
			$this->update_share_counts( 1000000, 5 );
		}
		else {
			// echo "updating counts last 5 posts";
			$this->update_share_counts( 5 );
		}
		$result = ob_get_clean();
		update_option('blmd_social_update_counts_result', $result . time());
	}
	
	public function cron_schedules($schedules) {
		$schedules['every5minutes'] = array(
			'interval' => 60*5,
			'display' => __( 'Every 5 Minutes' )
		);
		return $schedules;
	}
	
	// public function cron_test() {
	// 	wp_schedule_single_event( time() + 30, array($this, 'cron_test_run'), array( 'arg1', 'arg2', 'arg3') );
	//     // do something
	// }
	
	public static function get_compact_number( $full_number, $network = '' ) {
		$prefix = '';

		if ( 10000 == $full_number && 'googleplus' == $network ) {
			$prefix = '&gt;';
		}

		if ( 1000000 <= $full_number ) {
			$full_number = floor( $full_number / 100000 ) / 10;
			$full_number .= 'Mil';
		} elseif ( 1000 < $full_number ) {
			$full_number = floor( $full_number / 100 ) / 10;
			$full_number .= 'k';
		}

		return $prefix . $full_number;
	}

	public static function get_full_number( $compact_number ) {

		//support google+ big numbers
		if ( false !== strrpos( $compact_number, '>9999' ) ) {
			$compact_number = 10000;
		}

		if ( false !== strrpos( $compact_number, 'k' ) ) {
			$compact_number = floatval( str_replace( 'k', '', $compact_number ) ) * 1000;
		}
		if ( false !== strrpos( $compact_number, 'Mil' ) ) {
			$compact_number = floatval( str_replace( 'Mil', '', $compact_number ) ) * 1000000;
		}

		return $compact_number;
	}
	
	public function update_counts($posts, $type='permalink', $networks=null) { // or archive
		if ( empty( $type ) || !in_array( strtolower( $type ), array( 'archive', 'permalink' ) ) ) { return; }
		if ( empty( $networks ) ) { $networks = $this->get_option( 'networks' ); }
		$archive_url = $this->get_option( 'archive_url' );
		// no archive url set
		if ( $type == 'archive' && empty( $archive_url ) ) { return; }

		foreach ($posts as $post) {
		$permalink  = get_permalink( $post->ID );
		$_key       = 'counts';
		if ( $type == 'archive') {
			$permalink = str_replace( '%%slug%%', $post->post_name, $archive_url );
			$_key      = 'counts_archived';
		}
			$_blmd_social_shares = (array) get_post_meta( $post->ID, '_blmd_social_shares', true );
			if (empty($_blmd_social_shares[$_key])) { 
				$_blmd_social_shares[$_key] = array();
			}
			$services = $networks;
			if ( ( $_ = array_search( 'googleplus', $services ) ) !== false ) {
				$services[$_] = 'google_plus';
			}

			$options = array();
			$result = array();
			$do_check = apply_filters( 'blmd_social_before_update_counts', $post );
			if ( !$do_check ) {
				continue;
			}
			if ( ( $result = get_transient( md5( $permalink ) ) ) === false ) {
				$shares = new ShareaholicCurlMultiShareCount( $permalink, $services, $options );
				$result = $shares->get_counts();
				if ($result['status'] == 200 && !empty($result['data'])) {
					set_transient(md5($permalink), $result, 60);
				}
			}
			if ( isset( $result['data']['google_plus'] ) ) {
				$result['data']['googleplus'] = $result['data']['google_plus'];
				unset( $result['data']['google_plus'] );
			}
			
			foreach ($networks as $network) {
				$shares = $result['data'][$network];
				if ($shares === false) { continue; }
				$shares = (int)$shares;
				$existing_shares = (int)$_blmd_social_shares[$_key][$network];
				if ($shares > $existing_shares) {
					$_blmd_social_shares[$_key][$network] = $shares;
				}
				// echo "$permalink / $network / $shares<br>";
			}
			// for facebook returning double counts for 301'd url			
			if ( $type == 'archive' && count( $_blmd_social_shares['counts_archived'] ) <= 1 ) {
				$_blmd_social_shares['counts_archived'] = array();
			}
			
			// $_blmd_social_shares['counts_lastupdate'] = current_time( 'timestamp', true );
			$_blmd_social_shares[$_key.'_lastupdate'] = current_time( 'timestamp', true );
			update_post_meta($post->ID, '_blmd_social_shares', $_blmd_social_shares);

			// $myvals = get_post_meta($post->ID);
			// echo "<br><hr><br>";
		}	
	}
	
	protected function get_all_posts( $num=1000000, $offset=0 ) {
		$args = array(
			'posts_per_page'   => (int)$num,
			'offset'           => (int)$offset,
			'orderby'          => 'date',
			'order'            => 'DESC',
			'post_type'        => 'any',
			'post_status'      => 'publish',
			'meta_query' => array(
				'relation' => 'OR',
				array(
					'key' => 'blmd_social_counts',
					'value' => array( '1' ),
					'compare' => 'IN',
				)
			),
			'suppress_filters' => true
		);
		$posts_force = get_posts( $args );
		$not_in = array(0);
		foreach ($posts_force as $p) {
			$not_in[] = $p->ID;
		}

		$args = array(
			'posts_per_page'   => (int)$num,
			'offset'           => (int)$offset,
			'orderby'          => 'date',
			'order'            => 'DESC',
			'post_type'        => 'post',
			'post_status'      => 'publish',
			'post__not_in'		=> $not_in,
			'suppress_filters' => true
		);
		$posts = get_posts( $args );
		return array_merge((array)$posts, (array)$posts_force);
	}
	
	public function update_share_counts_archived( $num=1000000, $offset=0 ) {
		// $args = array(
		// 	'posts_per_page'   => (int)$num,
		// 	'offset'           => (int)$offset,
		// 	'orderby'          => 'date',
		// 	'order'            => 'DESC',
		// 	'post_type'        => 'post',
		// 	'post_status'      => 'publish',
		// 	'suppress_filters' => true
		// );
		// $posts = get_posts( $args );
		$posts = $this->get_all_posts( $num, $offset );
		$this->update_counts( $posts, 'archive' );
	}
	
	public function update_share_counts( $num=1000000, $offset=0 ) {
		// $args = array(
		// 	'posts_per_page'   => (int)$num,
		// 	'offset'           => (int)$offset,
		// 	'orderby'          => 'date',
		// 	'order'            => 'DESC',
		// 	'post_type'        => 'post',
		// 	'post_status'      => 'publish',
		// 	'suppress_filters' => true
		// );
		// $posts = get_posts( $args );
		$posts = $this->get_all_posts( $num, $offset );
		$this->update_counts( $posts, 'permalink' );
	}
	
	public function js() {
		$prefix                = apply_filters( 'blmd_social_class_prefix', 'blmd' );
		$class_main            = "{$prefix}-social";
		$class_button          = "{$prefix}-button";
		$class_total_count     = "{$prefix}-total-count";

		$class_main_esc        = esc_attr( $class_main );
		$class_button_esc      = esc_attr( $class_button );
		$class_total_count_esc = esc_attr( $class_total_count );
		
		
		$js = <<<EOS
			<script>
			window.twttr = (function(d, s, id) {
		  var js, fjs = d.getElementsByTagName(s)[0],
	    t = window.twttr || {};
		  if (d.getElementById(id)) return;
		  js = d.createElement(s);
		  js.id = id;
		  js.src = "https://platform.twitter.com/widgets.js";
		  fjs.parentNode.insertBefore(js, fjs);

		  t._e = [];
		  t.ready = function(f) {
		    t._e.push(f);
		  };

		  return t;
		}(document, "script", "twitter-wjs"));
	
		twttr.ready(function (twttr) {
		  twttr.events.bind('click',  function (ev) { console.log(ev); });
		  twttr.events.bind('tweet', function (ev) { console.log(ev); });
		  // twttr.events.bind('retweet', retweetIntentToAnalytics);
		  // twttr.events.bind('favorite', favIntentToAnalytics);
		  // twttr.events.bind('follow', followIntentToAnalytics);
		});
		</script>
EOS;
		echo $js;

		if ( is_singular() ) {
		$js = <<<EOS
		<script>
		jQuery(document).ready(function($) {
			var urls = {
				facebook:  "https://graph.facebook.com/?id={url}&callback=?",
				// twitter: 	 "http://cdn.api.twitter.com/1/urls/count.json?url={url}&callback=?",
				pinterest: "https://api.pinterest.com/v1/urls/count.json?url={url}&callback=?",
				linkedin:  "https://www.linkedin.com/countserv/count/share?format=jsonp&url={url}&callback=?"
			};
			var counts = {};
			$.each(urls, function(network, url) {
				var u = url.replace('{url}', encodeURIComponent(window.location.href));
				var count = 0;
				$.getJSON(u, function(json) {
					if (json) {
						if(typeof json.count !== 'undefined'){
							var temp = json.count + '';
							temp = temp.replace('\u00c2\u00a0', '');  //remove google plus special chars
							count += parseInt(temp, 10);
						}
						else if(typeof json.shares !== "undefined"){  //Facebook
							count += parseInt(json.shares, 10);
						}
					}
					$('.$class_main_esc').each(function() {
						var \$tc = $(this).find('.$class_total_count_esc .count')
						$(this).find('.$class_button.'+network).each(function(e) {
							var data = $(this).data();
							var data_count_archived = data.countArchived || 0;
							var data_count = data.count || 0;
							var new_count = data_count_archived + count
							if (new_count > data_count) {
								// console.log(new_count)
								$(this).find('.count').text(new_count)
								$(this).attr('data-count', new_count)
								\$tc.text(\$tc.text() - data_count + new_count)
							}
						});
					});
				});
			});
		});
		</script>
EOS;
		echo $js;
	}
		$js = <<<EOS
		<script>
		jQuery(document).ready(function($) {
			// if (window.twttr) { return; }
			$('.$class_main_esc .$class_button:not(.native)').on('click',function(event) {
			if ($(this).hasClass('no-window') == false) {
				event.preventDefault();
				href = $(this).attr("href").replace("’","'");
				if (false) {;}
				else if ($(this).hasClass("twitter")) { width = 650; height = 360; }
				else if ($(this).hasClass("facebook")) { width = 900; height = 500; }
				else if ($(this).hasClass("pinterest")) { width = 700; height = 550; }
				else if ($(this).hasClass("linkedin")) { width = 550; height = 550; }
				else if ($(this).hasClass("googleplus")) { width = 900; height = 650; }
				else if ($(this).hasClass("stumbleupon")) { width = 550; height = 550; }
				else { width = 500; height = 300; };
				instance = window.open("about:blank", "_blank", "height=" + height + ",width=" + width);
				instance.document.write("<meta http-equiv=\"refresh\" content=\"0;url="+href+"\">");
				instance.document.close();
				return false;
			};
		});
		});

		</script>
EOS;
	echo $js;
	}

	
	public function __construct() { }

	public function __clone() {
		_doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?', 'core-plugin' ), '0.1' );
	}

	public function __wakeup() {
		_doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?', 'core-plugin' ), '0.1' );
	}

};

function BLMD_Social() {
	return BLMD_Social::factory();
}

BLMD_Social();



// wpseo_og_title
// wpseo_og_description
// wpseo_og_image
// wpseo_twitter_title
// wpseo_twitter_description
// wpseo_twitter_image



