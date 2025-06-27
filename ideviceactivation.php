<?php
require_once 'CFPropertyList/CFPropertyList.php';

// Load custom certificates and keys
$root_ca_cert = file_get_contents('root_ca.crt');
$intermediate_ca_cert = file_get_contents('intermediate_ca.crt');
$server_cert = file_get_contents('server.crt');
$server_key = file_get_contents('server.key');

// Check if this is a POST request with activation-info
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['activation-info'])) {
    // Parse the activation-info plist
    $plist = new CFPropertyList();
    $plist->parse($_POST['activation-info']);
    $dict = $plist->toArray();

    // Extract device-specific information
    $device_id = $dict['DeviceID'];
    $udid = $device_id['UniqueDeviceID'];
    $serial_number = $device_id['SerialNumber'];

    // Decode and parse ActivationInfoXML
    $activation_info_xml = base64_decode($dict['ActivationInfoXML']);
    $activation_plist = new CFPropertyList();
    $activation_plist->parse($activation_info_xml);
    $activation_dict = $activation_plist->toArray();

    $activation_randomness = $activation_dict['ActivationRequestInfo']['ActivationRandomness'];
    $baseband_info = $activation_dict['BasebandRequestInfo'];
    $imei = $baseband_info['InternationalMobileEquipmentIdentity'];
    $phone_number = $baseband_info['PhoneNumber'];

    // Generate a dynamic AccountToken
    $account_token = [
        'InternationalMobileEquipmentIdentity' => $imei,
        'PhoneNumberNotificationURL' => 'https://albert.apple.com/deviceservices/phoneHome',
        'SerialNumber' => $serial_number,
        'ProductType' => $dict['DeviceInfo']['ProductType'],
        'UniqueDeviceID' => $udid,
        'WildcardTicket' => base64_encode("DynamicWildcardTicketFor-$udid"),
        'PostponementInfo' => [],
        'ActivationRandomness' => $activation_randomness,
        'ActivityURL' => 'https://albert.apple.com/deviceservices/activity',
    ];

    $account_token_plist = new CFPropertyList();
    $account_token_plist->add(new CFPropertyList\CFDictionary($account_token));
    $account_token_xml = $account_token_plist->toXML();
    $account_token_base64 = base64_encode($account_token_xml);

    // Sign the AccountToken with your server key
    $account_token_signature = '';
    openssl_sign($account_token_xml, $account_token_signature, $server_key, OPENSSL_ALGO_SHA384);
    $account_token_signature_base64 = base64_encode($account_token_signature);

    // Build the activation record mimicking Apple's structure
    $activation_record = [
        'ActivationRecord' => [
            'unbrick' => true,
            'AccountTokenCertificate' => base64_encode($server_cert),
            'DeviceCertificate' => base64_encode($dict['DeviceCertRequest']),
            'RegulatoryInfo' => base64_encode("RegulatoryInfoFor-$serial_number"),
            'FairPlayKeyData' => base64_encode("FairPlayKeyDataFor-$udid"),
            'AccountToken' => $account_token_base64,
            'AccountTokenSignature' => $account_token_signature_base64,
            'UniqueDeviceCertificate' => base64_encode($server_cert . $intermediate_ca_cert . $root_ca_cert),
        ]
    ];

    // Generate the plist for the activation record
    $activation_plist = new CFPropertyList();
    $activation_plist->add(new CFPropertyList\CFDictionary($activation_record));
    $activation_xml = $activation_plist->toXML();

    // Output the HTML response with embedded plist
    echo <<<HTML
<!DOCTYPE html>
<html>
   <head>
      <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
      <meta name="keywords" content="iTunes Store" />
      <meta name="description" content="iTunes Store" />
      <title>iPhone Activation</title>
      <link href="https://static.deviceservices.apple.com/deviceservices/stylesheets/common-min.css" charset="utf-8" rel="stylesheet" />
      <link href="https://static.deviceservices.apple.com/deviceservices/stylesheets/styles.css" charset="utf-8" rel="stylesheet" />
      <link href="https://static.deviceservices.apple.com/deviceservices/stylesheets/IPAJingleEndPointErrorPage-min.css" charset="utf-8" rel="stylesheet" />
      <script id="protocol" type="text/x-apple-plist">$activation_xml</script>
      <script>
         	var protocolElement = document.getElementById("protocol");
         	var protocolContent = protocolElement.innerText;
         	iTunes.addProtocol(protocolContent);
      </script>
   </head>
   <body>
   </body>
</html>
HTML;
} else {
    // Return an error for invalid requests
    http_response_code(400);
    echo '<html><body><h1>Invalid Request</h1></body></html>';
}
?>
