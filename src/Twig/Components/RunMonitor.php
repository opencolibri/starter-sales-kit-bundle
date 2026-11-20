<?php

declare(strict_types=1);

namespace OpenColibri\Starter\SalesKitBundle\Twig\Components;

use OpenColibri\Starter\SalesKitBundle\Castor\CastorApiClient;
use OpenColibri\Starter\SalesKitBundle\Castor\ConsoleOutput;
use OpenColibri\Starter\SalesKitBundle\Castor\RunDescription;
use OpenColibri\Starter\SalesKitBundle\Import\RunProgress;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveListener;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\UX\LiveComponent\Metadata\UrlMapping;
use Symfony\UX\TwigComponent\Attribute\PostMount;

/**
 * Lists the castor-api runs in progress and shows one of them in a modal, with its status and logs.
 *
 * The run shown in the modal is kept in the URL, so the result stays visible after a reload. When it finishes,
 * the page is reloaded once to refresh the data shown outside of this component.
 */
#[AsLiveComponent]
final class RunMonitor extends AbstractController
{
    use DefaultActionTrait;

    public const STARTED_EVENT = 'run:started';

    #[LiveProp(writable: true, url: new UrlMapping(as: 'run'))]
    public string $runId = '';

    #[LiveProp(writable: true, url: true)]
    public string $task = '';

    /** True while this page follows an unfinished run, to reload the page once it finishes. */
    #[LiveProp]
    public bool $following = false;

    /** The page reloaded when the run finishes. */
    #[LiveProp]
    public string $reloadRoute = 'opencolibri_sales_kit_shop_index';

    /** @var array<string, string> */
    #[LiveProp]
    public array $reloadParams = [];

    /**
     * The runs listed in the tasks panel, by id: the active ones, and those that finished while this page was open.
     *
     * @var array<string, array{task: string, subject: string, status: string, progressKey?: string|null}>
     */
    #[LiveProp]
    public array $runs = [];

    /** @var array<string, mixed>|null */
    private ?array $run = null;

    public function __construct(
        private readonly CastorApiClient $castorApi,
        private readonly RunProgress $runProgress,
    ) {}

    /**
     * Runs after the URL props are read, so a run started by a classic form is followed too.
     */
    #[PostMount]
    public function followUnfinishedRun(): void
    {
        $this->following = $this->isRunning();
        $this->syncRuns();
    }

    #[LiveListener(self::STARTED_EVENT)]
    public function follow(#[LiveArg] string $runId, #[LiveArg] string $task): void
    {
        $this->show($runId, $task);
        $this->syncRuns();
    }

    /**
     * Polled while a run is in progress.
     */
    #[LiveAction]
    public function refresh(): ?Response
    {
        $this->syncRuns();

        if (!$this->following || !$this->isDone()) {
            return null;
        }

        $this->following = false;

        if ('sylius:shop:delete' === $this->task && 'completed' === $this->getStatus()) {
            $this->addFlash('success', 'Shop deleted.');

            return $this->redirectToRoute('opencolibri_sales_kit_shop_index');
        }

        return $this->redirectToRoute($this->reloadRoute, $this->reloadParams + [
            'run' => $this->runId,
            'task' => $this->task,
        ]);
    }

    #[LiveAction]
    public function open(#[LiveArg] string $runId): void
    {
        if (isset($this->runs[$runId])) {
            $this->show($runId, $this->runs[$runId]['task']);
        }
    }

    #[LiveAction]
    public function forget(#[LiveArg] string $runId): void
    {
        unset($this->runs[$runId]);
    }

    #[LiveAction]
    public function dismiss(): void
    {
        $this->runId = '';
        $this->task = '';
        $this->following = false;
        $this->run = null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getRun(): ?array
    {
        if ('' === $this->runId || '' === $this->task) {
            return null;
        }

        if (null === $this->run) {
            try {
                $status = $this->castorApi->status($this->task, $this->runId);
            } catch (\RuntimeException $exception) {
                $status = ['status' => 'unknown', 'error' => $exception->getMessage()];
            }

            $status['stdout'] = ConsoleOutput::withoutProgressBars((string) ($status['stdout'] ?? ''));
            $status['stderr'] = ConsoleOutput::withoutProgressBars((string) ($status['stderr'] ?? ''));
            $this->run = $status;
        }

        return $this->run;
    }

    public function getStatus(): string
    {
        return (string) ($this->getRun()['status'] ?? '');
    }

    public function isRunning(): bool
    {
        return self::isActiveStatus($this->getStatus());
    }

    public function isDone(): bool
    {
        return \in_array($this->getStatus(), ['completed', 'failed', 'unknown'], true);
    }

    public function getTaskLabel(): string
    {
        return RunDescription::label($this->task);
    }

    public function label(string $task): string
    {
        return RunDescription::label($task);
    }

    /**
     * The progress of the image step of an active run, if it reports one.
     *
     * @return array{label: string, done: int, total: int, current: string|null, percent: int}|null
     */
    public function progress(string $runId): ?array
    {
        $run = $this->runs[$runId] ?? null;

        if (null === $run || !self::isActiveStatus($run['status'])) {
            return null;
        }

        return $this->runProgress->read($run['progressKey'] ?? null);
    }

    public function hasActiveRuns(): bool
    {
        foreach ($this->runs as $run) {
            if (self::isActiveStatus($run['status'])) {
                return true;
            }
        }

        return false;
    }

    private function show(string $runId, string $task): void
    {
        $this->runId = $runId;
        $this->task = $task;
        $this->run = null;
        $this->following = $this->isRunning();
    }

    /**
     * Adds the active runs to the panel, and records the final status of the runs that are no longer active.
     */
    private function syncRuns(): void
    {
        try {
            $active = $this->castorApi->listActiveRuns();
        } catch (\RuntimeException) {
            return;
        }

        $activeIds = [];

        foreach ($active as $run) {
            $id = (string) ($run['id'] ?? '');

            if ('' === $id) {
                continue;
            }

            $activeIds[$id] = true;
            $payload = \is_array($run['payload'] ?? null) ? $run['payload'] : [];
            $this->runs[$id] = [
                'task' => (string) ($run['task'] ?? ''),
                'subject' => RunDescription::subject($payload),
                'status' => (string) ($run['status'] ?? 'pending'),
                'progressKey' => RunProgress::key((string) ($run['task'] ?? ''), $payload),
            ];
        }

        foreach ($this->runs as $id => $run) {
            if (isset($activeIds[$id]) || !self::isActiveStatus($run['status'])) {
                continue;
            }

            try {
                $status = (string) ($this->castorApi->status($run['task'], $id)['status'] ?? 'unknown');
            } catch (\RuntimeException) {
                $status = 'unknown';
            }

            // Listed as active a moment ago: it finished in between.
            $this->runs[$id]['status'] = self::isActiveStatus($status) ? 'completed' : $status;
        }
    }

    private static function isActiveStatus(string $status): bool
    {
        return \in_array($status, ['pending', 'running'], true);
    }
}
