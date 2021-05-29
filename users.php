<?php


require_once 'vendor/autoload.php';

use Slim\Http\Request;
use Slim\Http\Response;



// All templates will be given userSession variable
$container['view']->getEnvironment()->addGlobal('userSession', $_SESSION['user'] ?? null );

$container['view']->getEnvironment()->addGlobal('flashMessage', getAndClearFlashMessage());

$container['view']->getEnvironment()->addGlobal('picture', convertPicture());


// **************** REGISTER USER ********************

function convertPicture() {
    $picture = null;
    $convertedPicture = ($_SESSION['user']['picture'] ??  null);
    if(!empty($convertedPicture)) {
        $picture = base64_encode($convertedPicture);
    }
    return $picture;
}


// STATE 1: first display the form
$app->get('/register', function (Request $request, Response $response, $args) {
    return $this->view->render($response, 'register.html.twig', ['userSession' => $_SESSION['user'] ?? null]);
});

//STATE 2&3: receiving form submission
$app->post('/register', function (Request $request, Response $response, $args) {
    $name = $request->getParam('name');
    $email = $request->getParam('email');
    $pass1 = $request->getParam('pass1');
    $pass2 = $request->getParam('pass2');
    $postal = $request->getParam('postal');
    $phone = $request->getParam('phone');
    $pic = $request->getParam('image');

    // ***** VALIDATION *****
    $errorList = [];

    // verify user name
    $result = verifyUserName($name);
    if ($result !== TRUE) { $errorList[] = $result; }

    // verify email
    if (filter_var($email, FILTER_VALIDATE_EMAIL) == FALSE) {
        array_push($errorList, "Email does not look valid");
        $email = "";
    } else {
        // is email already in use?
        $record = DB::queryFirstRow("SELECT userId FROM users WHERE email=%s", $email);
        if ($record) {
            array_push($errorList, "This email is already registered");
            $email = "";
        }
    }

    // verify password
    $result = verifyPasswordQuailty($pass1, $pass2);
    if ($result !== TRUE) { $errorList[] = $result; };

    // verify postal code
    $result = verifyPostalCode($postal);
    if ($result !== TRUE) { $errorList[] = $result; };

    // verify phone number
    $result = verifyPhone($phone);
    if ($result !== TRUE) { $errorList[] = $result; };

    // verify profile picture
    $hasPhoto = false;
    $mimeType = "";
    $uploadedImage = $request->getUploadedFiles()['image'];
    if ($uploadedImage->getError() != UPLOAD_ERR_NO_FILE) { // was anything uploaded?
        $hasPhoto = true;
        $result = verifyUploadedPhoto($uploadedImage, $mimeType);
        if ($result !== TRUE) {
            $errorList[] = $result;
        } 
    }


    if ($errorList) {
        return $this->view->render($response, 'register.html.twig',
                ['errorList' => $errorList, 'v' => ['name' => $name, 'email' => $email, 'postal' => $postal ]  ]);
    } else {
        $photoData = null;
        if ($hasPhoto) {
            $photoData = file_get_contents($uploadedImage->file);
        }
        //
        global $passwordPepper;
        $pwdPeppered = hash_hmac("sha256", $pass1, $passwordPepper);
        $pwdHashed = password_hash($pwdPeppered, PASSWORD_DEFAULT); // PASSWORD_ARGON2ID);
        DB::insert('users', ['name' => $name, 'email' => $email, 'password' => $pwdHashed, 'postalCode' => $postal,
                        'phone' => $phone, 'picture' => $photoData, 'imageMimeType' => $mimeType]);
        //return $this->view->render($response, 'register_success.html.twig');
        setFlashMessage("You've been registered. Please login now");
        return $response->withRedirect('/login');
    }
});

// used via AJAX
$app->get('/isemailtaken/[{email}]', function ($request, $response, $args) {
    $email = isset($args['email']) ? $args['email'] : "";
    $record = DB::queryFirstRow("SELECT userId FROM users WHERE email=%s", $email);
    if ($record) {
        return $response->write("Email already in use");
    } else {
        return $response->write("");
    }
});

// ******************** LOGIN USER ************************

// STATE 1: first display
$app->get('/login[/{params:.*}]', function (Request $request,Response $response, $args) {
    return $this->view->render($response, 'login.html.twig');
});

// STATE 2&3: receiving submission
$app->post('/login[/{params:.*}]', function ($request, $response, $args) use ($log) {
    $email = $request->getParam('email');
    $password = $request->getParam('password');
    //
    $record = DB::queryFirstRow("SELECT userId,name,email,password,picture FROM users WHERE email=%s", $email);
    $loginSuccess = false;
    if ($record) {
        global $passwordPepper;
        $pwdPeppered = hash_hmac("sha256", $password, $passwordPepper);
        $pwdHashed = $record['password'];
        if (password_verify($pwdPeppered, $pwdHashed)) {
            $loginSuccess = true;
        }
        // WARNING: only temporary solution to allow for old plain-text passwords to continue to work
        // Plain text passwords comparison
        else if ($record['password'] == $password) {
            $loginSuccess = true;
        }
    }
    //
    if (!$loginSuccess) {
        $log->info(sprintf("Login failed for email %s from %s", $email, $_SERVER['REMOTE_ADDR']));
        return $this->view->render($response, 'login.html.twig', [ 'error' => true ]);
    } else {
        unset($record['password']); // for security reasons remove password from session
        $_SESSION['user'] = $record; // remember user logged in
        $log->debug(sprintf("Login successful for email %s, uid=%d, from %s", $email, $record['id'], $_SERVER['REMOTE_ADDR']));
        //return $this->view->render($response, 'index.html.twig', ['userSession' => $_SESSION['user'] ] );
        setFlashMessage("You've been logged in");
        if($args['params']) { // if there is link to redirect, then transfer user after login to this page
            return $response->withRedirect("/".$args['params']);
        }
        return $response->withRedirect("/");

      }
});



// ************** LOGOUT USER ********************

$app->get('/logout', function (Request $request, Response $response, $args) use ($log) {
    $log->debug(sprintf("Logout successful for uid=%d, from %s", @$_SESSION['user']['id'], $_SERVER['REMOTE_ADDR']));
    unset($_SESSION['user']);
    setFlashMessage("You've been logged out");
    return $response->withRedirect("/");
    //return $this->view->render($response, 'logout.html.twig', ['userSession' => null ]);
});

$app->get('/session', function ($request, $response, $args) {
    echo "<pre>\n";
    print_r($_SESSION);
    return $response->write("");
});


// ************************ PROFILE USER *********************

// Display user profile information
$app->get('/myprofile', function(Request $request, Response $response, $args) {
    if(!isset($_SESSION['user'])) {
        return $response->withRedirect('/login');
    }
    $email = $_SESSION['user']['email'];
    $record = DB::queryFirstRow("SELECT userId,name,email,postalCode,phone,picture FROM users WHERE email=%s", $email); // get user from db
    return $this->view->render($response, 'myprofile.html.twig', ['user' => $record]); // path information about user to the template
});

// Return user picture
$app->get('/userimg/{id:[0-9]+}', function ($request, $response, $args) {
    // OPTIONAL - depending on security levels
    // if (!isset($_SESSION['user'])) { // refuse if adimg not logged in
    //     $response = $response->withStatus(403);
    //     return $this->view->render($response, '/error_access_denied.html.twig');
    // } 
    $userimg = DB::queryFirstRow("SELECT picture, imageMimeType FROM users WHERE userId=%d", $args['id']);
    if (!$userimg) { // not found - FIXME
        return $response->withStatus(404);
    }
    $response->getBody()->write($userimg['picture']);
    return $response->withHeader('Content-type', $userimg['imageMimeType']);
});
// Modify user contact information
$app->post('/myprofile/editcontacts', function(Request $request, Response $response, $args) {
    if(!isset($_SESSION['user'])) {
        return $response->withRedirect('/login');
    }
    $name = $request->getParam('name');
    $email = $request->getParam('email');
    $postal = $request->getParam('postal');
    $phone = $request->getParam('phone');
    // ***** VALIDATION *****
    $errorList = [];
    // verify user name
    $result = verifyUserName($name);
    if ($result !== TRUE) { $errorList[] = $result; }

    // verify email
    if($email !== $_SESSION['user']['email']) // check if current user email not equals input email than validate it 
    {
        if (filter_var($email, FILTER_VALIDATE_EMAIL) == FALSE) {
            array_push($errorList, "Email does not look valid");
        } else {
            // is email already in use?
            $record = DB::queryFirstRow("SELECT userId FROM users WHERE email=%s", $email);
            if ($record) {
                array_push($errorList, "This email is already registered");
            }
        }
    }
    // verify postal code
    $result = verifyPostalCode($postal);
    if ($result !== TRUE) { $errorList[] = $result; };

    // verify phone number
    $result = verifyPhone($phone);
    if ($result !== TRUE) { $errorList[] = $result; };

    if ($errorList) {

        setFlashMessage("Please don't cheat the system. You put not valid information in edit contact form!!");
        return $response->withRedirect('/myprofile');

    } else {
        DB::update('users',['name' => $name,'email' => $email,'postalCode' => $postal, // update record in the db
            'phone' => $phone], 'userId=%s', $_SESSION['user']['userId']);
            
            //$_SESSION['user']['email'] = $email; // update email in the session
            updateUserInSession(); // update user session after update information
            setFlashMessage("Contact information has changed");
            return $response->withRedirect('/myprofile');
    }
});

// Modify user password
$app->post('/myprofile/changepass', function(Request $request, Response $response, $args) {
    if(!isset($_SESSION['user'])) {
        return $response->withRedirect('/login');
    }
    $oldPassword = $request->getParam('oldpassword');
    $newPassword = $request->getParam('newpassword');
    $newPasswordRepeat = $request->getParam('newpasswordrepeat');
    // ***** VALIDATION *****
    $errorList = [];
    // verify old password
    $record = DB::queryFirstRow("SELECT password FROM users WHERE email=%s",$_SESSION['user']['email']);
    $oldPassSuccess = false;
    if ($record) {
        global $passwordPepper;
        $pwdPeppered = hash_hmac("sha256", $oldPassword, $passwordPepper);
        $pwdHashed = $record['password'];
        if (password_verify($pwdPeppered, $pwdHashed)) {
            $oldPassSuccess = true;
        } else {
            $errorList[] = "Wrong old password"; // if old pass not match than add error in the error list
        }
    }
    // verify new passwords are valid
    $result = verifyPasswordQuailty($newPassword, $newPasswordRepeat);
    if ($result !== TRUE) { $errorList[] = $result; };

    if ($errorList) {
        foreach($errorList as $error) {
            setFlashMessage($error); // message for notify user about errors after form submit
        }
        return $response->withRedirect('/myprofile');
    } else {
        unset($record['password']); // for security reasons remove password from session
        global $passwordPepper;
        $pwdPeppered = hash_hmac("sha256", $newPassword, $passwordPepper);
        $pwdHashed = password_hash($pwdPeppered, PASSWORD_DEFAULT); // PASSWORD_ARGON2ID);

        DB::update('users',['password' => $pwdHashed], 'userId=%s', $_SESSION['user']['userId']);
        setFlashMessage("Password changed successfully");
        return $response->withRedirect('/myprofile');
    }
});

// Modify user picture
$app->post('/myprofile/changeimage', function(Request $request, Response $response, $args) {
    if(!isset($_SESSION['user'])) {
        return $response->withRedirect('/login');
    }
    // ***** VALIDATION *****
    $errorList = [];
    // verify profile picture
    $hasPhoto = false;
    $mimeType = "";
    $uploadedImage = $request->getUploadedFiles()['image'];
    if ($uploadedImage->getError() != UPLOAD_ERR_NO_FILE) { // was anything uploaded?
        $hasPhoto = true;
        $result = verifyUploadedPhoto($uploadedImage, $mimeType);
        if ($result !== TRUE) {
            $errorList[] = $result;
        } 
    }

    if ($errorList) {
        foreach($errorList as $error) {
            setFlashMessage($error); // message for notify user about errors after form submit
        }
        return $response->withRedirect('/myprofile');
    } else {
        $photoData = null;
        if ($hasPhoto) {
            $photoData = file_get_contents($uploadedImage->file);
        }
        //
        DB::update('users',['picture' => $photoData], 'userId=%s', $_SESSION['user']['userId']);
        setFlashMessage("Picture changed successfully");
        updateUserInSession(); // update user session after update information
        return $response->withRedirect('/myprofile');
    }
});

// validate email AJAX POST
$app->post('/isemailvalid', function ($request, $response, $args) {
    $email = $request->getParam('email');
    $userEmail = isset($_SESSION['user']) ? $_SESSION['user']['email'] : ""; // grab email from session if exist
    if($email !== $userEmail) // check if current user email not equals input email than validate it 
    {
        $record = DB::queryFirstRow("SELECT userId FROM users WHERE email=%s", $email);
        if ($record) {
            return $response->write("false"); // Email alrready registered
        }
    }
    return $response->write("true"); // Email is not in use
   
});

// ********* FUNCTIONS **********

// these functions return TRUE on success and string describing an issue on failure
function verifyUserName($name) {
    if (preg_match('/^[a-zA-Z0-9\ \\._\'"-]{4,50}$/', $name) != 1) { // no match
        return "Name must be 4-50 characters long and consist of letters, digits, "
            . "spaces, dots, underscores, apostrophies, or minus sign.";
    }
    return TRUE;
}

function verifyPassword($pass) {
    if ((strlen($pass) < 6) || (strlen($pass) > 100)
            || (preg_match("/[A-Z]/", $pass) == FALSE )
            || (preg_match("/[a-z]/", $pass) == FALSE )
            || (preg_match("/[0-9]/", $pass) == FALSE )) {
        return "Password must be 6-100 characters long, "
            . "with at least one uppercase, one lowercase, and one digit in it";
    }
    return TRUE;
}

function verifyPasswordQuailty($pass1, $pass2) {
    if ($pass1 != $pass2) {
        return "Passwords do not match";
    } else {
        /*
        // FIXME: figure out how to use case-sensitive regexps with Validator
        if (!Validator::length(6,100)->regex('/[A-Z]/')->validate($pass1)) {
            return "VALIDATOR. Password must be 6-100 characters long, "
                . "with at least one uppercase, one lowercase, and one digit in it";
        } */
        if ((strlen($pass1) < 6) || (strlen($pass1) > 100)
                || (preg_match("/[A-Z]/", $pass1) == FALSE )
                || (preg_match("/[a-z]/", $pass1) == FALSE )
                || (preg_match("/[0-9]/", $pass1) == FALSE )) {
            return "Password must be 6-100 characters long, "
                . "with at least one uppercase, one lowercase, and one digit in it";
        }
    }
    return TRUE;
}

function verifyPostalCode($postal) {
    if(preg_match('/^[ABCEGHJ-NPRSTVXY]\d[ABCEGHJ-NPRSTV-Z][ -]?\d[ABCEGHJ-NPRSTV-Z]\d$/i', $postal) != 1) { //no match
        return "Postal code must be formatted like so: A1B 2C3";
    }
    return TRUE;
}

function verifyPhone($phone) {
    if(preg_match('/^(\+\d{1,2}\s)?\(?\d{3}\)?[\s.-]?\d{3}[\s.-]?\d{4}$/', $phone) != 1) { // no match
        return "Phone number must be at least 10 digits long, including the area code.";
    }
    return TRUE;
}

function verifyUploadedPhoto($photo, &$mime = null) {
    if ($photo->getError() != 0) {
        return "Error uploading photo " . $photo->getError();
    } 
    if ($photo->getSize() > 1024*1024) { // 1MB
        return "File too big. 1MB max is allowed.";
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
        case 'image/jpeg': $ext = "jpg"; break;
        case 'image/gif': $ext = "gif"; break;
        case 'image/png': $ext = "png"; break;
        default:
            return "Only JPG, GIF and PNG file types are allowed";
    } 
    if (!is_null($mime)) {
        $mime = $info['mime'];
    }
    return TRUE;
}

// ** LOGOUT USING FLASH MESSAGES TO CONFIRM THE ACTION **
function setFlashMessage($message) {
    $_SESSION['flashMessage'] = $message;
}

// returns empty string if no message, otherwise returns string with message and clears it
function getAndClearFlashMessage() {
    if (isset($_SESSION['flashMessage'])) {
        $message = $_SESSION['flashMessage'];
        unset($_SESSION['flashMessage']);
        return $message;
    }
    return "";
}

// Update session after user change some information in his profile
function updateUserInSession() {
    if(isset($_SESSION['user'])) {
        $record = DB::queryFirstRow("SELECT userId,name,email,picture FROM users WHERE userId=%s", $_SESSION['user']['userId']);
        $_SESSION['user'] = $record; // remember user logged in
    }
}