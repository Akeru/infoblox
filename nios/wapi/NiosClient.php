<?php

require 'vendor/autoload.php';
use Guzzle\Common\Event;
use Guzzle\Http\Client;
use Guzzle\Http\Message\Response;
use Guzzle\Http\Message\Request;
use Guzzle\Plugin\Cookie\CookiePlugin;
use Guzzle\Plugin\Cookie\CookieJar\ArrayCookieJar;
use Guzzle\Http\Exception\ClientErrorResponseException;
use Guzzle\Log\ArrayLogAdapter;
use Guzzle\Plugin\Log\LogPlugin;
use Guzzle\Log\MessageFormatter;

class NiosClient {

	private $api;
	private $logger;

	/**
	 * @param $address : grid master name or ip
	 * @param $username : api username
	 * @param $password : api password
	 * @param bool $sslCheck : whether to check SSL certificate or not, defautls to true
	 * @param string $version : WAPI version, defaults to 1.0
	 * @throws Exception|Guzzle\Http\Exception\ClientErrorResponseException
	 */
	public function __construct($address, $username, $password, $sslCheck = true, $version = '1.0') {
		$this->logger = new ArrayLogAdapter();

		$this->api = new Client('https://' . $address . '/wapi/v' . $version . '/');
		$this->api->setSslVerification($sslCheck);
		$this->api->addSubscriber(new CookiePlugin(new ArrayCookieJar()));
		$this->api->addSubscriber(new LogPlugin($this->logger, MessageFormatter::DEBUG_FORMAT));

		$this->api->getEventDispatcher()->addListener('request.before_send', function (Event $event) {
			$event['request']->setHeader('Content-Type', 'application/json');
		});

		try {
			//Get an empty object for auth purpose only
			$this->api->get()->setAuth($username, $password)->send();
		} catch (ClientErrorResponseException $e) {
			// 400 is the only error we should receive
			if ($e->getResponse()->getStatusCode() != 400) {
				throw $e;
			}
		}
	}

	/**
	 * Get WAPI supported objects
	 */
	public function getObjects() {
		$request = $this->api->get('/wapidoc');
		$response = $request->send();
		$body = $response->getBody();
		$dom = new DOMDocument;
		$dom->loadHTML($body);
		$xpath = new DOMXpath($dom);
		$elements = $xpath->query("//div[@id='objects']//ul/li");
		$objects = array();
		for ($i = 0; $i < $elements->length; $i++) {
			$value = $elements->item($i)->nodeValue;
			$parts = explode(' ', $value);
			$objectName = $parts[0];
			$objects[] = $objectName;
		}
		return $objects;
	}

	/**
	 * Get WAPI fields for the given object
	 * @param $objectName
	 * @return array
	 */
	public function getObjectAttributes($objectName) {
		$objectName = str_replace('%3A', '.', $objectName);
		$objectName = str_replace(':', '.', $objectName);
		$request = $this->api->get('/wapidoc/objects/' . $objectName . '.html');
		$response = $request->send();
		$body = $response->getBody();
		$dom = new DOMDocument;
		$dom->loadHTML($body);
		$xpath = new DOMXpath($dom);
		$elements = $xpath->query("//div[@id='fields']/div/@id");
		$attributes = array();
		for ($i = 0; $i < $elements->length; $i++) {
			$value = $elements->item($i)->nodeValue;
			$value = str_replace('-', '_', $value);
			if($value == 'auto_create_reversezone' || $value == 'template') {

			} else {
				$attributes[] = $value;
			}
		}
		return $attributes;
	}

	/**
	 * @param bool $flush : clear logs after retrieval, defaults to false
	 * @return array
	 */
	public function getLogs($flush = false) {
		$logs = $this->logger->getLogs();
		$results = array();
		if (!empty($logs)) {
			foreach ($logs as $log) {
				$results[] = $log['message'];
			}
			if ($flush) {
				$this->logger->clearLogs();
			}
		}
		return $results;
	}

	/**
	 * Clear logs
	 */
	public function clearLogs() {
		$this->logger->clearLogs();
	}

	/**
	 * Wrapper to the HTTP GET method
	 * @param $objectName : the object to retrieve
	 * @param array $filters : the filters to apply, defaults to none
	 * @param null $fields : the fields to select, default to all (@see getObjectAttributes).
	 * @param bool $single : return only the first record if found
	 * @throws Exception
	 * @return array : resulting objects
	 */
	public function get($objectName, array $filters = array(), $fields = null, $single = false) {
		$request = $this->api->get($objectName);
		$query = $request->getQuery();
		if (!empty($filters)) {
			foreach ($filters as $filter) {
				if (is_array($filter)) {
					if (count($filter) == 2) {
						list($object, $value) = $filter;
						$operator = '';
					} elseif (count($filter) == 3) {
						list($object, $operator, $value) = $filter;
						if ($operator == '=') {
							$operator = '';
						}
					} else {
						throw new Exception('Invalid filter array size');
					}
					$query->set($object . $operator, $value);
				} else {
					throw new Exception('Filter entry must be an array');
				}
			}
		}
		if (empty($fields)) {
			$fields = $this->getObjectAttributes($objectName);
		}
		if (is_array($fields)) {
			$fields = implode(',', $fields);
		}
		$query->set('_return_fields', $fields);
		$response = $request->send();
		$json = $response->json();
		if ($single) {
			if (!empty($json)) {
				$json = $json[0];
			}
		}
		return $json;
	}

	/**
	 * Wrapper to the HTTP POST method
	 * @param $url : the url to post data to
	 * @param array $params : a key-value array of data to put in the request body
	 * @return array
	 */
	public function post($url, array $params = array()) {
		$request = $this->api->post($url);
		if (empty($params)) {
			$body = '{}';
		} else {
			$body = json_encode($params);
		}
		$request->setBody($body);
		$response = $request->send();
		$json = $response->json();
		return $json;
	}

	/**
	 * Wrapper to the HTTP PUT method
	 * @param $url : the url to put data to
	 * @param array $params : a key-value array of data to put in the request body
	 * @return array
	 */
	public function put($url, array $params = array()) {
		$request = $this->api->put($url);
		if (empty($params)) {
			$body = '{}';
		} else {
			$body = json_encode($params);
		}
		$request->setBody($body);
		$response = $request->send();
		$json = $response->json();
		return $json;
	}

	/**
	 * Wrapper to call Nios WAPI function
	 * @param $url : the url of the function
	 * @param $functionName : the function name
	 * @param array $params : a key-value array of parameter to pass to the function
	 * @return array
	 */
	public function callFunction($url, $functionName, array $params = array()) {
		$url = $url . '?_function=' . $functionName;
		return $this->post($url, $params);
	}

	/**
	 * Get Host records
	 * @see get
	 */
	public function getHosts(array $filters = array(), $fields = null) {
		return $this->get('record%3Ahost', $filters, $fields);
	}

	/**
	 * Get Host record
	 * @see get
	 */
	public function getHost(array $filters = array(), $fields = null) {
		return $this->get('record%3Ahost', $filters, $fields, true);
	}

	/**
	 * Get Host record
	 * @see get
	 */
	public function getHostByName($hostname, $fields = null) {
		return $this->getHost(array(array('name', $hostname)), $fields);
	}

	/**
	 * Get Network records
	 * @see get
	 */
	public function getNetworks(array $filters = array(), $fields = null) {
		return $this->get('network', $filters, $fields);
	}

	/**
	 * Get Network record
	 * @see get
	 */
	public function getNetwork(array $filters = array(), $fields = null) {
		return $this->get('network', $filters, $fields, true);
	}

	/**
	 * Get host ipv4 addresses records
	 * @see get
	 */
	public function getHostAddresses(array $filters = array(), $fields = null) {
		return $this->get('record%3Ahost_ipv4addr', $filters, $fields);
	}

	/**
	 * Get host ipv4 address record
	 * @see get
	 */
	public function getHostAddress(array $filters = array(), $fields = null) {
		return $this->get('record%3Ahost_ipv4addr', $filters, $fields, true);
	}

	/**
	 * Get host ipv4 address record
	 * @see get
	 */
	public function getHostAddressByAddress($address, $fields = null) {
		return $this->get('record%3Ahost_ipv4addr', array(array('ipv4addr', $address)), $fields, true);
	}

	/**
	 * @param $ref : the network object retrieved
	 * @param null $num : the number of address to return, defaults to WAPI default
	 * @param null $excludes : an array of ip addresses to ignore
	 * @return array
	 */
	public function getNextAvailableIpsForNetwork($ref, $num = null, $excludes = null) {
		$params = array();
		if (!empty($num)) {
			$params['num'] = $num;
		}
		if (!empty($excludes)) {
			if (is_string($excludes)) {
				$excludes = explode(',', $excludes);
			}
			$params['exclude'] = $excludes;
		}
		return $this->callFunction($ref, 'next_available_ip', $params);
	}

	/**
	 * @param $prefix : the base to generate names from
	 * @param int $num : the number of names to return, defaults to 1
	 * @param int $length : the padding length to use for name generation, defaults to 1
	 * @return array
	 */
	public function getNextAvailableNames($prefix, $num = 1, $length = 1) {
		$results = $this->getHosts(array(array('name', '~', '^' . $prefix)), array('name', 'zone'));
		$names = array();
		$start = 0;
		if (!empty($results)) {
			$latest = $results[count($results) - 1];
			$name = $latest['name'];
			$zone = $latest['zone'];
			$name = str_replace($prefix, '', $name);
			$name = str_replace('.' . $zone, '', $name);
			$length = strlen($name);
			$start = (int)$name;
		}
		for ($i = 0; $i < $num; $i++) {
			$suffix = str_pad($start + $i + 1, $length, '0', STR_PAD_LEFT);
			$names[] = $prefix . $suffix;
		}
		return $names;
	}

	/**
	 * @param array $host : the host record to sort aliases from
	 */
	public function sortHostAliases(array $host) {
		if (array_key_exists('_ref', $host)) {
			$ref = str_replace(':', '%3A', $host['_ref']);
			if (array_key_exists('aliases', $host)) {
				$currentAliases = $host['aliases'];
				if (!empty($currentAliases)) {
					$sortedAliases = $currentAliases;
					natcasesort($sortedAliases);
					$diff = array_diff($currentAliases, $sortedAliases);
					if (!empty($diff)) {
						$this->put($ref, array('aliases' => array()));
						$this->put($ref, array('aliases' => $sortedAliases));
					}
				}
			}
		}
	}

	/**
	 * @param array $fromHost : the host record to move alias from
	 * @param array $toHost : the host record to move alias to
	 * @param $alias : the alias to move
	 */
	public function moveHostAliases(array $fromHost, array $toHost, $alias) {
		if (array_key_exists('_ref', $fromHost)) {
			$fromHostRef = str_replace(':', '%3A', $fromHost['_ref']);
			if (array_key_exists('aliases', $fromHost)) {
				$fromHostAliases = $fromHost['aliases'];
				if (in_array($alias, $fromHostAliases)) {
					if (array_key_exists('_ref', $toHost)) {
						$toHostRef = str_replace(':', '%3A', $toHost['_ref']);
						if (array_key_exists('aliases', $toHost)) {
							$toHostAliases = $toHost['aliases'];
							$toHostAliases[] = $alias;
							$newFromHostAliases = array_diff($fromHostAliases, array($alias));
							$this->put($fromHostRef, array('aliases' => $newFromHostAliases));
							$this->put($toHostRef, array('aliases' => $toHostAliases));
						}
					}
				}
			}
		}
	}

	/**
	 * @param array $ipv4address
	 * @param $mac
	 */
	public function addMacAddressReservationForHostAddress(array $ipv4address, $mac) {
		if (array_key_exists('_ref', $ipv4address)) {
			$hostAddressRef = str_replace(':', '%3A', $ipv4address['_ref']);
			$this->put($hostAddressRef, array('mac' => $mac, 'configure_for_dhcp' => true));
		}
	}
}
