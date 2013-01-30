<?php

require 'vendor/autoload.php';
use Guzzle\Common\Event;
use Guzzle\Http\Client;
use Guzzle\Http\Message\Response;
use Guzzle\Http\Message\Request;
use Guzzle\Plugin\Cookie\CookiePlugin;
use Guzzle\Plugin\Cookie\CookieJar\ArrayCookieJar;
use Guzzle\Http\Exception\ClientErrorResponseException;

class NiosClient {

	private $guzzle;

	/**
	 * @param $address : grid master name or ip
	 * @param $username : api username
	 * @param $password : api password
	 * @param bool $sslCheck : whether to check SSL certificate or not, defautls to true
	 * @param string $version : WAPI version, defaults to 1.0
	 * @throws Exception|Guzzle\Http\Exception\ClientErrorResponseException
	 */
	public function __construct($address, $username, $password, $sslCheck = true, $version = '1.0') {
		$this->guzzle = new Client('https://' . $address . '/wapi/v' . $version . '/');
		$this->guzzle->setSslVerification($sslCheck);
		$this->guzzle->addSubscriber(new CookiePlugin(new ArrayCookieJar()));
		$this->guzzle->getEventDispatcher()->addListener('request.before_send', function (Event $event) {
			$event['request']->setHeader('Content-Type', 'application/json');
		});
		try {
			$this->guzzle->get()->setAuth($username, $password)->send();
		} catch (ClientErrorResponseException $e) {
			if ($e->getResponse()->getStatusCode() != 400) {
				throw $e;
			}
		}
	}

	/**
	 * Wrapper to the HTTP GET method
	 * @param $dataName : the object to retrieve
	 * @param array $filters : the filters to apply, defaults to none
	 * @param null $fields : the fields to select, default to WAPI default.
	 * @return array : resulting objects
	 * @throws Exception in case of invalid filters
	 */
	public function get($dataName, array $filters = array(), $fields = null) {
		$request = $this->guzzle->get($dataName);
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
		if (!empty($fields)) {
			if (is_array($fields)) {
				$fields = implode(',', $fields);
			}
			$query->set('_return_fields', $fields);
		}
		$response = $request->send();
		return $response->json();
	}

	/**
	 * Wrapper to the HTTP POST method
	 * @param $url : the url to post data to
	 * @param array $params : a key-value array of data to put in the request body
	 * @return array
	 */
	public function post($url, array $params = array()) {
		$request = $this->guzzle->post($url);
		if (empty($params)) {
			$body = '{}';
		} else {
			$body = json_encode($params);
		}
		$request->setBody($body);
		$response = $request->send();
		return $response->json();
	}

	/**
	 * Wrapper to the HTTP PUT method
	 * @param $url : the url to put data to
	 * @param array $params : a key-value array of data to put in the request body
	 * @return array
	 */
	public function put($url, array $params = array()) {
		$request = $this->guzzle->put($url);
		if (empty($params)) {
			$body = '{}';
		} else {
			$body = json_encode($params);
		}
		$request->setBody($body);
		$response = $request->send();
		return $response->json();
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
	 * Get Network records
	 * @see get
	 */
	public function getNetworks(array $filters = array(), $fields = null) {
		return $this->get('network', $filters, $fields);
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

}