<?php

require_once 'vendor/autoload.php';

use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\UploadedFile;

// ******************** Ads ************************ */
$app->get('/ads', function (Request $request, Response $response, $args) {
    if (!isset($_SESSION['user'])) { // check if user logged in 
        return $response->withRedirect('/login'); // redirect to login page 
    }
    $records = DB::query("SELECT advId,title,description,price,visits,status FROM advertisements WHERE userId=%s", $_SESSION['user']['userId']); //get ads from user in database
    return $this->view->render($response, 'ads.html.twig', ['advertisements' => $records]);
});

// ********************** POST/EDIT ADS *************************


// STATE 1: first display
$app->get('/postad/{op:edit|add}[/{id:[0-9]+}]', function ($request, $response, $args) {
    // either op is add and id is not given OR op is edit and id must be given
    if ( ($args['op'] == 'add' && !empty($args['id'])) || ($args['op'] == 'edit' && empty($args['id'])) ) {
        $response = $response->withStatus(404);
        return $this->view->render($response, 'error_page_not_found.html.twig');
    }
    if ($args['op'] == 'edit') {
        $record = DB::queryFirstRow("SELECT advId,title,description,price,visits,status FROM advertisements WHERE advId=%s", $args['id']);
        $records = DB::query("SELECT * FROM categories ORDER BY category");
        if (!$record) {
            $response = $response->withStatus(404);
            return $this->view->render($response, 'error_page_not_found.html.twig');
        }
    } else {
        $records = DB::query("SELECT * FROM categories ORDER BY category");
        $record = [];
    }
    return $this->view->render($response, 'postad.html.twig', ['v' => $record, 'categories' => $records, 'op' => $args['op']]);
});

// STATE 2&3: receiving submission
$app->post('/postad/{op:edit|add}[/{id:[0-9]+}]', function(Request $request, Response $response, $args) {
    $op = $args['op'];
    if ( ($op == 'add' && !empty($args['id'])) || ($op == 'edit' && empty($args['id'])) ) {
        $response = $response->withStatus(404);
        return $this->view->render($response, 'error_page_not_found.html.twig');
    }
    if(!isset($_SESSION['user'])) {
        return $response->withRedirect('/login');
    }

    $records = DB::query("SELECT * FROM categories ORDER BY category"); // get categories
    $title = $request->getParam('title');
    $category = $request->getParam('category');
    $description = $request->getParam('description');
    $price = $request->getParam('price');
    $image = $request->getParam('image');
    $status = $request->GETPARAM('status');
    $userId = $_SESSION['user']['userId'];

    // ***** VALIDATION *****
    $errorList = [];

    // verify title
    $result = verifyTitle($title);
    if ($result !== TRUE) {
        $errorList[] = $result;
    }

    //verify category
    $result = verifyCategory($category);
    if ($result !== TRUE) {
        $errorList[] = $result;
    }

    //verify description
    $result = verifyDescription($description);
    if ($result !== TRUE) {
        $errorList[] = $result;
    }

    //verify price
    $result = verifyPrice($price);
    if ($result !== TRUE) {
        $errorList[] = $result;
    }

    // verify ad picture
    $hasPhoto = false;
    $mimeType = "";

    $uploadedFiles = $request->getUploadedFiles(); // get uploaded files from the form
    $uploadedImage = $uploadedFiles['image']; // get image

    if ($uploadedImage->getError() != UPLOAD_ERR_NO_FILE) { // was anything uploaded?
        $hasPhoto = true;
        $result = verifyUploadedPhotoAd($uploadedImage, $mimeType);

        if ($result !== TRUE) {
            $errorList[] = $result;
        }
    }
    if ($errorList) {
        return $this->view->render(
            $response,
            'postad.html.twig',
            ['categories' => $records, 'errorList' => $errorList, 'v' => ['title' => $title, 'category' => $category, 'description' => $description, 'price' => $price]]
        );
    } else {
        if ($op == 'add') {
            $photoData = null;
            if ($hasPhoto) {
                $photoData = file_get_contents($uploadedImage->file);
            }
            DB::insert('advertisements', ['userId' => $userId, 'categoryId' => $category, 'title' => $title, 'description' => $description, 'picture' => $photoData, 'imageMimeType' => $mimeType, 'price' => $price]);
            
        } else {
            if ($hasPhoto) {
                $photoData = file_get_contents($uploadedImage->file);
            }
            $data = ['userId' => $userId, 'categoryId' => $category, 'title' => $title, 'description' => $description, 'picture' => $photoData, 'imageMimeType' => $mimeType, 'price' => $price, 'status' => $status];
            
            DB::update('advertisements', $data, "advId=%s", $args['id']);
            
        }
        if($op == 'add') {
            setFlashMessage("Your advertisement has been posted successfully");
        } else {
            setFlashMessage("Your advertisement has been edited successfully");
        }

        return $response->withRedirect('/ads');
    }
});


// **************** DELETE AD ********************

// STATE 1: first display
$app->get('/deletead/{id:[0-9]+}', function ($request, $response, $args) {
    $record = DB::queryFirstRow("SELECT advId,title,description,price,visits,status FROM advertisements WHERE advId=%s", $args['id']);
    if (!$record) {
        $response = $response->withStatus(404);
        return $this->view->render($response, 'error_page_not_found.html.twig');
    }
    return $this->view->render($response, 'deletead.html.twig', ['ad' => $record] );
});

// STATE 2&3: submission
$app->post('/deletead/{id:[0-9]+}', function ($request, $response, $args) {
    DB::delete('advertisements', "advId=%s", $args['id']);
    setFlashMessage("Your advertisement has been deleted successfully");
    return $response->withRedirect('/ads');
});


//************** Ad Details ***************** */
// return details of advertisment
$app->get('/addetails/{id:[0-9]+}', function (Request $request, Response $response, $args) {
    // if(!isset($_SESSION['user'])) { // check if user loged in 
    //     return $response->withRedirect('/login'); // redirect to login page 
    // }
    $record = DB::queryFirstRow("SELECT a.advId, u.name, a.title,a.description,a.price,a.visits,a.creationTS FROM advertisements as a inner join users as u on a.userId=u.userId WHERE advId=%s", $args['id']);
    if (!$record) { // not found
        $response = $response->withStatus(403);
        return $this->view->render($response, 'error_page_not_found.html.twig');
    }
    $record['visits']++; // update number of visits
    DB::update('advertisements', ['visits' => $record['visits']], 'advId=%d', $args['id']);
    return $this->view->render($response, 'addetails.html.twig', ['advertisement' => $record]);
});

// post router for sending messages
$app->post('/addetails/{id:[0-9]+}', function (Request $request, Response $response, $args) {
    $id = $args['id'];
    $loginLink = '/login/addetails/' . $id;
    if(!isset($_SESSION['user'])) { // check if user loged in . if not then display page for login or registration options
        return $this->view->render($response, 'login_register_required.html.twig', ['loginLink' => $loginLink]);
    }
    $record = DB::queryFirstRow("SELECT a.advId, u.userId,u.name, a.title,a.description,a.price,a.visits,a.creationTS FROM advertisements as a inner join users as u on a.userId=u.userId WHERE advId=%s", $args['id']);
    if (!$record) { // not found
        $response = $response->withStatus(403);
        return $this->view->render($response, 'error_page_not_found.html.twig');
    }
    // Send a message
    // ***** VALIDATION *****
    $errorList = [];
    $message = $request->getParam('message');
    $result = verifyMessage($message);
    if($result !== TRUE) { // check if message is valid or not
        $errorList [] = $result;
    }

    if($errorList) { // if message is not valid
        return $this->view->render($response, 'addetails.html.twig', ['message' => $message, 'errorList' => $errorList, 'advertisement' => $record]);
    }else { // if message is valid
        $userIdFrom = $_SESSION['user']['userId'];
        $userIdTo = $record['userId'];
        $advId = $record['advId'];
        DB::insert('messages', ['userIdFrom' => $userIdFrom, 'userIdTo' => $userIdTo, 'advId' => $advId, 'message' => $message]);
        setFlashMessage("The message sent successfully");
        return $response->withRedirect('/addetails/'.$advId); // redirect to adv Page 
    }  
    
});


// Warning: this returns binary data, not HTML
$app->get('/adimg/{id:[0-9]+}', function ($request, $response, $args) {
    // OPTIONAL - depending on security levels
    // if (!isset($_SESSION['user'])) { // refuse if adimg not logged in
    //     $response = $response->withStatus(403);
    //     return $this->view->render($response, '/error_access_denied.html.twig');
    // } 
    $adimg = DB::queryFirstRow("SELECT picture, imageMimeType FROM advertisements WHERE advId=%d", $args['id']);
    if (!$adimg) { // not found - FIXME
        return $response->withStatus(404);
    }
    $response->getBody()->write($adimg['picture']);
    return $response->withHeader('Content-type', $adimg['imageMimeType']);
});


// ****************** Ad Categories *********************

$app->get('/categories', function (Request $request, Response $response, $args) {
    $records = DB::query("SELECT a.advId, a.title, a.description, a.price, a.visits, a.status, a.creationTS, c.category FROM advertisements as a, categories as c WHERE a.categoryId = c.categoryId ORDER BY a.creationTS DESC");
    return $this->view->render($response, 'categories.html.twig', ['advertisements' => $records]);
});


// ******************** Search page for Ads ***********************

$app->get('/adsearch', function(Request $request, Response $response, $args) {

    
    $result = $_GET['result'];
    //DB::$param_char = '^'; // doesn't seem to work...
    $records = DB::query("SELECT advId,title,description,price,visits,status FROM advertisements WHERE title LIKE '%$result%'");
    return $this->view->render($response, 'adsearch.html.twig', ['advertisements' => $records]);
});


// **************** AJAX VALIDATION ***************

// validate title AJAX
$app->get('/istitlevalid/[{title}]', function ($request, $response, $args) {
    $title = isset($args['title']) ? $args['title'] : "";
    // verify title
    $result = verifyTitle($title);
    if ($result !== TRUE) {
        return $response->write($result);
    } else {
        return $response->write("");
    }
});

// validate description AJAX
$app->get('/isdescriptionvalid/[{description}]', function ($request, $response, $args) {
    $description = isset($args['description']) ? $args['description'] : "";
    // verify description
    $result = verifyDescription($description);
    if ($result !== TRUE) {
        return $response->write($result);
    } else {
        return $response->write("");
    }
});

// validate price AJAX
$app->get('/ispricevalid/[{price}]', function ($request, $response, $args) {
    $price = isset($args['price']) ? $args['price'] : "";
    // verify price
    $result = verifyPrice($price);
    if ($result !== TRUE) {
        return $response->write($result);
    } else {
        return $response->write("");
    }
});


// ************* FUNCTIONS *************
/**
 * Moves the uploaded file to the upload directory and assigns it a unique name
 * to avoid overwriting an existing uploaded file.
 */
function moveUploadedFile($directory, UploadedFile $uploadedFile)
{
    // FIXME: extension here has a security flaw - use can upload .php file and expoit our server
    $extension = pathinfo($uploadedFile->getClientFilename(), PATHINFO_EXTENSION);
    $basename = bin2hex(random_bytes(8)); // see http://php.net/manual/en/function.random-bytes.php
    $filename = sprintf('%s.%0.8s', $basename, $extension);
    $uploadedFile->moveTo($directory . DIRECTORY_SEPARATOR . $filename);
    return $filename;
}

// these functions return TRUE on success and string describing an issue on failure
function verifyTitle($title)
{
    if (preg_match('/^[a-zA-Z0-9 ]{4,250}$/', $title) != 1) { // no match
        return "Title must be 4-250 characters long and consist of letters and digits.";
    }
    return TRUE;
}

// these functions return TRUE on success and string describing an issue on failure
function verifyCategory($category)
{
    if (!filter_var($category, FILTER_VALIDATE_INT)) {
        return "Wrong category index";
    }
    return TRUE;
}

// these functions return TRUE on success and string describing an issue on failure
function verifyDescription($description)
{
    if (preg_match('/^[a-zA-Z0-9\ \._\'"!?%*,-]{4,250}$/', $description) != 1) { // no match
        return "Description must be 4-250 characters long and consist of letters and digits and special characters (. _ ' \" ! - ? % * ,).";
    }
    return TRUE;
}

// these functions return TRUE on success and string describing an issue on failure
function verifyPrice($price)
{
    if (!filter_var($price, FILTER_VALIDATE_FLOAT)) {
        return "Price is not a number";
    }
    return TRUE;
}

// Verify picture
function verifyUploadedPhotoAd($photo, &$mime = null)
{
    if ($photo->getError() != 0) {
        return "Error uploading photo " . $photo->getError();
    }
    if ($photo->getSize() > 4096 * 1024) { // 4MB
        return "File too big. 4MB max is allowed.";
    }
    $info = getimagesize($photo->file);
    if (!$info) {
        return "File is not an image";
    }
    if ($info[0] < 200 || $info[0] > 1000 || $info[1] < 200 || $info[1] > 1000) {
        return "Width and height must be within 200-1000 pixels range";
    }
    $ext = "";
    switch ($info['mime']) {
        case 'image/jpeg':
            $ext = "jpg";
            break;
        case 'image/gif':
            $ext = "gif";
            break;
        case 'image/png':
            $ext = "png";
            break;
        case 'image/webp':
            $ext = "webp";
            break;
        default:
            return "Only JPG, GIF and PNG file types are allowed";
    }
    if (!is_null($mime)) {
        $mime = $info['mime'];
    }
    return TRUE;
}
// these functions return TRUE on success and string describing an issue on failure
function verifyMessage($message)
{
    if (preg_match('/^[a-zA-Z0-9\ \.,\'\" ?-]{4,250}$/', $message) != 1) { // no match
        return "Message must be 4-250 characters long and consist of letters and digits and special characters (. - , ' \" ?).";
    }
    return TRUE;
}