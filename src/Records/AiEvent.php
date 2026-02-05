<?php

namespace Laravel\Nightwatch\Records;

/**
 * @internal
 */
final class AiEvent
{
    public function __construct(
        public readonly string $operation,
        public readonly string $invocationId,
        public readonly string $toolInvocationId,
        public readonly string $provider,
        public readonly string $model,
        public readonly string $agent,
        public readonly string $tool,
        public readonly int $promptTokens,
        public readonly int $completionTokens,
        public readonly int $cacheReadTokens,
        public readonly int $cacheWriteTokens,
        public readonly int $reasoningTokens,
        public readonly int $duration,
        public readonly bool $failed,
    ) {
        //
    }
}
