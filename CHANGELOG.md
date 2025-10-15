# Release Notes

## [Unreleased](https://github.com/laravel/nightwatch/compare/v1.15.0...1.x)

## [v1.15.0](https://github.com/laravel/nightwatch/compare/v1.14.1...v1.15.0) - 2025-10-10

### What's Changed

* Capture request headers by [@jessarcher](https://github.com/jessarcher) in https://github.com/laravel/nightwatch/pull/270
* Add error handling for serializing of request headers by [@jessarcher](https://github.com/jessarcher) in https://github.com/laravel/nightwatch/pull/283

**Full Changelog**: https://github.com/laravel/nightwatch/compare/v1.14.1...v1.15.0

## [v1.14.1](https://github.com/laravel/nightwatch/compare/v1.14.0...v1.14.1) - 2025-10-07

### What's Changed

* Capture query connection name with read/write type by [@avosalmon](https://github.com/avosalmon) in https://github.com/laravel/nightwatch/pull/278
* Revert "Capture query connection name with read/write type (#278)" by [@avosalmon](https://github.com/avosalmon) in https://github.com/laravel/nightwatch/pull/279
* Do not include bootstrapping or web worker initial wait time in first request for Octane by [@timacdonald](https://github.com/timacdonald) in https://github.com/laravel/nightwatch/pull/280

**Full Changelog**: https://github.com/laravel/nightwatch/compare/v1.14.0...v1.14.1

## [v1.14.0](https://github.com/laravel/nightwatch/compare/v1.13.8...v1.14.0) - 2025-09-29

### What's Changed

* Associate events on the queue with currently authenticated user or the originating request by [@timacdonald](https://github.com/timacdonald) in https://github.com/laravel/nightwatch/pull/271
* Context handling improvements by [@timacdonald](https://github.com/timacdonald) in https://github.com/laravel/nightwatch/pull/273
* Add `Sample` middleware aliases by [@avosalmon](https://github.com/avosalmon) in https://github.com/laravel/nightwatch/pull/275
* Extract hook for queue payload modification by [@timacdonald](https://github.com/timacdonald) in https://github.com/laravel/nightwatch/pull/274

**Full Changelog**: https://github.com/laravel/nightwatch/compare/v1.13.8...v1.14.0

## [v1.13.8](https://github.com/laravel/nightwatch/compare/v1.13.7...v1.13.8) - 2025-09-23

### What's Changed

* Improve auto-restart with Envoyer by [@jamesdangercarpenter](https://github.com/jamesdangercarpenter) in https://github.com/laravel/nightwatch/pull/266
* Improve sub-minute task capture by [@avosalmon](https://github.com/avosalmon) in https://github.com/laravel/nightwatch/pull/265
* Fix logging when outside of the loop by [@timacdonald](https://github.com/timacdonald) in https://github.com/laravel/nightwatch/pull/269
* Bump minimum monolog version by [@timacdonald](https://github.com/timacdonald) in https://github.com/laravel/nightwatch/pull/272

**Full Changelog**: https://github.com/laravel/nightwatch/compare/v1.13.7...v1.13.8

## [v1.13.7](https://github.com/laravel/nightwatch/compare/v1.13.6...v1.13.7) - 2025-09-20

### What's Changed

* Write to stdout and stderr via loop by [@timacdonald](https://github.com/timacdonald) in https://github.com/laravel/nightwatch/pull/268

**Full Changelog**: https://github.com/laravel/nightwatch/compare/v1.13.6...v1.13.7

## [v1.13.6](https://github.com/laravel/nightwatch/compare/v1.13.5...v1.13.6) - 2025-09-16

### What's Changed

* Fix agent missing options by [@timacdonald](https://github.com/timacdonald) in https://github.com/laravel/nightwatch/pull/264

**Full Changelog**: https://github.com/laravel/nightwatch/compare/v1.13.5...v1.13.6

## [v1.13.5](https://github.com/laravel/nightwatch/compare/v1.13.4...v1.13.5) - 2025-09-15

### What's Changed

* Improve agent logging behaviour by [@jamesdangercarpenter](https://github.com/jamesdangercarpenter) in https://github.com/laravel/nightwatch/pull/262

**Full Changelog**: https://github.com/laravel/nightwatch/compare/v1.13.4...v1.13.5

## [v1.13.4](https://github.com/laravel/nightwatch/compare/v1.13.3...v1.13.4) - 2025-09-11

#### What's Changed

* Refactor UserProvider by [@jessarcher](https://github.com/jessarcher) in https://github.com/laravel/nightwatch/pull/259
* Update Fatal errors execution id by [@sbourouis](https://github.com/sbourouis) in https://github.com/laravel/nightwatch/pull/260
* Allow the user ID to be customized by [@jessarcher](https://github.com/jessarcher) in https://github.com/laravel/nightwatch/pull/261

### New Contributors

* [@sbourouis](https://github.com/sbourouis) made their first contribution in https://github.com/laravel/nightwatch/pull/260

**Full Changelog**: https://github.com/laravel/nightwatch/compare/v1.13.3...v1.13.4

## [v1.13.3](https://github.com/laravel/nightwatch/compare/v1.13.2...v1.13.3) - 2025-09-08

### What's Changed

* Restore missing changelog entries by [@timacdonald](https://github.com/timacdonald) in https://github.com/laravel/nightwatch/pull/257
* Capture scheduled tasks that run in background by [@avosalmon](https://github.com/avosalmon) in https://github.com/laravel/nightwatch/pull/258

**Full Changelog**: https://github.com/laravel/nightwatch/compare/v1.13.2...v1.13.3

## [v1.13.2](https://github.com/laravel/nightwatch/compare/v1.5.0...v1.13.2) - 2025-08-25

### What's Changed

* Commit changelog changes by [@timacdonald](https://github.com/timacdonald) in https://github.com/laravel/nightwatch/pull/253
* Send fatal errors to agent immediately by [@jessarcher](https://github.com/jessarcher) in https://github.com/laravel/nightwatch/pull/254

**Full Changelog**: https://github.com/laravel/nightwatch/compare/v1.13.1...v1.13.2

## [v1.13.1](https://github.com/laravel/nightwatch/compare/v1.13.0...v1.13.1) - 2025-08-21

### What's Changed

* Fix log processors applying side-effects to other handlers when used in a stack by [@timacdonald](https://github.com/timacdonald) in https://github.com/laravel/nightwatch/pull/249
* Fix changelog generation by [@timacdonald](https://github.com/timacdonald) in https://github.com/laravel/nightwatch/pull/250
* Fix changelog generation by [@timacdonald](https://github.com/timacdonald) in https://github.com/laravel/nightwatch/pull/251
* Fix changelog generation by [@timacdonald](https://github.com/timacdonald) in https://github.com/laravel/nightwatch/pull/252

**Full Changelog**: https://github.com/laravel/nightwatch/compare/v1.13.0...v1.13.1

## [v1.13.0](https://github.com/laravel/nightwatch/compare/v1.12.0...v1.13.0) - 2025-08-20

### What's Changed

* Preserve zero fractions for log context and extra by [@timacdonald](https://github.com/timacdonald) in https://github.com/laravel/nightwatch/pull/247
* Laravel Context by [@jessarcher](https://github.com/jessarcher) in https://github.com/laravel/nightwatch/pull/245

**Full Changelog**: https://github.com/laravel/nightwatch/compare/v1.12.0...v1.13.0

## [v1.12.0](https://github.com/laravel/nightwatch/compare/v1.11.5...v1.12.0) - 2025-08-14

### What's Changed

* Ignore build file by [@timacdonald](https://github.com/timacdonald) in https://github.com/laravel/nightwatch/pull/241
* Improve exception traces by [@jessarcher](https://github.com/jessarcher) in https://github.com/laravel/nightwatch/pull/243
* Add source code capture for exception traces by [@philliphartin](https://github.com/philliphartin) in https://github.com/laravel/nightwatch/pull/229
* Fix execution stage not being updated correctly after an unhandled exception in the action stage by [@jessarcher](https://github.com/jessarcher) in https://github.com/laravel/nightwatch/pull/242

**Full Changelog**: https://github.com/laravel/nightwatch/compare/v1.11.5...v1.12.0

## [v1.11.5](https://github.com/laravel/nightwatch/compare/v1.11.4...v1.11.5) - 2025-08-04

### What's Changed

* Add docker health check by [@jamesdangercarpenter](https://github.com/jamesdangercarpenter) in https://github.com/laravel/nightwatch/pull/239

**Full Changelog**: https://github.com/laravel/nightwatch/compare/v1.11.4...v1.11.5

## [v1.11.4](https://github.com/laravel/nightwatch/compare/v1.11.3...v1.11.4) - 2025-07-18

### What's Changed

* Fix autoloading files by [@timacdonald](https://github.com/timacdonald) in https://github.com/laravel/nightwatch/pull/238

**Full Changelog**: https://github.com/laravel/nightwatch/compare/v1.11.3...v1.11.4

## [v1.11.3](https://github.com/laravel/nightwatch/compare/v1.11.2...v1.11.3) - 2025-07-17

### What's Changed

* fix: checkout the code by [@jamesdangercarpenter](https://github.com/jamesdangercarpenter) in https://github.com/laravel/nightwatch/pull/236

**Full Changelog**: https://github.com/laravel/nightwatch/compare/v1.11.2...v1.11.3

## [v1.11.2](https://github.com/laravel/nightwatch/compare/v1.11.1...v1.11.2) - 2025-07-17

### What's Changed

* fix: docker build syntax by [@jamesdangercarpenter](https://github.com/jamesdangercarpenter) in https://github.com/laravel/nightwatch/pull/235

**Full Changelog**: https://github.com/laravel/nightwatch/compare/v1.11.1...v1.11.2

## [v1.11.1](https://github.com/laravel/nightwatch/compare/v1.11.0...v1.11.1) - 2025-07-17

### What's Changed

* fix: correctly trigger docker publish by [@jamesdangercarpenter](https://github.com/jamesdangercarpenter) in https://github.com/laravel/nightwatch/pull/234

**Full Changelog**: https://github.com/laravel/nightwatch/compare/v1.11.0...v1.11.1

## [v1.11.0](https://github.com/laravel/nightwatch/compare/v1.10.6...v1.11.0) - 2025-07-17

### What's Changed

* Improve agent payload validation by [@jamesdangercarpenter](https://github.com/jamesdangercarpenter) in https://github.com/laravel/nightwatch/pull/202
* Fix Beanstalkd job monitoring by [@timacdonald](https://github.com/timacdonald) in https://github.com/laravel/nightwatch/pull/227
* Control UUID generation by [@timacdonald](https://github.com/timacdonald) in https://github.com/laravel/nightwatch/pull/224
* Improve/reinstate restart on signature change behaviour by [@jamesdangercarpenter](https://github.com/jamesdangercarpenter) in https://github.com/laravel/nightwatch/pull/228
* Publish official Docker image by [@jamesdangercarpenter](https://github.com/jamesdangercarpenter) in https://github.com/laravel/nightwatch/pull/226
* Fix: docker build, token mismatch handling by [@jamesdangercarpenter](https://github.com/jamesdangercarpenter) in https://github.com/laravel/nightwatch/pull/230
* Eager load classes by [@timacdonald](https://github.com/timacdonald) in https://github.com/laravel/nightwatch/pull/231
* fix: don't force a major version for the first payload version by [@jamesdangercarpenter](https://github.com/jamesdangercarpenter) in https://github.com/laravel/nightwatch/pull/232
* Fix/version validation by [@jamesdangercarpenter](https://github.com/jamesdangercarpenter) in https://github.com/laravel/nightwatch/pull/233

**Full Changelog**: https://github.com/laravel/nightwatch/compare/v1.10.6...v1.11.0

## [v1.10.6](https://github.com/laravel/nightwatch/compare/v1.10.5...v1.10.6) - 2025-07-08

### What's Changed

* Normalize group hashes for singlestore connections by [@jessarcher](https://github.com/jessarcher) in https://github.com/laravel/nightwatch/pull/225
* Capture job attempts in Vapor by [@avosalmon](https://github.com/avosalmon) in https://github.com/laravel/nightwatch/pull/199

**Full Changelog**: https://github.com/laravel/nightwatch/compare/v1.10.5...v1.10.6

## [v1.10.5](https://github.com/laravel/nightwatch/compare/v1.10.4...v1.10.5) - 2025-07-07

### What's Changed

* Ignore events while interacting with the auth manager by [@timacdonald](https://github.com/timacdonald) in https://github.com/laravel/nightwatch/pull/218
* Change visibility of internal properties by [@timacdonald](https://github.com/timacdonald) in https://github.com/laravel/nightwatch/pull/223

**Full Changelog**: https://github.com/laravel/nightwatch/compare/v1.10.4...v1.10.5

## [v1.10.4](https://github.com/laravel/nightwatch/compare/v1.10.3...v1.10.4) - 2025-07-06

### What's Changed

* Improve user guard resolution by [@timacdonald](https://github.com/timacdonald) in https://github.com/laravel/nightwatch/pull/217
* Capture Livewire component name on Livewire update requests by [@jessarcher](https://github.com/jessarcher) in https://github.com/laravel/nightwatch/pull/208
* Fix tests not showing exceptions by [@jessarcher](https://github.com/jessarcher) in https://github.com/laravel/nightwatch/pull/219

**Full Changelog**: https://github.com/laravel/nightwatch/compare/v1.10.3...v1.10.4

## [v1.10.3](https://github.com/laravel/nightwatch/compare/v1.10.2...v1.10.3) - 2025-07-02

### What's Changed

* Gracefully handle exceptions while resolving user ids by [@timacdonald](https://github.com/timacdonald) in https://github.com/laravel/nightwatch/pull/216

**Full Changelog**: https://github.com/laravel/nightwatch/compare/v1.10.2...v1.10.3

## [v1.10.2](https://github.com/laravel/nightwatch/compare/v1.10.1...v1.10.2) - 2025-07-01

### What's Changed

* Fix miscellaneous errors with old Laravel versions by @jessarcher in https://github.com/laravel/nightwatch/pull/212

**Full Changelog**: https://github.com/laravel/nightwatch/compare/v1.10.1...v1.10.2

## [v1.10.1](https://github.com/laravel/nightwatch/compare/v1.10.0...v1.10.1) - 2025-06-30

### What's Changed

* Allow navigating to same stage by [@timacdonald](https://github.com/timacdonald) in https://github.com/laravel/nightwatch/pull/213

**Full Changelog**: https://github.com/laravel/nightwatch/compare/v1.10.0...v1.10.1

## [v1.10.0](https://github.com/laravel/nightwatch/compare/v1.9.1...v1.10.0) - 2025-06-30

### What's Changed

* Fix test cleanup by [@jessarcher](https://github.com/jessarcher) in https://github.com/laravel/nightwatch/pull/207
* Fix sampling at zero with Nova by [@timacdonald](https://github.com/timacdonald) in https://github.com/laravel/nightwatch/pull/211

**Full Changelog**: https://github.com/laravel/nightwatch/compare/v1.9.1...v1.10.0

## [v1.9.1](https://github.com/laravel/nightwatch/compare/v1.9.0...v1.9.1) - 2025-06-27

### What's Changed

* Ensure jobs are tracked correctly with Octane by [@timacdonald](https://github.com/timacdonald) in https://github.com/laravel/nightwatch/pull/204
* Remove internal API from facade by [@timacdonald](https://github.com/timacdonald) in https://github.com/laravel/nightwatch/pull/205

**Full Changelog**: https://github.com/laravel/nightwatch/compare/v1.9.0...v1.9.1

## [v1.9.0](https://github.com/laravel/nightwatch/compare/v1.8.4...v1.9.0) - 2025-06-26

### What's Changed

* fix: version calculation in release workflow by [@jamesdangercarpenter](https://github.com/jamesdangercarpenter) in https://github.com/laravel/nightwatch/pull/200
* Filtering improvements by [@timacdonald](https://github.com/timacdonald) in https://github.com/laravel/nightwatch/pull/197
* Octane support by [@timacdonald](https://github.com/timacdonald) in https://github.com/laravel/nightwatch/pull/201

**Full Changelog**: https://github.com/laravel/nightwatch/compare/v1.8.4...v1.9.0

## [v1.8.4](https://github.com/laravel/nightwatch/compare/v1.7.4...v1.8.4) - 2025-06-19

### What's Changed

* Sampling improvements by [@timacdonald](https://github.com/timacdonald) in https://github.com/laravel/nightwatch/pull/185
* Add support for log levels by [@jessarcher](https://github.com/jessarcher) in https://github.com/laravel/nightwatch/pull/198

**Full Changelog**: https://github.com/laravel/nightwatch/compare/v1.7.4...v1.8.4

## [v1.7.4](https://github.com/laravel/nightwatch/compare/v1.7.3...v1.7.4) - 2025-06-19

### What's Changed

* Force stream select loop by [@timacdonald](https://github.com/timacdonald) in https://github.com/laravel/nightwatch/pull/193
* Ensure internal composer class only included once by [@timacdonald](https://github.com/timacdonald) in https://github.com/laravel/nightwatch/pull/188

**Full Changelog**: https://github.com/laravel/nightwatch/compare/v1.7.3...v1.7.4

## [v1.7.3](https://github.com/laravel/nightwatch/compare/v1.7.2...v1.7.3) - 2025-06-18

### What's Changed

* Handle `null` cache keys by [@timacdonald](https://github.com/timacdonald) in https://github.com/laravel/nightwatch/pull/189
* Handle `null` connection name by [@timacdonald](https://github.com/timacdonald) in https://github.com/laravel/nightwatch/pull/191

**Full Changelog**: https://github.com/laravel/nightwatch/compare/v1.7.2...v1.7.3

## [v1.7.2](https://github.com/laravel/nightwatch/compare/v1.7.1...v1.7.2) - 2025-06-17

### What's Changed

* Report internal exceptions as handled by [@timacdonald](https://github.com/timacdonald) in https://github.com/laravel/nightwatch/pull/187

**Full Changelog**: https://github.com/laravel/nightwatch/compare/v1.7.1...v1.7.2

## [v1.7.1](https://github.com/laravel/nightwatch/compare/v1.7.0...v1.7.1) - 2025-06-17

### What's Changed

* Allow capturing of log context and extra that contain binary by [@timacdonald](https://github.com/timacdonald) in https://github.com/laravel/nightwatch/pull/186

**Full Changelog**: https://github.com/laravel/nightwatch/compare/v1.7.0...v1.7.1

## [v1.7.0](https://github.com/laravel/nightwatch/compare/v1.6.0...v1.7.0) - 2025-06-16

### What's Changed

* Respect ingest uri by [@timacdonald](https://github.com/timacdonald) in https://github.com/laravel/nightwatch/pull/179
* Extract sampling helper by [@timacdonald](https://github.com/timacdonald) in https://github.com/laravel/nightwatch/pull/180
* Add description to `AsCommand` attribute by [@timacdonald](https://github.com/timacdonald) in https://github.com/laravel/nightwatch/pull/181
* Extract execution stage helper by [@timacdonald](https://github.com/timacdonald) in https://github.com/laravel/nightwatch/pull/182
* Encapsulate state with command name helper by [@timacdonald](https://github.com/timacdonald) in https://github.com/laravel/nightwatch/pull/183
* Update version by [@timacdonald](https://github.com/timacdonald) in https://github.com/laravel/nightwatch/pull/184

**Full Changelog**: https://github.com/laravel/nightwatch/compare/v1.6.0...v1.7.0

## [v1.6.0](https://github.com/laravel/nightwatch/compare/v1.5.1...v1.6.0) - 2025-06-12

### What's Changed

* Add PHP 8.4 to CI by [@timacdonald](https://github.com/timacdonald) in https://github.com/laravel/nightwatch/pull/173
* Configuration based event ignores by type by [@timacdonald](https://github.com/timacdonald) in https://github.com/laravel/nightwatch/pull/175
* Fix scheduled tasks being collected twice by [@timacdonald](https://github.com/timacdonald) in https://github.com/laravel/nightwatch/pull/177
* Fake ingest at the TCP stream layer by [@timacdonald](https://github.com/timacdonald) in https://github.com/laravel/nightwatch/pull/176
* Configurable unsampled execution exception sample rate by [@timacdonald](https://github.com/timacdonald) in https://github.com/laravel/nightwatch/pull/178

**Full Changelog**: https://github.com/laravel/nightwatch/compare/v1.5.1...v1.6.0

## [v1.5.1](https://github.com/laravel/nightwatch/compare/v1.5.0...v1.5.1) - 2025-05-21

### What's Changed

* Update changlog on release by [@timacdonald](https://github.com/timacdonald) in https://github.com/laravel/nightwatch/pull/162
* Refactor agent tests to PHPUnit classes by [@timacdonald](https://github.com/timacdonald) in https://github.com/laravel/nightwatch/pull/158
* Migrate to PHPUnit by [@timacdonald](https://github.com/timacdonald) in https://github.com/laravel/nightwatch/pull/163
* Fix exception preview sticking between executions by [@jessarcher](https://github.com/jessarcher) in https://github.com/laravel/nightwatch/pull/164
* Support job attempts for Laravel < v11.23.0 by [@avosalmon](https://github.com/avosalmon) in https://github.com/laravel/nightwatch/pull/154
* Silence exceptions occurring while handling exceptions by [@timacdonald](https://github.com/timacdonald) in https://github.com/laravel/nightwatch/pull/166
* Fix user-agent string by [@timacdonald](https://github.com/timacdonald) in https://github.com/laravel/nightwatch/pull/165
* Ingest at threshold by [@timacdonald](https://github.com/timacdonald) in https://github.com/laravel/nightwatch/pull/161
* Improve codebase consistency by [@timacdonald](https://github.com/timacdonald) in https://github.com/laravel/nightwatch/pull/167
* Migrate to PHPUnit by [@timacdonald](https://github.com/timacdonald) in https://github.com/laravel/nightwatch/pull/168
* Respect `retry_in` from app response  by [@timacdonald](https://github.com/timacdonald) in https://github.com/laravel/nightwatch/pull/171
* Migrate to assertions by [@timacdonald](https://github.com/timacdonald) in https://github.com/laravel/nightwatch/pull/170
* Handle binary and unicode characters by [@timacdonald](https://github.com/timacdonald) in https://github.com/laravel/nightwatch/pull/172

**Full Changelog**: https://github.com/laravel/nightwatch/compare/v1.5.0...v1.5.1

## [v1.5.0](https://github.com/laravel/nightwatch/compare/v1.4.0...v1.5.0) - 2025-05-07

### What's Changed

* Revert to MIT license by [@timacdonald](https://github.com/timacdonald) in https://github.com/laravel/nightwatch/pull/155
* Fix intermittent ingest issues by [@timacdonald](https://github.com/timacdonald) in https://github.com/laravel/nightwatch/pull/152
* Always read base url from env by [@timacdonald](https://github.com/timacdonald) in https://github.com/laravel/nightwatch/pull/159
* Automagically restart the agent by [@timacdonald](https://github.com/timacdonald) in https://github.com/laravel/nightwatch/pull/156
* Add release action by [@timacdonald](https://github.com/timacdonald) in https://github.com/laravel/nightwatch/pull/160

**Full Changelog**: https://github.com/laravel/nightwatch/compare/v1.4.0...v1.5.0

## [v1.4.0](https://github.com/laravel/nightwatch/compare/v1.3.0...v1.4.0) - 2025-04-28

### What's Changed

* Job sampling by [@timacdonald](https://github.com/timacdonald) in https://github.com/laravel/nightwatch/pull/147
* Command sampling by [@timacdonald](https://github.com/timacdonald) in https://github.com/laravel/nightwatch/pull/148
* Encapsulate capturing of scheduled tasks by [@timacdonald](https://github.com/timacdonald) in https://github.com/laravel/nightwatch/pull/150
* Improve local ci check performance by [@timacdonald](https://github.com/timacdonald) in https://github.com/laravel/nightwatch/pull/151
* Fix `facades` build permission by [@crynobone](https://github.com/crynobone) in https://github.com/laravel/nightwatch/pull/149

### New Contributors

* [@crynobone](https://github.com/crynobone) made their first contribution in https://github.com/laravel/nightwatch/pull/149

**Full Changelog**: https://github.com/laravel/nightwatch/compare/v1.3.0...v1.4.0

## [v1.3.0](https://github.com/laravel/nightwatch/compare/v1.2.1...v1.3.0) - 2025-04-24

### What's Changed

* Improve quota check performance by [@timacdonald](https://github.com/timacdonald) in https://github.com/laravel/nightwatch/pull/136
* Add status command by [@timacdonald](https://github.com/timacdonald) in https://github.com/laravel/nightwatch/pull/140
* Use Bencode for agent payloads  by [@timacdonald](https://github.com/timacdonald) in https://github.com/laravel/nightwatch/pull/141
* Fix typo by [@timacdonald](https://github.com/timacdonald) in https://github.com/laravel/nightwatch/pull/143
* Request Sampling by [@timacdonald](https://github.com/timacdonald) in https://github.com/laravel/nightwatch/pull/142
* Write payload for debugging by [@timacdonald](https://github.com/timacdonald) in https://github.com/laravel/nightwatch/pull/144
* Add watch command by [@timacdonald](https://github.com/timacdonald) in https://github.com/laravel/nightwatch/pull/145
* Improve status command by [@timacdonald](https://github.com/timacdonald) in https://github.com/laravel/nightwatch/pull/146

**Full Changelog**: https://github.com/laravel/nightwatch/compare/v1.2.1...v1.3.0

## [v1.2.1](https://github.com/laravel/nightwatch/compare/v1.2.0...v1.2.1) - 2025-04-16

### What's Changed

* Ensure user ID is always captured by [@timacdonald](https://github.com/timacdonald) in https://github.com/laravel/nightwatch/pull/138

**Full Changelog**: https://github.com/laravel/nightwatch/compare/v1.2.0...v1.2.1

## [v1.2.0](https://github.com/laravel/nightwatch/compare/v1.1.2...v1.2.0) - 2025-04-15

### What's Changed

* Improved authentication retries by [@timacdonald](https://github.com/timacdonald) in https://github.com/laravel/nightwatch/pull/130
* Allow customisation of captured user details by [@timacdonald](https://github.com/timacdonald) in https://github.com/laravel/nightwatch/pull/131
* Support using Guzzle directly by [@timacdonald](https://github.com/timacdonald) in https://github.com/laravel/nightwatch/pull/132
* Capture and reuse loop instance by [@timacdonald](https://github.com/timacdonald) in https://github.com/laravel/nightwatch/pull/133
* Register hooks to ensure we capture exceptions occuring in the register method of the application's service provider by [@timacdonald](https://github.com/timacdonald) in https://github.com/laravel/nightwatch/pull/135
* Back off ingestion once over quota by [@timacdonald](https://github.com/timacdonald) in https://github.com/laravel/nightwatch/pull/134
* Add auth testing endpoints by [@timacdonald](https://github.com/timacdonald) in https://github.com/laravel/nightwatch/pull/137

**Full Changelog**: https://github.com/laravel/nightwatch/compare/v1.1.2...v1.2.0

## [v1.1.2](https://github.com/laravel/nightwatch/compare/v1.1.1...v1.1.2) - 2025-03-31

### What's Changed

* Add missing record property length limits by [@timacdonald](https://github.com/timacdonald) in https://github.com/laravel/nightwatch/pull/126
* Capture exception previews by [@jessarcher](https://github.com/jessarcher) in https://github.com/laravel/nightwatch/pull/127
* Capture execution previews by [@jessarcher](https://github.com/jessarcher) in https://github.com/laravel/nightwatch/pull/128
* Improve exception handling by [@timacdonald](https://github.com/timacdonald) in https://github.com/laravel/nightwatch/pull/129

**Full Changelog**: https://github.com/laravel/nightwatch/compare/v1.1.1...v1.1.2

## [v1.1.1](https://github.com/laravel/nightwatch/compare/v1.1.0...v1.1.1) - 2025-03-19

### What's Changed

* Fix horizon capturing jobs by [@timacdonald](https://github.com/timacdonald) in https://github.com/laravel/nightwatch/pull/125

**Full Changelog**: https://github.com/laravel/nightwatch/compare/v1.1.0...v1.1.1

## [v1.1.0](https://github.com/laravel/nightwatch/compare/v1.0.7...v1.1.0) - 2025-03-17

### What's Changed

* Send the server name with outgoing requests by [@timacdonald](https://github.com/timacdonald) in https://github.com/laravel/nightwatch/pull/120
* Add Laravel 10 support (excluding job attempts) by [@timacdonald](https://github.com/timacdonald) in https://github.com/laravel/nightwatch/pull/119
* Add Laravel 12 support by [@timacdonald](https://github.com/timacdonald) in https://github.com/laravel/nightwatch/pull/124

**Full Changelog**: https://github.com/laravel/nightwatch/compare/v1.0.7...v1.1.0

## [v1.0.7](https://github.com/laravel/nightwatch/compare/v1.0.6...v1.0.7) - 2025-03-03

### What's Changed

* Fix class declaration by [@timacdonald](https://github.com/timacdonald) in https://github.com/laravel/nightwatch/pull/123

**Full Changelog**: https://github.com/laravel/nightwatch/compare/v1.0.6...v1.0.7

## [v1.0.6](https://github.com/laravel/nightwatch/compare/v1.0.5...v1.0.6) - 2025-03-03

### What's Changed

* Improve deterministic builds by [@timacdonald](https://github.com/timacdonald) in https://github.com/laravel/nightwatch/pull/121
* Allow longer job names by [@timacdonald](https://github.com/timacdonald) in https://github.com/laravel/nightwatch/pull/122

**Full Changelog**: https://github.com/laravel/nightwatch/compare/v1.0.5...v1.0.6

## [v1.0.5](https://github.com/laravel/nightwatch/compare/v1.0.4...v1.0.5) - 2025-02-27

### What's Changed

* Use more performant hashing algorithm by [@timacdonald](https://github.com/timacdonald) in https://github.com/laravel/nightwatch/pull/116
* Improve deterministic builds by [@timacdonald](https://github.com/timacdonald) in https://github.com/laravel/nightwatch/pull/117
* Send package version in the user agent header by [@timacdonald](https://github.com/timacdonald) in https://github.com/laravel/nightwatch/pull/118

**Full Changelog**: https://github.com/laravel/nightwatch/compare/v1.0.4...v1.0.5

## [v1.0.4](https://github.com/laravel/nightwatch/compare/v1.0.3...v1.0.4) - 2025-02-25

### What's Changed

* Improve exported git attributes by [@timacdonald](https://github.com/timacdonald) in https://github.com/laravel/nightwatch/pull/113
* Ensure client can be included multiple times by [@timacdonald](https://github.com/timacdonald) in https://github.com/laravel/nightwatch/pull/112
* Improve gitattributes by [@timacdonald](https://github.com/timacdonald) in https://github.com/laravel/nightwatch/pull/114

**Full Changelog**: https://github.com/laravel/nightwatch/compare/v1.0.3...v1.0.4

## [v1.0.3](https://github.com/laravel/nightwatch/compare/v1.0.2...v1.0.3) - 2025-02-23

### What's Changed

* Build agent by [@timacdonald](https://github.com/timacdonald) in https://github.com/laravel/nightwatch/pull/107
* Remove unneeded extension dependency by [@timacdonald](https://github.com/timacdonald) in https://github.com/laravel/nightwatch/pull/108
* Pass agent token directly by [@timacdonald](https://github.com/timacdonald) in https://github.com/laravel/nightwatch/pull/110
* Build client by [@timacdonald](https://github.com/timacdonald) in https://github.com/laravel/nightwatch/pull/109
* Rename facade workflow by [@timacdonald](https://github.com/timacdonald) in https://github.com/laravel/nightwatch/pull/111

**Full Changelog**: https://github.com/laravel/nightwatch/compare/v1.0.2...v1.0.3

## [v1.0.2](https://github.com/laravel/nightwatch/compare/v1.0.1...v1.0.2) - 2025-02-18

### What's Changed

* Capture notification duration by [@avosalmon](https://github.com/avosalmon) in https://github.com/laravel/nightwatch/pull/104
* Mail duration by [@avosalmon](https://github.com/avosalmon) in https://github.com/laravel/nightwatch/pull/105
* Capture job dispatch duration by [@avosalmon](https://github.com/avosalmon) in https://github.com/laravel/nightwatch/pull/106

**Full Changelog**: https://github.com/laravel/nightwatch/compare/v1.0.1...v1.0.2

## [v1.0.1](https://github.com/laravel/nightwatch/compare/v1.0.0...v1.0.1) - 2025-02-14

### What's Changed

* fix: workflow permissions by [@jamesdangercarpenter](https://github.com/jamesdangercarpenter) in https://github.com/laravel/nightwatch/pull/101
* Migrate auth url from config to command option by [@timacdonald](https://github.com/timacdonald) in https://github.com/laravel/nightwatch/pull/100
* Revert "fix: workflow permissions" by [@jamesdangercarpenter](https://github.com/jamesdangercarpenter) in https://github.com/laravel/nightwatch/pull/102
* Rename Agent to match Laravel's naming conventions by [@timacdonald](https://github.com/timacdonald) in https://github.com/laravel/nightwatch/pull/103

**Full Changelog**: https://github.com/laravel/nightwatch/compare/v1.0.0...v1.0.1

## [v1.0.0](https://github.com/laravel/nightwatch/compare/v0.1.0...v1.0.0) - 2025-02-13

### What's Changed

* Initial release

**Full Changelog**: https://github.com/laravel/nightwatch/commits/v1.0.0
