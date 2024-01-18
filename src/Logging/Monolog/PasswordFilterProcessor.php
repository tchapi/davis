<?php

namespace App\Logging\Monolog;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

final class PasswordFilterProcessor implements ProcessorInterface
{
    private const REDACTED = '****';
    private const PASSWORD_KEYS = ['pass', 'password'];
    private const SENSITIVE_ARGS_FUNCTIONS = ['validateUserPass', 'ldapOpen', 'password_verify', 'imapOpen', 'ldap_bind', 'hashPassword', 'dav'];

    public static function redactContextRecursive(array $context): array
    {
        // Remove potentially sensitive data from function arguments
        $shouldRedactArgs = array_key_exists('function', $context) && in_array($context['function'], self::SENSITIVE_ARGS_FUNCTIONS);

        foreach ($context as $key => $item) {
            if (in_array(strtolower($key), self::PASSWORD_KEYS) || ('args' === $key && $shouldRedactArgs)) {
                $context[$key] = self::REDACTED;
            } elseif (is_array($item)) {
                $context[$key] = static::redactContextRecursive($item);
            }
        }

        return $context;
    }

    public function __invoke(LogRecord $record): LogRecord
    {
        $context = $record->context;

        $redactedContext = static::redactContextRecursive($context);

        return $record->with(context: $redactedContext);
    }
}
