# Laravel Nightwatch Agent Guidelines

## Project Overview

Laravel Nightwatch is a hosted application monitoring platform package for Laravel. It gathers metrics within Laravel applications and transmits them to the Nightwatch service. This is an official Laravel package that must maintain high code quality standards.

## Code Style & Formatting

- **Use Laravel Pint** with the project's configuration (`pint.json`)
- **Strict comparisons**: Use `===` and `!==` instead of `==` and `!=`
- **Strict parameters**: Enable strict parameter checking
- **Global namespace imports**: Import classes, constants, and functions from global namespace
- **Native function invocation**: Use namespaced native function calls
- **Method chaining indentation**: Properly indent method chains
- **Explicit string variables**: Use explicit string variable syntax when appropriate
- **Combine consecutive isset/unset**: Group consecutive isset/unset calls
- **Ternary to null coalescing**: Prefer null coalescing operator (`??`) over ternary

## Type Safety

- **PHPStan Level Max**: All code must pass PHPStan at maximum level
- **Type hints**: Always provide type hints for:
  - Method parameters
  - Return types
  - Properties (with PHPDoc when needed)
- **Generic types**: Use PHPDoc generics for complex types (e.g., `@template TState`)
- **Array shapes**: Document array shapes with PHPDoc (e.g., `array{enabled: bool, sampling: array{...}}`)

## Architecture Patterns

### Hooks Pattern
- Hooks are event listeners that capture Laravel framework events
- Located in `src/Hooks/`
- Should be `final` classes marked `@internal`
- Always wrap hook logic in try-catch and report exceptions as handled
- Example pattern:
```php
final class QueryExecutedListener
{
    public function __construct(
        private Core $nightwatch,
    ) {}

    public function __invoke(QueryExecuted $event): void
    {
        try {
            $this->nightwatch->query($event);
        } catch (Throwable $e) {
            $this->nightwatch->report($e, handled: true);
        }
    }
}
```

### Sensors Pattern
- Sensors transform events into records and payload factories
- Located in `src/Sensors/`
- Should be `final` classes marked `@internal`
- Return tuple: `[Record, callable(): array<mixed>]` (for record-based sensors) or `?array` (for direct array sensors like `ScheduledTaskSensor`)
- Use `Location` helper for file/line detection
- Use `Clock` for timestamp management
- **Check property visibility**: Before using reflection, verify if Laravel properties are public. Prefer direct property access over reflection when possible.
- **Trust Laravel's internal constraints**: If Laravel enforces constraints on a value (e.g., requires >= 1 second for repeating tasks), trust those constraints rather than re-checking in our code

### State Management
- Use `RequestState` for HTTP requests
- Use `CommandState` for Artisan commands, scheduled tasks, and job attempts
- State is managed through `Core` class
- Execution stages tracked via `ExecutionStage` enum

### Concerns/Traits
- Shared functionality goes in `src/Concerns/`
- Examples: `CapturesState`, `RedactsRecords`, `RejectsRecords`
- Use traits to keep `Core` class focused

## Class Design

- **Final classes**: Use `final` for internal classes that shouldn't be extended
- **Visibility**: Use `private` by default, `protected` only when necessary
- **Property promotion**: Prefer constructor property promotion
- **Readonly**: Consider `readonly` properties when appropriate (PHP 8.2+)
- **Avoid unnecessary variables**: Only create variables when they're used multiple times or significantly improve readability. Don't create variables for single-use values that can be inlined directly.

## Documentation

- **@internal**: Mark all internal classes/methods with `@internal`
- **@api**: Mark public API methods with `@api`
- **PHPDoc**: Provide comprehensive PHPDoc for:
  - Complex types
  - Generic types
  - Array shapes
  - Callable signatures
- **Method documentation**: Document public methods, especially those marked `@api`
- **Avoid unnecessary comments**: Don't add inline comments unless necessary, especially when existing code doesn't already have them for similar scenarios. Let the code be self-documenting through clear naming and structure.

## Error Handling

- **Graceful degradation**: Never let monitoring code break the application
- **Exception handling**: Always catch `Throwable` in hooks
- **Report as handled**: Internal exceptions should be reported as `handled: true`
- **Unrecoverable exceptions**: Use `Nightwatch::unrecoverableExceptionOccurred()` for fatal errors

## Testing

- **Test coverage**: Maintain high test coverage
- **Test structure**: 
  - Feature tests in `tests/Feature/`
  - Unit tests in `tests/Unit/`
- **Test helpers**: Use `TestCase` helper methods:
  - `fakeIngest()` for mocking ingest
  - `fakeTcpStreams()` for testing network
  - `setExecutionStart()` for time manipulation
- **Test isolation**: Each test should be independent
- **Test naming**: Use descriptive test method names

## Compatibility

- **Laravel versions**: Support Laravel 10, 11, and 12
- **PHP versions**: Minimum PHP 8.2
- **Backward compatibility**: Maintain backward compatibility within major versions
- **Compatibility layer**: Use `Compatibility` class for version-specific code

### Compatibility Flags

Compatibility flags in the `Compatibility` class are used to handle version-specific features and APIs. Use them strategically:

**When to use compatibility flags:**

1. **Events that don't fire in older versions**
   - Example: `$cacheDurationCapturable` - start events (`RetrievingKey`, `WritingKey`, etc.) don't fire in older versions, so the sensor needs to know not to expect them for duration calculation
   - Example: `$terminatingEventExists` - the `Terminating` event doesn't exist/fire in Laravel < 11.18.0
   - Prevents the sensor from waiting for events that will never fire

2. **Classes/facades that don't exist in older versions**
   - Example: `$contextExists` - the `Context` facade doesn't exist in Laravel < 11
   - Prevents fatal errors from accessing non-existent classes/methods

3. **Features added in specific versions that need conditional logic**
   - Example: `$subMinuteScheduledTasksSupported` - sub-minute scheduling was added in Laravel 10.15.0
   - Documents when features were introduced and provides explicit feature detection

4. **When fallback behavior is needed**
   - Example: `$queuedJobDurationCapturable` - duration defaults to `0` if not capturable
   - Allows graceful degradation when features aren't available

**When NOT to use compatibility flags:**

1. **Properties that always exist but may be null**
   - Example: `$event->task->expression`, `$event->task->timezone` - always exist, use null coalescing (`??`) if needed
   - No need for a flag if the property exists in all supported versions

2. **When null coalescing operator is sufficient**
   - Example: `$event->storeName ?? ''` - property may not exist, but `??` handles it gracefully
   - Simpler than adding a compatibility flag for optional properties

**Guidelines:**

- **Document the feature**: Always include PR link and version tag in the compatibility flag comment. You MUST verify both the PR link and version tag are correct using the process below - never guess or reuse PR numbers from other features.
- **How to find the correct PR and version**:
  1. Search GitHub: `curl -s "https://api.github.com/search/issues?q=repo:laravel/framework+<feature-keywords>+is:pr+is:merged" | grep -E '"title"|"number"|"html_url"'`
  2. Verify the PR title and number match the feature you're implementing
  3. Check the PR's merge date: `curl -s "https://api.github.com/repos/laravel/framework/pulls/<PR_NUMBER>" | grep "merged_at"`
  4. Find which release contains this PR: `curl -s "https://api.github.com/repos/laravel/framework/releases/tags/v<VERSION>" | grep "<PR_NUMBER>"`
  5. Verify the release notes explicitly mention the PR number
- **Common mistakes to avoid**:
  - Using similar PR numbers without verification (e.g., #47310 vs #47279)
  - Guessing version numbers based on approximate dates
  - Not checking if the PR actually exists or matches the feature
- **Be explicit**: Use flags to make intent clear (feature support vs. property existence)
- **Consistency**: Follow existing patterns in the codebase
- **Performance**: Flags are evaluated once at boot time, so they're efficient for runtime checks
- **Test coverage**: Ensure tests cover both supported and unsupported versions when using flags

## Performance

- **Sampling**: Respect sampling configuration
- **Buffering**: Use `RecordsBuffer` for efficient batching
- **Memory**: Be mindful of memory usage, especially in long-running processes

## Security & Privacy

- **Redaction**: Always redact sensitive data:
  - Passwords
  - Tokens
  - Authorization headers
  - Configurable via `redact_payload_fields` and `redact_headers`
- **Filtering**: Respect filtering configuration
- **No PII**: Avoid capturing personally identifiable information unless explicitly configured

## Configuration

- **Environment variables**: Use `env()` helper with defaults
- **Config file**: Add new config options to `config/nightwatch.php`
- **Dot notation**: Use config dot notation for nested values
- **Defaults**: Provide sensible defaults

## Records & Payloads

- **Record classes**: Located in `src/Records/`
- **Payload structure**: Follow existing payload structure patterns
- **Versioning**: Use payload version (`'v' => 1`)
- **Lazy values**: Use `LazyValue` for values that cannot be determined at event capture time and need to be evaluated later when the record is serialized. This is for values that depend on state that will change or finalize between when the event is captured and when the record is sent to the ingest service
- **Grouping**: Use `_group` hash for similar events. The `_group` key identifies the "type" or "identity" of an event, grouping events that are the same "thing" even if they're different executions. **Include fields that define the identity of the event, not execution details or results.** Guidelines:
  - **Include**: Fields that define WHAT the thing is or WHEN it's scheduled (e.g., task name, cron expression, route path, cache key, job class, query SQL)
  - **Exclude**: Fields that define HOW it runs or WHAT happened (e.g., withoutOverlapping, duration, status, memory usage, response code)
  - **Identity vs. Execution**: Identity fields define the "what" (e.g., route path for HTTP requests, cache key for cache operations, SQL for queries), while execution details define the "how" or results (e.g., middleware applied, cache hit/miss, query bindings)
  - **Consistency**: Changing identity fields should create a new group; changing execution details should not. For example, changing a route path creates a new group, but changing response status does not
  - **Backward compatibility**: When adding new fields to `_group` hashes, consider whether to include them conditionally to maintain existing group hashes. For example, only include `repeat_seconds` when it's non-zero to preserve hashes for regular scheduled tasks
  - **Examples**: 
    - ✅ Scheduled task: `{name},{expression},{timezone},{repeatSeconds}` when repeatSeconds > 0, else `{name},{expression},{timezone}` - defines the schedule identity while preserving backward compatibility
    - ✅ HTTP request: `{methods},{domain},{route}` - defines the route identity
    - ✅ Cache event: `{store},{key}` - defines what's being cached
    - ❌ Don't include: `withoutOverlapping`, `onOneServer`, `duration`, `status`, `memoryUsage`
- **String limits**: Use `Str::tinyText()`, `Str::mediumText()` for length limits
- **Field naming - ALWAYS check Laravel source code**: **Before creating any new field names, check Laravel's source code for the property names.** Look at the actual class properties and methods in Laravel's framework code. Convert camelCase property names to snake_case for JSON payloads. Use Laravel's property names rather than creating new terminology.
- **Complete records**: **All records must always contain all keys.** Optional fields should use appropriate sentinel values rather than being omitted from the record. This ensures consistent record structure across all records, which is important for data consistency, easier querying, backward compatibility, and type safety.
- **ClickHouse storage**: Data is stored in ClickHouse, which doesn't typically use Nullable fields due to the extra storage required. **Prefer using sentinel values instead of `null`** whenever possible. Choose sentinel values based on semantic validity:
  - **Strings**: Use empty strings (`''`) when an empty string is not a meaningful value for the field
  - **Numbers**: Use `0` when zero is **semantically invalid** for that field. Examples:
    - ✅ `repeat_seconds => 0` for non-repeating tasks (Laravel requires ≥1 second)
    - ✅ `duration => 0` for skipped tasks (they don't actually run)
    - ❌ Don't use `0` if zero is a valid/meaningful value (e.g., `offset`, `retry_count`)
  - **Booleans**: Use `false` when false is the appropriate default/absence state
  - **Only use `null` when there is no appropriate sentinel value**: Use `null` only when `0`/`false`/`''` would be ambiguous or when the absence of a value is semantically different from any sentinel value. For example:
    - ✅ Use `null` if both `0` and positive numbers are valid, and you need to distinguish "not set"
    - ✅ Use `null` if an empty string is a valid/meaningful value distinct from "not set"
  - **Check the existing codebase**: Look for similar fields in other sensors to maintain consistency

## Agent Development

- **Agent code**: Located in `agent/` directory
- **Build process**: Use `build.sh` for building PHAR
- **Docker**: Agent has Docker support
- **Scoping**: Use `scoper.inc.php` for PHAR scoping

## Git & Versioning

- **Branch**: Work on `1.x` branch
- **Changelog**: Update `CHANGELOG.md` for user-facing changes
- **Version**: Version managed in `version.txt`
- **Commits**: Write clear, descriptive commit messages

## Development Workflow

- **Always run checks**: After creating or editing files, always run:
  - **Tests**: Run the test suite to ensure all tests pass
  - **Linting**: Run Pint to ensure code formatting is correct
  - **Static analysis**: Run PHPStan to ensure type safety and catch errors
- **Before committing**: Ensure all checks pass before committing code
- **Automated checks**: The CI pipeline will verify these, but catch issues locally first
- **Verify links and PRs**: When adding PR references or version tags (especially in compatibility flags), use the GitHub API or curl commands to verify:
  - The PR number is correct and matches the feature
  - The version tag actually contains that PR in its release notes
  - Don't reuse PR numbers from other features or guess version numbers - always verify through GitHub's API or release notes
- **Verify Laravel source**: Before accessing Laravel properties or methods, check the actual Laravel source code to confirm:
  - Property names and types
  - Whether properties are public or require reflection
  - What values Laravel provides (e.g., `null` vs `0`)
  - **Valid value ranges** - Check for validation logic or constraints that indicate which values are semantically valid (e.g., if Laravel requires a value to be ≥1, then `0` can be used as a sentinel for "not set")
  - When features were actually introduced (check changelogs, not just version tags)
- **Match Laravel conventions**: When adding new fields to payloads, **always check Laravel's source code** for the property names. Convert camelCase property names to snake_case for JSON payloads. Use Laravel's property names rather than creating new terminology. This ensures consistency with Laravel's own APIs and makes the data more familiar to Laravel developers.
- **Group related fields**: Place new fields logically alongside related fields in payloads (e.g., scheduling-related fields grouped together)

## Code Review Checklist

- [ ] Code passes PHPStan at max level
- [ ] Code formatted with Pint
- [ ] All tests pass
- [ ] New code has tests
- [ ] PHPDoc is complete
- [ ] Internal classes marked `@internal`
- [ ] Public API marked `@api`
- [ ] Error handling is graceful
- [ ] Sensitive data is redacted
- [ ] Performance considerations addressed
- [ ] Backward compatibility maintained
- [ ] Configuration options documented

## Common Patterns

### Capturing Events
```php
// In Hook
try {
    $this->nightwatch->query($event);
} catch (Throwable $e) {
    $this->nightwatch->report($e, handled: true);
}
```

### Creating Records
```php
// In Sensor
return [
    $record = new Query(...),
    function () use ($event, $record) {
        $this->executionState->queries++;
        return [
            'v' => 1,
            't' => 'query',
            'timestamp' => $this->clock->microtime(),
            // ... other fields
        ];
    },
];
```

### State Management
```php
// Update execution state
$this->executionState->stage = ExecutionStage::Action;
$this->executionState->currentExecutionStageStartedAtMicrotime = $this->clock->microtime();
```

## Don'ts

- ❌ Don't break backward compatibility
- ❌ Don't let monitoring code crash the application
- ❌ Don't capture sensitive data without redaction
- ❌ Don't skip error handling in hooks
- ❌ Don't use `public` properties unless necessary
- ❌ Don't forget to mark internal code with `@internal`
- ❌ Don't ignore PHPStan errors
- ❌ Don't commit code that doesn't pass Pint
- ❌ Don't add features without tests
- ❌ Don't use `dd()` or `dump()` in production code

## Do's

- ✅ Do use strict typing
- ✅ Do handle all exceptions gracefully
- ✅ Do write comprehensive tests
- ✅ Do document public APIs
- ✅ Do respect configuration options
- ✅ Do use final classes for internal code
- ✅ Do follow Laravel conventions
- ✅ Do maintain backward compatibility
- ✅ Do optimize for performance
- ✅ Do redact sensitive data
