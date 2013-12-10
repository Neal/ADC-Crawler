<?php

require_once __dir__ . '/../simple_html_dom.php';

class iOSDC {

	public $ios_beta_found = false;
	public $ios_beta_title = null;
	public $ios_beta_build = null;
	public $ios_beta_posted = null;

	/**
	 * Check if iOS Dev Center is up.
	 * @return true if iOS Dev Center is up
	 */
	public function is_up($ios_index_page) {
		$html = str_get_html($ios_index_page);
		foreach ($html->find('h2') as $h2) {
			if (strpos($h2->plaintext, 'iOS') !== false) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Parse iOS Dev Center index page for the following info about
	 * the latest beta and save it:
	 *  > beta title (eg. "iOS 4.1 beta 2")
	 *  > beta build (eg. "8B5091b")
	 *  > beta posted (eg. "July 27, 2010")
	 * @return true if a beta is found
	 */
	public function parse_ios_index($ios_index_page) {
		$html = str_get_html($ios_index_page);
		foreach ($html->find('.downloads') as $downloads) {
			foreach ($downloads->find('h5') as $h5) {
				if (strpos($h5->plaintext, 'iOS') !== false && (strpos($h5->plaintext, 'beta') !== false || strpos($h5->plaintext, 'GM') !== false)) {
					$this->ios_beta_found = true;
					$this->ios_beta_title = str_replace(' Downloads', null, html_entity_decode($h5->innertext));
					foreach ($downloads->find('li') as $li) {
						if (strstr($li->innertext, 'Build'))
							$this->ios_beta_build = str_replace('Build: ', null, html_entity_decode($li->innertext));
						if (strstr($li->innertext, 'Posted'))
							$this->ios_beta_posted = str_replace('Posted: ', null, html_entity_decode($li->innertext));
					}
				}
			}
		}
		return $this->ios_beta_found;
	}
}
