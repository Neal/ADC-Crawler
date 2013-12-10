<?php

date_default_timezone_set('UTC');

define('__DEBUG__', false);

define('SALT', '53be2c3c8156924556d51fa3dd053e45'); // random string we append to memcache keys

$config['cookies_location'] = __dir__ . '/adc.cookies';
$config['database_location'] = __dir__ . '/adc.sqlite3';

$config['memcache'] = array(
	'host' => 'localhost',
	'port' => 11211
);

$config['adc'] = array(
	'username' => '__APPLEID_USER__',
	'password' => '__APPLEID_PASSWORD__'
);

$config['pushover'] = array(
	'token' => '__API_TOKEN__',
	'user' => '__USER_TOKEN__'
);

$config['twitter']['iOS_DevCenter'] = array(
	'consumer_key' => '__CONSUMER_KEY__',
	'consumer_secret' => '__CONSUMER_SECRET__',
	'user_token' => '__USER_TOKEN__',
	'user_secret' => '__USER_SECRET__'
);

$config['twitter']['ADCSystemStatus'] = array(
	'consumer_key' => '__CONSUMER_KEY__',
	'consumer_secret' => '__CONSUMER_SECRET__',
	'user_token' => '__USER_TOKEN__',
	'user_secret' => '__USER_SECRET__'
);

$config = json_decode(json_encode($config));
