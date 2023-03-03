# Changelog

All notable changes to `EdgeFlush` will be documented in this file.

## 1.4.3 - 2022-10-27
### Added
- Fix URLs not being correctly parsed with username and password

## 1.4.2 - 2022-10-26
### Added 
- Hook up update events to the Laravel Eloquent event system

## 1.4.0 - 2022-10-25
## 1.4.1 - 2022-10-25
### Changed
- Move up to PHPStan Level 9

## 1.3.14 - 2022-09-28
### Changed
- Improve Guzzle connection errors handling
- Allow invalidations when disabled
- Prevent CDN service instantiation until it's really needed
- Improve the way warmer reset tags and URLs

## 1.3.XX - 2022-10-XX
### Added 
- Full cache invalidation command: php artisan edge-flush:invalidate-all


## 1.3.10 - 2022-09-26
### Fixed
- Correctly setting was_purged_at update SQL

## 1.3.9 - 2022-09-26
### Changed
- Improve performance by not instantiating the CDN service until it's really needed

## 1.3.5 - 2022-09-14
### Changed
- Force invalidate all to clear all tags and urls

## 1.3.4 - 2022-09-13
### Changed
- Remove purged status from URLs that were hit

## 1.3.3 - 2022-09-13
### Changed
- Improved invalidation URLs query performance

## 1.3.2 - 2022-09-13
### Fixed
- Fix warmer not dispatching requests

## 1.3.1 - 2022-09-13
### Fixed
- Properly mark URLs as purged

## 1.3.0 - 2022-09-13
### Added
- Add a new Invalidation class to control invalidation items and the invalidation itself
### Changed
- Strong query and update optimization
### Fixed
- Fix all PHPStan level 8 errors

## 1.0.2 - 2021-11-10
### Fixed
- Disabled state generating recursion

## 1.0.1 - 2021-11-10
### Added
- Alow disabling on runtime
### Fixed
- EdgeFlush was not easily disablable 

## 1.0.0 - 2021-07-28
-   initial release
