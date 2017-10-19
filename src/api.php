<?php

namespace splattner\mailmanapi;

use EasyRequest\client;
use EasyRequest\Cookie\CookieJar;


class MailmanAPI {

	private $mailmanURL;
	private $password;
	private $cookieJar;
	
	/**
	 * @param $mailmanurl
	 *  Mailman Base URL
	 * @param $password
	 *  Administration Passwort for your Mailman List
	 */
	public function __construct($mailmalurl, $password) {

		$this->mailmanURL = $mailmalurl;
		$this->password = $password;

		$request = Client::request($this->mailmanURL, 'POST');
		$request->withFormParam("adminpw",$this->password);
		$request->send();

		$response = $request->getResponse();		

		$this->cookieJar = new CookieJar();
		$this->cookieJar->fromResponse($response);
	}


	/**
	 * Return Array of all Members in a Mailman List
	 */
	public function getMemberlist() {

		$request = Client::request($this->mailmanURL . "/members", 'GET', array('cookie_jar' => $this->cookieJar));
		$request->send();
		$response = $request->getResponse();

		$dom = new \DOMDocument;
		$dom->loadHTML($response->getBody());

		$tables = $dom->getElementsByTagName("table")[4];

		$trs = $tables->getElementsByTagName("tr");

		// Get all the urs for the letters
		$letterLinks = $trs[1];
		$links = $letterLinks->getElementsByTagName("a");
		$urlsForLetters = array();

		foreach($links as $link) {
			$urlsForLetters[] =  $link->getAttribute('href');
		}

		$memberList = array();

		foreach($urlsForLetters as $url) {
			$request = Client::request($url, 'GET', array('cookie_jar' => $this->cookieJar));
			$request->send();
			$response = $request->getResponse();

			$dom = new \DOMDocument;
			$dom->loadHTML($response->getBody());

			$tables = $dom->getElementsByTagName("table")[4];
			$trs = $tables->getElementsByTagName("tr");

			for ($i = 3 ; $i < $trs->length; $i++) {
				$tds = $trs[$i]->getElementsByTagName("td");
				$memberList[] = $tds[1]->nodeValue;
			}

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
		$request = Client::request($this->mailmanURL . "/members/add", 'POST', array('cookie_jar' => $this->cookieJar));

		$request->withFormParam("csrf_token", $token)
		->withFormParam("subscribe_or_invite","0")
		->withFormParam("send_welcome_msg_to_this_batch","0")
		->withFormParam("send_notifications_to_list_owner","0")
		->withFormParam("subscribees",join(chr(10), $members))
		->withFormParam("setmemberopts_btn","Änderungen speichern");
		$request->send();

		$response = $request->getResponse();

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
		$request = Client::request($this->mailmanURL . "/members/remove", 'POST', array('cookie_jar' => $this->cookieJar));

		$request->withFormParam("csrf_token", $token)
		->withFormParam("send_unsub_ack_to_this_batch","0")
		->withFormParam("send_unsub_notifications_to_list_owner","0")
		->withFormParam("unsubscribees",join(chr(10), $members))
		->withFormParam("setmemberopts_btn","Änderungen speichern");
		$request->send();

		$response = $request->getResponse();

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
		$request = Client::request($this->mailmanURL . "/members/change", 'POST', array('cookie_jar' => $this->cookieJar));

		$request->withFormParam("csrf_token", $token)
		->withFormParam("change_from",$memberFrom)
		->withFormParam("change_to",$memberTo)
		->withFormParam("setmemberopts_btn","Änderungen speichern");
		$request->send();

		$response = $request->getResponse();

		$dom = new \DOMDocument;
		$dom->loadHTML($response->getBody());

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

		$dom = new \DOMDocument;
		$dom->loadHTML($body);

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
		$request = Client::request($this->mailmanURL . "/members/" . $page, 'GET', array('cookie_jar' => $this->cookieJar));
		$request->send();
		$response = $request->getResponse();

		$dom = new \DOMDocument;
		$dom->loadHTML($response->getBody());

		$form = $dom->getElementsByTagName("form")[0];

		return $form->getElementsByTagName("input")[0]->getAttribute("value");
	}

}


?>