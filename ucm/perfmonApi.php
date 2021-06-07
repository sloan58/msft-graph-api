<?php

require __DIR__ . '/../vendor/autoload.php';

// Pull in the .env vars (need UCM_IP, UCM_USER and UCM_PASS set)
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

// Create the PerfmonSoap Client
try {
    $api = new SoapClient("https://{$_ENV['UCM_IP']}:8443/perfmonservice2/services/PerfmonService?wsdl",
        [
            'trace' => true,
            'exceptions' => true,
            'location' => "https://{$_ENV['UCM_IP']}:8443/perfmonservice2/services/PerfmonService",
            'login' => $_ENV['UCM_USER'],
            'password' => $_ENV['UCM_PASS'],
            'stream_context' => stream_context_create([
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                ]
            ]),
        ]
    );
} catch(\Exception $e) {
    die($e->getMessage());
}

// Fetch a Perfmon API session key
try {
    $response = $api->perfmonOpenSession();
    $session = $response->perfmonOpenSessionReturn->_;
} catch(\Exception $e) {
    die($e->getMessage());
}

// Collect Perfmon data for the "Cisco CallManager" object type (lots of interesting counters contained within)
try {
    $response = (array)$api->perfmonCollectCounterData([
        'Host' => 'hq-ucm-pub.karmatek.io',
        'Object' => 'Cisco CallManager'
    ]);
    
    // Iterate the response and extract our useful information
    // * array_values = remove the array keys (ie [0] => 'foo' becomes just 'foo')
    // * array_filter = only return elements that match our filter
    $hardwarePhonesElement = array_values(array_filter($response['perfmonCollectCounterDataReturn'], function($metric) {
        if(strpos($metric->Name->_, 'RegisteredHardwarePhones') !== false) {
            return $metric->Value;
        }
    }));

    $registeredHardwarePhones = isset($hardwarePhonesElement[0]) ? $hardwarePhonesElement[0]->Value : 0;
    

    // Iterate the response and extract our useful information
    // * array_values = remove the array keys (ie [0] => 'foo' becomes just 'foo')
    // * array_filter = only return elements that match our filter
    $otherStationElement = array_values(array_filter($response['perfmonCollectCounterDataReturn'], function($metric) {
        if(strpos($metric->Name->_, 'RegisteredOtherStationDevices') !== false) {
            return $metric->Value;
        }
    }));

    $registeredOtherStationDevices = isset($otherStationElement[0]) ? $otherStationElement[0]->Value : 0;
    

    // Echo output for fun :-)
    echo "RegisteredHardwarePhones: $registeredHardwarePhones\n";
    echo "RegisteredOtherStationDevices: $registeredOtherStationDevices\n";
    echo "Total: " . ($registeredHardwarePhones + $registeredOtherStationDevices) . "\n";

} catch(\Exception $e) {
    die($e->getMessage());
}

// Close the Perfmon session
try {
    $api->perfmonCloseSession([
        'SessionHandle' => $session
    ]);
} catch (\Exception $e) {
    die($e->getMessage());
}

