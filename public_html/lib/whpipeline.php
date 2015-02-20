<?php ;

// Copyright: see COPYING
// Authors: see git-blame(1)

// wh is the name of the 'old' arvados/keep (wh stands for 'ware house').
// Instead of creating files in the local file system, the 'genome'
// view will query the Arvados API directly and fetch the relevant
//
//
function run_whpipeline($locator, $shasum, $quick=false)
{
  // submit the dataset for processing via whpipeline
  $in_dir = $GLOBALS['gBackendBaseDir'].'/upload/'.$shasum;
  $out_dir = $in_dir . '-out';
  @mkdir($in_dir);
  @mkdir($out_dir);
  if (!is_link($in_dir.'/input.locator')) {
      @symlink($locator, $in_dir.'/input.locator');
  }
  $status_json = $out_dir.'/whpipeline-status.json';

  $cmd = "/get-evidence/public_html/submit_GE_pipeline";
  $cmd .= ' '.escapeshellarg( $locator );
  $cmd .= ' 2>&1 > '.escapeshellarg("$out_dir/whpipeline.stdout");

  shell_exec('echo ' . escapeshellarg($cmd) . ' | at now');

  $cmd = 'touch '.escapeshellarg($out_dir).'/whpipeline.lock > /dev/null 2>&1 ';
  shell_exec( $cmd );

  $cmd = 'touch '.escapeshellarg($out_dir).'/lock';
  shell_exec( $cmd );

}

