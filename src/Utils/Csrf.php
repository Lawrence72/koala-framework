<?php

namespace Koala\Utils;

class Csrf
{
    protected string $tokenKey = 'csrfToken';
    protected int $tokenLifetime = 3600; // 1 hour default

    public function __construct(
        protected Session $session,
        ?int $tokenLifetime = null
    ) {
        if ($tokenLifetime !== null) {
            $this->tokenLifetime = $tokenLifetime;
        }
    }

    /**
     * Generate a new CSRF token and store it in session
     */
    public function generateToken(): string
    {
        $token = bin2hex(random_bytes(32));
        $tokenData = [
            'token' => $token,
            'timestamp' => time()
        ];

        $this->session->set($this->tokenKey, $tokenData);
        return $token;
    }

    /**
     * Verify a CSRF token without removing it from session
     */
    public function verifyToken(string $token): bool
    {
        if (!$this->session->has($this->tokenKey)) {
            return false;
        }

        $tokenData = $this->session->get($this->tokenKey);

        if (!is_array($tokenData)) {
            $this->session->remove($this->tokenKey);
            return false;
        }

        $sessionToken = $tokenData['token'] ?? '';
        $timestamp = $tokenData['timestamp'] ?? 0;
        $isExpired = (time() - $timestamp) > $this->tokenLifetime;

        if ($isExpired) {
            $this->session->remove($this->tokenKey);
            return false;
        }

        return hash_equals($sessionToken, $token);
    }

    /**
     * Get the current token (does NOT generate if none exists)
     */
    public function getToken(): ?string
    {
        if (!$this->session->has($this->tokenKey)) {
            return null;
        }

        $tokenData = $this->session->get($this->tokenKey);

        if (!is_array($tokenData)) {
            $this->session->remove($this->tokenKey);
            return null;
        }

        $token = $tokenData['token'] ?? '';
        $timestamp = $tokenData['timestamp'] ?? 0;

        if ((time() - $timestamp) > $this->tokenLifetime) {
            $this->session->remove($this->tokenKey);
            return null;
        }

        return $token;
    }

    /**
     * Get token, generating one if needed (for initial page loads)
     */
    public function ensureToken(): string
    {
        $token = $this->getToken();
        return $token ?? $this->generateToken();
    }

    /**
     * Check if a valid token exists in session
     */
    public function hasValidToken(): bool
    {
        if (!$this->session->has($this->tokenKey)) {
            return false;
        }

        $tokenData = $this->session->get($this->tokenKey);

        if (!is_array($tokenData)) {
            $this->session->remove($this->tokenKey);
            return false;
        }

        $timestamp = $tokenData['timestamp'] ?? 0;
        return (time() - $timestamp) <= $this->tokenLifetime;
    }

    /**
     * Remove the current token from session
     */
    public function removeToken(): void
    {
        $this->session->remove($this->tokenKey);
    }

    /**
     * Regenerate the token (useful after successful form submission)
     */
    public function regenerateToken(): string
    {
        $this->removeToken();
        return $this->generateToken();
    }

    /**
     * Get HTML input field for forms (only if token exists)
     */
    public function getTokenField(): string
    {
        $token = $this->getToken();
        if ($token === null) {
            return '<!-- No CSRF token available -->';
        }

        return sprintf(
            '<input type="hidden" name="csrfToken" value="%s">',
            htmlspecialchars($token, ENT_QUOTES, 'UTF-8')
        );
    }

    /**
     * Get token for JavaScript/AJAX requests (only if token exists)
     */
    public function getTokenForAjax(): ?array
    {
        $token = $this->getToken();
        if ($token === null) {
            return null;
        }

        return [
            'csrfToken' => $token,
            'expires' => time() + $this->tokenLifetime
        ];
    }
}
