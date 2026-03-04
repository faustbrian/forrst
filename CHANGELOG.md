# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [2.0.0] - 2025-10-23

### Added

- **XML-RPC Protocol Support**: Full XML-RPC specification implementation alongside existing JSON-RPC 2.0
  - `ProtocolInterface` with encode/decode methods for request/response handling
  - `JsonRpcProtocol` class implementing JSON-RPC 2.0 specification
  - `XmlRpcProtocol` class implementing XML-RPC specification with proper type encoding/decoding
  - `Client::json()` and `Client::xml()` static factory methods for protocol selection
  - `SerializerInterface` for backward compatibility
  - Protocol singleton bindings in ServiceProvider
  - Comprehensive test coverage (22 XmlRpcProtocol tests, 962 total tests passing)

- **New Exception Classes**
  - `XmlRpcDecodingException` for XML-RPC decoding errors
  - `XmlRpcEncodingException` for XML-RPC encoding errors

### Changed

- **Breaking**: Renamed internal serializer references to protocol throughout codebase
  - `RequestHandler` now uses configurable protocol system
  - Client constructor accepts protocol parameter
  - ServiceProvider registers protocol bindings

### Fixed

- Enhanced type safety in protocol handling
- Improved error handling for malformed XML-RPC requests

## [1.0.0] - 2025-10-19

### Added

- Initial release with JSON-RPC 2.0 support
- Complete JSON-RPC server implementation
- Method discovery and introspection
- Resource-based architecture with Model and Data support
- Request validation using JSON Schema
- Comprehensive exception handling
- Laravel integration with middleware and service provider
- Query builder with filtering, sorting, and pagination
- Authentication and authorization support
- Transformer system for data serialization
- 100% test coverage
- Docker development environment
- GitHub Actions CI/CD workflow

[Unreleased]: https://github.com/faustbrian/rpc/compare/2.0.0...HEAD
[2.0.0]: https://github.com/faustbrian/rpc/compare/1.0.0...2.0.0
[1.0.0]: https://github.com/faustbrian/rpc/releases/tag/1.0.0
