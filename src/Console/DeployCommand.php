<?php

namespace Laravel\Nightwatch\Console;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use SensitiveParameter;
use Symfony\Component\Console\Attribute\AsCommand;
use Throwable;

use function config;
use function strlen;
use function substr;

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

        $baseUrl = $_SERVER['NIGHTWATCH_BASE_URL'] ?? 'https://nightwatch.laravel.com';

        try {
            $response = Http::connectTimeout(5)
                ->timeout(10)
                ->withHeaders([
                    'Authorization' => "Bearer {$this->token}",
                    'Accept' => 'application/json',
                ])
                ->post("{$baseUrl}/api/deployments", [
                    'timestamp' => CarbonImmutable::now()->timestamp,
                    'version' => $tag,
                ]);

            if ($response->successful()) {
                $this->info('Deployment successful');

                return 0;
            } else {
                $message = $response->body();

                if (strlen($message) > 1005) {
                    $message = substr($message, 0, 1000).'[...]';
                }

                $this->error("Deployment failed: {$response->status()} [{$message}]");

                return 1;
            }
        } catch (Throwable $e) {
            $this->error("Deployment failed: [{$e->getMessage()}]");

            return 1;
        }
    }
}
