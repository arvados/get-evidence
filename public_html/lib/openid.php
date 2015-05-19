<?php
    ;

// Copyright: see COPYING
// Authors: see git-blame(1)

// remove this horrible hack. Using system packages for php-openid now.
// Ward, 20150108 
//ini_set ('include_path',
//	 dirname(dirname(dirname(__FILE__))) . "/php-openid"
//	 . PATH_SEPARATOR . ini_get('include_path'));

require_once "config.php";

require_once "Auth/OpenID/Consumer.php";
require_once "Auth/OpenID/MySQLStore.php";
require_once "Auth/OpenID/SReg.php";
require_once "Auth/OpenID/AX.php";

require_once('JWT.php');

function getScheme() {
    $scheme = 'http';
    if (isset($_SERVER['HTTPS']) and $_SERVER['HTTPS'] == 'on') {
        $scheme .= 's';
    }
    return $scheme;
}

function getReturnTo() {
    return sprintf("%s://%s:%s/openid_verify.php?return_url=%s",
                   getScheme(), $_SERVER['SERVER_NAME'],
                   $_SERVER['SERVER_PORT'],
		   urlencode($_REQUEST["return_url"]));
}

function getReturnToOAuth2() {
    return sprintf("%s://%s/openid_verify_oauth2.php",
                   getScheme(), $_SERVER['SERVER_NAME']);
}

function getTrustRoot() {
    return sprintf("%s://%s:%s/",
                   getScheme(), $_SERVER['SERVER_NAME'],
                   $_SERVER['SERVER_PORT']);
}

function getTrustRootOAuth2() {
    return sprintf("%s://%s", getScheme(), $_SERVER['SERVER_NAME'] );
}

function openid_try ($url)
{
  $store = new Auth_OpenID_MySQLStore(theDb());
  $store->createTables();
  $consumer = new Auth_OpenID_Consumer ($store);
  $auth_request = $consumer->begin ($url);
  if (!$auth_request)
    {
      $_SESSION["auth_error"] = "Error: not a valid OpenID.";
      header ("Location: ./");
      exit;
    }
  $sreg_request = Auth_OpenID_SRegRequest::build(array('email'),
						 array('nickname', 'fullname'));
  if ($sreg_request) {
    $auth_request->addExtension($sreg_request);
  }

  // Attribute Exchange (Google ignores Simple Registration)
  // See http://code.google.com/apis/accounts/docs/OpenID.html#Parameters for parameters

  $ax = new Auth_OpenID_AX_FetchRequest;
  $ax->add (Auth_OpenID_AX_AttrInfo::make('http://axschema.org/contact/email',2,1, 'email'));
  $ax->add (Auth_OpenID_AX_AttrInfo::make('http://axschema.org/namePerson/first',1,1, 'firstname'));
  $ax->add (Auth_OpenID_AX_AttrInfo::make('http://axschema.org/namePerson/last',1,1, 'lastname'));
  $auth_request->addExtension($ax);

  if ($auth_request->shouldSendRedirect()) {
    $redirect_url = $auth_request->redirectURL(getTrustRoot(),
					       getReturnTo());

    // If the redirect URL can't be built, display an error
    // message.
    if (Auth_OpenID::isFailure($redirect_url)) {
      die("Could not redirect to server: " . $redirect_url->message);
    } else {
      // Send redirect.
      header("Location: ".$redirect_url);
    }
  } else {
    // Generate form markup and render it.
    $form_id = 'openid_message';
    $form_html = $auth_request->htmlMarkup(getTrustRoot(), getReturnTo(),
					   false, array('id' => $form_id));

    // Display an error if the form markup couldn't be generated;
    // otherwise, render the HTML.
    if (Auth_OpenID::isFailure($form_html)) {
      displayError("Could not redirect to server: " . $form_html->message);
    } else {
      print $form_html;
    }
  }
}


function openid_verify_oauth2() {
  global $gOAuth2ClientID;
  global $gOAuth2ClientSecret;

  $oauth2_code = $_GET['code'];

  $client_id = $gOAuth2ClientID;
  $client_secret = $gOAuth2ClientSecret;

  $discovery = json_decode(file_get_contents('https://accounts.google.com/.well-known/openid-configuration'));
  $ctx = stream_context_create(array(
      'http' => array(
          'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
          'method'  => 'POST',
          'ignore_errors' => TRUE,
          'content' => http_build_query(array(
              'client_id' => $client_id,
              'client_secret' => $client_secret,
              'code' => $oauth2_code,
              'grant_type' => 'authorization_code',
              'redirect_uri' => getReturnToOAuth2(),
              'openid.realm' => getTrustRootOAuth2(),
          )),
      ),
  ));

  $resp = file_get_contents($discovery->token_endpoint, false, $ctx);

  if (!$resp) {
      error_log(json_encode($http_response_header));
      $_SESSION["auth_error"] = "Error: not a valid OpenID.";
      header ("Location: ./");
      exit;
  }

  $resp = json_decode($resp);

  if ($resp->error) {
      error_log(json_encode($http_response_header));
      $_SESSION["auth_error"] =  "An error occured";

      if ($resp->error_description) {
        $_SESSION["auth_error"] = $resp->error_description;
      }
      header ("Location: ./");
      exit;
  }

  $access_token = $resp->access_token;
  $id_token = $resp->id_token;

  // Skip JWT verification: we got it directly from Google via https, nothing could go wrong.
  $id_payload = JWT::decode($resp->id_token, null, false);
  if (!$id_payload->sub) {
      error_log(json_encode($id_payload));
  }

  // To get the fullname we need to do a userinfo request.  Build the GET request
  // with the access token and request JSON response.
  //
  $userinfo_url = $discovery->userinfo_endpoint . "?alt=json&access_token=" . urlencode($access_token);

  $info_resp = file_get_contents($userinfo_url);
  if (!$info_resp) {
      error_log(json_encode($http_response_header));
  } else {
    $userinfo = json_decode($info_resp);
  }

  $user_id = 'google+' . $id_payload->sub;
  $user_email = $id_payload->email;

  $fullname = null;
  if ($userinfo) {
    $fullname = $userinfo->name;
  }

  // Finally, update user information and save session state.
  //
  $sreg = array( 'email' => $user_email, 'fullname' => $fullname );
  openid_user_update ($user_id, $sreg);
}


function openid_verify() {
  $consumer = new Auth_OpenID_Consumer (new Auth_OpenID_MySQLStore(theDb()));

  // Complete the authentication process using the server's
  // response.
  $return_to = getReturnTo();
  $response = $consumer->complete($return_to);

  // Check the response status.
  if ($response->status == Auth_OpenID_CANCEL) {
    // This means the authentication was cancelled.
    $msg = 'Verification cancelled.';
  } else if ($response->status == Auth_OpenID_FAILURE) {
    // Authentication failed; display the error message.
    $msg = "OpenID authentication failed: " . $response->message;
  } else if ($response->status == Auth_OpenID_SUCCESS) {
    // This means the authentication succeeded; extract the
    // identity URL and Simple Registration data (if it was
    // returned).
    $openid = $response->getDisplayIdentifier();
    $esc_identity = htmlentities($openid);

    $success = sprintf('You have successfully verified ' .
		       '<a href="%s">%s</a> as your identity.',
		       $esc_identity, $esc_identity);

    if ($response->endpoint->canonicalID) {
      $escaped_canonicalID = htmlentities($response->endpoint->canonicalID);
      $success .= '  (XRI CanonicalID: '.$escaped_canonicalID.') ';
    }

    $sreg_resp = Auth_OpenID_SRegResponse::fromSuccessResponse($response);
    $sreg = $sreg_resp->contents();

    $ax = new Auth_OpenID_AX_FetchResponse();
    $obj = $ax->fromSuccessResponse($response);
    if ($obj) {
      function ax_get ($obj, $url) {
	if (!$obj) return "";
	$x = $obj->get ($url);
	if (is_array ($x) && (count($x) > 0) && is_string($x[0])) return $x[0];
	return "";
      }
      if ($x = ax_get($obj, 'http://axschema.org/contact/email')) $sreg["email"] = $x;
      if ($x = ax_get($obj, 'http://axschema.org/namePerson/first'))
	$sreg["fullname"] = $x . " " . ax_get ($obj, 'http://axschema.org/namePerson/last');
    }

    openid_user_update ($openid, $sreg);
    unset ($_SESSION["auth_error"]);
    return true;
  }

  $_SESSION["auth_error"] = $msg;
  return false;
}

function openid_user_update ($openid, $sreg)
{
  openid_create_tables ();
  theDb()->query ('INSERT IGNORE INTO eb_users (oid) values (?)', array ($openid));
  foreach (array ('nickname', 'fullname', 'email') as $key)
    {
      if (array_key_exists ($key, $sreg))
	theDb()->query ("UPDATE eb_users SET $key=? WHERE oid=?",
		     array ($sreg[$key], $openid));
    }
  $user =& theDb()->getRow ('SELECT * FROM eb_users WHERE oid=?', array($openid));
  if (!strlen ($user["nickname"])) $user["nickname"] = $user["fullname"];
  if (!strlen ($user["nickname"])) $user["nickname"] = $user["email"];
  if (!strlen ($user["nickname"])) $user["nickname"] = substr(md5($openid),0,8);
  $_SESSION["user"] = $user;
}

function openid_login_as_robot ($robot_name)
{
  openid_user_update ($_SESSION["oid"] = "none:///".md5($robot_name),
		      array ("nickname" => $robot_name,
			     "fullname" => $robot_name));
}

function openid_create_tables ()
{
  theDb()->query ('
CREATE TABLE IF NOT EXISTS eb_users (
  oid VARCHAR(255) NOT NULL PRIMARY KEY,
  nickname VARCHAR(64),
  fullname VARCHAR(128),
  email VARCHAR(128),
  is_admin TINYINT NOT NULL DEFAULT 0
)');
  theDb()->query ('ALTER TABLE eb_users ADD is_admin TINYINT NOT NULL DEFAULT 0');
  theDb()->query ('ALTER TABLE eb_users ADD tos_date_signed INTEGER UNSIGNED;');
}
?>
