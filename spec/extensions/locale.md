---
title: Locale
description: Internationalization and localization preferences
---

# Locale

> Internationalization and localization preferences

**Extension URN:** `urn:forrst:ext:locale`

---

## Overview

The locale extension allows clients to specify language, region, and formatting preferences. Servers use these hints to localize responses, error messages, and formatted values.

---

## When to Use

Locale hints SHOULD be used for:
- User-facing error messages
- Formatted dates, times, and numbers
- Currency display preferences
- Content in multiple languages

Locale hints MAY NOT be needed for:
- Service-to-service calls with no user context
- Machine-readable responses only
- APIs with single-language support

---

## Options (Request)

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `language` | string | Yes | Language tag per [RFC 5646](https://www.rfc-editor.org/rfc/rfc5646) (e.g., `en`, `en-US`, `zh-Hans`) |
| `fallback` | array | No | Fallback languages in preference order |
| `timezone` | string | No | IANA timezone (e.g., `America/New_York`) |
| `currency` | string | No | Preferred currency code per [ISO 4217](https://www.iso.org/iso-4217-currency-codes.html) |

---

## Data (Response)

| Field | Type | Description |
|-------|------|-------------|
| `language` | string | Language used in response |
| `fallback_used` | boolean | Whether a fallback language was used |
| `timezone` | string | Timezone used for formatting |
| `currency` | string | Currency used for formatting |

---

## Behavior

When the locale extension is included:

1. Server SHOULD localize error messages to requested language
2. Server SHOULD format dates/times according to locale
3. Server MUST indicate which language was actually used
4. If requested language unavailable, server SHOULD try fallbacks
5. Server SHOULD use `en` as final fallback if no match found

### Language Resolution

```
requested: zh-Hans-CN
     │
     ▼
┌─────────────────┐
│ Exact match?    │──yes──► Use zh-Hans-CN
│ zh-Hans-CN      │
└────────┬────────┘
         │ no
         ▼
┌─────────────────┐
│ Base match?     │──yes──► Use zh-Hans
│ zh-Hans         │
└────────┬────────┘
         │ no
         ▼
┌─────────────────┐
│ Language match? │──yes──► Use zh
│ zh              │
└────────┬────────┘
         │ no
         ▼
┌─────────────────┐
│ Try fallbacks   │──yes──► Use first available
└────────┬────────┘
         │ no
         ▼
    Use default (en)
```

---

## Examples

### Request with Locale

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_locale",
  "call": {
    "function": "orders.get",
    "version": "1.0.0",
    "arguments": { "order_id": "ord_123" }
  },
  "extensions": [
    {
      "urn": "urn:forrst:ext:locale",
      "options": {
        "language": "de-DE",
        "fallback": ["de", "en"],
        "timezone": "Europe/Berlin",
        "currency": "EUR"
      }
    }
  ]
}
```

### Response with Localized Content

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_locale",
  "result": {
    "order_id": "ord_123",
    "status": "versandt",
    "total": { "amount": "99,99", "currency": "EUR" },
    "created_at": "15.01.2024, 14:30 Uhr"
  },
  "extensions": [
    {
      "urn": "urn:forrst:ext:locale",
      "data": {
        "language": "de-DE",
        "fallback_used": false,
        "timezone": "Europe/Berlin",
        "currency": "EUR"
      }
    }
  ]
}
```

### Localized Error Message

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_locale_error",
  "result": null,
  "errors": [{
    "code": "NOT_FOUND",
    "message": "Bestellung nicht gefunden",
  }],
  "extensions": [
    {
      "urn": "urn:forrst:ext:locale",
      "data": {
        "language": "de-DE",
        "fallback_used": false
      }
    }
  ]
}
```

### Fallback Used

When requested language is unavailable:

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_fallback",
  "result": {
    "order_id": "ord_123",
    "status": "shipped"
  },
  "extensions": [
    {
      "urn": "urn:forrst:ext:locale",
      "data": {
        "language": "en",
        "fallback_used": true,
        "timezone": "Europe/Berlin"
      }
    }
  ]
}
```

---

## HTTP Mapping

Locale preferences MAY be provided via HTTP headers as an alternative:

| Header | Maps To | Description |
|--------|---------|-------------|
| `Accept-Language` | `language`, `fallback` | Language preferences per [RFC 9110](https://www.rfc-editor.org/rfc/rfc9110) |

When both header and extension are present, extension takes precedence.

---

## Server Implementation

### Translation Strategy

Servers SHOULD:
- Store translations in resource bundles keyed by language tag
- Support at least `en` as a universal fallback
- Cache language resolution for performance
- Document supported languages via `urn:cline:forrst:fn:capabilities`

### Timezone Handling

Servers SHOULD:
- Store timestamps in UTC internally
- Format timestamps according to requested timezone
- Use timezone database (IANA/Olson) for conversions
- Default to UTC if no timezone specified

---

## Discovery

Clients can discover supported locales via `urn:cline:forrst:fn:capabilities`:

```json
{
  "protocol": { "name": "forrst", "version": "0.1.0" },
  "id": "req_caps",
  "result": {
    "extensions": [
      {
        "urn": "urn:forrst:ext:locale",
        "supported_languages": ["en", "de", "fr", "es", "zh-Hans", "ja"],
        "default_language": "en"
      }
    ]
  }
}
```
