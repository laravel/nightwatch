<?php

arch()->expect([
    'Laravel\NightwatchAgent',
])->not->toUse([
    \React\EventLoop\Loop::class,
]);
