<?php

namespace Koala\Utils;

class Csrf
{
	public function  __construct(protected Session $session) {}

	/**
	 * 
	 * @return string 
	 */
	public function generateToken(): string
	{
		$token = bin2hex(random_bytes(32));
		$this->session->set('csrf_token', $token);
		return $token;
	}

	/**
	 * 
	 * @param string $token 
	 * @return bool 
	 */
	public function verifyToken(string $token): bool
	{
		if (!$this->session->has('csrf_token')) {
			return false;
		}

		$valid_token = hash_equals($this->session->get('csrf_token'), $token);

		$this->session->remove('csrf_token');

		return $valid_token;
	}
}