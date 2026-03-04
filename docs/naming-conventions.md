---
title: Function Naming Conventions
description: Comprehensive guide to function identifier formats with recommendations based on scale and requirements
---

This guide documents function naming approaches in order of recommendation, with clear reasoning for when to use each pattern.

## Quick Decision Matrix

| Your Situation | Recommended Approach |
|----------------|---------------------|
| **Forrst projects** | [URN Format](#4-urn-format) ⭐ |
| Single vendor, <50 functions | [Simple Dotted](#1-simple-dotted) |
| Single vendor, 50-200 functions | [Service-Grouped Dotted](#2-service-grouped-dotted) |
| Multi-vendor OR >200 functions | [Vendor-Prefixed](#3-vendor-prefixed) |
| Enterprise/compliance requirements | [URN Format](#4-urn-format) |
| Protobuf/schema-first design | [gRPC-Style](#5-grpc-style) |
| AWS ecosystem integration | [AWS-Style](#6-aws-style) |
| Azure ecosystem integration | [Azure-Style](#7-azure-style) |
| Blockchain/Ethereum APIs | [OpenRPC-Style](#8-openrpc-style) |
| AI/LLM tool calling | [MCP-Style](#9-mcp-style) |
| Frontend-driven, type-safe APIs | [GraphQL-Style](#10-graphql-style) |

---

## Approaches (Ranked by Recommendation)

### 1. Simple Dotted

**Best for:** Small projects, single vendor, <50 functions

```
Format: {service}.{function}
```

**Examples:**
```
orders.create
orders.get
users.authenticate
payments.process
```

**Pros:**
- Minimal verbosity
- Familiar to JSON-RPC users
- Easy to type and read
- No configuration required

**Cons:**
- No vendor isolation
- No versioning strategy
- Namespace collisions in multi-vendor environments

**When to use:**
- Internal tools and services
- Single-team projects
- Rapid prototyping
- When simplicity trumps extensibility

---

### 2. Service-Grouped Dotted

**Best for:** Medium projects, single vendor, 50-200 functions

```
Format: {domain}.{service}.{function}
```

**Examples:**
```
commerce.orders.create
commerce.orders.get
commerce.inventory.check
identity.users.authenticate
billing.payments.process
```

**Pros:**
- Logical grouping by domain
- Scales to hundreds of functions
- Clear organizational hierarchy
- Still relatively concise

**Cons:**
- No vendor attribution
- No explicit versioning
- Requires domain taxonomy planning

**When to use:**
- Growing monoliths
- Microservices within a single organization
- When you need better organization but not multi-vendor support

---

### 3. Vendor-Prefixed

**Best for:** Multi-vendor environments, >200 functions, API marketplaces

```
Format: {vendor}.{service}/{function}
```

**Examples:**
```
acme.orders/create
acme.orders/get
acme.orders/cancel
stripe.payments/charge
cline.discovery/describe
```

**Pros:**
- Clear vendor ownership
- Scales to thousands of functions
- The `/` separator makes parsing unambiguous
- Service grouping within vendor namespace
- Concise yet fully qualified

**Cons:**
- More verbose than simple dotted
- Requires vendor registry/coordination

**When to use:**
- Multi-vendor platforms
- Public APIs with external consumers
- API marketplaces and plugin systems
- When clear ownership attribution matters

**Note:** Versioning is handled at the protocol level, not in the identifier. Forrst supports native function versioning, keeping identifiers stable across versions.

---

### 4. URN Format

**Best for:** Forrst projects ⭐, enterprise environments, systems with extensions/plugins

```
Format: urn:{vendor}:{service}:{type}:{name}
```

**Examples:**
```
# Vendor functions
urn:acme:orders:fn:create
urn:acme:logistics:fn:list-shipments

# Extension functions
urn:forrst:ext:discovery:fn:describe
urn:forrst:ext:deprecation:fn:list-deprecated

# System functions
urn:forrst:system:fn:ping
```

**Pros:**
- Self-documenting structure with semantic segments
- Type distinction (`fn`, `ext`, `system`) enables routing/permissions
- Clear ownership attribution (vendor segment)
- Extension clarity (which extension provides what)
- Wildcard permission patterns (`urn:vendor:*`)
- Written once, referenced many times (verbosity cost is minimal)

**Cons:**
- Most verbose option
- Requires understanding URN structure

**When to use:**
- **Forrst projects** (recommended default)
- Systems with extensions/plugins requiring type distinction
- Multi-vendor platforms needing clear ownership
- When debugging clarity matters (logs, traces, errors)
- Permission systems using prefix matching

---

### 5. gRPC-Style

**Best for:** Protobuf/schema-first projects, strongly-typed environments

```
Format: /{package}.{Service}/{Method}
```

**Examples:**
```
/acme.orders.v1.OrderService/CreateOrder
/acme.orders.v1.OrderService/GetOrder
/acme.payments.v1.PaymentService/ProcessPayment
```

**Pros:**
- Industry-proven at massive scale (Google, etc.)
- Tight integration with Protobuf schemas
- Service grouping built-in
- Clear versioning via package
- Excellent tooling ecosystem

**Cons:**
- Requires Protobuf commitment
- Very verbose for simple use cases
- Service suffix (`Service`) often redundant

**When to use:**
- Already using Protobuf/gRPC
- Need strong typing and schema validation
- High-performance binary serialization required
- Polyglot environments with code generation

---

### 6. AWS-Style

**Best for:** AWS ecosystem integration, IAM-like permission systems

```
Format: {service}:{Action}
```

**Examples:**
```
s3:GetObject
s3:PutObject
s3:DeleteObject
dynamodb:GetItem
dynamodb:PutItem
lambda:InvokeFunction
ec2:StartInstances
```

**Pros:**
- Instantly familiar to AWS users
- Clean service:action separation
- Works well with permission/policy systems
- PascalCase actions are self-documenting
- Proven at AWS scale (thousands of actions)

**Cons:**
- No vendor prefix (assumes single provider)
- No explicit versioning
- PascalCase may conflict with kebab-case conventions
- Colon separator can conflict with URN schemes

**When to use:**
- Building AWS-integrated services
- IAM-style permission systems
- When your users already think in AWS terms
- Action-centric (vs resource-centric) APIs

**Real-world patterns from AWS:**
```
# Pattern: service:VerbNoun
s3:GetObject
s3:ListBuckets
iam:CreateUser
iam:AttachRolePolicy

# Pattern: service:VerbNounProperty
ec2:DescribeInstanceStatus
rds:ModifyDBClusterEndpoint
```

---

### 7. Azure-Style

**Best for:** Azure ecosystem integration, resource-centric APIs, RBAC systems

```
Format: {Provider}/{ResourceType}/{Action}
```

**Examples:**
```
Microsoft.Storage/storageAccounts/read
Microsoft.Storage/storageAccounts/write
Microsoft.Storage/storageAccounts/delete
Microsoft.Compute/virtualMachines/start
Microsoft.Compute/virtualMachines/restart
Microsoft.Web/sites/functions/read
```

**Pros:**
- Resource hierarchy is explicit
- Provider namespacing (multi-vendor ready)
- Maps directly to REST resource paths
- Built for RBAC permission systems
- Clear read/write/delete/action taxonomy

**Cons:**
- Very verbose
- Deep nesting can get unwieldy
- Provider prefix often redundant
- Slash separator conflicts with URL paths

**When to use:**
- Building Azure-integrated services
- Resource-centric (vs action-centric) APIs
- Hierarchical permission systems
- When resources have clear parent/child relationships

**Real-world patterns from Azure:**
```
# Pattern: Provider/Resource/action
Microsoft.Storage/storageAccounts/read
Microsoft.Storage/storageAccounts/listKeys/action

# Pattern: Provider/Resource/SubResource/action
Microsoft.Storage/storageAccounts/blobServices/containers/read
Microsoft.Web/sites/config/appsettings/read

# Wildcards for permissions
Microsoft.Storage/storageAccounts/*
Microsoft.Compute/*/read
```

**Azure vs AWS comparison:**
```
# AWS (action-centric)
s3:GetObject
s3:PutObject

# Azure (resource-centric)
Microsoft.Storage/storageAccounts/blobServices/containers/blobs/read
Microsoft.Storage/storageAccounts/blobServices/containers/blobs/write
```

---

### 8. OpenRPC-Style

**Best for:** Blockchain/Ethereum APIs, JSON-RPC with underscore conventions

```
Format: {namespace}_{method}
```

**Examples:**
```
eth_getBalance
eth_sendTransaction
eth_getBlockByNumber
eth_estimateGas
net_version
net_peerCount
web3_clientVersion
personal_sign
debug_traceTransaction
```

**Pros:**
- Standard in Ethereum/blockchain ecosystem
- Simple flat namespace
- Underscore separator is unambiguous
- Easy to parse and validate
- Well-documented in OpenRPC spec

**Cons:**
- Underscore conflicts with snake_case function names
- Limited hierarchy (only one level)
- No versioning strategy
- Namespace feels like a prefix, not a category

**When to use:**
- Building blockchain/Ethereum-compatible APIs
- JSON-RPC APIs following OpenRPC specification
- When interoperability with Web3 tooling matters
- Simple APIs with flat structure

**Real-world patterns:**
```
# Core Ethereum methods
eth_accounts
eth_blockNumber
eth_call
eth_chainId
eth_getCode

# Namespaces indicate subsystem
eth_*        → Core Ethereum
net_*        → Network info
web3_*       → Web3 utilities
personal_*   → Account management
debug_*      → Debugging tools
trace_*      → Transaction tracing
```

---

### 9. MCP-Style

**Best for:** AI tool integration, LLM function calling, multi-provider tool registries

```
Format: {vendor}__{tool} or {tool}
```

**Examples:**
```
# Vendor-prefixed (multi-provider)
github__create_issue
github__list_repos
github__create_pull_request
slack__send_message
slack__list_channels
linear__create_issue

# Simple (single-provider)
create_file
read_file
search_code
run_terminal_command
```

**Pros:**
- Designed for AI/LLM tool calling
- Double underscore is unambiguous separator
- Vendor prefix enables multi-provider environments
- Snake_case matches Python conventions (common in AI)
- Simple tools can omit vendor prefix

**Cons:**
- Double underscore looks unusual
- No versioning strategy
- No service/domain grouping
- Relatively new standard (less proven)

**When to use:**
- Building MCP (Model Context Protocol) servers
- AI assistant tool integrations
- LLM function calling systems
- Multi-provider tool registries

**Real-world patterns from MCP:**
```
# File operations
read_file
write_file
list_directory

# With vendor prefix
github__search_repositories
github__get_file_contents
github__create_or_update_file

# Naming convention
- Use snake_case for tool names
- Verb_noun pattern: create_issue, list_repos
- Vendor prefix when multiple providers possible
```

---

### 10. GraphQL-Style

**Best for:** Query languages, frontend-driven APIs, type-safe schemas

```
Format: {verbNoun} (camelCase)
```

**Examples:**
```
# Queries (read operations)
user
users
orderById
ordersByCustomer
searchProducts

# Mutations (write operations)
createUser
updateUser
deleteUser
createOrder
cancelOrder
processPayment

# Subscriptions (real-time)
onOrderCreated
onPaymentProcessed
onUserUpdated
```

**Pros:**
- Extremely concise
- Self-documenting with verb prefixes
- Type system provides context (Query vs Mutation)
- Frontend-developer friendly
- Strong tooling ecosystem (Apollo, Relay)

**Cons:**
- No namespace/vendor isolation
- Relies on schema context for meaning
- Name collisions in large schemas
- Not suitable for flat RPC (needs GraphQL schema)

**When to use:**
- Building GraphQL APIs
- Frontend-driven API design
- When schema provides the namespace context
- Type-safe, introspectable APIs

**Naming conventions:**
```
# Queries - noun or getNoun
user                    # Get single by ID
users                   # Get list
userByEmail            # Get by specific field

# Mutations - verbNoun
createUser
updateUser
deleteUser
assignUserRole
resetPassword

# Subscriptions - onNounVerbed
onUserCreated
onOrderUpdated
onPaymentFailed

# Input types
CreateUserInput
UpdateOrderInput
```

**GraphQL vs RPC comparison:**
```
# GraphQL (schema-contextual)
query { user(id: "123") { name } }
mutation { createUser(input: {...}) { id } }

# RPC equivalent
users.get(id: "123")
users.create(input: {...})
```

---

## Industry Standards Reference

| Standard | Format | Primary Use Case |
|----------|--------|------------------|
| JSON-RPC 2.0 | `namespace.method` | Simple RPC |
| gRPC | `/package.Service/Method` | High-performance microservices |
| OpenRPC | `namespace_method` | Ethereum/blockchain APIs |
| MCP | `vendor__tool` | AI tool integration |
| AWS IAM | `service:Action` | Cloud permissions |
| Azure RBAC | `Provider/Type/Action` | Cloud permissions |
| GraphQL | `verbNoun` | Query languages |

---

## Migration Paths

### From Simple → Service-Grouped

```
# Before
orders.create
users.get

# After
commerce.orders.create
identity.users.get
```

**Strategy:** Prefix with domain, update clients, deprecate old names.

### From Dotted → Vendor-Prefixed

```
# Before
commerce.orders.create

# After
acme.orders/create
```

**Strategy:**
1. Support both formats during transition
2. Log usage of deprecated format
3. Set deprecation deadline
4. Remove old format support

### From Any → URN

```
# Before
acme.orders/create

# After
urn:acme:forrst:fn:orders:create
```

**Strategy:** Usually unnecessary. Only migrate if compliance requires it.

---

## Forrst Recommendations

For Forrst projects, we recommend **URN format** due to its explicit structure that distinguishes between vendor functions, system extensions, and extension functions.

### Recommended Format: URN

URNs provide semantic structure that carries meaning beyond just identification:

```
urn:{vendor}:{service}:{type}:{function}
```

### URN Structures

#### 1. Vendor Functions

User-defined functions provided by a vendor/service:

```
Format:  urn:{vendor}:{service}:fn:{function}
```

**Examples:**
```
urn:acme:logistics:fn:create-shipment
urn:acme:logistics:fn:list-events
urn:acme:postal:fn:validate-address
urn:acme:locations:fn:search-locations
urn:stripe:payments:fn:create-charge
urn:acme:orders:fn:cancel-order
```

#### 2. Extension Functions

Functions provided by Forrst protocol extensions:

```
Format:  urn:forrst:ext:{extension}:fn:{function}
```

**Examples:**
```
urn:forrst:ext:discovery:fn:describe
urn:forrst:ext:discovery:fn:capabilities
urn:forrst:ext:deprecation:fn:list-deprecated
urn:forrst:ext:diagnostics:fn:health-check
urn:forrst:ext:tracing:fn:get-trace
urn:forrst:ext:caching:fn:invalidate
urn:forrst:ext:rate-limit:fn:get-limits
```

#### 3. System Functions

Core Forrst protocol functions (not extensions):

```
Format:  urn:forrst:system:fn:{function}
```

**Examples:**
```
urn:forrst:system:fn:ping
urn:forrst:system:fn:version
urn:forrst:system:fn:shutdown
```

### URN Segment Reference

| Segment | Purpose | Examples |
|---------|---------|----------|
| `{vendor}` | Who owns/provides this | `acme`, `stripe`, `forrst` |
| `{service}` | Domain/service grouping | `orders`, `payments`, `inventory`, `logistics` |
| `{type}` | What kind of identifier | `fn` (function), `ext` (extension), `system` |
| `{extension}` | Which extension (for ext type) | `discovery`, `deprecation`, `tracing` |
| `{function}` | The function name | `create-shipment`, `describe` |

### Why URNs for Forrst

The explicit structure enables:

```php
// Instantly identify what you're dealing with
$urn = 'urn:forrst:ext:deprecation:fn:list-deprecated';

// Parse and route based on structure
if (str_starts_with($urn, 'urn:forrst:ext:')) {
    // System extension - special handling
} elseif (str_starts_with($urn, 'urn:forrst:system:')) {
    // Core system function
} else {
    // Vendor function - route to appropriate handler
}
```

**Debugging clarity:**
```
[ERROR] urn:acme:logistics:fn:create-shipment failed    → User code issue
[ERROR] urn:forrst:ext:discovery:fn:describe failed      → System extension issue
```

**Permission patterns:**
```
allow: urn:acme:*             # All acme functions
allow: urn:forrst:ext:*       # All extensions
deny:  urn:forrst:system:*    # Block system functions
```

### Reserved Namespaces

```
urn:forrst:*     → Forrst protocol (extensions & system)
urn:cline:*      → Cline official services
```

All other vendor namespaces are available for user registration.

### Function Naming Rules

- Use `kebab-case` for function names: `create-order`, not `createOrder`
- Use singular nouns for resources: `order`, not `orders`
- Use verb-noun pattern: `create-order`, `get-user`, `process-payment`

### Versioning

Versioning is handled at the protocol level, not in URNs. This keeps identifiers stable across versions and allows the protocol to manage version negotiation, deprecation, and compatibility.

---

## Examples at Scale

For a platform with 400 functions across 20 services:

```
# Payment processing vendor
urn:stripe:charges:fn:create
urn:stripe:charges:fn:capture
urn:stripe:charges:fn:refund
urn:stripe:customers:fn:create
urn:stripe:customers:fn:update

# Shipping vendor
urn:acme:shipments:fn:create
urn:acme:shipments:fn:track
urn:acme:rates:fn:calculate

# Internal services
urn:acme:orders:fn:create
urn:acme:orders:fn:cancel
urn:acme:inventory:fn:reserve
urn:acme:inventory:fn:release

# Forrst extensions
urn:forrst:ext:discovery:fn:describe
urn:forrst:ext:discovery:fn:capabilities
urn:forrst:ext:deprecation:fn:list-deprecated
urn:forrst:ext:diagnostics:fn:health-check

# Forrst system
urn:forrst:system:fn:ping
urn:forrst:system:fn:version
```

This provides:
- **Clear ownership** — vendor segment identifies who to contact
- **Type distinction** — `fn` vs `ext` vs `system` immediately visible
- **Service grouping** — related functions organized together
- **Extension clarity** — which extension provides what function
- **Stable identifiers** — versioning handled at protocol level
- **Permission patterns** — wildcard matching on URN prefixes
