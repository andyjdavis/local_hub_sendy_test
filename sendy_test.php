<?php

/**
 * A script to test Sendy updates sent from the local_hub Moodle plugin.
 *
 * Registered sites are created and updated and the associated Sendy subscriptions checked.
 *
 * INSTRUCTIONS:
 * Place this file it in local/hub/admin/cli.
 * Check your Sendy config in the local_hub settings within the Moodle site.
 * Add those same values to the $sendyurl, $sendyapikey and $sendylistid variables below.
 * Run "php sendy_test.php". You may need to sudo depending on your file system permissions.
 *
 * @copyright 2015 Andrew Davis
 */

define('CLI_SCRIPT', true);

require('../../../../config.php');
require_once($CFG->libdir.'/clilib.php');      // Cli only functions.

require($CFG->dirroot. '/local/hub/lib.php');

$hub = new local_hub();
raise_memory_limit(MEMORY_HUGE);

// Site specific configuration.
$sendyurl = '';
$sendyapikey = '';
$sendylistid = '';
// End of site specific configuration.

$time = time();
$testemail1 = 'test1_'.$time.'@moodle.com';
$testemail2 = 'test2_'.$time.'@moodle.com';
$testemail3 = 'test3_'.$time.'@moodle.com';

$site = new stdClass();
$site->url = 'http://example.com';
$site->name = 'sendy test site';
$site->description = 'this is a test site for testing sendy';
$site->secret = '12345';
$site->timeregistered = $time;
$site->contactable = 1;
$site->unreachable = 0;
$site->timeunreachable = 0;
$site->score = 8;
$site->visible = 1;
$site->contactemail = $testemail1;
$site->emailalert = 1;

$site = $hub->add_site($site);
$status = get_sendy_status($sendyurl, $sendyapikey, $sendylistid, $testemail1);
if ($status != 'Subscribed') {
    delete_test_site($site->id);
    echo "Newly added site should have been subscribed\n";
    echo "status returned (".$status.")\n";
    die;
}
echo "Newly added site was correctly subscribed\n";

$site->emailalert = 0;
$hub->update_site($site);
$status = get_sendy_status($sendyurl, $sendyapikey, $sendylistid, $testemail1);
if ($status != 'Unsubscribed') {
    delete_test_site($site->id);
    echo "Updated site should have been unsubscribed\n";
    echo "status returned (".$status.")\n";
    die;
}
echo "Updated site was correcly unsubscribed\n";

$site->emailalert = 1;
$hub->update_site($site);
$status = get_sendy_status($sendyurl, $sendyapikey, $sendylistid, $testemail1);
if ($status != 'Unsubscribed') {
    delete_test_site($site->id);
    echo "Previously unsubscribed site should have been left unsubscribed\n";
    echo "status returned (".$status.")\n";
    die;
}
echo "Previously unsubscribed site was correctly left unsubscribed\n";


// Directly insert two new sites into the database for the batch Sendy update to find.
// Due to the emailalert value the first should be subscribed, the second should not be.
$site2 = new stdClass();
$site2->url = 'http://example2.com';
$site2->name = 'sendy test site';
$site2->description = 'this is a second test site for testing sendy';
$site2->secret = '123456';
$site2->timeregistered = $time;
$site2->contactable = 0;
$site2->unreachable = 0;
$site2->timeunreachable = 0;
$site2->score = 8;
$site2->visible = 1;
$site2->contactemail = $testemail2;
$site2->emailalert = 1;
$site2->id = $DB->insert_record('hub_site_directory', $site2);

$site3 = new stdClass();
$site3->url = 'http://example2.com';
$site3->name = 'sendy test site';
$site3->description = 'this is a third test site for testing sendy';
$site3->secret = '1234567';
$site3->timeregistered = $time;
$site3->contactable = 1;
$site3->unreachable = 0;
$site3->timeunreachable = 0;
$site3->score = 8;
$site3->visible = 1;
$site3->contactemail = $testemail3;
$site3->emailalert = 0;
$site3->id = $DB->insert_record('hub_site_directory', $site3);

$sites = $hub->get_sites();
$sitestoupdate = array();
// Only include our test sites to make this run quickly.
foreach ($sites as $k => $site) {
    if ($site->contactemail == $testemail1
        || $site->contactemail == $testemail2
        || $site->contactemail == $testemail3) {

        $sitestoupdate[] = $site;
    }
}
echo "Performing batch update of ".count($sitestoupdate)." sites\n";
update_sendy_list_batch($sitestoupdate);

$status = get_sendy_status($sendyurl, $sendyapikey, $sendylistid, $testemail1);
if ($status != 'Unsubscribed') {
    delete_test_site($site->id);
    delete_test_site($site2->id);
    delete_test_site($site3->id);
    echo "Previously unsubscribed site should not have been resubscribed by batch update\n";
    echo "status returned (".$status.")\n";
    die;
}
echo "Previously unsubscribed site was correctly left unsubscribed\n";

$status = get_sendy_status($sendyurl, $sendyapikey, $sendylistid, $testemail2);
if ($status != 'Subscribed') {
    delete_test_site($site->id);
    delete_test_site($site2->id);
    delete_test_site($site3->id);
    echo "Manually inserted site should have been subscribed by batch update\n";
    echo "status returned (".$status.")\n";
    die;
}
echo "Manually inserted site with emailalert==1 was correctly subscribed\n";

$status = get_sendy_status($sendyurl, $sendyapikey, $sendylistid, $testemail3);
if ($status != 'Email does not exist in list') {
    delete_test_site($site->id);
    delete_test_site($site2->id);
    delete_test_site($site3->id);
    echo "Manually inserted site should NOT have been subscribed by batch update\n";
    echo "status returned (".$status.")\n";
    die;
}
echo "Manually inserted site with emailalert==0 was correctly left unsubscribed\n";

// If a subscribed email belongs to a site that unregisters it stays subscribed.
// Not being associated with a currently registered site could be considered grounds for unsubscription.
$hub->unregister_site($site2);
$status = get_sendy_status($sendyurl, $sendyapikey, $sendylistid, $testemail2);
if ($status != 'Subscribed') {
    delete_test_site($site->id);
    echo "Unregistered site should have stayed subscribed\n";
    echo "status returned (".$status.")\n";
    die;
}
echo "Unregistered site was correcly left subscribed\n";

delete_test_site($site->id);
delete_test_site($site2->id);
delete_test_site($site3->id);
echo "Testing successful\n";

function delete_test_site($siteid) {
    global $DB;
    $DB->delete_records('hub_site_directory', array('id' => $siteid));
}
