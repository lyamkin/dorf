<?php

require_once 'vendor/autoload.php';

use Slim\Http\Request;
use Slim\Http\Response;

require_once 'init.php';

// register/login/logout/prolfile page:
require_once 'users.php';

// post ad page:
require_once 'ads.php';

// managing messages:
require_once 'messages.php';

// for index.html.twig:
$app->get('/', function (Request $request, Response $response, $args) {
    // select records with 3 highest visit counts
    $records = DB::query("SELECT advId,title,description,price,visits FROM advertisements ORDER BY visits DESC LIMIT 3");
    return $this->view->render($response, 'index.html.twig', ['advertisements' => $records]);
});



$app->run();