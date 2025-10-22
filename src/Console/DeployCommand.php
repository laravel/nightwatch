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
            $this->error('No NIGHTWATCH_TOKEN environment variable configured.');

            return 1;
        }

        $tag = config('nightwatch.deployment') ?? '';

        $baseUrl = ! empty($_SERVER['NIGHTWATCH_BASE_URL']) ? $_SERVER['NIGHTWATCH_BASE_URL'] : 'https://nightwatch.laravel.com';

        try {
            Http::connectTimeout(5)
                ->timeout(10)
                ->withHeaders([
                    'Authorization' => "Bearer {$this->token}",
                    'Accept' => 'application/json',
                ])
                ->post("{$baseUrl}/api/deployments", [
                    'timestamp' => CarbonImmutable::now()->timestamp,
                    'version' => $tag,
                ])
                ->throw();

            $this->info('Deployment successful');

            return 0;
        } catch (RequestException $e) {
            $message = Str::limit($e->response->body(), 1000, '[...]');

            $this->error("Deployment failed: {$e->getCode()} [{$message}]");

            return 1;
        } catch (Throwable $e) {
            $this->error("Deployment failed: [{$e->getMessage()}]");

            return 1;
        }
    }
}
