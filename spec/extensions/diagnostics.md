---
title: Diagnostics
description: Health monitoring and connectivity checks
---

# Diagnostics

> Health monitoring and connectivity checks

**Extension URN:** `urn:forrst:ext:diagnostics`

---

## Overview

The diagnostics extension provides health monitoring functions for service availability and component-level health checks. Enables load balancers, monitoring systems, and clients to verify service status.

This extension provides two functions:

| Function | Description |
|----------|-------------|
| `urn:cline:forrst:ext:diagnostics:fn:ping` | Simple connectivity check |
| `urn:cline:forrst:ext:diagnostics:fn:health` | Comprehensive component health |

---

## When to Use

Diagnostics functions SHOULD be used for:
- Load balancer health checks
- Kubernetes liveness/readiness probes
- Monitoring and alerting systems
- Client-side service availability checks
- Debugging connectivity issues

---

## Functions

### urn:cline:forrst:ext:diagnostics:fn:ping

Simple connectivity check with minimal overhead. Always returns immediately.

**Request:**

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_ping",
  "call": {
    "function": "urn:cline:forrst:ext:diagnostics:fn:ping",
    "version": "1.0.0"
  }
}
```

**Response:**

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_ping",
  "result": {
    "status": "healthy",
    "timestamp": "2024-01-15T10:30:00Z"
  }
}
```

**Result Fields:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `status` | string | Yes | Always "healthy" for ping |
| `timestamp` | string | Yes | ISO 8601 timestamp |

---

### urn:cline:forrst:ext:diagnostics:fn:health

Comprehensive health check with component-level status aggregation.

**Request:**

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_health",
  "call": {
    "function": "urn:cline:forrst:ext:diagnostics:fn:health",
    "version": "1.0.0"
  }
}
```

**Request (Specific Component):**

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_health_db",
  "call": {
    "function": "urn:cline:forrst:ext:diagnostics:fn:health",
    "version": "1.0.0",
    "arguments": {
      "component": "database",
      "include_details": true
    }
  }
}
```

**Arguments:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `component` | string | No | Check specific component only |
| `include_details` | boolean | No | Include detailed check info (default: true) |

**Response:**

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_health",
  "result": {
    "status": "healthy",
    "timestamp": "2024-01-15T10:30:00Z",
    "components": {
      "database": {
        "status": "healthy",
        "latency_ms": 5,
        "connections": 10
      },
      "cache": {
        "status": "healthy",
        "hit_rate": 0.95
      },
      "external_api": {
        "status": "degraded",
        "latency_ms": 850,
        "message": "High latency detected"
      }
    }
  }
}
```

**Result Fields:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `status` | string | Yes | Aggregate status (worst of all components) |
| `timestamp` | string | Yes | ISO 8601 timestamp |
| `components` | object | No | Component-level health details |
| `version` | string | No | Service version |

### Status Values

| Status | Description |
|--------|-------------|
| `healthy` | All systems operational |
| `degraded` | Operational but with issues |
| `unhealthy` | Service is not functional |

The aggregate status is the **worst** status across all components:
- If any component is `unhealthy`, aggregate is `unhealthy`
- If any component is `degraded` (and none unhealthy), aggregate is `degraded`
- Only if all components are `healthy` is aggregate `healthy`

---

## Server Implementation

### Health Checkers

Servers register health checkers for each component:

```php
// Example health checker implementation
class DatabaseHealthChecker implements HealthCheckerInterface
{
    public function getName(): string
    {
        return 'database';
    }

    public function check(): array
    {
        try {
            $latency = $this->measureLatency();
            return [
                'status' => $latency < 100 ? 'healthy' : 'degraded',
                'latency_ms' => $latency,
            ];
        } catch (Exception $e) {
            return [
                'status' => 'unhealthy',
                'message' => $e->getMessage(),
            ];
        }
    }
}
```

### Customization

Servers MAY customize:
- What components are checked
- Thresholds for degraded/unhealthy status
- Additional metadata in responses
- Ping behavior (though it should remain lightweight)

---

## Examples

### Kubernetes Probes

**Liveness Probe** (use ping for lightweight check):

```yaml
livenessProbe:
  exec:
    command:
      - forrst-cli
      - call
      - urn:cline:forrst:ext:diagnostics:fn:ping
  initialDelaySeconds: 5
  periodSeconds: 10
```

**Readiness Probe** (use health for dependency checks):

```yaml
readinessProbe:
  exec:
    command:
      - forrst-cli
      - call
      - urn:cline:forrst:ext:diagnostics:fn:health
  initialDelaySeconds: 10
  periodSeconds: 30
```

### Load Balancer Configuration

```
┌─────────────────┐
│  Load Balancer  │
└────────┬────────┘
         │
         │  ping every 5s
         ▼
┌─────────────────┐     ┌─────────────────┐
│   Server A      │     │   Server B      │
│   (healthy)     │     │   (unhealthy)   │
│   ← traffic     │     │   ✗ removed     │
└─────────────────┘     └─────────────────┘
```

### Monitoring Dashboard

```json
// Poll health endpoint for dashboard
{
  "call": {
    "function": "urn:cline:forrst:ext:diagnostics:fn:health",
    "arguments": {
      "include_details": true
    }
  }
}

// Response with component details
{
  "result": {
    "status": "degraded",
    "timestamp": "2024-01-15T10:30:00Z",
    "components": {
      "database": { "status": "healthy" },
      "cache": { "status": "healthy" },
      "payment_gateway": {
        "status": "degraded",
        "message": "Response times elevated"
      }
    }
  }
}
```

---

## Error Handling

The health function itself SHOULD NOT return errors - it should always return a status. If a component check fails, that component should be marked as `unhealthy` in the response rather than returning an error.

The ping function SHOULD always succeed if the server is reachable. A failed ping indicates the server is completely unavailable.
