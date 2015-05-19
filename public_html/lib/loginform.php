<?php ; // -*- mode: java; c-basic-offset: 2; tab-width: 8; indent-tabs-mode: nil; -*-

require_once("lib/openid.php");

global $gOpenidEasyProviders;
foreach ($gOpenidEasyProviders as $url => $name) {
?>
  <form action="/openid_start.php" method="post">
    <input type="hidden" name="return_url" value="<?=htmlentities($_SERVER["REQUEST_URI"])?>" />
    <input type="hidden" name="auth_url" id="auth_url" value="<?=htmlentities($url)?>" />
    <input type="submit" value="<?=htmlentities($name)?> login" class="button" />
  </form>
  <br />
<?php
}
?>


<!-- OAuth 2.0 -->

<form action="https://accounts.google.com/o/oauth2/auth" method="get">
  <input type="hidden" name="response_type" value="code" />

  <input type="hidden" name="client_id"
  <?php
  global $gOAuth2ClientID;
  print "value='$gOAuth2ClientID'";
  ?>
  />

  <input type="hidden" name="redirect_uri" value="<?php print getReturnToOAuth2(); ?>" />

  <input type="hidden" name="state" value="<?php print $_SERVER['REQUEST_URI']; ?>" />
  <input type="hidden" name="scope" value="email openid profile" />
  <input type="hidden" name="access_type" value="online" />
  <input type="hidden" name="approval_prompt" value="auto" />
  <input type="hidden" name="openid.realm" value="<?php print getTrustRootOAuth2(); ?>" />
  <input type="submit" value="Log in using Google" />
</form>


<?php
if (isset($_SESSION) && array_key_exists ("auth_error", $_SESSION)) {
  print "<br />" . htmlspecialchars($_SESSION["auth_error"]);
  unset ($_SESSION["auth_error"]);
}
?>
