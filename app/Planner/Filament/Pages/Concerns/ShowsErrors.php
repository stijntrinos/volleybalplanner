<?php

namespace App\Planner\Filament\Pages\Concerns;

use DomainException;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\View\View;
use Illuminate\Database\QueryException;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * An action that did not go through deserves an explanation, not a toast that
 * is gone after five seconds. This modal names what failed and why.
 *
 * Only for failed actions. Short confirmations and notices such as "this
 * schedule is read-only" stay a notification: they need no explanation and a
 * modal would make the user click for nothing.
 */
trait ShowsErrors
{
    public function errorModalAction(): Action
    {
        return Action::make('errorModal')
            ->modalHeading(fn (array $arguments): string => $arguments['title'] ?? __('Er ging iets mis'))
            ->modalIcon(Heroicon::OutlinedExclamationTriangle)
            ->modalIconColor('danger')
            ->modalDescription(fn (array $arguments): ?string => $arguments['reason'] ?? null)
            ->modalContent(fn (array $arguments): ?View => filled($arguments['details'] ?? [])
                ? view('planner::filament.error-details', ['details' => $arguments['details']])
                : null)
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__('Sluiten'));
    }

    /**
     * @param  list<string>  $details  Lines below the explanation, e.g. the
     *                                 rejected rows of an import file.
     */
    protected function showError(string $title, string $reason, array $details = []): void
    {
        $arguments = compact('title', 'reason', 'details');

        // From within an open action the error modal replaces that action.
        // Filament does not stack a second modal on top of the first, so
        // mountAction() would show nothing there.
        $this->getMountedAction()
            ? $this->replaceMountedAction('errorModal', $arguments)
            : $this->mountAction('errorModal', $arguments);
    }

    /**
     * Runs a task and turns a failure into the error modal. The exception
     * always goes to the log: the user gets the readable side, we get the
     * stack trace.
     *
     * @template T
     *
     * @param  callable(): T  $task
     * @return T|null
     */
    protected function attempt(string $title, callable $task): mixed
    {
        try {
            return $task();
        } catch (Throwable $exception) {
            report($exception);
            $this->showError($title, $this->readableReason($exception));

            return null;
        }
    }

    /**
     * Our own exceptions carry text written for the planning committee and may
     * go on screen as is. Anything else is technical: class and message help
     * when passing on an error, but the query of a database error stays out --
     * it holds the submitted values, which do not belong on a screen.
     */
    private function readableReason(Throwable $exception): string
    {
        if ($exception instanceof QueryException) {
            return __('De database wees de bewerking af. De technische details staan in het logboek.');
        }

        return $exception instanceof DomainException
            || $exception instanceof RuntimeException
            || $exception instanceof InvalidArgumentException
                ? $exception->getMessage()
                : __('Onverwachte fout (:class): :message', [
                    'class' => class_basename($exception),
                    'message' => $exception->getMessage(),
                ]);
    }
}
