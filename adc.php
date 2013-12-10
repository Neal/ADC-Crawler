<?php

require_once __dir__ . '/config.php';
require_once __dir__ . '/include/simple_html_dom.php';

function select_value_from_database($key) {
	global $DB, $memcache;
	$mckey = md5('ADC:'.$key.SALT);
	if ($memcache->get($mckey)) {
		$value = $memcache->get($mckey);
	} else {
		$stmt = $DB->prepare("SELECT `value` FROM `adc` WHERE `key` = :key LIMIT 1;");
		$stmt->bindParam('key', $key, PDO::PARAM_STR);
		$stmt->execute();
		$value = $stmt->fetch(PDO::FETCH_OBJ)->value;
		$memcache->set($mckey, $value);
	}
	return $value;
}

$DB = new PDO('sqlite:'.$config->database_location);
$memcache = new Memcached;
$memcache->addServer($config->memcache->host, $config->memcache->port);

if (array_key_exists('QUERY_STRING', $_SERVER) && $_SERVER['QUERY_STRING'] === 'dl') {
	header('Content-Type: text/plain');
	$html = str_get_html(base64_decode(select_value_from_database('indexpage')));
	foreach($html->find('a[class=dmg]') as $e) {
		$url = 'https://developer.apple.com' . $e->href;
		if (!strpos($url,'Developer_Tools') && !strpos($url,'appldnld') && !strpos($url,'itunes')) {
			print 'adcdl ' . $url . PHP_EOL;
		}
	}
	exit();
}

$key = md5('ADC:frontend'.SALT);
if ($memcache->get($key)) {
	header('X-Data-Location: cache');
	$return_data = $memcache->get($key);
} else {
	header('X-Data-Location: database');
	$html = str_get_html(base64_decode(select_value_from_database('indexpage')));
	foreach ($html->find('.downloads') as $downloads) {
		foreach ($downloads->find('h5') as $h5s) {
			if (strpos($h5s->plaintext,'beta') || strpos($h5s->plaintext,'GM')) {
				// <h4>iOS 6.1 beta Downloads</h4> || <h4>iOS 6 GM seed Downloads</h4>
				$return_data .= '<h4>' . $h5s->plaintext . '</h4>';
				foreach ($downloads->find('div') as $divs) {
					foreach ($divs->find('li') as $lis) {
						// Posted: November 1, 2012 | Build: 10B5095f
						$return_data .= $lis->plaintext . PHP_EOL;
					}
					$return_data .= PHP_EOL;
				}
				foreach ($downloads->find('a[class=dmg]') as $dmgs) {
					$return_data .= "<a href='https://developer.apple.com{$dmgs->href}'>{$dmgs->plaintext}</a>" . PHP_EOL;
				}
				$return_data .= PHP_EOL . PHP_EOL;
				$didfindabeta = true;
			}
		}
	}
	if (!isset($didfindabeta)) {
		$return_data .= '<h3>No iOS beta found</h3>' . PHP_EOL;
	}
	$memcache->set($key, $return_data);
}

print '<pre>' . PHP_EOL;
print '<h2>iOS Dev Center beta firmware links parser</h2>';
print 'last checked: ' . (time() - select_value_from_database('time')) . ' seconds ago' . PHP_EOL;
print '------------------------------' . PHP_EOL . PHP_EOL;
print $return_data;
print '</pre>' . PHP_EOL;

?>
