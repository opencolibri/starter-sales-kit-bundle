<?php

declare(strict_types=1);

namespace OpenColibri\Starter\SalesKitBundle\Castor;

/**
 * castor-api refused to start a task because another run holds one of its locks.
 */
final class RunConflictException extends \RuntimeException
{
    /**
     * @param array<string, mixed> $conflict the run holding the lock
     */
    public function __construct(
        string $message,
        public readonly array $conflict,
    ) {
        parent::__construct($message);
    }
}
