<?php

require_once __dir__ . '/../simple_html_dom.php';

class ADC {

	private $username;
	private $password;
	private $cookies_location;

	public $ios_index_url = 'https://developer.apple.com/devcenter/ios/index.action';
	public $download_url = 'https://developer.apple.com/devcenter/download.action';
	public $system_status_url = 'https://developer.apple.com/support/system-status';

	// iOS Dev Center index page
	public $ios_index = null;

	// array to store status for all services on system status page
	public $services = array();

	// cURL timeouts
	const TIMEOUT = 15;
	const CONNECTIONTIMEOUT = 10;

	public function __construct($username, $password, $cookies_location = 'adc.cookies') {
		if (!extension_loaded('curl')) {
			throw new exception('PHP extension cURL is not loaded.');
		}
		$this->username = $username;
		$this->password = $password;
		$this->cookies_location = $cookies_location;
	}

	/**
	 * Check if iOS Dev Center is under maintenance.
	 * @return true if iOS Dev Center is under maintenance
	 */
	public function is_maintenance() {
		$html = str_get_html($this->ios_index);
		return (strpos($html->find('body', 0)->class, 'maintenance') !== false);
	}

	/**
	 * Download the iOS Dev Center index page and save it to $ios_index.
	 * @return true if download was success
	 */
	public function download_ios_index() {
		$this->ios_index = $this->download($this->ios_index_url);
		return (strpos($this->ios_index, 'logout.action') !== false);
	}

	/**
	 * Force log in to ADC (truncate the cookies file and re-login).
	 * @return login()
	 */
	public function force_login() {
		file_put_contents($this->cookies_location, null);
		return $this->login();
	}

	/**
	 * Update System Status and store status for each service in $this->services
	 * @return $this->services
	 */
	public function update_system_status() {
		$html = str_get_html($this->download($this->system_status_url));
		foreach ($html->find('table[class=status-table]') as $table) {
			foreach ($table->find('td') as $td) {
				$this->services[trim($td->plaintext)] = $td->class;
			}
		}
		return $this->services;
	}


	/**
	 * Download anything from ADC with saved cookies.
	 * @param $url - URL to download
	 * @return http response if success; false otherwise
	 */
	private function download($url) {
		$ch = curl_init();
		curl_setopt_array($ch, array(
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_TIMEOUT => self::TIMEOUT,
			CURLOPT_CONNECTTIMEOUT => self::CONNECTIONTIMEOUT,
			CURLOPT_COOKIEFILE => $this->cookies_location,
			CURLOPT_URL => $url
		));
		$http_response = curl_exec($ch);
		$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		return ($http_code == 200) ? $http_response : false;
	}

	/**
	 * Log in to ADC and save cookies to $cookies_location.
	 * @return true if login data is found in the cookies file.
	 */
	private function login() {
		$login_page = file_get_html($this->download_url);
		if (!is_object($login_page)) return false;

		$ch = curl_init();
		curl_setopt_array($ch, array(
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_TIMEOUT => self::TIMEOUT,
			CURLOPT_CONNECTTIMEOUT => self::CONNECTIONTIMEOUT,
			CURLOPT_COOKIEJAR => $this->cookies_location,
			CURLOPT_POSTFIELDS => array('theAccountName' => $this->username, 'theAccountPW' => $this->password),
			CURLOPT_URL => 'https://daw.apple.com/' . $login_page->find('form[name="appleConnectForm"]', 0)->action
		));
		$http_result = curl_exec($ch);
		curl_close($ch);

		return (strpos(file_get_contents($this->cookies_location), 'myacinfo') !== false);
	}
}
