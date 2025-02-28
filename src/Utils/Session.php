<?php

namespace Koala\Utils;

class Session
{

	protected $encryption_key;

	public function __construct(string $session_name = null, $encryption_key = null)
	{
		$this->encryption_key = $encryption_key;
		if (!empty($session_name)) {
			$this->setSessionName($session_name);
		}
		self::start();
	}

	public function setSessionName(string $session_name)
	{
		session_name($session_name);
	}

	public static function start()
	{
		if (session_status() == PHP_SESSION_NONE) {
			session_start();
		}
	}

	public function set(string $key, $value)
	{
		$_SESSION[$key] = !empty($this->encryption_key) ? $this->encrypt($value) : $value;
	}

	public function get(string $key)
	{
		return $this->has($key) ? (!empty($this->encryption_key) ? $this->decrypt($_SESSION[$key]) : $_SESSION[$key]) : null;
	}

	public function has(string $key)
	{
		return array_key_exists($key, $_SESSION);
	}

	public function remove(string $key)
	{
		if ($this->has($key)) {
			unset($_SESSION[$key]);
		}
	}

	public function destroy()
	{
		session_destroy();
	}

	public function setFlash($text, $type)
	{
		if (!$this->has('koala_flash') || !is_array($this->get('koala_flash'))) {
			$this->set('koala_flash', []);
		}
		$koala_flash = $this->get('koala_flash');
		$koala_flash[] = ['message' => $text, 'type' => $type];
		$this->set('koala_flash', $koala_flash);
	}

	public function getFlash()
	{
		$flash =  $this->has('koala_flash') ? $this->get('koala_flash') : [];
		$this->remove('koala_flash');
		return $flash;
	}

	protected function encrypt($value)
	{
		return openssl_encrypt($value, 'AES-256-CBC', $this->encryption_key, 0, substr($this->encryption_key, 0, 16));
	}

	protected function decrypt($value)
	{
		return openssl_decrypt($value, 'AES-256-CBC', $this->encryption_key, 0, substr($this->encryption_key, 0, 16));
	}
}
