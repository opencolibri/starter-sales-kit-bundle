<?php

declare(strict_types=1);

namespace OpenColibri\Starter\SalesKitBundle\Twig\Components;

use OpenColibri\Starter\SalesKitBundle\Castor\CastorApiClient;
use OpenColibri\Starter\SalesKitBundle\Castor\RunConflictException;
use OpenColibri\Starter\SalesKitBundle\Castor\RunDescription;
use Symfony\UX\LiveComponent\ComponentToolsTrait;

/**
 * Starts a castor-api run and hands it over to the RunMonitor component of the page.
 *
 * @property CastorApiClient $castorApi
 */
trait StartsRunTrait
{
    use ComponentToolsTrait;

    public ?string $error = null;

    /**
     * @param array<string, mixed> $payload
     */
    private function startRun(string $task, array $payload): void
    {
        try {
            $started = $this->castorApi->start($task, $payload);
        } catch (RunConflictException $exception) {
            $subject = RunDescription::subject(\is_array($exception->conflict['payload'] ?? null) ? $exception->conflict['payload'] : []);
            $this->error = \sprintf(
                '"%s"%s is still running. Wait for it to finish before starting "%s".',
                RunDescription::label((string) ($exception->conflict['task'] ?? '')),
                '' !== $subject ? ' (' . $subject . ')' : '',
                RunDescription::label($task),
            );

            return;
        } catch (\RuntimeException $exception) {
            $this->error = $exception->getMessage();

            return;
        }

        $this->emit(RunMonitor::STARTED_EVENT, [
            'runId' => $started['id'],
            'task' => $started['task'],
        ]);
    }
}
