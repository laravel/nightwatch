# Lifecycle hook

## Known route

This is the expected flow for majority requests.

### Flow

1. Bootstrap                                                       [`LARAVEL_START`]
2. Before middleware                                               [`Event::listen(Booting)`]
    1. Global -> `NightwatchTerminatingMiddleware::before`
    2. Global -> `A::before`
    3. Global -> `B::before`
    4. Global -> `C::before`
    5. Route  -> `NightwatchTerminatingMiddleware::before`
    6. Route  -> `A::before`
    7. Route  -> `B::before`
    8. Route  -> `C::before`
    9. Route  -> `NightwatchRouteMiddleware::before`
3. Action                                                          [`NightwatchRouteMiddleware::before`]
4. Render                                                          [`Event::listen(PreparingResponse)` when `ExecutionPhase::Action`]
5. After middleware                                                [`Event::listen(ResponsePrepared)` when `ExecutionPhase::Render`]
    1. Route  -> `NightwatchRouteMiddleware::after`
    2. Route  -> `C::after`
    3. Route  -> `B::after`
    4. Route  -> `A::after`
    5. Route  -> `NightwatchTerminatingMiddleware::after`
    6. Global -> `C::after`
    7. Global -> `B::after`
    8. Global -> `A::after`
    9. Global -> `NightwatchTerminatingMiddleware::before`
6. Sending                                                         [`Event::listen(RequestHandled)`]
7. Terminating                                                     [`NightwatchTerminatingMiddleware::terminate` when !class_exists(Terminate)] || [`Event::listen(Terminating)` when `class_exists(Terminating)`]
    1. Route  -> `NightwatchTerminatingMiddleware::terminate`
    2. Route  -> `A::terminate` 
    3. Route  -> `B::terminate`
    4. Route  -> `C::terminate`
    6. Route  -> `NightwatchRouteMiddleware::terminate`
    7. Global  -> `NightwatchTerminatingMiddleware::terminate`
    8. Global -> `A::terminate`
    9. Global -> `B::terminate`
    10. Global -> `C::terminate`
    11. Terminating callbacks

## Unknown route

This flow illustrates what happens when a request and no route is matched, e.g., `/wp-admin`.

### UI considerations

- Hide action and render
- Collapse before and after middleware into a single phase

### Flow

1. Bootstrap                                                       [`LARAVEL_START`]
2. Before middleware                                               [`Event::listen(Booting)`]
    1. Global -> `NightwatchTerminatingMiddleware::before`
    2. Global -> `A::before`
    3. Global -> `B::before`
    4. Global -> `C::before`
3. After middleware                                                [`Event::listen(ResponsePrepared)` when `ExecutionPhase::BeforeMiddleware`]
    1. Global -> `C::after`
    2. Global -> `B::after`
    3. Global -> `A::after`
    4. Global -> `NightwatchTerminatingMiddleware::after`
4. Sending                                                         [`Event::listen(RequestHandled)`]
5. Terminating                                                     [`NightwatchTerminatingMiddleware::terminate` when !class_exists(Terminate)] || [`Event::listen(Terminating)` when `class_exists(Terminating)`]
    1. Global -> `NightwatchTerminatingMiddleware::terminate`
    2. Global -> `A::terminate`
    3. Global -> `B::terminate`
    4. Global -> `C::terminate`
    5. Terminating callbacks

## Global before middleware returns a response

This flow illustrates what happens when a global middleware returns a response and does not call `$next($request)`, e.g., when the `PreventRequestsDuringMaintenance` returns a maintenance mode response.

### UI considerations

- Hide action, render, and after middleware

### Flow

`B::before` will return the response.

1. Bootstrap                                                       [`LARAVEL_START`]
2. Before middleware                                               [`Event::listen(Booting)`]
    1. Global -> `NightwatchTerminatingMiddleware::before`
    2. Global -> `A::before`
    3. Global -> `B::before` <
  ~~x. Global -> `C::before`~~
~~x. After middleware~~
  ~~x. Global -> `C::before`~~
  ~~x. Global -> `B::after`~~
    4. Global -> `A::after`
    5. Global -> `NightwatchTerminatingMiddleware::after`
3. Sending                                                         [`Event::listen(RequestHandled)`]
4. Terminating                                                     [`NightwatchTerminatingMiddleware::terminate` when !class_exists(Terminate)] || [`Event::listen(Terminating)` when `class_exists(Terminating)`]
    1. Global -> `NightwatchTerminatingMiddleware::terminate`
    2. Global -> `A::terminate`
    3. Terminating callbacks

## Global after middleware returns a different response

This may happen if a global middleware wants to decorate a response or augment a response in some way.

### Flow

`B::after` will return the response.

1. Bootstrap                                                       [`LARAVEL_START`]
2. Before middleware                                               [`Event::listen(Booting)`]
    1. Global -> `NightwatchTerminatingMiddleware::before`
    2. Global -> `A::before`
    3. Global -> `B::before`
    4. Global -> `C::before`
3. Action
4. Render
5. After middleware                                                [`Event::listen(ResponsePrepared)` when `ExecutionPhase::BeforeMiddleware`]
    1. Global -> `C::after`
    2. Global -> `B::after` <
    3. Global -> `C::after`
    4. Global -> `NightwatchTerminatingMiddleware::after`
6. Sending                                                         [`Event::listen(RequestHandled)`]
7. Terminating                                                     [`NightwatchTerminatingMiddleware::terminate` when !class_exists(Terminate)] || [`Event::listen(Terminating)` when `class_exists(Terminating)`]
    1. Global -> `NightwatchTerminatingMiddleware::terminate`
    2. Global -> `A::terminate`
    3. Global -> `B::terminate`
    4. Global -> `C::terminate`
    5. Terminating callbacks

## When route before middleware returns a response

This flow illustrates what happens when a route middleware returns a response and does not call `$next($request)`, e.g., when the `Authenticate` middleware is present the request is made by a guest.

### UI considerations

- Hide action, render, and after middleware

### Flow

`B::before` returns the response.

1. Bootstrap                                                       [`LARAVEL_START`]
2. Before middleware                                               [`Event::listen(Booting)`]
    1. Global -> `NightwatchTerminatingMiddleware::before`
    2. Global -> `A::before`
    3. Global -> `B::before`
    4. Global -> `C::before`
    5. Route  -> `NightwatchTerminatingMiddleware::before`
    6. Route  -> `A::before`
    7. Route  -> `B::before` <
  ~~x. Route  -> `C::before`~~
  ~~x. Route  -> `NightwatchRouteMiddleware::before`~~
~~x. Action                                                          [`NightwatchRouteMiddleware::before`]~~
~~x. Render                                                          [`Event::listen(PreparingResponse)` when `ExecutionPhase::Action`]~~
~~x. After middleware                                                [`Event::listen(ResponsePrepared)` when `ExecutionPhase::Render`]~~
  ~~x. Route  -> `NightwatchRouteMiddleware::after`~~
  ~~x. Route  -> `C::after`~~
  ~~x. Route  -> `B::after`~~
    1. Route  -> `A::after`
    2. Route  -> `NightwatchTerminatingMiddleware::after`
    3. Global -> `C::after`
    4. Global -> `B::after`
    5. Global -> `A::after`
    2. Global  -> `NightwatchTerminatingMiddleware::after`
6. Sending                                                         [`Event::listen(RequestHandled)`]
7. Terminating                                                     [`NightwatchTerminatingMiddleware::terminate` when !class_exists(Terminate)] || [`Event::listen(Terminating)` when `class_exists(Terminating)`]
    1. Route  -> `NightwatchTerminatingMiddleware::terminate`
    2. Route  -> `A::terminate` 
    3. Route  -> `B::terminate`
    4. Route  -> `C::terminate`
    5. Route  -> `NightwatchRouteMiddleware::terminate`
    6. Global  -> `NightwatchTerminatingMiddleware::terminate`
    7. Global -> `A::terminate`
    8. Global -> `B::terminate`
    9. Global -> `C::terminate`
    10. Terminating callbacks

## When route after middleware returns a response

This flow illustrates what happens when a route middleware returns a different response from that received after calling `$next($request)`, e.g., when a middleware wants to decorate or augment a response.

### Flow

`B::after` returns the response.

1. Bootstrap                                                       [`LARAVEL_START`]
2. Before middleware                                               [`Event::listen(Booting)`]
    1. Global -> `NightwatchTerminatingMiddleware::before`
    2. Global -> `A::before`
    3. Global -> `B::before`
    4. Global -> `C::before`
    5. Route  -> `NightwatchTerminatingMiddleware::before`
    6. Route  -> `A::before`
    7. Route  -> `B::before` 
    8. Route  -> `C::before`
    9. Route  -> `NightwatchRouteMiddleware::before`
3. Action                                                          [`NightwatchRouteMiddleware::before`]
4. Render                                                          [`Event::listen(PreparingResponse)` when `ExecutionPhase::Action`]
5. After middleware                                                [`Event::listen(ResponsePrepared)` when `ExecutionPhase::Render`]
    1. Route  -> `NightwatchRouteMiddleware::after`
    2. Route  -> `C::after`
    3. Route  -> `B::after` <
    4. Route  -> `A::after`
    5. Route  -> `NightwatchTerminatingMiddleware::after`
    6. Global -> `C::after`
    7. Global -> `B::after`
    8. Global -> `A::after`
    9. Global  -> `NightwatchTerminatingMiddleware::after`
6. Sending                                                         [`Event::listen(RequestHandled)`]
7. Terminating                                                     [`NightwatchTerminatingMiddleware::terminate` when !class_exists(Terminate)] || [`Event::listen(Terminating)` when `class_exists(Terminating)`]
    1. Route  -> `NightwatchTerminatingMiddleware::terminate`
    2. Route  -> `A::terminate` 
    3. Route  -> `B::terminate`
    4. Route  -> `C::terminate`
    5. Route  -> `NightwatchRouteMiddleware::terminate`
    6. Global  -> `NightwatchTerminatingMiddleware::terminate`
    7. Global -> `A::terminate`
    8. Global -> `B::terminate`
    9. Global -> `C::terminate`
    10. Terminating callbacks
