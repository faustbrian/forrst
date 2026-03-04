---
title: Simulation
description: Sandbox mode with predefined scenarios
---

# Simulation

> Sandbox mode with predefined scenarios

**Extension URN:** `urn:forrst:ext:simulation`

---

## Overview

The simulation extension enables sandbox/demo mode where functions return predefined responses without executing real logic or causing side effects. Unlike dry-run (which validates real requests against real state), simulation operates entirely on predefined input/output scenarios defined by the function author.

---

## When to Use

Simulation SHOULD be used for:
- Interactive API documentation and explorers
- Client SDK testing without backend dependencies
- Demo environments with predictable behavior
- Integration testing with deterministic responses
- Onboarding flows showing example behavior

Simulation SHOULD NOT be used for:
- Validating real user input (use dry-run instead)
- Testing actual business logic
- Production environments

---

## Options (Request)

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `enabled` | boolean | Yes | Enable simulation mode |
| `scenario` | string | No | Scenario name to execute (uses default if omitted) |
| `list_scenarios` | boolean | No | List available scenarios instead of executing |

---

## Data (Response)

| Field | Type | Description |
|-------|------|-------------|
| `simulated` | boolean | Whether response was simulated |
| `scenario` | string | Name of the executed scenario |
| `available_scenarios` | array | List of scenarios (when `list_scenarios=true`) |
| `error` | string | Error type when simulation fails |
| `requested_scenario` | string | Requested scenario name (when not found) |

---

## Behavior

When the simulation extension is enabled:

1. Server MUST check if function implements `SimulatableInterface`
2. Server MUST NOT execute real function logic
3. Server MUST NOT modify any state
4. Server MUST NOT trigger side effects
5. Server MUST return the predefined scenario output
6. Server SHOULD match requested scenario by name

When `list_scenarios` is true:

1. Server MUST return available scenarios without executing
2. Server SHOULD include scenario descriptions and error indicators

---

## Scenario Structure

Functions expose scenarios with:

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `name` | string | Yes | Unique scenario identifier |
| `input` | object | Yes | Arguments that trigger this scenario |
| `output` | any | No | Successful result value |
| `description` | string | No | Human-readable explanation |
| `error` | object | No | Error response (code, message, data) |
| `metadata` | object | No | Additional response metadata |

---

## Examples

### List Available Scenarios

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_list",
  "call": {
    "function": "users.get",
    "version": "1.0.0",
    "arguments": {}
  },
  "extensions": [
    {
      "urn": "urn:forrst:ext:simulation",
      "options": {
        "enabled": true,
        "list_scenarios": true
      }
    }
  ]
}
```

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_list",
  "result": null,
  "extensions": [
    {
      "urn": "urn:forrst:ext:simulation",
      "data": {
        "simulated": false,
        "available_scenarios": [
          {
            "name": "default",
            "description": "Returns a typical user object",
            "is_error": false,
            "is_default": true
          },
          {
            "name": "not_found",
            "description": "User ID does not exist",
            "is_error": true,
            "is_default": false
          },
          {
            "name": "suspended",
            "description": "User account is suspended",
            "is_error": false,
            "is_default": false
          }
        ]
      }
    }
  ]
}
```

### Execute Default Scenario

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_sim",
  "call": {
    "function": "users.get",
    "version": "1.0.0",
    "arguments": { "id": "user_123" }
  },
  "extensions": [
    {
      "urn": "urn:forrst:ext:simulation",
      "options": {
        "enabled": true
      }
    }
  ]
}
```

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_sim",
  "result": {
    "id": "user_123",
    "name": "Jane Doe",
    "email": "jane@example.com",
    "status": "active"
  },
  "extensions": [
    {
      "urn": "urn:forrst:ext:simulation",
      "data": {
        "simulated": true,
        "scenario": "default"
      }
    }
  ]
}
```

### Execute Specific Scenario

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_error",
  "call": {
    "function": "users.get",
    "version": "1.0.0",
    "arguments": { "id": "user_999" }
  },
  "extensions": [
    {
      "urn": "urn:forrst:ext:simulation",
      "options": {
        "enabled": true,
        "scenario": "not_found"
      }
    }
  ]
}
```

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_error",
  "errors": [
    {
      "code": "NOT_FOUND",
      "message": "User not found",
    }
  ],
  "extensions": [
    {
      "urn": "urn:forrst:ext:simulation",
      "data": {
        "simulated": true,
        "scenario": "not_found"
      }
    }
  ]
}
```

### Function Does Not Support Simulation

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_unsupported",
  "errors": [
    {
      "code": "SIMULATION_NOT_SUPPORTED",
      "message": "Function 'legacy.process' does not support simulation",
    }
  ],
  "extensions": [
    {
      "urn": "urn:forrst:ext:simulation",
      "data": {
        "simulated": false,
        "error": "unsupported"
      }
    }
  ]
}
```

### Scenario Not Found

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_missing",
  "errors": [
    {
      "code": "SIMULATION_SCENARIO_NOT_FOUND",
      "message": "Simulation scenario 'invalid_scenario' not found",
    }
  ],
  "extensions": [
    {
      "urn": "urn:forrst:ext:simulation",
      "data": {
        "simulated": false,
        "error": "scenario_not_found",
        "requested_scenario": "invalid_scenario"
      }
    }
  ]
}
```

---

## Comparison: Simulation vs Dry-Run

| Aspect | Simulation | Dry-Run |
|--------|------------|---------|
| Purpose | Demo/sandbox mode | Validate before executing |
| Input | Ignored (uses predefined) | Validated against real state |
| Output | Predefined by function | Computed from real data |
| Backend required | No | Yes |
| Deterministic | Always | Usually |
| Use case | Testing, demos, docs | Confirmation flows |

---

## Notes

- Functions MUST implement `SimulatableInterface` to support simulation
- Scenarios SHOULD cover success, error, and edge cases
- Scenario names MUST be unique within a function
- The `default` scenario SHOULD represent the most common success case
- Input arguments in simulation mode are ignored; output is predetermined
