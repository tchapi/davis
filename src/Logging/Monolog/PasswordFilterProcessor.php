<?php

namespace App\Logging\Monolog;

use Monolog\Processor\ProcessorInterface;

final class PasswordFilterProcessor implements ProcessorInterface
{
    private const PASSWORD_KEY = 'password';
    private const SENSITIVE_ARGS_FUNCTIONS = ['validateUserPass', 'ldapOpen', 'password_verify', 'imapOpen', 'ldap_bind', 'hashPassword'];

    public function __invoke(array $record): array
    {
        foreach ($record as $key => $item) {
            if (self::PASSWORD_KEY === strtolower($key)) {
                $record[$key] = '****';
            } elseif ('function' === strtolower($key)) {
                // Remove potentially sensitive data from function arguments
                if (in_array($item, self::SENSITIVE_ARGS_FUNCTIONS)) {
                    $record['args'] = ['****'];
                }
            } elseif (is_array($item)) {
                $record[$key] = $this($item);
            }
        }

        return $record;
    }
}
