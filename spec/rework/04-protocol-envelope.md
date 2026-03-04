---
title: "Issue 4: Protocol Envelope"
---

# Issue 4: Protocol Envelope

> ✅ **FINAL DECISION:** Keep object format, document extensibility

---

## Decision

**Keep the verbose protocol object format. Document that it's an extension point.**

The format is intentional — extensions can add properties:

```json
{
  "protocol": {
    "name": "forrst",
    "version": "0.1.0",
    "stability": "beta",
    "features": ["streaming"]
  }
}
```

---

## Context

Every Forrst request and response includes:

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  ...
}
```

### Original Concern

The format seemed verbose and redundant:

| Format | Bytes | Example |
|--------|-------|---------|
| Current | 45 | `"protocol": { "name": "forrst", "version": "0.1.0" }` |
| String | 22 | `"protocol": "0.1.0"` |

### Clarification

The verbose format is **intentional** — the `protocol` object is an **extension point**:

1. Extensions can add properties to the protocol object
2. The `name` field supports protocol variants (e.g., `"forrst-reloaded"`)
3. Additional metadata can be added (e.g., `"stability": "beta"`)

---

## Problem

**The spec doesn't document this extensibility.**

Developers see the verbose format and assume it's redundant. The spec should explain:
1. That `protocol` is an object for extensibility
2. What extensions can add to it
3. What additional properties are valid

---

## Missing Documentation

### What Extensions Can Add

```json
{
  "protocol": {
    "name": "forrst",
    "version": "0.1.0",
    "stability": "beta",        // Extension-added
    "features": ["streaming"],  // Extension-added
    "variant": "enterprise"     // Fork/variant identifier
  }
}
```

### Valid Additional Properties

| Property | Type | Description |
|----------|------|-------------|
| `stability` | string | `"stable"`, `"beta"`, `"experimental"` |
| `features` | string[] | Enabled protocol features |
| `variant` | string | Protocol fork/variant name |
| `extensions` | string[] | Declared extension URNs |

---

## Proposed Solution

**Document the protocol object as an extension point.**

### Spec Addition

Add to `protocol.md`:

```markdown
## Protocol Object

The `protocol` object identifies the Forrst protocol version and provides an extension point for additional metadata.

### Required Fields

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `name` | string | Yes | Protocol name. MUST be `"forrst"` or a registered variant. |
| `version` | string | Yes | Protocol version using semver format. |

### Extension Fields

Extensions MAY add properties to the protocol object:

| Field | Type | Description |
|-------|------|-------------|
| `stability` | string | Release stability: `"stable"`, `"beta"`, `"experimental"` |
| `features` | string[] | Enabled optional features |
| `variant` | string | Protocol variant identifier for forks |

### Examples

**Standard:**
```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" }
}
```

**With extensions:**
```json
{
  "protocol": {
    "name": "forrst",
    "version": "0.1.0",
    "stability": "beta",
    "features": ["streaming", "compression"]
  }
}
```

**Protocol variant:**
```json
{
  "protocol": {
    "name": "forrst-enterprise",
    "version": "0.1.0",
    "variant": "enterprise"
  }
}
```

### Extensibility Rules

1. Extensions MUST NOT modify `name` or `version` semantics
2. Additional properties SHOULD use descriptive names
3. Unknown properties MUST be ignored by receivers
4. Protocol variants MUST remain compatible with base Forrst
```

---

## Final Recommendation

**Keep the object format, document its purpose.**

The verbosity is a feature, not a bug — it enables:
1. Protocol variants and forks
2. Feature negotiation
3. Stability indicators
4. Future extensibility

### Actions Required

1. Add "Protocol Object Extensibility" section to `protocol.md`
2. Document valid extension properties
3. Add examples showing extended protocol objects
4. Explain design rationale in FAQ
