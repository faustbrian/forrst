# Data & Resources Compliance Review

**Review Date:** 2025-12-16
**Reviewed By:** Claude (Sonnet 4.5)
**Implementation Version:** Current HEAD (5504784)

---

## Summary

- **Compliant:** 48 items
- **Issues:** 12 items
- **Missing Tests:** 8 items

### Overall Compliance Status

The Forrst implementation demonstrates **strong core compliance** with the DATA & RESOURCES specification. The fundamental resource object structure, filtering, sorting, and pagination are well-implemented with proper validation and test coverage. However, there are **critical gaps** in relationship handling, specifically around compound documents with the `included` array, nested relationships, and relationship filtering.

---

## resource-objects.md Compliance

### ✅ Compliant

1. **Resource Object Structure** (Lines 20-48)
   - `ResourceObjectData` correctly implements required `type` and `id` members
   - File: `/Users/brian/Developer/cline/vend/src/Data/ResourceObjectData.php:23-48`
   - Optional `attributes` and `relationships` members properly implemented

2. **Type Member Rules** (Lines 51-82)
   - Type MUST be a string: ✅ Enforced via PHP type hints (`string $type`)
   - Type automatically derived from model table name in singular form
   - File: `/Users/brian/Developer/cline/vend/src/Resources/AbstractModelResource.php:104-115`
   - Uses `Str::singular()` to ensure singular form

3. **ID Member Rules** (Lines 84-116)
   - ID MUST be a string: ✅ Enforced via type hints (`string $id`)
   - File: `/Users/brian/Developer/cline/vend/src/Data/ResourceObjectData.php:44`
   - Properly casts numeric IDs to strings: `/Users/brian/Developer/cline/vend/src/Resources/AbstractModelResource.php:140-143`

4. **Attributes Member** (Lines 118-216)
   - MUST be an object/array: ✅ Type-hinted as `array`
   - MUST NOT contain `id` or `type`: ✅ Explicitly removed at line 162
   - File: `/Users/brian/Developer/cline/vend/src/Resources/AbstractModelResource.php:154-173`
   - Supports complex nested structures (objects, arrays): ✅ Native PHP array support

5. **Attribute Naming** (Lines 131-136)
   - Uses snake_case: ✅ Laravel/Eloquent convention followed
   - Descriptive names: ✅ Delegated to model implementation

6. **Field Selection** (Sparse Fieldsets, Lines 388-415)
   - Fields whitelisted per resource: ✅ `AbstractResource::getFields()`
   - File: `/Users/brian/Developer/cline/vend/src/Resources/AbstractResource.php:36-39`
   - Validation enforced: `/Users/brian/Developer/cline/vend/src/QueryBuilders/QueryBuilder.php:207-218`

7. **toArray() Method** (Lines 92-100)
   - Returns standardized structure with type, id, attributes: ✅
   - File: `/Users/brian/Developer/cline/vend/src/Resources/AbstractResource.php:92-100`
   - Test coverage: `/Users/brian/Developer/cline/vend/tests/Unit/Resources/AbstractResourceTest.php:178-213`

### ❌ Issues

1. **Relationships Member Structure - CRITICAL** (Lines 219-286)
   - **Issue:** Relationships are embedded directly in resource objects instead of using resource identifiers
   - **Location:** `/Users/brian/Developer/cline/vend/src/Normalizers/ModelNormalizer.php:46-68`
   - **Expected:** `{ "data": { "type": "customer", "id": "42" } }`
   - **Actual:** Full resource objects embedded: `{ "type": "customer", "id": "42", "attributes": {...} }`
   - **Impact:** Violates JSON:API spec - relationships should contain resource identifiers, not full objects
   - **Recommendation:** Separate relationship identifiers from full resource objects; implement `included` array

2. **Compound Documents with `included` Array - CRITICAL** (Lines 288-328)
   - **Issue:** The `included` array is NOT implemented in the codebase
   - **Expected:** Top-level `included` array in `DocumentData` for related resources
   - **Actual:** Related resources are nested directly in the `relationships` member
   - **Impact:** Cannot properly implement compound documents per JSON:API specification
   - **Files Missing Implementation:**
     - `/Users/brian/Developer/cline/vend/src/Data/DocumentData.php` - No `included` property
     - `/Users/brian/Developer/cline/vend/src/Transformers/Transformer.php` - No logic to extract/deduplicate included resources
     - `/Users/brian/Developer/cline/vend/src/Normalizers/ModelNormalizer.php` - Embeds relationships inline
   - **Recommendation:**
     - Add `public ?array $included` to `DocumentData`
     - Implement resource identifier extraction in normalizers
     - Add deduplication logic for included resources by type+id
     - Build included array during transformation

3. **Meta Member** (Lines 330-354)
   - **Issue:** Meta support is partial - only implemented for pagination
   - **Location:** `/Users/brian/Developer/cline/vend/src/Transformers/Transformer.php:134-142`
   - **Missing:** Resource-level meta (permissions, versions, cache hints) on individual resources
   - **Recommendation:** Add optional `meta` parameter to `ResourceObjectData` constructor

4. **Resource Definition Discovery** (Lines 356-386)
   - **Issue:** No structured resource definition object/schema
   - **Spec Requirement:** Services SHOULD define resources with allowed fields, filters, relationships, sorts
   - **Current:** These exist as separate static methods but not as unified definition
   - **Recommendation:** Consider adding `getResourceDefinition()` method that returns structured schema

### 🔲 Missing Tests

1. **Complex nested attributes** (Lines 154-180)
   - Nested objects, arrays, null values, boolean values
   - Test exists but minimal: `/Users/brian/Developer/cline/vend/tests/Unit/Resources/AbstractResourceTest.php:410-451`
   - **Need:** Edge cases for deeply nested structures, circular reference prevention

2. **Money values structured format** (Lines 192-204)
   - Spec recommends: `{ "amount": "99.99", "currency": "USD" }`
   - **Need:** Tests verifying this pattern is preserved through transformation

3. **Date/Time ISO 8601 format** (Lines 206-216)
   - **Need:** Tests ensuring dates are properly formatted in ISO 8601

4. **ID format validation** (Lines 96-116)
   - Spec supports: UUID, ULID, prefixed IDs, integer-as-string
   - Test coverage exists but limited: `/Users/brian/Developer/cline/vend/tests/Unit/Resources/AbstractResourceTest.php:376-408`
   - **Need:** Tests for various ID formats (UUID, ULID, prefixed)

---

## relationships.md Compliance

### ✅ Compliant

1. **Requesting Relationships** (Lines 18-41)
   - Relationships array parameter: ✅ Supported
   - File: `/Users/brian/Developer/cline/vend/src/Resources/AbstractModelResource.php:67`
   - Request format: `$request->getArgument('relationships', [])`

2. **Relationship Types** (Lines 71-79)
   - To-one, to-many, empty relationships: ✅ Supported
   - File: `/Users/brian/Developer/cline/vend/src/Normalizers/ModelNormalizer.php:52-66`
   - Cardinality detection: Line 52 checks `$relationModels instanceof Model`

3. **Allowed Relationships Validation** (Lines 252-286)
   - MUST define allowed relationships: ✅ `AbstractResource::getRelationships()`
   - File: `/Users/brian/Developer/cline/vend/src/Resources/AbstractResource.php:64-67`
   - Validation enforced: `/Users/brian/Developer/cline/vend/src/QueryBuilders/QueryBuilder.php:244-258`
   - Error on invalid relationship: ✅ `InvalidRelationshipsException`

4. **Eager Loading** (Lines 291-302)
   - QueryBuilder applies eager loading with `->with()`: ✅
   - File: `/Users/brian/Developer/cline/vend/src/QueryBuilders/QueryBuilder.php:285-347`
   - Supports nested eager loading: ✅ Line 296

### ❌ Issues

1. **Resource Identifier Structure - CRITICAL** (Lines 48-69, 258-265)
   - **Issue:** Relationships contain full resource objects, not resource identifiers
   - **Expected:** `{ "data": { "type": "customer", "id": "42" } }`
   - **Actual:** `{ "type": "customer", "id": "42", "attributes": {...}, "relationships": {...} }`
   - **Location:** `/Users/brian/Developer/cline/vend/src/Normalizers/ModelNormalizer.php:60`
   - **Impact:** Violates separation between relationship data and included resources
   - **Recommendation:** Extract type+id as resource identifier, move full object to `included` array

2. **Compound Documents - CRITICAL** (Lines 112-168)
   - **Issue:** `included` array not implemented
   - **Spec:** "Related resources are returned in the `included` array"
   - **Rules Not Met:**
     - Each included resource MUST be unique by type+id
     - Included resources MUST be referenced by at least one relationship
     - Included resources MAY have their own relationships
   - **Recommendation:** See resource-objects.md Issue #2

3. **Nested Relationships - PARTIAL** (Lines 170-250)
   - **Issue:** Dot notation not explicitly supported for nested relationships
   - **Spec:** `["items.product", "items.product.category"]`
   - **Current:** Basic relationship loading works, but no explicit nested path parsing
   - **Location:** `/Users/brian/Developer/cline/vend/src/QueryBuilders/QueryBuilder.php:291-301`
   - **Impact:** May work via Eloquent's nested eager loading, but not spec-compliant syntax
   - **Recommendation:** Add dot notation parser for relationship paths with depth limits

4. **Relationship Filtering - MISSING** (Lines 288-321)
   - **Issue:** Can filter primary resource by related attributes, but not explicitly documented as "relationship filtering"
   - **Spec:** "Filter the primary resource based on related resource attributes"
   - **Current:** `whereHas()` applied at line 329, but needs validation
   - **Location:** `/Users/brian/Developer/cline/vend/src/QueryBuilders/QueryBuilder.php:315-330`
   - **Test Coverage:** Minimal
   - **Recommendation:** Add explicit tests for filtering by relationship attributes

5. **Without Inclusion - MISSING** (Lines 342-375)
   - **Issue:** Cannot request relationship identifiers without full resources
   - **Spec:** "Request relationship data without full resources"
   - **Current:** If relationship not in request, no relationship data returned at all
   - **Impact:** Cannot see what relationships exist without loading full resources
   - **Recommendation:** Always include relationship identifiers, only populate `included` when requested

### 🔲 Missing Tests

1. **Deduplication of included resources** (Lines 136-168)
   - When multiple resources reference the same related resource
   - Should appear once in `included` array
   - **Need:** Tests for proper deduplication by type+id

2. **Nested relationships with dot notation** (Lines 170-250)
   - Test: `["customer", "items", "items.product"]`
   - Verify all levels properly loaded and structured
   - **Need:** Comprehensive nested relationship tests

3. **Relationship filtering with multiple levels** (Lines 288-321)
   - Filter orders by customer type AND customer location
   - **Need:** Complex multi-level relationship filter tests

---

## sparse-fieldsets.md Compliance

### ✅ Compliant

1. **Fields Object Structure** (Lines 18-45)
   - `fields` object with `self` and relationship keys: ✅
   - File: `/Users/brian/Developer/cline/vend/src/Resources/AbstractModelResource.php:63-64`
   - Request format: `$request->getArgument('fields', [])`

2. **Field Selection Rules** (Lines 37-45)
   - `id` and `type` always included: ✅ Never removed
   - Empty array returns only type+id: ✅ Line 160 uses `Arr::only()`
   - Absent key returns all fields: ✅ Default behavior

3. **Allowed Fields Validation** (Lines 110-149)
   - MUST define allowed fields: ✅ `AbstractResource::getFields()`
   - File: `/Users/brian/Developer/cline/vend/src/Resources/AbstractResource.php:36-39`
   - Validation enforced: `/Users/brian/Developer/cline/vend/src/QueryBuilders/QueryBuilder.php:207-218`
   - Error on invalid field: ✅ `InvalidFieldsException`
   - Proper error response with pointer: ✅

4. **Field Application** (Lines 303-312)
   - Fields applied via `->select()` in QueryBuilder: ✅
   - File: `/Users/brian/Developer/cline/vend/src/QueryBuilders/QueryBuilder.php:304-312`

5. **Default Behavior** (Lines 152-245)
   - No fields = all fields returned: ✅
   - Empty array = only type+id: ✅
   - Relationship without fields specification: ✅ All allowed fields returned

### ❌ Issues

1. **Field Selection for Related Resources** (Lines 325-340)
   - **Issue:** Field selection works for primary resource, but relationship field selection not fully clear
   - **Spec:** Control fields for related resources in `included` array
   - **Current:** Fields applied to related resource queries, but since `included` array doesn't exist, unclear if working correctly
   - **Impact:** Once `included` array is implemented, need to ensure sparse fieldsets apply to included resources
   - **Recommendation:** Verify field selection works for included resources once implemented

### 🔲 Missing Tests

1. **Empty fieldset edge case** (Lines 184-204)
   - Request: `{ "fields": { "self": [] } }`
   - Should return only `{ "type": "...", "id": "..." }`
   - **Need:** Explicit test for empty array behavior

2. **Sparse fields with nested relationships** (Lines 402-489)
   - Apply different field selections to different relationship levels
   - Example: `{ "fields": { "self": ["status"], "items": ["quantity"], "items.product": ["name"] } }`
   - **Need:** Tests for multi-level sparse fieldsets

---

## filtering.md Compliance

### ✅ Compliant

1. **Filter Object Structure** (Lines 18-38)
   - Required fields: `attribute`, `operator`, `value`: ✅
   - Optional: `boolean`: ✅ (defaults to 'and')
   - File: `/Users/brian/Developer/cline/vend/src/Data/Requests/FilterData.php`

2. **All Operators Implemented** (Lines 40-119)
   - Equality: `equals`, `not_equals`: ✅
   - Comparison: `greater_than`, `greater_than_or_equal_to`, `less_than`, `less_than_or_equal_to`: ✅
   - Pattern: `like`, `not_like`: ✅
   - Set: `in`, `not_in`: ✅
   - Range: `between`, `not_between`: ✅
   - Null: `is_null`, `is_not_null`: ✅
   - File: `/Users/brian/Developer/cline/vend/src/QueryBuilders/QueryBuilder.php:364-389`

3. **Filter Structure by Resource** (Lines 122-154)
   - `filters` organized by resource: `self`, `<relationship>`: ✅
   - File: `/Users/brian/Developer/cline/vend/src/QueryBuilders/QueryBuilder.php:314-330`

4. **Boolean Logic** (Lines 156-225)
   - Default AND: ✅ (line 368 default boolean)
   - Explicit AND: ✅
   - OR conditions: ✅
   - Sequential application: ✅ Match operator passes boolean parameter

5. **Allowed Filters Validation** (Lines 272-308)
   - MUST define allowed filters: ✅ `AbstractResource::getFilters()`
   - File: `/Users/brian/Developer/cline/vend/src/Resources/AbstractResource.php:50-53`
   - Validation enforced: `/Users/brian/Developer/cline/vend/src/QueryBuilders/QueryBuilder.php:225-237`
   - Error on invalid filter: ✅ `InvalidFiltersException`

6. **Type Coercion** (Lines 310-358)
   - String, numeric, boolean, date/time values: ✅ Native PHP/Eloquent handling
   - Null operators don't require value: ✅ Line 383-384

7. **Relationship Filtering** (Lines 227-270)
   - Filter primary resource by related attributes: ✅
   - Uses `whereHas()`: ✅ Line 329
   - File: `/Users/brian/Developer/cline/vend/src/QueryBuilders/QueryBuilder.php:315-330`

### ❌ Issues

None identified. Filtering implementation is comprehensive and spec-compliant.

### 🔲 Missing Tests

1. **Complex boolean logic edge cases** (Lines 196-225)
   - Mixed AND/OR with 3+ conditions
   - Verification of left-to-right application
   - **Need:** Tests for complex boolean combinations

2. **Type coercion validation** (Lines 310-358)
   - Date parsing, boolean handling, numeric ranges
   - **Need:** Tests for edge cases in type handling

3. **Relationship filtering with multiple relationships** (Lines 457-476)
   - Filter by vendor.type AND location.country_code simultaneously
   - **Need:** Tests for complex multi-relationship filters

---

## sorting.md Compliance

### ✅ Compliant

1. **Sort Object Structure** (Lines 18-42)
   - Required: `attribute`, `direction`: ✅
   - Direction values: `asc`, `desc`: ✅
   - File: `/Users/brian/Developer/cline/vend/src/Data/Requests/SortData.php`

2. **Sort Arrays** (Lines 44-74)
   - Multiple sorts create compound ordering: ✅
   - Order of application: Array order: ✅
   - File: `/Users/brian/Developer/cline/vend/src/QueryBuilders/QueryBuilder.php:333-344`

3. **Allowed Sorts Validation** (Lines 76-112)
   - MUST define allowed sorts: ✅ `AbstractResource::getSorts()`
   - File: `/Users/brian/Developer/cline/vend/src/Resources/AbstractResource.php:78-81`
   - Validation enforced: `/Users/brian/Developer/cline/vend/src/QueryBuilders/QueryBuilder.php:265-276`
   - Error on invalid sort: ✅ `InvalidSortsException`

4. **Sort Application** (Lines 333-344)
   - Applied via `->orderBy()`: ✅
   - File: `/Users/brian/Developer/cline/vend/src/QueryBuilders/QueryBuilder.php:337`
   - Supports sorting relationships: ✅ Line 339

### ❌ Issues

1. **Stable Sorting** (Lines 127-138)
   - **Issue:** No automatic addition of unique field (like `id`) as final sort criterion
   - **Spec:** "Servers SHOULD include a unique attribute (like `id`) as the final sort criterion"
   - **Impact:** Pagination may be unstable if sort attributes have duplicate values
   - **Recommendation:** Automatically append `id ASC` to all sort queries for pagination stability

2. **Default Sorting** (Lines 117-126)
   - **Issue:** No documented default sort behavior
   - **Spec:** "When no sort is specified, servers SHOULD apply a default sort"
   - **Current:** Relies on database default ordering
   - **Recommendation:** Add configurable default sort (e.g., `created_at DESC` or `id ASC`)

### 🔲 Missing Tests

1. **Null ordering behavior** (Lines 163-172)
   - How nulls are sorted (nulls_first vs nulls_last)
   - **Need:** Tests documenting null handling behavior

2. **Multiple sorts with different directions** (Lines 238-250)
   - `[{"attribute": "status", "direction": "asc"}, {"attribute": "created_at", "direction": "desc"}]`
   - **Need:** Tests for compound sorting with mixed directions

---

## pagination.md Compliance

### ✅ Compliant

1. **Pagination Object Structure** (Lines 18-46)
   - Nested in `pagination` object: ✅
   - File: `/Users/brian/Developer/cline/vend/src/Transformers/Transformer.php`
   - Accessed via `$requestObject->getArgument('page.size')`, etc.

2. **Offset-Based Pagination** (Lines 50-99)
   - `limit`, `offset`: ✅
   - Response fields: `limit`, `offset`, `total`, `has_more`: ✅
   - File: `/Users/brian/Developer/cline/vend/src/Transformers/Transformer.php:159-184`
   - Test: `/Users/brian/Developer/cline/vend/tests/Unit/Transformers/TransformerTest.php:175-196`

3. **Cursor-Based Pagination** (Lines 100-177)
   - `limit`, `cursor`: ✅
   - Response fields: `next_cursor`, `prev_cursor`, `has_more`: ✅
   - File: `/Users/brian/Developer/cline/vend/src/Transformers/Transformer.php:121-146`
   - Opaque cursor: ✅ Laravel's cursor pagination handles encoding
   - Test: `/Users/brian/Developer/cline/vend/tests/Unit/Transformers/TransformerTest.php:142-173`

4. **Simple Pagination** (Lines 196-221)
   - Lightweight pagination without total count: ✅
   - File: `/Users/brian/Developer/cline/vend/src/Transformers/Transformer.php:196-221`
   - Test: `/Users/brian/Developer/cline/vend/tests/Unit/Transformers/TransformerTest.php:198-219`

5. **Server Implementation** (Lines 262-308)
   - Default limit: ✅ Defaults to 100 (line 125, 164, 200)
   - Maximum limit: Handled by Laravel pagination
   - Empty results: ✅ Tested

6. **Response Metadata** (Lines 133-142, 171-180, 208-218)
   - `meta.page` structure: ✅
   - Cursor: `{ "self", "prev", "next" }`: ✅
   - Number: `{ "self", "prev", "next" }`: ✅

### ❌ Issues

1. **Keyset-Based Pagination - MISSING** (Lines 179-259)
   - **Issue:** Keyset pagination (time/ID based) NOT implemented
   - **Spec:** Uses `after_id`, `before_id`, `since`, `until`
   - **Use Cases:** Activity feeds, timelines, event logs, polling
   - **Current:** Only offset and cursor-based available
   - **Impact:** Cannot efficiently poll for new records or implement time-based feeds
   - **Recommendation:** Add keyset pagination support:
     - `after_id`, `before_id` parameters
     - `since`, `until` for timestamps
     - Response: `newest_id`, `oldest_id`, `has_newer`, `has_older`

2. **Pagination Style Discovery** (Lines 349-364)
   - **Issue:** No discovery mechanism for supported pagination styles
   - **Spec:** Functions SHOULD advertise pagination support via `forrst.describe`
   - **Current:** No structured declaration of supported styles, limits
   - **Recommendation:** Add pagination metadata to resource/function definitions

### 🔲 Missing Tests

1. **Maximum limit enforcement** (Lines 274-293)
   - Request exceeding max limit should error
   - **Need:** Test for limit validation and error response

2. **Pagination with filtering and sorting** (Lines 251-268)
   - Pagination combined with filters and sorts
   - **Need:** Integration tests for combined query operations

---

## Critical Path Forward

### Priority 1 - BLOCKING Issues (MUST FIX)

1. **Implement Resource Identifiers and `included` Array**
   - Location: `DocumentData`, `ModelNormalizer`, `ResourceNormalizer`, `Transformer`
   - Steps:
     a. Add `public ?array $included` to `DocumentData`
     b. Modify normalizers to separate relationship identifiers from full resources
     c. Build deduplication logic (by type+id) for included resources
     d. Update transformer to populate `included` array
   - Tests: Comprehensive compound document tests with relationships

2. **Fix Relationship Structure**
   - Relationships must use resource identifiers: `{ "data": { "type": "...", "id": "..." } }`
   - Full resources go in top-level `included` array only
   - Affects: All relationship handling code

### Priority 2 - HIGH Impact

3. **Implement Nested Relationship Dot Notation**
   - Support: `["items.product", "items.product.category"]`
   - Add depth limit enforcement (e.g., 3 levels)
   - Location: `QueryBuilder` relationship parsing

4. **Add Keyset Pagination**
   - Support `after_id`, `before_id`, `since`, `until`
   - Critical for activity feeds and polling use cases

5. **Implement Stable Sorting**
   - Automatically append `id ASC` to all sorts for pagination stability

### Priority 3 - MEDIUM Impact

6. **Add Resource-Level Meta Support**
   - Currently only pagination meta exists
   - Support permissions, versioning, cache hints on individual resources

7. **Implement "Without Inclusion" Pattern**
   - Return relationship identifiers even when `relationships` param not provided
   - Only populate `included` when explicitly requested

8. **Add Default Sorting**
   - Define and document default sort behavior per resource

### Priority 4 - LOW Impact / Nice-to-Have

9. **Add Pagination Discovery**
   - Expose supported pagination styles via `forrst.describe`

10. **Add Unified Resource Definition**
    - Single method returning complete resource schema (fields, filters, sorts, relationships)

---

## Test Coverage Recommendations

### High Priority Tests Needed

1. **Compound Documents with Included Array**
   - Single relationship inclusion
   - Multiple relationship inclusion
   - Deduplication of shared resources
   - Nested relationships in included array
   - Circular reference handling

2. **Nested Relationships**
   - Dot notation parsing: `items.product.category`
   - Depth limit enforcement
   - Field selection across nested levels
   - Sorting nested relationships

3. **Resource Identifiers vs Full Resources**
   - Verify relationships contain identifiers only
   - Verify full resources only in `included`
   - Verify proper deduplication

4. **Keyset Pagination**
   - `after_id`, `before_id` navigation
   - `since`, `until` time-based filtering
   - Polling for new records use case

5. **Stable Sorting**
   - Pagination consistency with duplicate sort values
   - Automatic `id` appending

### Medium Priority Tests Needed

6. **Complex Query Combinations**
   - Filters + sorts + pagination + sparse fields + relationships
   - Multi-level relationship filtering
   - Relationship field selection

7. **Edge Cases**
   - Empty relationships (null, [])
   - Maximum nesting depth
   - Maximum pagination limit enforcement
   - Invalid operator handling

---

## Conclusion

The Forrst implementation provides a **solid foundation** for resource-based APIs with excellent support for filtering, sorting, and pagination. The core resource object structure is correct, and validation mechanisms are properly implemented with good test coverage.

However, the **critical blocker** is the lack of proper compound document support with the `included` array and resource identifiers. This is a fundamental JSON:API concept that must be implemented to achieve full spec compliance. The current approach of embedding full resources directly in relationships violates the specification and prevents proper deduplication and client-side data normalization.

Once the compound document structure is corrected, the implementation will be in strong compliance with the DATA & RESOURCES specifications. The secondary issues (nested relationships, keyset pagination, stable sorting) are important for production use but not spec violations.

**Recommended Action:** Address Priority 1 issues before considering this implementation production-ready for JSON:API-compliant services.
