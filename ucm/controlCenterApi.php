<?php

require __DIR__ . '/../vendor/autoload.php';

use Carbon\Carbon;

// Pull in the .env vars (need UCM_IP, UCM_USER and UCM_PASS set)
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

// Create the Control Center API Client
try {
    $api = new SoapClient("https://{$_ENV['UCM_IP']}:8443/controlcenterservice2/services/ControlCenterServices?wsdl",
        [
            'trace' => true,
            'exceptions' => true,
            'location' => "https://{$_ENV['UCM_IP']}:8443/controlcenterservice2/services/ControlCenterServices",
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
} catch (\Exception $e) {
    die($e->getMessage());
}

// Wrapper to get the CallManager service data
function getCallManagerService($api) {
    // Get all service statuses
    try {
        $response = $api->soapGetServiceStatus([
            'ServiceStatus' => ''
        ]);

        if(!property_path_exists($response, 'soapGetServiceStatusReturn->ServiceInfoList->item')) {
            echo "SOAP Response did not contain object properties.  Sleeping for 10 and trying again..\n";
            sleep(10);
            getCallManagerService($api);
        }

        // Extract the Cisco CallManager service data
        // (I'm not seeing a way to query for the state of one particular service...)
        $callManagerService = array_values(array_filter(array_map(function($service) {
            return $service->ServiceName === 'Cisco CallManager' ? $service : null;
        }, $response->soapGetServiceStatusReturn->ServiceInfoList->item)))[0];

        return $callManagerService;
    } catch (\Exception $e) {
        // If we've exceeded the API rate limit, back off for 60 seconds
        if (preg_match('/Exceeded allowed rate for Reatime information/', $e->getMessage())) { // Typo in Realtime is not mine!
            echo "Received API back off notification.  Sleeping 60 seconds\n";
            sleep(60);
            getCallManagerService($api);
        } else {
            die($e->getMessage());
        }
    }
}

// Monitor the service status and alert when it's back up
function monitorServiceStatus($api, $lastStartTime) {
    while(true) {
        $callManagerService = getCallManagerService($api);
        if($callManagerService->ServiceStatus === 'Started') {
            $currentServiceStartTime = Carbon::now()->subSeconds($callManagerService->UpTime);
            if($lastStartTime->isBefore($currentServiceStartTime)) {
                echo "CallManager Service successfully restarted!\n";
                echo "Previous Start Time: " . $lastStartTime->toDateTimeString() . "\n";
                echo "Current Start Time: " . $currentServiceStartTime->toDateTimeString() . "\n";
                return false;
            }
        }
    }
}

// Recursively check for the object properties we need to proceed
function property_path_exists($object, $property_path)
{
    $path_components = explode('->', $property_path);

    if (count($path_components) == 1) {
        return property_exists($object, $property_path);
    } else {
        return (
            property_exists($object, $path_components[0]) &&
            property_path_exists(
                $object->{array_shift($path_components)},
                implode('->', $path_components)
            )
        );
    }
}

// Get the current service status to begin the operation
$callManagerService = getCallManagerService($api);

// If the service status is 'Started', let's Restart it
if($callManagerService->ServiceStatus === 'Started') {
    $currentServiceStartTime = Carbon::now()->subSeconds($callManagerService->UpTime);
    try {
        $response = $api->soapDoControlServices([
            'ControlServiceRequest' => [
                'NodeName' => 'hq-ucm-pub.karmatek.io',
                'ControlType' => 'Restart',
                'ServiceList' => [
                    'item' => $callManagerService->ServiceName
                ]
            ]
        ]);
        echo "$callManagerService->ServiceName Restart was requested\n";
        echo "Starting service check routine\n";
        monitorServiceStatus($api, $currentServiceStartTime);
    } catch (\Exception $e) {
        // If we've exceeded the API rate limit, back off for 60 seconds
        if (preg_match('/Exceeded allowed rate for Reatime information/', $e->getMessage())) { // Typo in Realtime is not mine!
            echo "Received API back off notification.  Sleeping 60 seconds\n";
            sleep(60);
            monitorServiceStatus($api, $currentServiceStartTime);
        } else {
            die($e->getMessage());
        }
    }
// If the service status is not 'Started', print the current status
} else {
    echo "$callManagerService->ServiceName is in the state $callManagerService->ServiceStatus ¯\_(ツ)_/¯\n";
}

