<?php

namespace Koala\Utils;

class Cookie
{

	/**
	 * 
	 * @param string $key 
	 * @param mixed $value 
	 * @param int $time 
	 * @param string $path 
	 * @param string $domain 
	 * @param int $secure 
	 * @param int $http_only 
	 * @return bool 
	 */
	public function set(string $key, $value, $time = 3600, $path = '/', $domain = '', $secure = 0, $http_only = 1): bool
	{
		setcookie($key, $value, $time, $path, $domain, $secure, $http_only);
		return true;
	}

	/**
	 * 
	 * @param string $key 
	 * @return mixed 
	 */
	public function get(string $key)
	{
		return $_COOKIE[$key] ?? null;
	}

	/**
	 * 
	 * @param string $key 
	 * @return bool 
	 */
	public function has(string $key)
	{
		return isset($_COOKIE[$key]);
	}

	/**
	 * 
	 * @param string $key 
	 * @return void 
	 */
	public function remove(string $key)
	{
		unset($_COOKIE[$key]);
	}
}
