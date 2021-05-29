<?php

require_once 'vendor/autoload.php';

use Slim\Http\Request;
use Slim\Http\Response;

// ******************** Messages between users ************************ */
$app->get('/mymessages', function (Request $request, Response $response, $args) {
    if (!isset($_SESSION['user'])) { // check if user logged in 
        $loginLink = '/login';
        return $this->view->render($response, 'login_register_required.html.twig', ['loginLink' => $loginLink]);
    }
    $records = DB::query("SELECT m.messageId,m.message,m.userIdFrom, m.creationTS,u.name, u.email, a.title, a.advId FROM messages as m inner join advertisements as a on m.advId=a.advId inner join users as u on m.userIdFrom=u.userId WHERE userIdTo=%s ORDER BY a.advId, u.name, m.creationTS DESC", $_SESSION['user']['userId']); //get ads from user in database
    return $this->view->render($response, 'mymessages.html.twig', ['messages' => $records]);
});

// Delete message
$app->get('/mymessages/delete/{id:[0-9]+}', function (Request $request, Response $response, $args) {
    if (!isset($_SESSION['user'])) { // check if user logged in 
        $loginLink = '/login';
        return $this->view->render($response, 'login_register_required.html.twig', ['loginLink' => $loginLink]);
    }
    if(!empty($args['id'])) {
        DB::delete('messages', 'messageId=%s',$args['id']);
    }
    return $response->withRedirect('/mymessages');
});
