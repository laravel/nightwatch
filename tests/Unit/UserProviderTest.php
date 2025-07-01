<?php

namespace Tests\Unit;

use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Auth;
use Laravel\Nightwatch\UserProvider;
use RuntimeException;
use Tests\TestCase;

use function collect;
use function json_encode;
use function str_repeat;
use function strlen;

class UserProviderTest extends TestCase
{
    public function test_it_limits_the_length_of_the_user_identifier(): void
    {
        Auth::login(new GenericUser([
            'id' => str_repeat('x', 1000),
        ]));
        $provider = new UserProvider($this->app['auth'], fn () => [], fn () => fn () => null);

        $this->assertSame(1000, strlen(Auth::id()));
        $this->assertSame($provider->id(), str_repeat('x', 255));
    }

    public function test_it_can_lazily_retrieve_the_user(): void
    {
        $provider = new UserProvider($this->app['auth'], fn () => [], fn () => fn () => null);

        $id = $provider->id();

        Auth::login(new GenericUser([
            'id' => str_repeat('x', 1000),
        ]));

        $this->assertSame(str_repeat('x', 255), $id->jsonSerialize());
    }

    public function test_it_can_remember_an_authenticated_user_and_limits_the_length_of_their_identifier(): void
    {
        $provider = new UserProvider($this->app['auth'], fn () => [], fn () => fn () => null);
        $provider->remember($user = new GenericUser([
            'id' => str_repeat('x', 1000),
        ]));

        $this->assertSame(str_repeat('x', 255), $provider->id()->jsonSerialize());
    }

    public function test_it_only_reports_exceptions_occurring_while_resolving_user_ids_once_before_user_is_available(): void
    {
        $exceptions = collect();
        $provider = new UserProvider($this->app['auth'], fn () => [], fn () => function ($e) use ($exceptions) {
            $exceptions[] = $e;
        });

        $ids = [
            $provider->id(),
            $provider->id(),
        ];

        $this->app['auth']->setUser(new class([]) extends GenericUser
        {
            public function getAuthIdentifier()
            {
                throw new RuntimeException('Whoops!');
            }
        });

        json_encode($ids);

        $this->assertCount(1, $exceptions);
        $this->assertSame('Whoops!', $exceptions[0]->getMessage());
    }

    public function test_it_only_reports_exceptions_occurring_while_resolving_user_ids_once_after_user_is_available(): void
    {
        $exceptions = collect();
        $provider = new UserProvider($this->app['auth'], fn () => [], fn () => function ($e) use ($exceptions) {
            $exceptions[] = $e;
        });

        $this->app['auth']->setUser(new class([]) extends GenericUser
        {
            public function getAuthIdentifier()
            {
                throw new RuntimeException('Whoops!');
            }
        });

        json_encode([
            $provider->id(),
            $provider->id(),
        ]);

        $this->assertCount(1, $exceptions);
        $this->assertSame('Whoops!', $exceptions[0]->getMessage());
    }

    public function test_it_only_reports_exceptions_occurring_while_resolving_user_ids_once_regardless_of_where_resolving_occurs(): void
    {
        $exceptions = collect();
        $provider = new UserProvider($this->app['auth'], fn () => [], fn () => function ($e) use ($exceptions) {
            $exceptions[] = $e;
        });

        $ids = [
            $provider->id(),
            $provider->id(),
        ];

        $this->app['auth']->setUser(new class([]) extends GenericUser
        {
            public function getAuthIdentifier()
            {
                throw new RuntimeException('Whoops!');
            }
        });

        json_encode($ids);
        json_encode([
            $provider->id(),
            $provider->id(),
        ]);

        $this->assertCount(1, $exceptions);
        $this->assertSame('Whoops!', $exceptions[0]->getMessage());
    }

    public function test_it_allows_reporting_exceptions_occurring_while_resolving_user_ids_again_after_flush(): void
    {
        $exceptions = collect();
        $provider = new UserProvider($this->app['auth'], fn () => [], fn () => function ($e) use ($exceptions) {
            $exceptions[] = $e;
        });
        $this->app['auth']->setUser(new class([]) extends GenericUser
        {
            public function getAuthIdentifier()
            {
                throw new RuntimeException('Whoops!');
            }
        });

        json_encode($provider->id());
        json_encode($provider->id());

        $this->assertCount(1, $exceptions);
        $this->assertSame('Whoops!', $exceptions[0]->getMessage());

        $provider->flush();

        json_encode($provider->id());
        json_encode($provider->id());

        $this->assertCount(2, $exceptions);
        $this->assertSame('Whoops!', $exceptions[1]->getMessage());
    }
}
