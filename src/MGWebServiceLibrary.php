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

		if (!empty($options['store_code'])) $this->base_uri.= '/'.$options['store_code'];
		else $this->base_uri.= '/default';


		$this->client = new \GuzzleHttp\Client(array('base_uri' => $this->base_uri));


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

		if ($this->debug) $this->headers['debug'] = true;

		var_dump($this->headers);
	}

	public function getToken()
	{
		if (!empty($this->consumer_key) && !empty($this->consumer_secret) && !empty($this->token) && !empty($this->token_secret))
		{
			// TODO voir comment ça marche (OAuth1)
			// https://devdocs.magento.com/guides/v2.2/get-started/authentication/gs-authentication-oauth.html#pre-auth-token
		}
		else
		{
			$request = new \GuzzleHttp\Psr7\Request(
				'POST'
				,$this->base_uri.'/V1/integration/admin/token'
				,array('Content-Type' => 'application/json')
				,json_encode(array(
					'username'=>$this->username
					,'password'=>$this->password
				))
			);

			try {
				$response = $this->client->send($request);

				$token = $response->getBody()->getContents();
				$token = trim($token, '"');
			} catch (RequestException $e) {
				throw new MagentoWebserviceException("[{$e->getCode()}] {$e->getMessage()}", $e->getCode());
			} catch (\Exception $e) {
				throw new MagentoWebserviceException("[{$e->getCode()}] {$e->getMessage()}", $e->getCode());
			}

			return $token;
		}

		return '';
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

	public function get($options)
	{
		if (isset($options['resource']))
		{
			$queryParams = is_array($options['params']) ? $options['params'] : array();

			$query = \GuzzleHttp\Psr7\build_query($queryParams);

			$request = new \GuzzleHttp\Psr7\Request(
				'GET'
				,$this->base_uri.$options['resource'] . ($query ? "?{$query}" : '')
				,$this->headers
			);
		}
		else
			throw new MagentoWebserviceException('Parameters "resource" is missing');

		try {
			$response = $this->client->send($request);

			// check the response validity
			if (empty($this->error) && $this->checkStatusCode($response->getStatusCode()) )
			{
				return json_decode($response->getBody()->getContents());
			}
		} catch (RequestException $e) {
			throw new MagentoWebserviceException("[{$e->getCode()}] {$e->getMessage()}", $e->getCode());
		} catch (\Exception $e) {
			throw new MagentoWebserviceException("[{$e->getCode()}] {$e->getMessage()}", $e->getCode());
		}

		return false;
	}


	public function post($options)
	{
		if (isset($options['resource']))
		{
			$request = new \GuzzleHttp\Psr7\Request(
				'POST'
				,$this->base_uri.$options['resource']
				,$this->headers
				,json_encode($options['params'])
			);
		}
		else
		{
			throw new MagentoWebserviceException('Parameters "resource" is missing');
		}

		$response = '';

		// check the response validity
		if (empty($this->error) && $this->checkStatusCode($response->getStatusCode()) )
		{
			return json_decode($response->getBody()->getContents());
		}

		return false;
	}
}

class MagentoWebserviceException extends \Exception { }
