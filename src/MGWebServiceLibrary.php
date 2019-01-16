<?php
/*
 * Copyright (C) 2018		ATM Consulting			<support@atm-consulting.fr>
 * Copyright (C) 2018		Pierre-Henry Favre		<phf@atm-consulting.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace Dolishop\MGWebServiceLibrary;


class MGWebServiceLibrary
{
	/** @var string Shop URL */
	protected $url;

	/** @var string Shop URL with rest suffix */
	protected $base_uri;

	/** @var string Shop code */
	public $store_code='default';

	/** @var string Authentification user name */
	protected $username;

	/** @var string Authentification user password */
	protected $password;

	/** @var string Consumer key */
	protected $consumer_key;

	/** @var string Consumer secret */
	protected $consumer_secret;

	/** @var string Token */
	protected $token;

	/** @var string Token secret */
	protected $token_secret;


	/** @var boolean is debug activated */
	protected $debug;

	/** @var \GuzzleHttp\Client $client */
	protected $client;

	/** @var array $headers */
	protected $headers;



	function __construct($url, $options, $debug = true)
	{
		if (!extension_loaded('curl'))
			throw new MagentoWebserviceException('Please activate the PHP extension \'curl\' to allow use of PrestaShop webservice library');

		$this->url = $url;
		$this->base_uri = $this->url.'/rest';

		if (!empty($options['store_code'])) $this->store_code = $options['store_code'];

		$this->base_uri.= '/'.$this->store_code;

		$this->client = new \GuzzleHttp\Client(array(
			'base_uri' => $this->base_uri
		));

		// Used to get admin token (basic auth)
		$this->username = $options['username'];
		$this->password = $options['password'];

		// Used to get access with OAuth
		$this->consumer_key = $options['consumer_key'];
		$this->consumer_secret = $options['consumer_secret'];
		$this->token = $options['token'];
		$this->token_secret = $options['token_secret'];

		$this->debug = $debug;
	}

	public function setHeader(array $headers)
	{
		$this->headers = array();

		foreach ($headers as $key => $val)
		{
			$this->headers[$key] = $val;
		}

//		if ($this->debug) var_dump($this->headers);
	}

	/**
	 * Check the status code
	 * @param int $status_code Status code of an HTTP return
	 */
	protected function checkStatusCode($status_code)
	{
		if ($status_code < 200 || $status_code > 299) {
			throw new MagentoWebserviceException(
				sprintf(
					'[%d] Error connecting to the API',
					$status_code
				)
			);

			return false;
		}

		return true;
	}

	/**
	 * @param string $callback
	 * @return bool|\GuzzleHttp\Promise\PromiseInterface|mixed|\Psr\Http\Message\ResponseInterface|string
	 * @throws MagentoWebserviceException
	 */
	public function getToken($request_opt=array())
	{
		if (!empty($this->consumer_key) && !empty($this->consumer_secret) && !empty($this->token) && !empty($this->token_secret))
		{
			// TODO voir comment ça marche (OAuth1)
			// https://devdocs.magento.com/guides/v2.2/get-started/authentication/gs-authentication-oauth.html#pre-auth-token
			return false;
		}
		else
		{
			$response = $this->post(
				array(
					'resource' => '/V1/integration/admin/token'
					,'headers' => array('Content-Type' => 'application/json')
					,'params' => array(
						'username' => $this->username
						,'password' => $this->password
					)
				)
				,$request_opt
			);

			if (!empty($response) && empty($request_opt['async']))
			{
				return trim($response, '"');
			}

			return $response;
		}
	}


	/**
	 * @param array $options		'resource' and other parameters
	 * @param array  $request_opt
	 * @return bool|\GuzzleHttp\Promise\PromiseInterface|mixed|\Psr\Http\Message\ResponseInterface
	 * @throws MagentoWebserviceException
	 */
	public function get($options, $request_opt=array())
	{
		$response = $this->executeRequest('GET', $options['resource'], (isset($options['headers']) ? $options['headers'] : $this->headers), $options['params'], $options['body'], $request_opt);

		if ($response instanceof \GuzzleHttp\Psr7\Response)
		{
			$as_array = !empty($options['return_as_array']) ? true : false;
			return json_decode($response->getBody()->getContents(), $as_array);
		}

		return $response;
	}

	/**
	 * @param array  $options		'resource' and other parameters
	 * @param array  $request_opt
	 * @return bool|\GuzzleHttp\Promise\PromiseInterface|mixed|\Psr\Http\Message\ResponseInterface
	 * @throws MagentoWebserviceException
	 */
	public function post($options, $request_opt=array())
	{
		$response = $this->executeRequest('POST', $options['resource'],  (isset($options['headers']) ? $options['headers'] : $this->headers), $options['params'], $options['body'], $request_opt);

		if ($response instanceof \GuzzleHttp\Psr7\Response)
		{
			$as_array = !empty($options['return_as_array']) ? true : false;
			return json_decode($response->getBody()->getContents(), $as_array);
		}

		return $response;
	}

	public function put($options, $request_opt=array())
	{
		$response = $this->executeRequest('PUT', $options['resource'],  (isset($options['headers']) ? $options['headers'] : $this->headers), $options['params'], $options['body'], $request_opt);

		if ($response instanceof \GuzzleHttp\Psr7\Response)
		{
			$as_array = !empty($options['return_as_array']) ? true : false;
			return json_decode($response->getBody()->getContents(), $as_array);
		}

		return $response;
	}

	public function delete($options, $request_opt=array())
	{
		$response = $this->executeRequest('DELETE', $options['resource'],  (isset($options['headers']) ? $options['headers'] : $this->headers), $options['params'], $options['body'], $request_opt);

		if ($response instanceof \GuzzleHttp\Psr7\Response)
		{
			$as_array = !empty($options['return_as_array']) ? true : false;
			return json_decode($response->getBody()->getContents(), $as_array);
		}

		return $response;
	}


	public function executeRequest($method, $resource, $headers, $params=null, $body=null, $request_opt=array())
	{
		if (empty($method))
			throw new MagentoWebserviceException('Parameters "method" is empty');
		if (empty($resource))
			throw new MagentoWebserviceException('Parameters "resource" is empty');

		if ($params !== null) $query = \GuzzleHttp\Psr7\build_query($params);
		else $query = '';

		$request = new \GuzzleHttp\Psr7\Request(
			$method
			,$this->base_uri.$resource.($query ? '?'.$query : '')
			,$headers
			,($body) ? json_encode($body) : null
		);

		if ($this->debug) $request_opt[\GuzzleHttp\RequestOptions::DEBUG] = true;

		try {
			/** @var $response \GuzzleHttp\Promise\PromiseInterface | \GuzzleHttp\Psr7\Response */
			if (isset($request_opt['async']) && $request_opt['async'] === true) $response = $this->client->sendAsync($request, $request_opt);
			else $response = $this->client->send($request, $request_opt);

			// check the response validity
			if ($response instanceof \GuzzleHttp\Promise\Promise) return $response;
			else if ($this->checkStatusCode($response->getStatusCode())) return $response;

		} catch (RequestException $e) {
			throw new MagentoWebserviceException("[{$e->getCode()}] {$e->getMessage()}", $e->getCode());
		} catch (\Exception $e) {
			throw new MagentoWebserviceException("[{$e->getCode()}] {$e->getMessage()}", $e->getCode());
		}

		return false;
	}
}

class MagentoWebserviceException extends \Exception { }
