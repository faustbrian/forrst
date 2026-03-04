---
title: "Issue 5: Version Format"
---

# Issue 5: Version Format

> ✅ **FINAL DECISION:** Enforce semantic versioning

---

## Decision

**Function versions use semantic versioning format.**

```json
{ "version": "1.0.0" }
{ "version": "2.1.0" }
{ "version": "3.0.0-beta.1" }
```

---

## Context

Function versions are currently integer strings:

```json
{
  "call": {
    "function": "users.get",
    "version": "1.0.0",
    "arguments": {}
  }
}
```

### Decision

**Enforce semantic versioning** for function versions.

```json
{
  "call": {
    "function": "users.get",
    "version": "1.0.0",
    "arguments": {}
  }
}
```

---

## Rationale for Semantic Versioning

### Why Semver?

1. **Industry standard**: npm, Composer, Cargo, Go modules all use semver
2. **Clear breaking change signals**: Major bump = breaking change
3. **Patch-level fixes**: Bug fixes without version churn
4. **Pre-release support**: `1.0.0-beta.1`, `2.0.0-rc.1`

### Semver Semantics for Functions

| Version Component | Meaning |
|-------------------|---------|
| **Major** | Breaking changes to arguments or return type |
| **Minor** | New optional arguments, additional return fields |
| **Patch** | Bug fixes, no contract changes |

### Examples

```json
// Initial release
{ "version": "1.0.0" }

// Added optional 'include' argument
{ "version": "1.1.0" }

// Fixed edge case bug
{ "version": "1.1.1" }

// Breaking: renamed 'userId' to 'user_id'
{ "version": "2.0.0" }

// Beta of new major version
{ "version": "3.0.0-beta.1" }
```

---

## Spec Update

### Version Field

The `version` field specifies which function version to invoke.

**Type:** String (semantic version)

**Format:** `MAJOR.MINOR.PATCH[-PRERELEASE]`

Following [Semantic Versioning 2.0.0](https://semver.org/):
- `MAJOR`: Incremented for breaking changes
- `MINOR`: Incremented for backwards-compatible additions
- `PATCH`: Incremented for backwards-compatible fixes
- `PRERELEASE`: Optional pre-release identifier (e.g., `-beta.1`, `-rc.2`)

**Examples:**
```json
{ "version": "1.0.0" }
{ "version": "2.3.1" }
{ "version": "3.0.0-beta.1" }
```

### Version Resolution

When version is omitted, server resolves to latest stable:

1. Find all versions with no pre-release suffix
2. Select highest by semver ordering
3. If no stable versions, return `VERSION_NOT_FOUND`

### Version Compatibility

Clients requesting `1.x.x` SHOULD receive compatible responses:
- `1.0.0` request → `1.0.0`, `1.1.0`, `1.2.3` all valid
- Server MAY route to latest patch of requested minor

### Deprecation with Semver

```json
{
  "meta": {
    "deprecated": {
      "version": "1.0.0",
      "reason": "Use version 2.0.0",
      "sunset": "2025-06-01",
      "migration": "https://docs.example.com/v2-migration"
    }
  }
}
```

---

## Migration

### From Integer Strings

| Old | New |
|-----|-----|
| `"1"` | `"1.0.0"` |
| `"2"` | `"2.0.0"` |
| `"3"` | `"3.0.0"` |

### Transition Period

Servers MAY accept integer strings and normalize:
- `"1"` → `"1.0.0"` (latest 1.x.x)
- `"2"` → `"2.0.0"` (latest 2.x.x)

This allows gradual client migration.

---

## Actions Required

1. Update `versioning.md` with semver specification
2. Update `protocol.md` call object documentation
3. Update all examples throughout spec to use semver format
4. Add migration guidance for existing implementations
