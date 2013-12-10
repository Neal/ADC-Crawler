#!/usr/bin/env php
<?php

require_once __dir__ . '/config.php';
require_once __dir__ . '/include/classes/ADC.php';
require_once __dir__ . '/include/classes/iOSDC.php';
require_once __dir__ . '/include/classes/Notifications.php';

if (__DEBUG__ !== true) error_reporting(E_ERROR | E_PARSE);

function console_print($message) {
	print date('H:i:s') . '  ' . $message . PHP_EOL;
}

function select_value_from_database($key, $database = 'adc') {
	global $DB, $memcache;
	$mckey = md5('ADC:'.$key.SALT);
	if ($memcache->get($mckey) && __DEBUG__ !== true) {
		$value = $memcache->get($mckey);
	} else {
		$stmt = $DB->prepare("SELECT `value` FROM `${database}` WHERE `key` = :key LIMIT 1;");
		$stmt->bindParam('key', $key, PDO::PARAM_STR);
		$stmt->execute();
		$value = $stmt->fetch(PDO::FETCH_OBJ)->value;
		if (__DEBUG__ !== true) $memcache->set($mckey, $value);
	}
	return $value;
}

function set_ios_dev_center_down($time_threshold = 80) {
	global $DB, $memcache;

	if ((select_value_from_database('isup') == '1') && (time() - select_value_from_database('time') > $time_threshold)) {
		$notification = 'iOS Dev Center went down at '.date('r', select_value_from_database('time')).'. #iOSDevCenterStatus';

		console_print($notification);

		$nfc->send_pushover('ADC Crawler', $notification, 'http://developer.apple.com/ios');
		$nfc->post_tweet('iOS_DevCenter', $notification . ' http://developer.apple.com/ios');

		$stmt = $DB->prepare("UPDATE `adc` SET `value` = 0 WHERE `key` = 'isup';");
		$stmt->execute();
		$memcache->set(md5('ADC:isup'.SALT), false);
	}
}

function set_ios_dev_center_up() {
	global $DB, $memcache;

	if (select_value_from_database('isup') == '0') {
		$notification = 'iOS Dev Center came back up at '.date('r').'. #iOSDevCenterStatus';

		console_print($notification);

		$nfc->send_pushover('ADC Crawler', $notification, 'http://developer.apple.com/ios');
		$nfc->post_tweet('iOS_DevCenter', $notification . ' http://developer.apple.com/ios');

		$stmt = $DB->prepare("UPDATE `adc` SET `value` = 1 WHERE `key` = 'isup';");
		$stmt->execute();
		$memcache->set(md5('ADC:isup'.SALT), true);
	}
}

try {

	$ADC      = new ADC($config->adc->username, $config->adc->password, $config->cookies_location);
	$iOSDC    = new iOSDC;
	$nfc      = new Notifications($config);
	$memcache = new Memcached;
	$DB       = new PDO('sqlite:'.$config->database_location);

	$memcache->addServer($config->memcache->host, $config->memcache->port);

	$DB->exec("CREATE TABLE IF NOT EXISTS `adc` (`id` INTEGER PRIMARY KEY, `key` TEXT, `value` TEXT);");
	$DB->exec("CREATE TABLE IF NOT EXISTS `systemstatus` (`key` TEXT PRIMARY KEY, `value` TEXT);");
	$DB->exec("INSERT OR IGNORE INTO `adc` (`id`, `key`, `value`) VALUES (1, 'isup', 1);");
	$DB->exec("INSERT OR IGNORE INTO `adc` (`id`, `key`, `value`) VALUES (2, 'title', 'null');");
	$DB->exec("INSERT OR IGNORE INTO `adc` (`id`, `key`, `value`) VALUES (3, 'build', 'null');");
	$DB->exec("INSERT OR IGNORE INTO `adc` (`id`, `key`, `value`) VALUES (4, 'posted', 'null');");
	$DB->exec("INSERT OR IGNORE INTO `adc` (`id`, `key`, `value`) VALUES (5, 'time', 0);");
	$DB->exec("INSERT OR IGNORE INTO `adc` (`id`, `key`, `value`) VALUES (6, 'indexpage', 'null');");

	if (__DEBUG__) $DB->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	// parse the ADC System Status page
	$ADC->update_system_status();

	$num_services_online = 0;
	$num_services_offline = 0;

	foreach ($ADC->services as $service => $status) {
		$stmt = $DB->prepare("INSERT OR IGNORE INTO `systemstatus` (`key`, `value`) VALUES (:key, :value);");
		$stmt->execute(array('key' => $service, 'value' => $status));

		if ($status === 'online') $num_services_online++;
		if ($status === 'offline') $num_services_offline++;

		if (strcmp($status, select_value_from_database($service, 'systemstatus'))) {
			$notification = html_entity_decode($service) . (($status == 'online') ? ' came back ' : ' went ') . $status . ' at ' . date('r') . '. #ADCSystemStatus';

			console_print($notification);

			$nfc->send_pushover('ADC System Status Crawler', $notification, $ADC->system_status_url);
			$nfc->post_tweet('ADCSystemStatus', $notification . ' ' . $ADC->system_status_url);

			$stmt = $DB->prepare("UPDATE `systemstatus` SET `value` = :value WHERE `key` = :key;");
			$stmt->execute(array('value' => $status, 'key' => $service));
			$memcache->set(md5('ADC:'.$service.SALT), $status);
		}
	}

	console_print("$num_services_online services online and $num_services_offline services offline.");

	// auto force-login every hour
	$key = md5('ADC:lastloggedinhour');
	if (strcmp($memcache->get($key), date('H'))) {
		console_print('Logging in...');
		$ADC->force_login();
		$memcache->set($key, date('H'));
	}

	// download the iOS Dev Center index page and check if it's up or under maintenance -- re-login if it fails
	if (!$ADC->download_ios_index() || !$iOSDC->is_up($ADC->ios_index)) {

		if ($ADC->is_maintenance()) {
			console_print('Maintenenace page found! Time since last success: ' . (time() - select_value_from_database('time')) . ' seconds.');
			set_ios_dev_center_down(65);
			exit();
		}

		console_print('Session expired. Logging in...');

		if (!$ADC->force_login()) {
			console_print('Failed to log in to ADC. Time since last success: ' . (time() - select_value_from_database('time')) . ' seconds.');
			set_ios_dev_center_down();
			exit();
		}

		if (!$ADC->download_ios_index()) {
			console_print('Failed to download iOS index page. Time since last success: ' . (time() - select_value_from_database('time')) . ' seconds.');
			set_ios_dev_center_down();
			exit();
		}
	}

	// dev center is definitely up if we get here
	set_ios_dev_center_up();

	// parse the downloaded iOSDC page for beta firmwares
	$iOSDC->parse_ios_index($ADC->ios_index);

	// check if the beta firmware found (if any) is newer than what we have
	if ($iOSDC->ios_beta_found) {

		$title = $iOSDC->ios_beta_title;
		$build = $iOSDC->ios_beta_build;
		$posted = $iOSDC->ios_beta_posted;

		if (strcmp($posted, select_value_from_database('posted'))) {
			$notification = $title.' ('.$build.') has been released on iOS Dev Center! #iOSBetaRelease http://ineal.me/adc '.$ADC->ios_index_url;

			console_print($notification);

			$nfc->send_pushover('iOS beta Crawler', $notification, $ADC->ios_index_url);
			$nfc->post_tweet('iOS_DevCenter', $notification);
			$nfc->post_tweet('iNeal', $notification . ' (via @iOS_DevCenter)');

			$stmt = $DB->prepare("UPDATE `adc` SET `value` = :value WHERE `key` = 'title';");
			$stmt->execute(array('value' => $title));
			$memcache->set(md5('ADC:title'.SALT), $title);

			$stmt = $DB->prepare("UPDATE `adc` SET `value` = :value WHERE `key` = 'build';");
			$stmt->execute(array('value' => $build));
			$memcache->set(md5('ADC:build'.SALT), $build);

			$stmt = $DB->prepare("UPDATE `adc` SET `value` = :value WHERE `key` = 'posted';");
			$stmt->execute(array('value' => $posted));
			$memcache->set(md5('ADC:posted'.SALT), $posted);
		}

		console_print("Latest iOS beta is $title [$build], released on $posted.");

	} else {

		console_print('No iOS beta found.');
	}

	// store time when we last successfully crawled
	$stmt = $DB->prepare("UPDATE `adc` SET `value` = :value WHERE `key` = 'time';");
	$stmt->execute(array('value' => time()));
	$memcache->set(md5('ADC:time'.SALT), time());

	// store the iOS index page for adc.php
	$stmt = $DB->prepare("UPDATE `adc` SET `value` = :value WHERE `key` = 'indexpage';");
	$stmt->execute(array('value' => base64_encode($ADC->ios_index)));
	$memcache->set(md5('ADC:indexpage'.SALT), base64_encode($ADC->ios_index));

} catch(Exception $e) {

	console_print('ERROR: '. ((__DEBUG__) ? $e : $e->getMessage()));
}

?>
