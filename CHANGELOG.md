# Release Notes

## [Unreleased](https://github.com/laravel/nightwatch/compare/v1.2.1...1.x)

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

## v0.1.0 (202x-xx-xx)

Initial pre-release.
