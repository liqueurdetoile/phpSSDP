<?php
/**
 *  phpSSDP class file
 */

 namespace LqdT;
 use \SimpleXMLElement;
 
/**
 * This class is a utility to search for devices on a local network through UPnP SSDP Discovery.
 *  
 * Given the query, it can retrieve informations of a specific device based on the UUID or URN or browse local network to fetch all responding UPNP devices.
 * The returned array is associative with the following keys :
 * <ul>
 * <li>RESPONSE : Base-64 encoded full device UPNP response</li>
 * <li>SERVER : Server Name</li>
 * <li>LOCATION : URI of the XML device description file</li>
 * <li>ST : Search target value</li>
 * <li>USN : USN response value (usually a combination of UUID and ST)</li>
 * <li>IP : IP of the device</li>
 * <li>UUID : Extracted UUID value of the device</li>
 * <li>DESCRIPTION : Only added if not calling getAllDevices for performance issue. It contains an array with the content of the <DEVICE> node of the XML description file</li>
 * </ul>
 * 
 * You can use this class to automatically send the array back to the client as a JSON response (in an ajax call context for instance).
 * 
 * You should not modify default timeout and MX values for best performance. However, if your devices are not responding in time, you can try to increase timeout and/or request shorter MX response delay than timeout. You should also dig in LAN performance issues.
 * 
 * @author Liqueur de Toile : <contact@liqueurdetoile.com>
 * @copyright 2017 Liqueur de Toile : https://liqueurdetoile.com
 *  
 * @licence MIT : https://opensource.org/licenses/MIT
 */
 class phpSSDP {	
	 /**
	 * Utility method to fetch a device description XML file content.
	 *  
	 * Most of the time, troubles will come from timeout issue with slow devices or malformed xml device description file.
	 * 
	 * @param	string	$location	URI of the XML file
	 * @return	array|null			Array with the XML file content of the device node or null if no XML file found
	 */
	static private function _getDeviceInfo($location) {
		$curl = curl_init();
		curl_setopt($curl, CURLOPT_TIMEOUT, 200);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);		
		curl_setopt($curl, CURLOPT_HEADER, true);
		curl_setopt($curl, CURLOPT_URL, $location);		
		$response = curl_exec($curl);		
		if ( curl_getinfo($curl, CURLINFO_HTTP_CODE) == 200) {
			$header_size = curl_getinfo($curl, CURLINFO_HEADER_SIZE);			
			$ret = new SimpleXMLElement(substr($response, $header_size));			
		}
		curl_close($curl);
		return (!empty($ret->device))?self::_object_to_array($ret->device):null;
	}
	 
	 /**
	 * Utility method to recursively convert an object into an array.
	 * Thanks to Ben Lobaugh for the snippet.
	 * @see https://ben.lobaugh.net/blog/567/php-recursively-convert-an-object-to-an-array	Blog of Ben Lobaugh
	 * 
	 * @param	object		$obj		Object to be converted
	 * @return	array				Associative array based on original object
	 */
	private static function _object_to_array($obj) {
		if(is_object($obj)) $obj = (array) $obj;
		if(is_array($obj)) {
			$new = array();
			foreach($obj as $key => $val) {
				$new[$key] = self::_object_to_array($val);
			}
		}
		else $new = $obj;
		return $new;       
	}
	
	/**
	 * Utility method to handle result return
	 *  
	 * @param	array		$devices	An array of devices
	 * @param	boolean		$json		Triggers a JSON output
	 * 
	 * @return	array|null				Array of devices or null if input devices array is null
	 */
	private static function _sendResponse($devices, $json) {
		if($json && !empty($devices)) {
			http_response_code(200);
			header('Content-Type: application/json');
			echo json_encode($devices);
		}
		elseif($json && empty($devices)) {
			http_response_code(204);
			return null;
		}
		return $devices;		
	}
	
	/**
	 * Utility method to sort a list of devices by IP value
	 *  
	 * @param	array		$devices	Array to be sorted
	 * @return	array|null				Sorted array by IP value or null if input array is empty
	 */
	
	private static function _sortByIP($devices) {
		if(empty($devices)) return null;
		usort($devices, function($k1, $k2) {
			preg_match("/\d{1,3}$/",$k1['IP'], $ip1);
			preg_match("/\d{1,3}$/",$k2['IP'], $ip2);
			return ($ip1[0] - $ip2[0]);
		});
		return $devices;
	}
	
	/**
	 * Main utility method to perform a multicast SSDP request and build a responding devices array
	 * 
	 * By default the MX value is the same than the timeout value, but it can be forced through each callable static methods.
	 *  
	 * @param	string	$st			Search value for ST (search target) of the request
	 * @param	int		$timeout	Request timeout value in seconds
	 * @param	int		$mx			MX value in seconds (Response delay for devices)
	 * 
	 * @return	array|null			Array of devices or null if no devices are detected
	 */
	private static function _search($st, $timeout, $mx = null) {
		if(is_null($mx)) $mx = $timeout;
		$headers = "M-SEARCH * HTTP/1.1\r\nHost:239.255.255.250:1900\r\nST:$st\r\nMan:\"ssdp:discover\"\r\nMX:$mx\r\n\r\n";
		$response = null;
		$_tmp = null;
		$devices = array();
		$keys = ['SERVER','LOCATION','ST','USN'];		
		
		$socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
        socket_set_option($socket, SOL_SOCKET, SO_RCVTIMEO, array('sec'=>$timeout, 'usec'=>0));
        $send_ret = socket_sendto($socket, $headers, 1024, 0, '239.255.255.250', 1900);
		while(@socket_recvfrom($socket, $response, 1024, MSG_WAITALL, $_tmp, $_tmp)) {
			$ret=[];
			$ret['RESPONSE'] = base64_encode($response);
			//Response analysis
			foreach($keys as $key) 	{
				preg_match("/$key\s*:\s*(.*)/i", $response, $tmp);
				$ret[$key] = (!empty($tmp[1]))?trim($tmp[1]):'';
			}
			preg_match("/http:\/\/(\d+\.\d+\.\d+\.\d+)/i", $response, $tmp);
			$ret['IP'] = (!empty($tmp[1]))?trim($tmp[1]):'';
			preg_match("/uuid\s*:\s*([\w-]*)/i", $response, $tmp);
			$ret['UUID'] = (!empty($tmp[1]))?trim($tmp[1]):'';			
			$devices[] = $ret;			
		}		
		socket_close($socket);
		return (!empty($devices))?$devices:null;
	}	
	
	/**
	 * Callable static method to fetch all responding UPNP devices on LAN.
	 *  
	 * This method doesn't filter anything and you'll usually have a long list of found items, even duplicates, for some devices
	 *  
	 * @param	boolean		$json		Triggers a JSON output
	 * @param	int			$timeout	Defines the timeout value in seconds
	 * @param	int			$mx			MX value in seconds (Response delay for devices)
	 * 
	 * @return	array		An array with the UPNP responses sorted by IP
	 */
	public static function getAllDevices($json = false, $timeout = 2, $mx = null) {
		$devices = self::_search('ssdp:all', $timeout, $mx);
		return self::_sendResponse(self::_sortByIP($devices), $json);
	}
	
	/**
	 * Callable static method to fetch all devices which are responding as root devices.
	 *  
	 * It's especially useful to have a clean list of all main devices without redundant responses and services. It can cut the results count by ten.
	 *  
	 * @param	boolean		$json		Triggers a JSON output
	 * @param	int			$timeout	Defines the timeout value in seconds
	 * @param	int			$mx			MX value in seconds (Response delay for devices)
	 * 
	 * @return	array		An array sorted by IP with the devices list and full description for each device
	 */
	public static function getAllRootDevices($json = false, $timeout = 2, $mx = null) {
		// Performs SSDP upnp:rootdevice
		$devices = self::_search('upnp:rootdevice', $timeout, $mx);
		//Cleaning list
		$list = [];
		foreach($devices as $device) {
			if( $device['ST'] == 'upnp:rootdevice' && empty($list[$device['UUID']]) ) {
				$list[$device['UUID']] = $device;
				//Fetching additionnal informations
				$list[$device['UUID']]['DESCRIPTION'] = self::_getDeviceInfo($device['LOCATION']);
			}
		}		
		return self::_sendResponse(self::_sortByIP($list), $json);
	}
	
	/**
	 * Callable static method to launch a custom LAN search for a specific device or service given an URN description
	 * 
	 * @param	string		$urn		Device or service to find
	 * @param	boolean		$json		Triggers a JSON output
	 * @param	int			$timeout	Defines the timeout value in seconds
	 * @param	int			$mx			MX value in seconds (Response delay for devices)
	 *  
	 * @return	array		An array with the devices description
	 */
	public static function getDevicesByURN($urn, $json = false, $timeout = 1, $mx = null) {
		$devices = self::_search($urn, $timeout, $mx);
		//Fetching additional informations
		foreach($devices as $key => $device) $devices[$key]['DESCRIPTION'] = self::_getDeviceInfo($device['LOCATION']);
		return self::_sendResponse(self::_sortByIP($devices), $json);
	}
	
	/**
	 * Callable static method to look for a device with a given UUID.
	 *  
	 * Following standards, an UUID value SHOULD be unique, like a MAC address. However, it's still possible to encounter an UUID conflict.
	 * In this case, this method will return only the first result.
	 *  
	 * @link https://en.wikipedia.org/wiki/Universally_unique_identifier
	 *  
	 * @param	string		$uuid		Device UUID to query
	 * @param	boolean		$json		Triggers a JSON output
	 * @param	int			$timeout	Defines the timeout value in seconds
	 * @param	int			$mx			MX value in seconds (Response delay for devices)
	 *  
	 * @return	array		An array with the device description
	 */
	public static function getDeviceByUUID($uuid, $json = false, $timeout = 1, $mx = null) {
		$device = null;
		$devices = self::_search('uuid:'.$uuid, $timeout, $mx);
		if(!empty($devices)) $device = $devices[0];
		//Fetching additionnal informations
		$device['DESCRIPTION'] = self::_getDeviceInfo($device['LOCATION']);		
		return self::_sendResponse($device, $json);
	}
}