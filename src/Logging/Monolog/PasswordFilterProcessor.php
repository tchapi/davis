<?php

namespace App\Logging\Monolog;

use Monolog\Processor\ProcessorInterface;

final class PasswordFilterProcessor implements ProcessorInterface
{
    private const REDACTED = '****';
    private const PASSWORD_KEY = 'password';
    private const SENSITIVE_ARGS_FUNCTIONS = ['validateUserPass', 'ldapOpen', 'password_verify', 'imapOpen', 'ldap_bind', 'hashPassword', 'dav'];

    public function __invoke(array $record): array
    {
        // Remove potentially sensitive data from function arguments
        $shouldRedactArgs = array_key_exists('function', $record) && in_array($record['function'], self::SENSITIVE_ARGS_FUNCTIONS);

        foreach ($record as $key => $item) {
            if (self::PASSWORD_KEY === strtolower($key) || ('args' === $key && $shouldRedactArgs)) {
                $record[$key] = self::REDACTED;
            } elseif (is_array($item)) {
                $record[$key] = $this($item);
            }
        }

        return $record;
    }
}
