<?php

require_once __dir__ . '/pushover/Pushover.php';
require_once __dir__ . '/twitter/src/twitter.class.php';

class Notifications {

	private $config;

	public function __construct($config) {
		$this->config = $config;
	}

	/**
	 * Send Pushover notification
	 */
	public function send_pushover($title, $message, $url = null, $url_title = null, $priority = 0) {
		$Pushover = new Pushover();
		$Pushover->setToken($this->config->pushover->token);
		$Pushover->setUser($this->config->pushover->user);

		$Pushover->setTitle($title);
		$Pushover->setMessage($message);
		$Pushover->setUrl($url);
		$Pushover->setUrlTitle($url_title);
		$Pushover->setPriority($priority);
		$Pushover->setTimestamp(time());

		print 'Sending Pushover... ';
		$success = $Pushover->send();
		print ($success ? 'success' : 'failed') . PHP_EOL;

		return $success;
	}

	/**
	 * Post a new tweet
	 */
	public function post_tweet($account, $tweet) {
		if (!isset($this->config->twitter->{$account})) throw new exception("unknown twitter account: '$account'");

		if (__DEBUG__) return false;

		$twitter = new Twitter(
			$this->config->twitter->{$account}->consumer_key,
			$this->config->twitter->{$account}->consumer_secret,
			$this->config->twitter->{$account}->user_token,
			$this->config->twitter->{$account}->user_secret
		);

		print 'tweeting via @' . $account . PHP_EOL;

		try {
			$twitter->send($tweet);
			return true;
		} catch (TwitterException $e) {
			print_r($e);
			return false;
		}
	}
}
