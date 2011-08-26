#!/usr/bin/env php
<?php

// Limit file_get_contents()
define ('MAX_READ_SIZE', 1000000);

$command = basename(array_shift($argv));
main($argv, $argc);

function usage() {
  global $command;
  printf ("Usage: %s logfile destination\n", $command);
  exit(1);
}

function process_log_entry ($log_entry, $destination, &$last_database) {

  if (preg_match('/^use ([a-z0-9_]+);$/im', $log_entry, $matches)) {
    $last_database = $matches[1];
  }

  if ($last_database) {
    file_put_contents("$destination/$last_database-mysqld-slow.log", $log_entry, FILE_APPEND);
  }
}

function process_query_log ($logfile, $destination) {

  $log_offset = 0;
  $last_database = NULL;
  $processing = TRUE;

  while ($processing) {

    // Load part of the file, explode it on entry separator
    // TODO: Confirm separator still works if mysqld relaunched mid-log
    $log = file_get_contents($logfile, false, NULL, $log_offset, MAX_READ_SIZE);
    $entries = explode(";\n#", $log);

    // Tweak behavior based on how far we've read
    // TODO: Confirm it works for cases where filesize <= MAX_READ_SIZE
    $bytes_read = strlen($log);
    if ($bytes_read < MAX_READ_SIZE) {
      // Final read: stop looping
      $processing = FALSE;
    }
    else {
      // Intermediate read: drop the last chunk (partially read entry)
      // Need to make sure that the query itself isn't more than MAX_READ_SIZE
      if (count($entries) > 1) {
       $last = array_pop($entries);
      }
    }

    // Loop entries
    foreach($entries as $idx => $log_entry) {
      $log_entry = "#$log_entry;\n" ;
      process_log_entry($log_entry, $destination, $last_database);
      $log_offset += strlen($log_entry);
    }
  }
}

function main($argv, $argc) {
  exec('renice +10 -p ' . getmypid());
  $logfile = array_shift($argv);
  $destination = array_shift($argv);
  if (!empty($logfile) && !empty($destination)) {
    process_query_log ($logfile, $destination);
  }
  else {
    usage();
  }
  exit(0);
}

