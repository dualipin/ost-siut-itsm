<?php

namespace App\Infrastructure\Security;

use App\Infrastructure\Session\SessionManager;

/**
 * Servicio para manejar tokens CSRF
 */
final class CsrfTokenManager
{
    private const TOKEN_FIELD_NAME = '_csrf_token';
    private const SESSION_KEY = '_csrf_tokens';

    public function __construct(private readonly SessionManager $session) {}

    /**
     * Genera un nuevo token CSRF
     */
    public function generate(): string
    {
        $token = bin2hex(string: random_bytes(32));
        
        $tokens = $this->session->get(self::SESSION_KEY, []);
        $tokens[$token] = time();
        
        $this->session->set(self::SESSION_KEY, $tokens);
        
        return $token;
    }

    /**
     * Verifica si un token CSRF es válido
     */
    public function verify(string $token): bool
    {
        $tokens = $this->session->get(self::SESSION_KEY, []);
        
        if (!isset($tokens[$token])) {
            return false;
        }

        // Token válido por 1 hora
        if (time() - $tokens[$token] > 3600) {
            unset($tokens[$token]);
            $this->session->set(self::SESSION_KEY, $tokens);
            return false;
        }

        // Consumir el token (una sola vez)
        unset($tokens[$token]);
        $this->session->set(self::SESSION_KEY, $tokens);

        return true;
    }

    /**
     * Obtiene el nombre del campo CSRF
     */
    public static function getFieldName(): string
    {
        return self::TOKEN_FIELD_NAME;
    }
}
