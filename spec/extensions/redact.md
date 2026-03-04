---
title: Redact
description: Sensitive field masking and data protection
---

# Redact

> Sensitive field masking and data protection

**Extension URN:** `urn:forrst:ext:redact`

---

## Overview

The redact extension controls how sensitive data is handled in responses and logs. Clients can request field masking, and servers indicate which fields were redacted for security or compliance.

---

## When to Use

Redaction SHOULD be used for:
- Responses containing PII (personally identifiable information)
- Payment and financial data
- Authentication credentials
- Health or medical information
- Compliance with GDPR, HIPAA, PCI-DSS

Redaction MAY NOT be needed for:
- Internal service-to-service calls in trusted networks
- Responses with no sensitive data
- Development/debugging environments

---

## Options (Request)

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `mode` | string | No | Redaction mode: `full`, `partial`, `none` (default: `full`) |
| `fields` | array | No | Specific fields to redact (overrides defaults) |
| `purpose` | string | No | Why data is needed (for audit logging) |

### Redaction Modes

| Mode | Description |
|------|-------------|
| `full` | Replace sensitive values entirely (e.g., `***`) |
| `partial` | Mask partially, preserve format (e.g., `****-****-****-1234`) |
| `none` | No redaction (requires elevated permissions) |

---

## Data (Response)

| Field | Type | Description |
|-------|------|-------------|
| `mode` | string | Redaction mode applied |
| `redacted_fields` | array | List of fields that were redacted |
| `policy` | string | Redaction policy applied |

---

## Behavior

When the redact extension is included:

1. Server MUST redact fields according to its security policy
2. Server MUST indicate which fields were redacted
3. Server MAY reject `mode: none` requests without proper authorization
4. Server SHOULD log access to sensitive data with `purpose`

### Default Redacted Fields

Servers SHOULD redact these fields by default:

| Category | Fields |
|----------|--------|
| Authentication | `password`, `secret`, `token`, `api_key` |
| Payment | `card_number`, `cvv`, `account_number` |
| PII | `ssn`, `tax_id`, `passport_number` |
| Contact | `email`, `phone` (partial) |

---

## Examples

### Request with Full Redaction

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_redact",
  "call": {
    "function": "users.get",
    "version": "1.0.0",
    "arguments": { "user_id": "usr_123" }
  },
  "extensions": [
    {
      "urn": "urn:forrst:ext:redact",
      "options": {
        "mode": "full"
      }
    }
  ]
}
```

### Response with Redacted Fields

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_redact",
  "result": {
    "user_id": "usr_123",
    "name": "Alice Smith",
    "email": "***",
    "phone": "***",
    "ssn": "***"
  },
  "extensions": [
    {
      "urn": "urn:forrst:ext:redact",
      "data": {
        "mode": "full",
        "redacted_fields": ["email", "phone", "ssn"],
        "policy": "pii_protection"
      }
    }
  ]
}
```

### Partial Redaction

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_partial",
  "call": {
    "function": "payments.get",
    "version": "1.0.0",
    "arguments": { "payment_id": "pay_456" }
  },
  "extensions": [
    {
      "urn": "urn:forrst:ext:redact",
      "options": {
        "mode": "partial"
      }
    }
  ]
}
```

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_partial",
  "result": {
    "payment_id": "pay_456",
    "card_number": "****-****-****-4242",
    "cardholder": "A*** S***",
    "expiry": "**/**",
    "cvv": "***"
  },
  "extensions": [
    {
      "urn": "urn:forrst:ext:redact",
      "data": {
        "mode": "partial",
        "redacted_fields": ["card_number", "cardholder", "expiry", "cvv"],
        "policy": "pci_dss"
      }
    }
  ]
}
```

### Request Unredacted Data (Authorized)

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_unredacted",
  "call": {
    "function": "users.get",
    "version": "1.0.0",
    "arguments": { "user_id": "usr_123" }
  },
  "extensions": [
    {
      "urn": "urn:forrst:ext:redact",
      "options": {
        "mode": "none",
        "purpose": "customer_support_ticket_12345"
      }
    }
  ]
}
```

### Unauthorized Access Denied

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_denied",
  "result": null,
  "errors": [{
    "code": "FORBIDDEN",
    "message": "Unredacted access requires elevated permissions",
    "details": {
      "required_scope": "pii:read:unredacted"
    }
  }],
  "extensions": [
    {
      "urn": "urn:forrst:ext:redact",
      "data": {
        "mode": "full",
        "policy": "access_denied"
      }
    }
  ]
}
```

### Selective Field Redaction

Request specific fields to be redacted:

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_selective",
  "call": {
    "function": "orders.get",
    "version": "1.0.0",
    "arguments": { "order_id": "ord_789" }
  },
  "extensions": [
    {
      "urn": "urn:forrst:ext:redact",
      "options": {
        "mode": "full",
        "fields": ["shipping_address", "billing_address"]
      }
    }
  ]
}
```

---

## Error Codes

| Code | Description |
|------|-------------|
| `REDACTION_REQUIRED` | Request attempted to bypass required redaction |
| `FORBIDDEN` | Insufficient permissions for requested redaction mode |

---

## Server Implementation

### Redaction Patterns

| Pattern | Full | Partial |
|---------|------|---------|
| Email | `***` | `a***@***.com` |
| Phone | `***` | `***-***-1234` |
| Card | `***` | `****-****-****-1234` |
| SSN | `***` | `***-**-6789` |
| Name | `***` | `A*** S***` |

### Audit Logging

Servers SHOULD log:
- Who accessed sensitive data
- What fields were accessed
- Stated purpose
- Redaction mode used
- Timestamp

### Policy Configuration

Servers SHOULD configure redaction policies per:
- Field type (PII, financial, health)
- Client role/permissions
- Compliance requirements
- Environment (prod vs dev)

---

## Compliance Considerations

| Regulation | Recommendation |
|------------|----------------|
| GDPR | Default `full` for EU user PII |
| PCI-DSS | Always redact full card numbers, never store CVV |
| HIPAA | `full` redaction for PHI |
| SOC 2 | Audit log all `mode: none` requests |
