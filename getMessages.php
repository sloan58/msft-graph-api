<?php

require('./vendor/autoload.php');
require('./api.php');

use Microsoft\Graph\Model;

try {
    $graph = getApiClient();
} catch (Exception $e) {
    die($e->getMessage());
}

try {
    $messages = $graph->createRequest("GET", '/users/marty@karmatek.io/messages?$filter=isRead eq false')
        ->setReturnType(Model\Message::class)
        ->execute();
} catch(Exception $e) {
    die($e->getMessage());
}

foreach($messages as $message) {
    echo "{$message->getSubject()}\n";
}