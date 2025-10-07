<?php

namespace Laravel\Nightwatch\Console;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Http\Client\Factory as HttpFactory;
use SensitiveParameter;
use Symfony\Component\Console\Attribute\AsCommand;

use function config;
use function now;

/**
 * @internal
 */
#[AsCommand(name: 'nightwatch:deploy', description: 'Notice the Nightwatch agent that a new deployment has been made.')]
final class DeployCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'nightwatch:deploy';

    protected $hidden = true;

    /**
     * @var string
     */
    protected $description = 'Notice the Nightwatch agent that a new deployment has been made.';

    public function __construct(
        private HttpFactory $http,
        private ?string $baseUrl,
        #[SensitiveParameter] private ?string $token,
    ) {
        parent::__construct();
    }

    public function handle(): void
    {
        $tag = config('nightwatch.deployment') ?? '';

        if (! $this->baseUrl) {
            $this->error('No Nightwatch base URL configured.');

            return;
        }

        if (! $this->token) {
            $this->error('No Nightwatch token configured.');

            return;
        }

        try {
            $response = $this->http
                ->withHeaders([
                    'Authorization' => "Bearer {$this->token}",
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ])
                ->post("{$this->baseUrl}/api/deployments", [
                    'timestamp' => now()->timestamp,
                    'version' => $tag,
                ]);

            if ($response->successful()) {
                $this->info('Deployment successful');
            } else {
                $this->error("Deployment failed: {$response->status()} {$response->body()}");
            }
        } catch (Exception $e) {
            $this->error("Deployment failed: {$e->getMessage()}");
        }
    }
}
