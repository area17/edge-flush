# Changelog

All notable changes to `CDN` will be documented in this file.

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
