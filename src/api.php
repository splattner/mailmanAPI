<?php

namespace splattner\mailmanapi;

use GuzzleHttp\Client;

class MailmanAPI {

	private $mailmanURL;
	private $password;
	private $client;

	/**
	 * @param $mailmanurl
	 *  Mailman Base URL
	 * @param $password
	 *  Administration Passwort for your Mailman List
	 */
	public function __construct($mailmalurl, $password, $validade_ssl_certs = true) {

		$this->mailmanURL = $mailmalurl;
		$this->password = $password;

		$this->client = new Client(['base_uri' => $this->mailmanURL, 'cookies' => true, 'verify' => $validade_ssl_certs]);

		$response = $this->client->request('POST', '', [
    'form_params' => [
        'adminpw' => $this->password
    	]
		]);

	}


	/**
	 * Return Array of all Members in a Mailman List
	 */
	public function getMemberlist() {

		$response = $this->client->request('GET', $this->mailmanURL . '/members');

		$dom = new \DOMDocument('1.0', 'UTF-8');

		// set error level
		$internalErrors = libxml_use_internal_errors(true);

		$dom->loadHTML($response->getBody());

		// Restore error level
		libxml_use_internal_errors($internalErrors);

		$tables = $dom->getElementsByTagName("table")[4];

		$trs = $tables->getElementsByTagName("tr");

		// Get all the urs for the letters
		$letterLinks = $trs[1];
		$links = $letterLinks->getElementsByTagName("a");

		$memberList = array();

		if (count($links) === 0) {
			return $this->getMembersFromTableRows($trs, $isSinglePage = true);
		}

		$urlsForLetters = array();

		foreach($links as $link) {
			$urlsForLetters[] =  $link->getAttribute('href');
		}

		foreach($urlsForLetters as $url) {
			$response = $this->client->request('GET', $url);

			$dom = new \DOMDocument('1.0', 'UTF-8');

			// set error level
			$internalErrors = libxml_use_internal_errors(true);

			$dom->loadHTML($response->getBody());

			// Restore error level
			libxml_use_internal_errors($internalErrors);

			$tables = $dom->getElementsByTagName("table")[4];
			$trs = $tables->getElementsByTagName("tr");

			$memberList = array_merge(
				$memberList,
				$this->getMembersFromTableRows($trs)
			);
		}

		return $memberList;
	}

	/**
	 * Get the e-mail addresses from a list of table rows (<tr>).
	 *
	 * @param  DOMNodeList  $trs
	 * @param  bool    	$isSinglePage
	 *
	 * @return array
	 */
	protected function getMembersFromTableRows($trs, $isSinglePage = false)
	{
		$firstRowIndex = $isSinglePage ? 2 : 3;

		$memberList = [];

		for ($i = $firstRowIndex; $i < $trs->length; $i++) {
			$tds = $trs[$i]->getElementsByTagName("td");
			$memberList[] = $tds[1]->nodeValue;
		}

		return $memberList;
	}

	/**
	 * Add new Members to a Mailman List
	 * @param $members
	 *  Array of Members that should be added
	 * @return
	 *  Array of Members that were successfully added
	 */
	public function addMembers($members) {

		$token = $this->getCSRFToken("add");

		$response = $this->client->request('POST', $this->mailmanURL . '/members/add', [
			'form_params' => [
				'csrf_token' => $token,
				'subscribe_or_invite' => '0',
				'send_welcome_msg_to_this_batch' => '0',
				'send_notifications_to_list_owner' => '0',
				'subscribees' => join(chr(10), $members),
				'setmemberopts_btn' => 'Änderungen speichern'
			]
		]);


		return $this->parseResultList($response->getBody());
	}

	/**
	 * Remove Members to a Mailman List
	 * @param $members
	 *  Array of Members that should be added
	 * @return
	 *  Array of Members that were successfully removed
	 */
	public function removeMembers($members) {

		$token = $this->getCSRFToken("remove");

		$response = $this->client->request('POST', $this->mailmanURL . '/members/remove', [
			'form_params' => [
				'csrf_token' => $token,
				'send_unsub_ack_to_this_batch' => '0',
				'send_unsub_notifications_to_list_owner' => '0',
				'unsubscribees' => join(chr(10), $members),
				'setmemberopts_btn' => 'Änderungen speichern'
			]
		]);

		return $this->parseResultList($response->getBody());
	}

	/**
	 * Change Address for a member
	 * @param $memberFrom
	 *  The Adress from the member you wanna change
	 * @param $memberTo
	 *  The Adress it should be changed to

	 */
	public function changeMember($memberFrom, $memberTo) {

		$token = $this->getCSRFToken("change");
		$response = $this->client->request('POST', $this->mailmanURL . '/members/change', [
			'form_params' => [
				'csrf_token' => $token,
				'change_from' => $memberFrom,
				'change_to' => $memberTo,
				'setmemberopts_btn' => 'Änderungen speichern'
			]
		]);

		$dom = new \DOMDocument('1.0', 'UTF-8');

		// set error level
		$internalErrors = libxml_use_internal_errors(true);

		$dom->loadHTML($response->getBody());

		// Restore error level
		libxml_use_internal_errors($internalErrors);

		$h3 = $dom->getElementsByTagName("h3")[0];

		return (strpos($h3->nodeValue, $memberFrom) == True && strpos($h3->nodeValue, $memberTo) == True);

	}

	/**
	 * Parse the HTML Body of an Add or Remove Action to get List of successfull add/remove entries
	 * @param $body
	 *  the HTML Body of the Result Page
	 * @return
	 * Array of Entrys that were successfull
	 */
	private function parseResultList($body) {

		$dom = new \DOMDocument('1.0', 'UTF-8');

		// set error level
		$internalErrors = libxml_use_internal_errors(true);

		$dom->loadHTML($body);

		// Restore error level
		libxml_use_internal_errors($internalErrors);

		$result = array();

		// Are there entrys with success?
		$haveSuccessfullEntry = $dom->getElementsByTagName("h5")[0] != null;

		if ($haveSuccessfullEntry) {
			$uls = $dom->getElementsByTagName("ul")[0];
			$lis = $uls->getElementsByTagName("li");

			foreach($lis as $li) {
				// Warning after --
				if (strpos($li->nodeValue, '--') == False) {
					$result[] = $li->nodeValue;
				}
			}
		}

		return $result;
	}

	/*
	 * Get CSRF Token for a Page
	 * @param $page
	 *  the Page you want the token for
	 */
	private function getCSRFToken($page) {

		$response = $this->client->request('GET', $this->mailmanURL . '/members');

		$dom = new \DOMDocument('1.0', 'UTF-8');

		// set error level
		$internalErrors = libxml_use_internal_errors(true);

		$dom->loadHTML($response->getBody());

		// Restore error level
		libxml_use_internal_errors($internalErrors);

		$form = $dom->getElementsByTagName("form")[0];

		return $form->getElementsByTagName("input")[0]->getAttribute("value");
	}

}


?>
