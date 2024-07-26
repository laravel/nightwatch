# Middleware hook points

## Known route

0. Bootstrap                                                       [`LARAVEL_START`]
1. Before middleware                                               [`Event::listen(Booting)`]
    1. Global -> `A::before`
    2. Global -> `B::before`
    3. Global -> `C::before`
    4. Route  -> `NightwatchRouteTerminatingMiddleware::before`
    5. Route  -> `A::before`
    6. Route  -> `B::before`
    7. Route  -> `C::before`
    8. Route  -> `NightwatchRouteMiddleware::before`
2. Action                                                          [`NightwatchRouteMiddleware::before`]
3. Render                                                          [`Event::listen(PreparingResponse)` when `ExecutionPhase::Action`]
4. After middleware                                                [`Event::listen(ResponsePrepared)` when `ExecutionPhase::Render`]
    1. Route  -> `NightwatchRouteMiddleware::after`
    2. Route  -> `C::after`
    3. Route  -> `B::after`
    4. Route  -> `A::after`
    5. Route  -> `NightwatchRouteTerminatingMiddleware::after`
    6. Global -> `B::after`
    7. Global -> `B::after`
    8. Global -> `A::after`
5. Sending                                                         [`Event::listen(RequestHandled)`]
6. Terminating                                                     [`NightwatchRouteTerminatingMiddleware::terminate` when !class_exists(Terminate)] || [`Event::listen(Terminating)` when `class_exists(Terminating)`]
    1. Route  -> `NightwatchRouteTerminatingMiddleware::terminate`
    1. Route  -> `A::terminate` 
    2. Route  -> `B::terminate`
    2. Route  -> `C::terminate`
    3. Route  -> `NightwatchRouteMiddleware::terminate`
    4. Global -> `A::terminate`
    5. Global -> `B::terminate`
    5. Global -> `C::terminate`

## Unknown route

0. Bootstrap                                                       [`LARAVEL_START`]
1. Before middleware                                               [`Event::listen(Booting)`]
    1. Global -> `NightwatchGlobalTerminatingMiddleware::before`
    2. Global -> `A::before`
    3. Global -> `B::before`
    4. Global -> `C::before`
2. After middleware                                                [`Event::listen(ResponsePrepared)` when `ExecutionPhase::BeforeMiddleware`]
    1. Global -> `C::after`
    2. Global -> `B::after`
    3. Global -> `A::after`
    4. Global -> `NightwatchGlobalTerminatingMiddleware::after`
5. Sending                                                         [`Event::listen(RequestHandled)`]
6. Terminating                                                     [`NightwatchGlobalTerminatingMiddleware::terminate` when !class_exists(Terminate)] || [`Event::listen(Terminating)` when `class_exists(Terminating)`]
    1. Global -> `NightwatchGlobalTerminatingMiddleware::terminate`
    2. Global -> `A::terminate`
    3. Global -> `B::terminate`
    4. Global -> `C::terminate`

## When global before middleware returns a response

0. Bootstrap                                                       [`LARAVEL_START`]
1. Before middleware                                               [`Event::listen(Booting)`]
    1. Global -> `NightwatchGlobalTerminatingMiddleware::before`
    2. Global -> `A::before`
    3. Global -> `B::before`
  ~~x. Global -> `C::before`~~
2. After middleware
  ~~x. Global -> `C::before`~~
    1. Global -> `B::after`
    2. Global -> `A::after`
    3. Global -> `NightwatchGlobalTerminatingMiddleware::after`
5. Sending                                                         [`Event::listen(RequestHandled)`]
6. Terminating                                                     [`NightwatchGlobalTerminatingMiddleware::terminate` when !class_exists(Terminate)] || [`Event::listen(Terminating)` when `class_exists(Terminating)`]
    1. Global -> `NightwatchGlobalTerminatingMiddleware::terminate`
    2. Global -> `A::terminate`

## When global after middleware returns a response

0. Bootstrap                                                       [`LARAVEL_START`]
1. Before middleware                                               [`Event::listen(Booting)`]
    1. Global -> `NightwatchGlobalTerminatingMiddleware::before`
    2. Global -> `A::before`
    3. Global -> `B::before`
    4. Global -> `C::before`
// ...
2. After middleware                                                [`Event::listen(ResponsePrepared)` when `ExecutionPhase::BeforeMiddleware`]
    1. Global -> `C::after`
    2. Global -> `B::after`
    3. Global -> `C::after`
    4. Global -> `NightwatchGlobalTerminatingMiddleware::after`
5. Sending                                                         [`Event::listen(RequestHandled)`]
6. Terminating                                                     [`NightwatchGlobalTerminatingMiddleware::terminate` when !class_exists(Terminate)] || [`Event::listen(Terminating)` when `class_exists(Terminating)`]
    1. Global -> `NightwatchGlobalTerminatingMiddleware::terminate`
    2. Global -> `A::terminate`

## When route before middleware returns a response

0. Bootstrap                                                       [`LARAVEL_START`]
1. Before middleware                                               [`Event::listen(Booting)`]
    1. Global -> `A::before`
    2. Global -> `B::before`
    3. Route  -> `NightwatchRouteTerminatingMiddleware::before`
    3. Route  -> `A::before`
2. After middleware                                                [`Event::listen(ResponsePrepared)` when `ExecutionPhase::BeforeMiddleware`]
    1. Global -> `A::after`
    2. Global -> `NightwatchGlobalTerminatingMiddleware::after`
5. Sending                                                         [`Event::listen(RequestHandled)`]
6. Terminating                                                     [`NightwatchGlobalTerminatingMiddleware::terminate` when !class_exists(Terminate)] || [`Event::listen(Terminating)` when `class_exists(Terminating)`]
    1. Global -> `NightwatchGlobalTerminatingMiddleware::terminate`
    2. Global -> `A::terminate`
