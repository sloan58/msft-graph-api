<?php

require('./vendor/autoload.php');
require('./api.php');

use Microsoft\Graph\Model;

try {
    $graph = getApiClient();
} catch(Exception $e) {
    die($e->getMessage());
}

try {
    $user = $graph->createRequest("GET", "/users/marty@karmatek.io")
        ->setReturnType(Model\User::class)
        ->execute();
} catch(Exception $e) {
    die($e->getMessage());
}

echo "Hello, I am {$user->getGivenName()}.";