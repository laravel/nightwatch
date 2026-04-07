<?php

namespace Laravel\Nightwatch\Support;

use Ramsey\Uuid\Uuid as BaseUuid;

use function call_user_func;

/**
 * @internal
 */
final class Uuid
{
    private const UUID_SEED = 'd7b1993d-1268-4f10-88e1-6fab60dbc51b';

    /**
     * @param  (callable(): string)  $uuidResolver
     */
    public function __construct(public $uuidResolver)
    {
        //
    }

    public function make(?string $from = null): string
    {
        if ($from !== null && $from !== '') {
            return BaseUuid::uuid5(self::UUID_SEED, $from)->toString();
        }

        return call_user_func($this->uuidResolver);
    }
}
