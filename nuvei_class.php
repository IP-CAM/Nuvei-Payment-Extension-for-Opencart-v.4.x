<?php

/**
 * Nuvei_Class
 * 
 * A class for work with Nuvei REST API.
 * 
 * @author Nuvei
 */

const NUVEI_PLUGIN_CODE         = 'nuvei';
const NUVEI_PLUGIN_TITLE        = 'Nuvei';

const NUVEI_LIVE_URL_BASE       = 'https://secure.safecharge.com/ppp/api/v1/';
const NUVEI_TEST_URL_BASE       = 'https://ppp-test.nuvei.com/ppp/api/v1/';
const NUVEI_SDK_URL_PROD        = 'https://cdn.safecharge.com/safecharge_resources/v1/checkout/checkout.js';
const NUVEI_SDK_URL_TAG         = 'https://devmobile.sccdev-qa.com/checkoutNext/checkout.js';
const NUVEI_SDK_AUTOCLOSE_URL   = 'https://cdn.safecharge.com/safecharge_resources/v1/websdk/autoclose.html';
const NUVEI_QA_HOSTS            = [
    'opencart4021-automation.gw-4u.com',
    'opencart4-automation.gw-4u.com'
];

const NUVEI_AUTH_CODE           = 'authCode';
const NUVEI_TRANS_ID            = 'transactionId';
const NUVEI_TRANS_TYPE          = 'transactionType';

// if change the both consts above, change the ajaxURL in admin nuvei_order.js
const NUVEI_TOKEN_NAME          = 'user_token';
const NUVEI_CONTROLLER_PATH     = 'extension/nuvei/payment/nuvei';

const NUVEI_SETTINGS_PREFIX     = 'payment_nuvei_';
const NUVEI_SOURCE_APP          = 'openCart 3.0 Plugin';

//const NUVEI_ADMIN_TXT_EXT_KEY =    'text_extension';
const NUVEI_ADMIN_EXT_URL       = 'marketplace/extension';

class Nuvei_Class
{
    private static $fieldsToMask = [
        'ips'       => ['ipAddress'],
        'names'     => ['firstName', 'lastName', 'first_name', 'last_name', 'shippingFirstName', 'shippingLastName'],
        'emails'    => [
            'userTokenId',
            'email',
            'shippingMail', // from the DMN
            'userid', // from the DMN
            'user_token_id', // from the DMN
        ],
        'address'   => ['address', 'phone', 'zip'],
        'others'    => ['userAccountDetails', 'userPaymentOption', 'paymentOption'],
    ];
    
    private static $trace_id;
    
	// array details to validate request parameters
    private static $params_validation = array(
        // deviceDetails
        'deviceType' => array(
            'length' => 10,
        ),
        'deviceName' => array(
            'length' => 255,
            'flag'    => FILTER_DEFAULT
        ),
        'deviceOS' => array(
            'length' => 255,
            'flag'    => FILTER_DEFAULT
        ),
        'browser' => array(
            'length' => 255,
            'flag'    => FILTER_DEFAULT
        ),
//        'ipAddress' => array(
//            'length' => 15,
//            'flag'    => FILTER_VALIDATE_IP
//        ),
        // deviceDetails END
        
        // userDetails, shippingAddress, billingAddress
        'firstName' => array(
            'length' => 30,
            'flag'    => FILTER_DEFAULT
        ),
        'lastName' => array(
            'length' => 40,
            'flag'    => FILTER_DEFAULT
        ),
        'address' => array(
            'length' => 60,
            'flag'    => FILTER_DEFAULT
        ),
        'cell' => array(
            'length' => 18,
            'flag'    => FILTER_DEFAULT
        ),
        'phone' => array(
            'length' => 18,
            'flag'    => FILTER_DEFAULT
        ),
        'zip' => array(
            'length' => 10,
            'flag'    => FILTER_DEFAULT
        ),
        'city' => array(
            'length' => 30,
            'flag'    => FILTER_DEFAULT
        ),
        'country' => array(
            'length' => 20,
        ),
        'state' => array(
            'length' => 2,
        ),
        'county' => array(
            'length' => 255,
            'flag'    => FILTER_DEFAULT
        ),
        // userDetails, shippingAddress, billingAddress END
        
        // specific for shippingAddress
        'shippingCounty' => array(
            'length' => 255,
            'flag'    => FILTER_DEFAULT
        ),
        'addressLine2' => array(
            'length' => 50,
            'flag'    => FILTER_DEFAULT
        ),
        'addressLine3' => array(
            'length' => 50,
            'flag'    => FILTER_DEFAULT
        ),
        // specific for shippingAddress END
        
        // urlDetails
        'successUrl' => array(
            'length' => 1000,
            'flag'    => FILTER_VALIDATE_URL
        ),
        'failureUrl' => array(
            'length' => 1000,
            'flag'    => FILTER_VALIDATE_URL
        ),
        'pendingUrl' => array(
            'length' => 1000,
            'flag'    => FILTER_VALIDATE_URL
        ),
        'notificationUrl' => array(
            'length' => 1000,
            'flag'    => FILTER_VALIDATE_URL
        ),
        // urlDetails END
    );
	
	private static $params_validation_email = array(
		'length'	=> 79,
		'flag'		=> FILTER_VALIDATE_EMAIL
	);
	
    private static $devices = array('iphone', 'ipad', 'android', 'silk', 'blackberry', 'touch', 'linux', 'windows', 'mac');
    
    private static $browsers = array('ucbrowser', 'firefox', 'chrome', 'opera', 'msie', 'edge', 'safari', 'blackberry', 'trident');
    
    private static $device_types = array('macintosh', 'tablet', 'mobile', 'tv', 'windows', 'linux', 'tv', 'smarttv', 'googletv', 'appletv', 'hbbtv', 'pov_tv', 'netcast.tv', 'bluray');
    
    /**
	 * Call REST API with cURL post and get response.
	 * The URL depends from the case.
	 *
	 * @param string $method
	 * @param array $settings           The plugin settings
	 * @param array $checksum_params    The parameters for Checksum
	 * @param array $params             Specific method parameters
	 *
	 * @return array
	 */
    public static function call_rest_api($method, array $settings, array $checksum_params, array $params = [])
    {
        if(empty($method)) {
			self::create_log($settings, 'call_rest_api() Error - the passed method can not be empty.');
			return array(
                'status'    => 'ERROR',
                'msg'       => 'call_rest_api() Error - the passed method can not be empty.'
            );
		}
        
        $url = self::get_endpoint_base($settings) . $method . '.do';
		
		if(!is_array($params)) {
			self::create_log($settings, 'callRestApi() Error - the passed params parameter is not array.');
			return array(
                'status'    => 'ERROR',
                'msg'       => 'call_rest_api() Error - the passed params parameter is not array.'
            );
		}
        
        if(empty($settings[NUVEI_SETTINGS_PREFIX . 'hash'])) {
            self::create_log($settings, 'callRestApi() Error - the hash params parameter is empty.');
            return array(
                'status'    => 'ERROR',
                'msg'       => 'call_rest_api() Error - the hash params parameter is empty.'
            );
        }
        
        $time = date('YmdHis', time());
       
        // set here some of the mandatory parameters
//        $params = array_merge_recursive(
        $params = array_merge(
            array(
                'clientRequestId'   => $time . '_' . uniqid(),
                'merchantId'        => trim($settings[NUVEI_SETTINGS_PREFIX . 'merchantId']),
                'merchantSiteId'    => trim($settings[NUVEI_SETTINGS_PREFIX . 'merchantSiteId']),
                'timeStamp'         => $time,
                'webMasterId'       => 'OpenCart ' . VERSION . '; Plugin v' . self::get_plugin_version(),
                'sourceApplication' => NUVEI_SOURCE_APP,
//                'merchantDetails'	=> array(
//					'customField2' => time(), // time when we create request
//				),
                'deviceDetails'     => self::get_device_details($settings),
            ),
            $params
        );
        
        $params['merchantDetails']['customField2'] = time();
        
        // calculate the checksum
        $concat = '';
        
        foreach($checksum_params as $key) {
            if(!isset($params[$key])) {
                self::create_log(
                    $settings,
                    array(
                        'request url'   => $url,
                        'params'        => $params,
                        'missing key'   => $key,
                    ),
                    'Error - Missing a mandatory parameter for the Checksum:'
                );
                
                return array('status' => 'ERROR');
            }
            
            $concat .= $params[$key];
        }
        
        $concat .= trim($settings[NUVEI_SETTINGS_PREFIX . 'secret']);
        
        $params['checksum'] = hash($settings[NUVEI_SETTINGS_PREFIX . 'hash'], $concat);
        // /calculate the checksum
        
        // validate parameters
        $params = self::validate_params($params, $settings);
        
        if(isset($params['status']) && 'ERROR' == $params['status']) {
            return $params;
        }
        
        self::create_log(
            $settings,
            array(
				'url'       => $url,
				'params'    => $params,
			)
            , 'REST API request'
        );
        // /validate parameters
        
        $json_post = json_encode($params);
        
        try {
            $header =  array(
                'Content-Type: application/json',
                'Content-Length: ' . strlen($json_post),
            );
            
            // create cURL post
            $ch = curl_init();

            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
            curl_setopt($ch, CURLOPT_POSTFIELDS, $json_post);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

            $resp = curl_exec($ch);
            curl_close ($ch);
			
			$resp_arr = json_decode($resp, true);
            
            self::create_log($settings, $resp_arr, 'REST API Response: ');
			
			return $resp_arr;
        }
        catch(Exception $e) {
            self::create_log($settings, $e->getMessage(), 'Call REST API Exception');
			
            return array('status' => 'ERROR');
        }
    }
    
    /**
     * Helper function to safety access request parameters
     * 
     * @param type $name
     * @param type $filter
     * 
     * @return mixed
     */
    public static function get_param($name, $filter = FILTER_DEFAULT)
    {
        $val = filter_input(INPUT_GET, $name, $filter);
        
        if(null === $val || false === $val) {
            $val = filter_input(INPUT_POST, $name, $filter);
        }
        
        if(null === $val || false === $val) {
            return false;
        }
        
        return $val;
    }
    
	/**
     * Create plugin logs.
	 * 
     * @param array $settings   The plugin settings
     * @param mixed $data       The data to save in the log.
     * @param string $message   Record message.
     * @param string $log_level The Log level.
     * @param string $span_id   Process unique ID.
     */
    public static function create_log($settings, $data, $message = '', $log_level = 'INFO', $span_id = '')
	{
        // it is defined in OC config.php file
        if(!defined('DIR_LOGS') || !is_dir(DIR_LOGS)) {
            return;
        }
        
        // is logging enabled
        $log_files = '';
        
        if(isset($settings[NUVEI_SETTINGS_PREFIX . 'create_logs'])) {
            $log_files = $settings[NUVEI_SETTINGS_PREFIX . 'create_logs'];
        }
        
        if(empty($log_files) || 'no' == $log_files) {
            return;
        }

        // can we save DEBUG logs
        $test_mode = 0;
        
        if(!empty($settings[NUVEI_SETTINGS_PREFIX . 'test_mode'])) {
            $test_mode = $settings[NUVEI_SETTINGS_PREFIX . 'test_mode'];
        }
        
        if('DEBUG' == $log_level && 0 == $test_mode) {
            return;
        }
        // /can we save DEBUG logs
        
        $mask_details   = true; // true if the setting is not set
        $beauty_log     = (1 == $test_mode) ? true : false;
        $tab            = '    '; // 4 spaces
        
        if(isset($settings[NUVEI_SETTINGS_PREFIX . 'mask_user_details'])) {
            $mask_details = (bool) $settings[NUVEI_SETTINGS_PREFIX . 'mask_user_details'];
        }
        
        # prepare log parts
        $utimestamp     = microtime(true);
        $timestamp      = floor($utimestamp);
        $milliseconds   = round(($utimestamp - $timestamp) * 1000000);
        $record_time    = date('Y-m-d') . 'T' . date('H:i:s') . '.' . $milliseconds . date('P');
        
        if(null == self::$trace_id) {
            self::$trace_id = bin2hex(random_bytes(16));
        }
        
        if(!empty($span_id)) {
            $span_id .= $tab;
        }
        
        $machine_name       = '';
        $service_name       = NUVEI_SOURCE_APP . ' ' . self::get_plugin_version() . '|';
        $source_file_name   = '';
        $member_name        = '';
        $source_line_number = '';
        $backtrace          = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1);
        
        if(!empty($backtrace)) {
            if(!empty($backtrace[0]['file'])) {
                $file_path_arr  = explode(DIRECTORY_SEPARATOR, $backtrace[0]['file']);
                
                if(!empty($file_path_arr)) {
                    $source_file_name = end($file_path_arr) . '|';
                }
            }
            
            if(!empty($backtrace[0]['line'])) {
                $source_line_number = $backtrace[0]['line'] . $tab;
            }
        }
        
        if(!empty($message)) {
            $message .= $tab;
        }
        
        if(is_array($data)) {
            if ($mask_details) {
                // clean possible objects inside array
                $data = json_decode(json_encode($data), true);
                
                array_walk_recursive($data, 'self::maskData', self::$fieldsToMask);
            }
            
            // paymentMethods can be very big array
            if(!empty($data['paymentMethods'])) {
                $exception = json_encode($data);
            }
            else {
                $exception = $beauty_log ? json_encode($data, JSON_PRETTY_PRINT) : json_encode($data);
            }
        }
        elseif(is_object($data)) {
            if ($mask_details) {
                // clean possible objects inside array
                $data = json_decode(json_encode($data), true);
                
                array_walk_recursive($data, 'self::maskData', self::$fieldsToMask);
            }
            
            $data_tmp   = print_r($data, true);
            $exception  = $beauty_log ? json_encode($data_tmp, JSON_PRETTY_PRINT) : json_encode($data_tmp);
        }
        elseif(is_bool($data)) {
            $exception = $data ? 'true' : 'false';
        }
        elseif(is_string($data)) {
            $exception = false === strpos($data, 'http') ? $data : urldecode($data);
        }
        else {
            $exception = $data;
        }
        # prepare log parts END
        
        // Content of the log string:
        $string = $record_time      // timestamp
            . $tab                  // tab
            . $log_level            // level
            . $tab                  // tab
            . self::$trace_id       // TraceId
            . $tab                  // tab
            . $span_id              // SpanId, if not empty it will include $tab
//            . $parent_id            // ParentId, if not empty it will include $tab
            . $machine_name         // MachineName if not empty it will include a "|"
            . $service_name         // ServiceName if not empty it will include a "|"
            // TreadId
            . $source_file_name     // SourceFileName if not empty it will include a "|"
            . $member_name          // MemberName if not empty it will include a "|"
            . $source_line_number   // SourceLineName if not empty it will include $tab
            // RequestPath
            // RequestId
            . $message
            . $exception            // the exception, in our case - data to print
        ;
        
        $string     .= "\r\n\r\n";
        $file_name  = 'nuvei.log';
        
        if($log_files == 'both') {
            // save the single file, then the daily
            try {
                file_put_contents(DIR_LOGS . $file_name, $string, FILE_APPEND);
            }
            catch (Exception $exc) {}
            
            $file_name = 'nuvei-' . date('Y-m-d', time()) . '.log';
        }
        
        if($log_files == 'daily') {
            $file_name = 'nuvei-' . date('Y-m-d', time()) . '.log';
        }
        
		try {
			file_put_contents(DIR_LOGS . $file_name, $string, FILE_APPEND);
		}
		catch (Exception $exc) {}
	}
    
    /**
     * @return string
     */
    public static function get_plugin_version()
    {
        $json_file = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'install.json';
        
        if (is_readable($json_file)) {
            $json_arr = json_decode(file_get_contents($json_file), true);
            
            if (is_array($json_arr) && !empty($json_arr['version'])) {
                return $json_arr['version'];
            }
        }
        
        return '';
    }
    
    /**
     * Function get_device_details
	 * 
     * Get browser and device based on HTTP_USER_AGENT.
     * The method is based on D3D payment needs.
     * 
     * @param array $settings
     * @return array $device_details
     */
    private static function get_device_details($settings)
    {
        $device_details = array(
            'deviceType'    => 'UNKNOWN', // DESKTOP, SMARTPHONE, TABLET, TV, and UNKNOWN
            'deviceName'    => 'UNKNOWN',
			'deviceOS'      => 'UNKNOWN',
			'browser'       => 'UNKNOWN',
			'ipAddress'     => '0.0.0.0',
        );
        
        if(empty($_SERVER['HTTP_USER_AGENT'])) {
			$device_details['Warning'] = 'User Agent is empty.';
			
			self::create_log($settings, $device_details['Warning'], 'get_device_details Error');
			return $device_details;
		}
		
		$user_agent = strtolower($_SERVER['HTTP_USER_AGENT']);
		
		if (empty($user_agent)) {
			$device_details['Warning'] = 'Probably the merchant Server has problems with PHP filter_var function!';
			
			self::create_log($settings, $device_details['Warning'], 'get_device_details Error');
			return $device_details;
		}
		
		$device_details['deviceName'] = $user_agent;
		
        foreach (self::$device_types as $d) {
            if (strstr($user_agent, $d) !== false) {
                if(in_array($d, array('linux', 'windows', 'macintosh'), true)) {
                    $device_details['deviceType'] = 'DESKTOP';
                } else if('mobile' === $d) {
                    $device_details['deviceType'] = 'SMARTPHONE';
                } else if('tablet' === $d) {
                    $device_details['deviceType'] = 'TABLET';
                } else {
                    $device_details['deviceType'] = 'TV';
                }

                break;
            }
        }

        foreach (self::$devices as $d) {
            if (strstr($user_agent, $d) !== false) {
                $device_details['deviceOS'] = $d;
                break;
            }
        }

        foreach (self::$browsers as $b) {
            if (strstr($user_agent, $b) !== false) {
                $device_details['browser'] = $b;
                break;
            }
        }

        // get ip
		if (!empty($_SERVER['REMOTE_ADDR'])) {
			$ip_address = filter_var($_SERVER['REMOTE_ADDR'], FILTER_VALIDATE_IP);
		}
		if (empty($ip_address) && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
			$ip_address = filter_var($_SERVER['HTTP_X_FORWARDED_FOR'], FILTER_VALIDATE_IP);
		}
		if (empty($ip_address) && !empty($_SERVER['HTTP_CLIENT_IP'])) {
			$ip_address = filter_var($_SERVER['HTTP_CLIENT_IP'], FILTER_VALIDATE_IP);
		}
		if (!empty($ip_address)) {
			$device_details['ipAddress'] = (string) $ip_address;
		}
            
        return $device_details;
    }
    
    /**
	 * Get the URL to the endpoint, without the method name, based on the site mode.
	 * 
     * @param array $settings The plugin settings.
	 * @return string
	 */
	private static function get_endpoint_base(array $settings)
    {
		if (isset($settings[NUVEI_SETTINGS_PREFIX . 'test_mode'])
            && 1 == $settings[NUVEI_SETTINGS_PREFIX . 'test_mode']
        ) {
			return NUVEI_TEST_URL_BASE;
		}
		
		return NUVEI_LIVE_URL_BASE;
	}
    
    /**
     * Just move out the validation outside of call_rest_api method.
     * 
     * @param array $params
     * @param array $settings Plugin settings.
     * 
     * @return array $params
     */
    private static function validate_params(array $params, $settings)
    {
		# validate parameters
		// directly check the mails
		if(isset($params['billingAddress']['email'])) {
			if(!filter_var($params['billingAddress']['email'], self::$params_validation_email['flag'])) {
				self::create_log($settings, 'call_rest_api() Error - Billing Address Email is not valid.');
				
				return array(
					'status' => 'ERROR',
					'message' => 'Billing Address Email is not valid.'
				);
			}
			
			if(strlen($params['billingAddress']['email']) > self::$params_validation_email['length']) {
				self::create_log($settings, 'call_rest_api() Error - Billing Address Email is too long');
				
				return array(
					'status' => 'ERROR',
					'message' => 'Billing Address Email is too long.'
				);
			}
		}
		
		if(isset($params['shippingAddress']['email'])) {
			if(!filter_var($params['shippingAddress']['email'], self::$params_validation_email['flag'])) {
				self::create_log($settings, 'call_rest_api() Error - Shipping Address Email is not valid.');
				
				return array(
					'status' => 'ERROR',
					'message' => 'Shipping Address Email is not valid.'
				);
			}
			
			if(strlen($params['shippingAddress']['email']) > self::$params_validation_email['length']) {
				self::create_log($settings, 'call_rest_api() Error - Shipping Address Email is too long.');
				
				return array(
					'status' => 'ERROR',
					'message' => 'Shipping Address Email is too long'
				);
			}
		}
		// directly check the mails END
		
		foreach ($params as $key1 => $val1) {
            if (!is_array($val1) && !empty($val1) && array_key_exists($key1, self::$params_validation)) {
                $new_val = $val1;
                
                if (mb_strlen($val1) > self::$params_validation[$key1]['length']) {
                    $new_val = mb_substr($val1, 0, self::$params_validation[$key1]['length']);
                    
                    self::create_log($settings, $key1, 'Limit');
                }
                
                if (isset(self::$params_validation[$key1]['flag'])) {
                    $params[$key1] = filter_var($new_val, self::$params_validation[$key1]['flag']);
                }
            }
			elseif (is_array($val1) && !empty($val1)) {
                foreach ($val1 as $key2 => $val2) {
                    if (!is_array($val2) && !empty($val2) && array_key_exists($key2, self::$params_validation)) {
                        $new_val = $val2;

                        if (mb_strlen($val2) > self::$params_validation[$key2]['length']) {
                            $new_val = mb_substr($val2, 0, self::$params_validation[$key2]['length']);
                            
                            self::create_log($settings, $key2, 'Limit');
                        }

                        if (isset(self::$params_validation[$key2]['flag'])) {
                            $params[$key1][$key2] = filter_var($new_val, self::$params_validation[$key2]['flag']);
                        }
                    }
                }
            }
        }
		# validate parameters END
        
        return $params;
    }
    
    /**
     * A callback function for arraw_walk_recursive.
     * 
     * @param mixed $value
     * @param mixed $key
     * @param array $fields
     */
    private static function maskData(&$value, $key, $fields)
    {
        if (!empty($value)) {
            if (in_array($key, $fields['ips'])) {
                $value = rtrim(long2ip(ip2long($value) & (~255)), "0")."x";
            } elseif (in_array($key, $fields['names'])) {
                $value = substr($value, 0, 1) . '****';
            } elseif (in_array($key, $fields['emails'])) {
                $value = '****' . substr($value, 4);
            } elseif (in_array($key, $fields['address'])
                || in_array($key, $fields['others'])
            ) {
                $value = '****';
            }
        }
    }
    
}
