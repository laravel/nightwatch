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

/**
 * @internal
 */
#[AsCommand(name: 'nightwatch:deploy', description: 'Notify Nightwatch of a deployment.')]
final class DeployCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'nightwatch:deploy';

    /**
     * @var string
     */
    protected $description = 'Notify Nightwatch of a deployment.';

    /**
     * @var bool
     */
    protected $hidden = true;

    public function __construct(
        #[SensitiveParameter] private ?string $token,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if (! $this->token) {
            $this->components->error('Please configure the [NIGHTWATCH_TOKEN] environment variable.');

            return 1;
        }

        $version = config('nightwatch.deployment') ?? '';

        $baseUrl = ! empty($_SERVER['NIGHTWATCH_BASE_URL']) ? $_SERVER['NIGHTWATCH_BASE_URL'] : 'https://nightwatch.laravel.com';

        try {
            Http::connectTimeout(5)
                ->timeout(10)
                ->acceptJson()
                ->withToken($this->token)
                ->post("{$baseUrl}/api/deployments", [
                    'v' => 1,
                    'timestamp' => CarbonImmutable::now()->timestamp,
                    'version' => $version,
                ])
                ->throw();

            $this->components->info('Deployment sent to Nightwatch successfully.');

            return 0;
        } catch (RequestException $e) {
            $message = Str::limit($e->response->json('message') ?? "[{$e->getCode()}] {$e->response->body()}", 1000, '[...]'); // @phpstan-ignore argument.type

            $this->components->error("Deployment could not be sent to Nightwatch: {$message}");

            return 1;
        } catch (Throwable $e) {
            $this->components->error("Deployment could not be sent to Nightwatch: {$e->getMessage()}");

            return 1;
        }
    }
}
