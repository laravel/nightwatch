<?php

namespace Laravel\Nightwatch\Console;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use SensitiveParameter;
use Symfony\Component\Console\Attribute\AsCommand;
use Throwable;

use function config;
use function is_string;

/**
 * @internal
 */
#[AsCommand(name: 'nightwatch:deploy', description: 'Send deployment metadata to Nightwatch.')]
final class DeployCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'nightwatch:deploy
        {deploy? : A unique value for the deploy <comment>[default: `NIGHTWATCH_DEPLOY`]</comment>}
        {--ref= : The git ref (tag or hash) of the deploy}
        {--name= : The human-readable name of the deploy}
        {--url= : A URL with information related to the deploy}
        {--timestamp= : The timestamp of the deploy <comment>[default: `now()`]</comment>}';

    /**
     * @var string
     */
    protected $description = 'Send deployment metadata to Nightwatch.';

    public function __construct(
        #[SensitiveParameter] private ?string $token,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $timestamp = is_string($this->option('timestamp')) ? CarbonImmutable::parse($this->option('timestamp')) : CarbonImmutable::now();

        $deploy = $this->argument('deploy') ?? config('nightwatch.deployment');

        if (! $deploy) {
            $this->components->error('Please configure the [NIGHTWATCH_DEPLOY] environment variable.');

            return 0;
        }

        if (! $this->token) {
            $this->components->error('Please configure the [NIGHTWATCH_TOKEN] environment variable.');

            return 0;
        }

        $baseUrl = ! empty($_SERVER['NIGHTWATCH_BASE_URL']) ? $_SERVER['NIGHTWATCH_BASE_URL'] : 'https://nightwatch.laravel.com';

        try {
            Http::connectTimeout(5)
                ->timeout(10)
                ->acceptJson()
                ->withToken($this->token)
                ->post("{$baseUrl}/api/deployments", [
                    'timestamp' => $timestamp->utc()->toDateTimeString('microsecond'),
                    'deploy' => $deploy,
                    'ref' => $this->option('ref'),
                    'name' => $this->option('name'),
                    'url' => $this->option('url'),
                ])
                ->throw();

            $this->components->info('Deployment sent to Nightwatch successfully.');
        } catch (RequestException $e) {
            $message = Str::limit($e->response->json('message') ?? "[{$e->getCode()}] {$e->response->body()}", 1000, '[...]'); // @phpstan-ignore argument.type

            $this->components->error("Deployment could not be sent to Nightwatch: {$message}");
        } catch (Throwable $e) {
            $this->components->error("Deployment could not be sent to Nightwatch: {$e->getMessage()}");
        }

        return 0;
    }
}
