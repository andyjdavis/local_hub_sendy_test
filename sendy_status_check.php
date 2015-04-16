<?php

define('CLI_SCRIPT', true);

require('../../../../config.php');
require_once($CFG->libdir.'/clilib.php');      // Cli only functions.

require($CFG->dirroot. '/local/hub/lib.php');

raise_memory_limit(MEMORY_HUGE);

$email = 'test@example.com';

// Site specific configuration.
$sendyurl = '';
$sendyapikey = '';
$sendylistid = '';
// End of site specific configuration.

$status = get_sendy_status($sendyurl, $sendyapikey, $sendylistid, $email);
echo "$email status => $status\n";
