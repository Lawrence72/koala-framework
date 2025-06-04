<?php

namespace Koala\Middleware;

use Koala\Application;
use Koala\Request\Request;
use Koala\Utils\Csrf;

class CsrfMiddleware
{
    public function __construct(
        protected Application $app,
        protected Csrf $csrf
    ) {}

    /**
     * Handle CSRF token verification for state-changing requests
     */
    public function handle(Request $request, callable $next): mixed
    {
        $method = $request->getMethod();

        if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            $token = $this->getTokenFromRequest($request);

            if ($token === null || !$this->csrf->verifyToken($token)) {
                return $this->app->response->json([
                    'error' => 'Invalid CSRF token'
                ], 419);
            }
        }

        return $next();
    }

    /**
     * Extract CSRF token from request (supports both form and JSON requests)
     */
    protected function getTokenFromRequest(Request $request): ?string
    {
        $token = $request->getPostParam('csrfToken');

        if ($token === null && $request->isJson()) {
            $token = $request->getJsonParam('csrfToken');
        }

        if ($token === null) {
            $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        }

        return $token;
    }

    /**
     * Alternative method name for backward compatibility
     */
    public function verifyCsrfToken(Request $request, callable $next): mixed
    {
        return $this->handle($request, $next);
    }
}
