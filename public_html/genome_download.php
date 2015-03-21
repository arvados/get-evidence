<?php

// Copyright: see COPYING
// Authors: see git-blame(1)

include "lib/setup.php";

// Find the size of a fully qualified locator and filename.
// For example:
//   cafecafcafecafcafecafcafecafcafe+80/mydir/myfile.txt
//
// returns size as a string
// return -1 on error
//
function arv_keep_size( $locator ) {
  if (!preg_match('/^([\da-f]{32}(\+\d+)?)\/(.*)/', $locator, $m)) {
    return -1;
  }
  $pdh = $m[1];
  $pdh_esc = escapeshellarg($pdh);

  $fqfn = $m[3];
  $cmp_dirname = "./" . dirname($fqfn) + " ";
  $n = strlen($cmp_dirname);

  $base_fn = basename($fqfn);

  $manifest = preg_split( '/\n/', trim(`HOME=/home/trait arv-get --no-progress ''$pdh_esc `) );
  foreach ($manifest as $key => $val) {

    $s = substr( $val, 0, $n );
    if ( $s == $cmp_dirname) {

      $block_fn_list = preg_split( '/ /', $val );
      foreach ($block_fn_list as $ind => $pos_sz_fn) {
        if (preg_match( '/^(\d+):(\d+):(.*)$/', $pos_sz_fn, $psf_m )) {
          $sz = $psf_m[2];
          $f = $psf_m[3];
          if ( $f == $base_fn ) { return($sz); }
        }
      }
    }
  }
  return -1;
}


$ext = '';
$genome_id = $_REQUEST['download_genome_id'];
if (@$_REQUEST['download_type'] == 'ns') {
    $ext = $ext . '.ns';
    $fullPath = $GLOBALS["gBackendBaseDir"] . "/upload/" . $genome_id . "-out/ns";
} else {
    $fullPath = $GLOBALS["gBackendBaseDir"] . "/upload/" . $genome_id . "/genotype";
}

if (! file_exists($fullPath)) {
  if (file_exists($fullPath . '.gff')) {
    $ext = $ext . '.gff';
    $fullPath = $fullPath . '.gff';
  } elseif (file_exists($fullPath . '.gz')) {
    $ext = $ext . '.gz';
    $fullPath = $fullPath . '.gz';
  } elseif (file_exists($fullPath . '.gff.gz')) {
    $ext = $ext . '.gff.gz';
    $fullPath = $fullPath . '.gff.gz';
  } elseif (file_exists($fullPath . '.bz2')) {
    $ext = $ext . '.bz2';
    $fullPath = $fullPath . '.bz2';
  } elseif (file_exists($fullPath . '.gff.bz2')) {
    $ext = $ext . '.gff.bz2';
    $fullPath = $fullPath . '.gff.bz2';
  } elseif (is_link($locator_symlink = $GLOBALS["gBackendBaseDir"] . "/upload/" . $genome_id . "/input.locator")) {
    $locator = readlink($locator_symlink);
    $locator_esc = escapeshellarg($locator);

    # We have a difference between 'old-style' locators and 'new-style' locators.
    # In the newer version, the input.locator is a symlink to the file within the collection.
    # The 'old-style' is just a link to the collection and the input file is assumed to be
    # the only file in that collection.
    #
    # We differentiate between these two cases here by looking at what the input.locator links
    # to.
    #
    # Also, some 'old-style' locators have '+K@Ant' strings the end.
    #
    if (preg_match('/^([\da-f]{32}(\+\d+)?[^\/]*)$/', $locator, $m)) {

      # old-style locator, assume first (and only) file is the one we want.
      #
      $pdh = $m[0];
      $pdh_esc = escapeshellarg($pdh);
      $manifest = trim(`HOME=/home/trait arv-get --no-progress ''$pdh_esc`);
      if (preg_match('/^(\.[^\s]*) .* 0:(\d+):(\S+)$/', $manifest, $regs)) {
        //$passthru_command = "whget ".escapeshellarg("$locator/**/$regs[2]");
        $subdir = preg_replace( '/^\.\/?/', '', $regs[1] );
        if ( $subdir != "" ) { $subdir = $subdir . "/"; }
        $passthru_command = "arv-get --no-progress ".escapeshellarg("$pdh/$subdir$regs[3]");
        $fsize = $regs[2];
        $ext = preg_replace ('/^.*?((\.\w{3})?(\.[bg]z2?)?)$/', '\1', $regs[2]);
      }

    } elseif (preg_match('/^([\da-f]{32}(\+\d+)?)\/(.*)/', $locator, $m)) {

      # new-style locator.  We need to do some actual processing of the manifest
      # file to get the size which is what arv_keep_size is for.
      #
      $loc_esc = escapeshellarg($locator);
      $fsize = arv_keep_size( $locator );
      $fn = basename($locator);
      $ext = preg_replace ('/^.*?((\.\w{3})?(\.[bg]z2?)?)$/', '\1', $fn);
      if ($fsize != -1) {
        $passthru_command = "arv-get --no-progress ".escapeshellarg($locator);
      }
    }

  }

}

$nickname = $_REQUEST['download_nickname'];
$nickname = preg_replace('/ +/', '_', $nickname) . $ext;

$user = getCurrentUser();
$db_query = theDb()->getAll ("SELECT * FROM private_genomes WHERE shasum=?",
                                    array($genome_id));

# check you should have permission
$permission = false;
foreach ($db_query as $result) {
    if ($result['oid'] == $user['oid']
	|| $result['is_public'] > 0
	|| @$_REQUEST['access_token'] == hash_hmac('md5', $genome_id, $GLOBALS['gSiteSecret'])
	|| $result['oid'] == $pgp_data_user
        || $result['oid'] == $public_data_user)
	$permission = true;
}

if ($permission) {
    if (isset($passthru_command)) {
	send_headers($nickname, $fsize);
	ob_clean();
	flush();

  putenv("HOME=/home/trait");
  passthru($passthru_command);

    }
    else if (is_readable ($fullPath)) {
	$fsize = filesize($fullPath);
	send_headers($nickname, $fsize);
	ob_clean();
	flush();
	readfile($fullPath);
    } else {
        print "Error: Unable to open file for download!";
    }
} else {
    print "Sorry, you don't have permission to download this genome.";
}

function send_headers($nickname, $fsize)
{
    header("Content-type: text/plain");
    header("Content-Disposition: attachment; filename=\"" . $nickname . "\"");
    if ($fsize)
	header("Content-length: $fsize");
    header("Cache-control: private"); //use this to open files directly
}

?>
