# phpSSDP, a simple PHP class to discover UPnP devices

Utility PHP class to search for devices on a local network through UPnP SSDP Discovery.

This class is only about device discovery, not about UPnP communications.

When searching over a simple utility for performing a multicast SSDP search over LAN, I didn't find one available. Many good things are proposed but they are always embedded for other purposes.

This class can be used as standalone or part of a wider app. It is heavily commented in phpDoc format.

## Usage

### Calling

You can either require the class file and call methods with qualified NS or either in autoloading context : `use \LqdT\phpSSDP`

### Returned value

The class will always try to return an array of associative arrays built like that :

*   RESPONSE : Base-64 encoded device full response
*   SERVER : Server Name
*   LOCATION : URI of the XML device description file
*   ST : Search target value
*   USN : USN response value (usually a combination of UUID and ST)
*   IP : IP of the device
*   UUID : Extracted UUID value of the device
*   DESCRIPTION : Only added if not calling getAllDevices for performance issue. It contains an array with the content of the <device>node of the XML description file</device>

### Available methods

The class exposes 4 methods to performs a search :

*   `getAllDevices($json = false, $timeout = 2, $mx = null)` : The RAW one to search for all devices and services responding on a LAN. The results might be quite huge. For performance sake, the DESCRIPTION value is not filled.
*   `getAllRootDevices($json = false, $timeout = 2, $mx = null)` : Results are limited to devices responding as upnp:rootdevice.
*   `getDevicesByURN($urn, $json = false, $timeout = 1, $mx = null)` : The search is performed with an ST (search target) initialized as the `$urn` parameter (e.g. `urn:schemas-upnp-org:device:InternetGatewayDevice:1`)
*   `getDeviceByUUID($uuid, $json = false, $timeout = 1, $mx = null)` : The search is performed with ST initialized as `uuid:$uuid` parameter. This one is useful when looking for a device which you do know UUID but not IP.

Common parameters are :

*   `$json` : id set to true, the class will not only return the array to the caller script but will also try to send it as a JSON response to client.
*   `$timeout` : It is the timeout value for the socket to listen to UPnP responses.
*   `$mx` : It is the delay given to devices to respond to the SSDP multicast request.

Obviously, timeout and MX values should be the same and the default values should be the ones with the best results in a common LAN. However, you can try to tweak these to fit your needs and your LAN characteristics. If your devices are not showing up in results, try first to increase timeout by 1 second and next, if not solved, to decrease MX value.