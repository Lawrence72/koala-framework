<?php

namespace Koala\Utils;

class Session
{
    protected ?string $encryptionKey = null;

    public function __construct(?string $sessionName = null, ?string $encryptionKey = null)
    {
        $this->encryptionKey = $encryptionKey;

        if ($sessionName !== null && $sessionName !== '') {
            $this->setSessionName($sessionName);
        }

        self::start();
    }

    public function setSessionName(string $sessionName): void
    {
        if (session_status() !== PHP_SESSION_NONE) {
            throw new \RuntimeException('Cannot change session name after session has started');
        }
        session_name($sessionName);
    }

    public static function start(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $this->encryptionKey !== null ? $this->encrypt($value) : $value;
    }

    public function get(string $key): mixed
    {
        if (!$this->has($key)) {
            return null;
        }

        return $this->encryptionKey !== null ? $this->decrypt($_SESSION[$key]) : $_SESSION[$key];
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $_SESSION);
    }

    public function remove(string $key): void
    {
        if ($this->has($key)) {
            unset($_SESSION[$key]);
        }
    }

    public function destroy(): void
    {
        session_destroy();
    }

    public function setFlash(string $text, string $type): void
    {
        if (!$this->has('koalaFlash') || !is_array($this->get('koalaFlash'))) {
            $this->set('koalaFlash', []);
        }

        $koalaFlash = $this->get('koalaFlash');
        $koalaFlash[] = ['message' => $text, 'type' => $type];
        $this->set('koalaFlash', $koalaFlash);
    }

    public function getFlash(): array
    {
        $flash = $this->has('koalaFlash') ? $this->get('koalaFlash') : [];
        $this->remove('koalaFlash');
        return $flash;
    }

    /**
     * Encrypt a value using AES-256-CBC with random IV
     */
    protected function encrypt(mixed $value): string
    {
        if ($this->encryptionKey === null) {
            throw new \RuntimeException('Encryption key not set');
        }
        
        $serialized = serialize($value);

        $iv = random_bytes(16);

        $encrypted = openssl_encrypt($serialized, 'AES-256-CBC', $this->encryptionKey, OPENSSL_RAW_DATA, $iv);

        if ($encrypted === false) {
            throw new \RuntimeException('Encryption failed');
        }

        return base64_encode($iv . $encrypted);
    }

    /**
     * Decrypt a value encrypted with encrypt()
     */
    protected function decrypt(string $encryptedValue): mixed
    {
        if ($this->encryptionKey === null) {
            throw new \RuntimeException('Encryption key not set');
        }

        $data = base64_decode($encryptedValue);

        if ($data === false || strlen($data) < 16) {
            throw new \RuntimeException('Invalid encrypted data');
        }

        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);

        $decrypted = openssl_decrypt($encrypted, 'AES-256-CBC', $this->encryptionKey, OPENSSL_RAW_DATA, $iv);

        if ($decrypted === false) {
            throw new \RuntimeException('Decryption failed');
        }

        return unserialize($decrypted);
    }

    /**
     * Regenerate session ID for security
     */
    public function regenerateId(bool $deleteOldSession = true): void
    {
        session_regenerate_id($deleteOldSession);
    }
}
