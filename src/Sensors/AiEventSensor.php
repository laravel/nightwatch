<?php

namespace Laravel\Nightwatch\Sensors;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Events\AgentPrompted;
use Laravel\Ai\Events\AgentStreamed;
use Laravel\Ai\Events\AudioGenerated;
use Laravel\Ai\Events\EmbeddingsGenerated;
use Laravel\Ai\Events\GeneratingAudio;
use Laravel\Ai\Events\GeneratingEmbeddings;
use Laravel\Ai\Events\GeneratingImage;
use Laravel\Ai\Events\GeneratingTranscription;
use Laravel\Ai\Events\ImageGenerated;
use Laravel\Ai\Events\InvokingTool;
use Laravel\Ai\Events\PromptingAgent;
use Laravel\Ai\Events\StreamingAgent;
use Laravel\Ai\Events\ToolInvoked;
use Laravel\Ai\Events\TranscriptionGenerated;
use Laravel\Ai\Providers\Provider;
use Laravel\Ai\Responses\Data\Usage;
use Laravel\Nightwatch\Clock;
use Laravel\Nightwatch\Records\AiEvent;
use Laravel\Nightwatch\State\CommandState;
use Laravel\Nightwatch\State\RequestState;
use Laravel\Nightwatch\Types\Str;
use RuntimeException;

use function hash;
use function method_exists;
use function round;

/**
 * @internal
 */
final class AiEventSensor
{
    /**
     * @var array<string, float>
     */
    private array $startTimes = [];

    /**
     * @var array<string, array{provider: string, model: string}>
     */
    private array $startContext = [];

    public function __construct(
        private RequestState|CommandState $executionState,
        private Clock $clock,
    ) {
        //
    }

    /**
     * @return ?array{0: AiEvent, 1: callable(): array<mixed>}
     */
    public function __invoke(
        PromptingAgent|StreamingAgent|AgentPrompted|AgentStreamed|
        InvokingTool|ToolInvoked|
        GeneratingAudio|AudioGenerated|
        GeneratingEmbeddings|EmbeddingsGenerated|
        GeneratingImage|ImageGenerated|
        GeneratingTranscription|TranscriptionGenerated $event
    ): ?array {
        $now = $this->clock->microtime();

        // Handle start events
        if ($event instanceof PromptingAgent || $event instanceof StreamingAgent) {
            $this->startTimes[$event->invocationId] = $now;

            return null;
        }

        if ($event instanceof InvokingTool) {
            $this->startTimes[$event->toolInvocationId] = $now;

            return null;
        }

        if ($event instanceof GeneratingAudio ||
            $event instanceof GeneratingEmbeddings ||
            $event instanceof GeneratingImage ||
            $event instanceof GeneratingTranscription) {
            $this->startTimes[$event->invocationId] = $now;
            $this->startContext[$event->invocationId] = [
                'provider' => $event->provider->name(),
                'model' => $event->model,
            ];

            return null;
        }

        // Handle end events
        return match (true) {
            $event instanceof AgentPrompted, $event instanceof AgentStreamed => $this->handleAgentResponse($event, $now),
            $event instanceof ToolInvoked => $this->handleToolInvoked($event, $now),
            $event instanceof AudioGenerated => $this->handleGeneration($event, 'audio_generation', $now),
            $event instanceof EmbeddingsGenerated => $this->handleGeneration($event, 'embeddings_generation', $now),
            $event instanceof ImageGenerated => $this->handleGeneration($event, 'image_generation', $now),
            $event instanceof TranscriptionGenerated => $this->handleGeneration($event, 'transcription_generation', $now),
        };
    }

    /**
     * @return array{0: AiEvent, 1: callable(): array<mixed>}
     */
    private function handleAgentResponse(AgentPrompted|AgentStreamed $event, float $now): array
    {
        $startTime = $this->startTimes[$event->invocationId] ?? null;

        if ($startTime === null) {
            throw new RuntimeException("No start time found for agent invocation [{$event->invocationId}].");
        }

        unset($this->startTimes[$event->invocationId]);

        $usage = $event->response->usage;
        $meta = $event->response->meta;

        return $this->buildRecord(
            operation: $event instanceof AgentStreamed ? 'agent_stream' : 'agent_prompt',
            invocationId: $event->invocationId,
            toolInvocationId: '',
            provider: $meta->provider ?? '',
            model: $meta->model ?? '',
            agent: '',
            tool: '',
            usage: $usage,
            duration: (int) round(($now - $startTime) * 1_000_000),
            failed: false,
            now: $now,
        );
    }

    /**
     * @return array{0: AiEvent, 1: callable(): array<mixed>}
     */
    private function handleToolInvoked(ToolInvoked $event, float $now): array
    {
        $startTime = $this->startTimes[$event->toolInvocationId] ?? null;

        if ($startTime === null) {
            throw new RuntimeException("No start time found for tool invocation [{$event->toolInvocationId}].");
        }

        unset($this->startTimes[$event->toolInvocationId]);

        return $this->buildRecord(
            operation: 'tool_invocation',
            invocationId: $event->invocationId,
            toolInvocationId: $event->toolInvocationId,
            provider: '',
            model: '',
            agent: $this->getClassName($event->agent),
            tool: $this->getClassName($event->tool),
            usage: null,
            duration: (int) round(($now - $startTime) * 1_000_000),
            failed: false,
            now: $now,
        );
    }

    /**
     * @return array{0: AiEvent, 1: callable(): array<mixed>}
     */
    private function handleGeneration(
        AudioGenerated|EmbeddingsGenerated|ImageGenerated|TranscriptionGenerated $event,
        string $operation,
        float $now
    ): array {
        $startTime = $this->startTimes[$event->invocationId] ?? null;
        $context = $this->startContext[$event->invocationId] ?? null;

        if ($startTime === null) {
            throw new RuntimeException("No start time found for generation [{$event->invocationId}].");
        }

        unset($this->startTimes[$event->invocationId], $this->startContext[$event->invocationId]);

        // Get usage if available on response
        $usage = method_exists($event->response, 'usage') ? $event->response->usage : null;

        return $this->buildRecord(
            operation: $operation,
            invocationId: $event->invocationId,
            toolInvocationId: '',
            provider: $context['provider'] ?? $event->provider->name(),
            model: $context['model'] ?? $event->model,
            agent: '',
            tool: '',
            usage: $usage instanceof Usage ? $usage : null,
            duration: (int) round(($now - $startTime) * 1_000_000),
            failed: false,
            now: $now,
        );
    }

    /**
     * @return array{0: AiEvent, 1: callable(): array<mixed>}
     */
    private function buildRecord(
        string $operation,
        string $invocationId,
        string $toolInvocationId,
        string $provider,
        string $model,
        string $agent,
        string $tool,
        ?Usage $usage,
        int $duration,
        bool $failed,
        float $now,
    ): array {
        return [
            $record = new AiEvent(
                operation: $operation,
                invocationId: $invocationId,
                toolInvocationId: $toolInvocationId,
                provider: $provider,
                model: $model,
                agent: $agent,
                tool: $tool,
                promptTokens: $usage?->promptTokens ?? 0,
                completionTokens: $usage?->completionTokens ?? 0,
                cacheReadTokens: $usage?->cacheReadInputTokens ?? 0,
                cacheWriteTokens: $usage?->cacheWriteInputTokens ?? 0,
                reasoningTokens: $usage?->reasoningTokens ?? 0,
                duration: $duration,
                failed: $failed,
            ),
            function () use ($now, $record) {
                $this->executionState->aiEvents++;

                return [
                    'v' => 1,
                    't' => 'ai-event',
                    'timestamp' => $now,
                    'deploy' => $this->executionState->deploy,
                    'server' => $this->executionState->server,
                    '_group' => hash('xxh128', $record->operation.','.$record->provider.','.$record->model.','.$record->agent.','.$record->tool),
                    'trace_id' => $this->executionState->trace,
                    'execution_source' => $this->executionState->source,
                    'execution_id' => $this->executionState->id(),
                    'execution_preview' => $this->executionState->executionPreview(),
                    'execution_stage' => $this->executionState->stage,
                    'user' => $this->executionState->user->id(),
                    // --- //
                    'operation' => Str::tinyText($record->operation),
                    'invocation_id' => Str::tinyText($record->invocationId),
                    'tool_invocation_id' => Str::tinyText($record->toolInvocationId),
                    'provider' => Str::tinyText($record->provider),
                    'model' => Str::tinyText($record->model),
                    'agent' => Str::tinyText($record->agent),
                    'tool' => Str::tinyText($record->tool),
                    'prompt_tokens' => $record->promptTokens,
                    'completion_tokens' => $record->completionTokens,
                    'cache_read_tokens' => $record->cacheReadTokens,
                    'cache_write_tokens' => $record->cacheWriteTokens,
                    'reasoning_tokens' => $record->reasoningTokens,
                    'duration' => $record->duration,
                    'failed' => $record->failed,
                ];
            },
        ];
    }

    private function getClassName(Agent|Tool $object): string
    {
        return $object::class;
    }
}
