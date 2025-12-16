<?php

namespace Laravel\Nightwatch\Hooks;

use Illuminate\Log\Context\Repository;
use Laravel\Nightwatch\Core;
use Laravel\Nightwatch\State\CommandState;
use Laravel\Nightwatch\State\RequestState;
use Throwable;

/**
 * @internal
 */
final class ContextDehydratingHandler
{
    /**
     * @param  Core<RequestState|CommandState>  $nightwatch
     */
    public function __construct(
        private Core $nightwatch,
    ) {
        //
    }

    public function __invoke(Repository $context): void
    {
        try {
            if (($context->getHidden('nightwatch_user_id') ?? '') === '') {
                $context->addHidden('nightwatch_user_id', $this->nightwatch->executionState->user->resolvedUserId());
            }
        } catch (Throwable $e) {
            $this->nightwatch->report($e);
        }
    }
}
