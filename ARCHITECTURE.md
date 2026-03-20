# Architecture: flysystem-path-prefixing

## Purpose

A Flysystem adapter decorator that automatically prefixes all paths with a fixed string. It allows a single underlying adapter (e.g., S3, local disk) to be partitioned into virtual subdirectories without leaking prefix concerns into calling code.

## Directory Structure

```
PathPrefixedAdapter.php      — Decorator: prepends a prefix to every path before delegating to the inner adapter
PathPrefixedAdapterTest.php  — Contract tests verifying the adapter behaves like any other FilesystemAdapter
```

## Key Design Decisions

- **Decorator pattern** — wraps any `FilesystemAdapter` and transparently prepends the prefix; callers see a clean virtual root.
- **PathPrefixer** — delegates actual path manipulation to Flysystem's built-in `PathPrefixer` to handle slash normalisation, preventing double-slash or traversal issues.
- **Empty-prefix guard** — rejects an empty string prefix at construction time, which would be a silent no-op and likely a misconfiguration.
- **Implements optional interfaces** — `PublicUrlGenerator`, `TemporaryUrlGenerator`, and `ChecksumProvider` are delegated to the inner adapter when it supports them.

## Extension Points

None beyond the standard `FilesystemAdapter` contract. Compose with any adapter.

## Dependency Flow

```
Caller
  └── PathPrefixedAdapter::read('path/to/file')
        └── PathPrefixer::prefixPath('path/to/file') → 'my-prefix/path/to/file'
              └── InnerAdapter::read('my-prefix/path/to/file')
```
