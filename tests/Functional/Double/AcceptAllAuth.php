<?php

declare(strict_types=1);

namespace App\Tests\Functional\Double;

use App\Services\AbstractAuth;

/**
 * A backend that accepts everything and records what reached it, to prove the guard in
 * AbstractAuth never lets an empty username or password through to the concrete backend.
 */
final class AcceptAllAuth extends AbstractAuth
{
    /** @var list<array{string, string}> */
    public array $seen = [];

    protected function checkCredentials(string $username, string $password): bool
    {
        $this->seen[] = [$username, $password];

        return true;
    }
}
