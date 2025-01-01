<?php

namespace Koala\Middleware;

use Koala\Application;
use Koala\Request\Request;
use Koala\Utils\Csrf;

class CsrfMiddleware
{
	protected $app;

	public function __construct(Application $app, protected Csrf $csrf)
	{
		$this->app = $app;
	}

	public function verifyCsrfToken(Request $request, callable $next)
	{
		$method = $request->getMethod();

		if ($method === 'POST') {
			$token = $request->getPostParam('csrf_token');

			if (!$this->csrf->verifyToken($token)) {
				return $this->app->response->json([
					'error' => 'Unauthorized'
				], 401);
			}
		}
		return $next();
	}
}
