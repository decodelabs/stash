* Updated Archetype dependency

## v0.5.4 (2024-04-24)
* Updated Carbon dependency

## v0.5.3 (2024-03-22)
* Fixed generics across whole Store interface

## v0.5.2 (2024-03-22)
* Added full generics to Stores

## v0.5.1 (2024-03-22)
* Added generic return type to fetch()

## v0.5.0 (2024-03-22)
* Renamed FileStore multi-get methods to scan*()

## v0.4.1 (2024-03-21)
* Added TTL to FileStore fetch()
* Fixed FileStore get() TTL

## v0.4.0 (2024-03-21)
* Renamed default file cache paths
* Added prune to FileStore
* Added purge to FileStore
* Removed SimpleCache interface from FileStore
* Improved FileStore interface
* Renamed cache purge methods

## v0.3.2 (2024-02-29)
* Added FileStores

## v0.3.1 (2024-02-29)
* Catch exceptions in fetch()
* Added unlock to items

## v0.3.0 (2023-11-14)
* Renamed enums to PascalCase

## v0.2.1 (2023-11-09)
* Improved selection of drivers to purge

## v0.2.0 (2023-11-06)
* Added Dovetail config implementation
* Converted PileUpPolicy to enum
* Added getKeys() and count() to Store and Driver
* Added default prefix to Context
* Renamed get() to load()
* Refactored package file structure
* Updated Genesis dependency

## v0.1.2 (2023-09-26)
* Converted phpstan doc comments to generic
* Migrated to use effigy in CI workflow
* Fixed PHP8.1 testing

## v0.1.1 (2022-10-18)
* Added Composer driver

## v0.1.0 (2022-10-18)
* Ported initial codebase from DF
* Moved manager to Veneer context
