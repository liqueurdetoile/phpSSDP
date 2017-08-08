# phpSSDP
Utility PHP class to search for devices on a local network through UPnP SSDP Discovery.

When searching over a simple utility for performing a multicast SSDP search over LAN, I didn't find one available. Many good things are proposed but they are always embedded for other purposes.

This class can be used as standalone or part of a wider app. It is heavily commented in phpDocumentator format.

# Usage

## Calling
You can either require the class file and call methods with qualified NS or either in autoloading context : `use \LqdT\phpSSDP`

## Returned value
The class will always try to return an array of associative arrays built like that :
<ul>
 <li>RESPONSE : Base-64 encoded device full response</li>
 <li>SERVER : Server Name</li>
 <li>LOCATION : URI of the XML device description file</li>
 <li>ST : Search target value</li>
 <li>USN : USN response value (usually a combination of UUID and ST)</li>
 <li>IP : IP of the device</li>
 <li>UUID : Extracted UUID value of the device</li>
 <li>DESCRIPTION : Only added if not calling getAllDevices for performance issue. It contains an array with the content of the <DEVICE> node of the XML description file</li>
</ul>

## Available methods
The class exposes 4 methods to performs a search :
<ul>
  <li><code>getAllDevices($json = false, $timeout = 2, $mx = null)</code> : The RAW one to search for all devices and services responding on a LAN. The results might be quite huge. For performance sake, the DESCRIPTION value is not filled.</li>
  <li><code>getAllRootDevices($json = false, $timeout = 2, $mx = null)</code> : Results are limited to devices responding as upnp:rootdevice.</li>
  <li><code>getDevicesByURN($urn, $json = false, $timeout = 1, $mx = null)</code> : The search is performed with an ST (search target) initialized as the <code>$urn</code> parameter (e.g. <code>urn:schemas-upnp-org:device:InternetGatewayDevice:1</code>)</li>
  <li><code>getDeviceByUUID($uuid, $json = false, $timeout = 1, $mx = null)</code> : The search is performed with ST initialized as <code>uuid:$uuid</code> parameter. This one is useful when looking for a device which you do know UUID but not IP.</li>
</ul>

Common parameters are :
* `$json` : id set to true, the class will not only return the array to the caller script but will also try to send it as a JSON response to client.
* `$timeout` : It is the timeout value for the socket to listen to UPnP responses.
* `$mx` : It is the delay given to devices to respond to the SSDP multicast request.

Obviously, timeout and MX values should be the same and the default values should be the ones with the best results in a common LAN. However, you can try to tweak these to fit your needs and your LAN characteristics. If your devices are not showing up in results, try first to increase timeout by 1 second and next, if not solved, to decrease MX value.
