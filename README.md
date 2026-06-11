# Coresh_CustomerAttribute Documentation

## Overview

The **Coresh_CustomerAttribute** module adds a stable customer `uuid` attribute to Magento 2 / Adobe Commerce.

The module automatically assigns UUIDs to existing and new customers, enforces UUID uniqueness, displays UUIDs in the Admin customer grid, prevents manual UUID changes, and exposes UUID through GraphQL only for authenticated customers.

## Compatibility

- Magento Open Source 2.4.7+
- Adobe Commerce 2.4.7+
- Magento 2.4.8 / 2.4.9 compatible
- PHP 8.2+
- Composer-installable Magento 2 module

## Installation

### 1. Install the module

Using Composer:

```bash
composer require coresh/module-customer-attribute
```

If installing manually, place the module in:

```text
app/code/Coresh/CustomerAttribute
```

### 2. Enable the module

```bash
bin/magento module:enable Coresh_CustomerAttribute
```

### 3. Run Magento setup

```bash
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento cache:flush
bin/magento indexer:reset customer_grid
bin/magento indexer:reindex customer_grid
```

### 4. Verify module status

```bash
bin/magento module:status Coresh_CustomerAttribute
```

Expected result:

```text
Coresh_CustomerAttribute
```

## Database Verification

### Verify the UUID column on `customer_entity`

```sql
SHOW COLUMNS FROM customer_entity LIKE 'uuid';
```

Expected result:

```text
uuid | varchar(36)
```

### Verify UUID values for customers

```sql
SELECT entity_id, email, uuid
FROM customer_entity
ORDER BY entity_id DESC
LIMIT 10;
```

Expected result: each customer should have a UUID value.

### Verify UUID uniqueness

```sql
SELECT uuid, COUNT(*) AS total
FROM customer_entity
WHERE uuid IS NOT NULL AND uuid <> ''
GROUP BY uuid
HAVING total > 1;
```

Expected result:

```text
Empty set
```

### Verify UUID in the customer grid index

```sql
SHOW COLUMNS FROM customer_grid_flat LIKE 'uuid';
```

Expected result:

```text
uuid | varchar(255)
```

Then verify values:

```sql
SELECT entity_id, email, uuid
FROM customer_grid_flat
ORDER BY entity_id DESC
LIMIT 10;
```

## Admin Verification

Open Magento Admin:

```text
Customers -> All Customers
```

Verify:

```text
- The UUID column is visible in the customer grid.
- UUID values are displayed.
- UUID is not editable in the customer edit form.
- Customer grid reindex completes successfully.
```

If the UUID column is not visible immediately, run:

```bash
bin/magento cache:flush
bin/magento indexer:reset customer_grid
bin/magento indexer:reindex customer_grid
```

## GraphQL API Access

The module extends the existing GraphQL `Customer` object with the `uuid` field.

UUID is available only through authenticated customer GraphQL requests.

### GraphQL query

```graphql
query {
  customer {
    email
    uuid
  }
}
```

## GraphQL Testing with curl

Set test variables:

```bash
BASE_URL="https://example.com"
EMAIL="customer@example.com"
PASSWORD="Pa5Sw0rd123!"
```

### 1. Verify guest access is rejected

```bash
curl -s -X POST "$BASE_URL/graphql" \
  -H "Content-Type: application/json" \
  --data-binary '{
    "query": "query { customer { email uuid } }"
  }' | jq
```

Expected result: the request should be rejected with an authorization error.

The response must not expose `uuid`.

### 2. Generate customer token

```bash
TOKEN=$(curl -s -X POST "$BASE_URL/graphql" \
  -H "Content-Type: application/json" \
  --data-binary "{
    \"query\": \"mutation { generateCustomerToken(email: \\\"$EMAIL\\\", password: \\\"$PASSWORD\\\") { token } }\"
  }" | jq -r '.data.generateCustomerToken.token')

echo "$TOKEN"
```

Expected result: a customer bearer token is returned.

### 3. Verify authenticated UUID access

```bash
curl -s -X POST "$BASE_URL/graphql" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  --data-binary '{
    "query": "query { customer { email uuid } }"
  }' | jq
```

Expected result:

```json
{
  "data": {
    "customer": {
      "email": "customer@example.com",
      "uuid": "550e8400-e29b-41d4-a716-446655440000"
    }
  }
}
```

### 4. Verify UUID format

```bash
curl -s -X POST "$BASE_URL/graphql" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  --data-binary '{
    "query": "query { customer { uuid } }"
  }' | jq -r '.data.customer.uuid'
```

Expected format:

```text
xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx
```

Example:

```text
550e8400-e29b-41d4-a716-446655440000
```

## Testing Procedures

### 1. PHP syntax check

```bash
find vendor/coresh/module-customer-attribute -name "*.php" -print0 \
  | xargs -0 -n1 php -l
```

Expected result:

```text
No syntax errors detected
```

### 2. Composer validation

```bash
composer validate vendor/coresh/module-customer-attribute/composer.json --strict
```

### 3. Magento dependency injection compilation

```bash
bin/magento setup:di:compile
```

Expected result: compilation completes without errors.

### 4. Magento setup and index verification

```bash
bin/magento setup:upgrade
bin/magento cache:flush
bin/magento indexer:reset customer_grid
bin/magento indexer:reindex customer_grid
```

Expected result: setup and customer grid reindex complete successfully.

### 5. Unit tests

```bash
vendor/bin/phpunit vendor/coresh/module-customer-attribute/Test/Unit --display-all-issues
```

Expected result:

```text
OK (4 tests, 13 assertions)
```

### 6. Magento Coding Standard

If Magento Coding Standard is installed:

```bash
vendor/bin/phpcs vendor/coresh/module-customer-attribute --standard=Magento2
```

If the Magento test ruleset is available:

```bash
vendor/bin/phpcs vendor/coresh/module-customer-attribute \
  --standard=dev/tests/static/testsuite/Magento/Test/Php/_files/phpcs/ruleset.xml
```

### 7. Integration tests

Integration tests require a configured Magento integration testing environment.

Check that the integration test configuration exists:

```bash
ls -la dev/tests/integration/etc/install-config-mysql.php
```

Run integration tests:

```bash
vendor/bin/phpunit \
  -c dev/tests/integration/phpunit.xml.dist \
  vendor/coresh/module-customer-attribute/Test/Integration
```

## Functional Acceptance Checklist

```text
[OK] Module is installed and enabled.
[OK] setup:upgrade completes successfully.
[OK] setup:di:compile completes successfully.
[OK] customer_entity.uuid exists.
[OK] customer_entity.uuid has a unique index.
[OK] Existing customers have UUID values.
[OK] New customers receive UUID values automatically.
[OK] Existing UUIDs are not overwritten.
[OK] Duplicate UUIDs do not exist.
[OK] customer_grid_flat.uuid exists after customer_grid reindex.
[OK] UUID is visible in the Admin customer grid.
[OK] UUID is not editable in the Admin customer form.
[OK] Guest GraphQL request does not expose UUID.
[OK] Authenticated GraphQL customer query returns UUID.
[OK] Unit tests pass.
[OK] PHP syntax check passes.
[OK] Magento Coding Standard is checked.
```

## Rollback Notes

To disable the module:

```bash
bin/magento module:disable Coresh_CustomerAttribute
bin/magento setup:upgrade
bin/magento cache:flush
```

The module adds persistent database schema changes, including the `customer_entity.uuid` column. Removing database columns should be handled carefully and only after confirming that no external integrations depend on UUID values.

Recommended safe rollback approach:

```text
1. Disable the module.
2. Keep existing UUID data unless permanent removal is required.
3. Take a database backup before removing schema changes.
4. Remove schema only through a controlled deployment or migration process.
```

# Architecture

It automatically:

- assigns unique UUIDs to existing customers;
- assigns UUIDs to new customers;
- prevents manual UUID changes;
- displays UUIDs in the Admin customer grid;
- exposes UUIDs through GraphQL only for authenticated customers.

Its main purpose is to give each customer a stable, unique, secure external identifier without exposing the internal `customer_id`.

## Installation

During `bin/magento setup:upgrade`, the module creates the customer UUID attribute metadata, updates the customer grid configuration, and assigns UUIDs to existing customer records.

## Module Overview

The architecture uses both **Data Patches** and a **Plugin** because they solve two separate Magento lifecycle problems.

A **Data Patch** handles installation and upgrade-time work. It is the right place to create the customer attribute metadata, configure the attribute for the Admin customer grid, and backfill UUIDs for existing customer records during `bin/magento setup:upgrade`.

A **Plugin** handles runtime behavior. It is responsible for assigning a UUID when a new customer is saved and protecting an existing UUID from being changed through Admin, REST API, GraphQL, imports, or custom code.

These two mechanisms do not replace each other.

```text
Patch  = installation / upgrade-time setup
Plugin = runtime customer save protection
```

## Why Data Patches Are Used

The assessment requires UUIDs to be assigned to existing customers when the module is installed. That is installation-time data work, so a Data Patch is the correct Magento mechanism.

`db_schema.xml` can create the physical database column and unique index:

```text
customer_entity.uuid
UNIQUE INDEX(uuid)
```

However, `db_schema.xml` cannot perform business/data operations such as:

```text
- creating customer attribute metadata;
- enabling customer grid visibility;
- assigning UUIDs to existing customers;
- preserving existing UUIDs;
- processing existing customers in batches.
```

That is why the module uses:

```text
db_schema.xml = database structure
Data Patch    = installation-time data and metadata setup
```

Using old `InstallData`, `UpgradeData`, `InstallSchema`, or `UpgradeSchema` scripts would be less suitable for a Magento 2.4.7+ implementation. Declarative schema and patches are the modern, production-ready approach.

## Why a Plugin Is Used

The Plugin is used because UUID assignment and UUID immutability must be enforced every time a customer is saved.

Customer records can be created or updated through multiple entry points:

```text
- Admin customer form;
- storefront registration;
- REST API;
- GraphQL-related flows;
- imports;
- custom modules;
- programmatic CustomerRepository saves.
```

The Plugin enforces the rule at the save layer:

```text
If the customer is new:
    generate a UUID

If the customer already exists and already has a UUID:
    preserve the existing UUID

If the customer exists but the UUID is missing:
    generate a UUID
```

This is stronger than relying only on the Admin UI.

Hiding or disabling the UUID field in Admin is not enough because the value could still be changed through direct POST requests, API calls, imports, or custom code. The save layer must protect the UUID.

## Can the Module Be Implemented Without Data Patches?

Technically, yes, but it would be weaker for this assessment.

One alternative is a CLI command:

```bash
bin/magento customer:uuid:backfill
```

That can be useful for very large production databases because it gives more control and can show progress.

However, by itself, a CLI command does not satisfy the requirement that existing customers receive UUIDs during module installation. Someone may forget to run it, and customers may temporarily have empty UUID values.

A stronger enterprise version could include both:

```text
Data Patch  = automatic installation-time backfill
CLI command = optional controlled re-run/backfill tool
```

A cron-based or lazy backfill would be even weaker because UUIDs would not be available immediately after installation.

## Can the Module Be Implemented Without a Plugin?

Yes, but another reliable runtime mechanism would still be required.

Possible alternatives include:

```text
- observer on customer_save_before;
- customer attribute backend model;
- custom service contract used by all customer creation flows.
```

An observer can work, but it is less explicit than a CustomerRepository plugin and can become hidden business logic if not designed carefully.

A backend model can also work, but in this architecture the UUID is stored as a static column on `customer_entity`, not as a normal EAV varchar value. Immutability and collision retry handling are clearer in a dedicated service plus plugin.

A DI preference would be a poor choice because it is more invasive, creates a higher conflict risk with other modules, and is harder to maintain during Magento upgrades.

A database trigger would also be a poor Magento solution because it hides business logic outside the application layer, is harder to test, and is less suitable for Adobe Commerce Cloud-style deployments.

## Practical Conclusion

For this assessment, the best implementation is:

```text
Use Data Patch: yes
Use Plugin: yes
```

The Data Patch prepares the system during installation:

```text
- creates customer attribute metadata;
- configures grid visibility;
- backfills existing customers;
- keeps backfill idempotent.
```

The Plugin protects runtime behavior:

```text
- assigns UUIDs to new customers;
- preserves existing UUIDs;
- prevents Admin/API/GraphQL/custom code from overwriting UUIDs.
```

This separation is important:

```text
installation lifecycle ≠ runtime lifecycle
```

The most production-ready architecture is therefore:

```text
Declarative schema
+ Data Patches
+ Customer save Plugin
+ dedicated UUID services
+ GraphQL resolver
+ Admin grid configuration
```

This design satisfies the assessment requirements while keeping the module upgrade-safe, testable, and aligned with Magento extension architecture.

## Check metadata:

```
SELECT ea.attribute_id,
              ea.attribute_code,
              ea.backend_type,
              ea.is_unique,
              cea.is_visible,
              cea.is_used_in_grid,
              cea.is_visible_in_grid,
              cea.is_filterable_in_grid,
              cea.is_searchable_in_grid
       FROM eav_attribute ea
       JOIN customer_eav_attribute cea
         ON cea.attribute_id = ea.attribute_id
       WHERE ea.entity_type_id = (
           SELECT entity_type_id
           FROM eav_entity_type
           WHERE entity_type_code = 'customer'
       )
       AND ea.attribute_code = 'uuid';
+--------------+----------------+--------------+-----------+------------+-----------------+--------------------+-----------------------+-----------------------+
| attribute_id | attribute_code | backend_type | is_unique | is_visible | is_used_in_grid | is_visible_in_grid | is_filterable_in_grid | is_searchable_in_grid |
+--------------+----------------+--------------+-----------+------------+-----------------+--------------------+-----------------------+-----------------------+
|          831 | uuid           | static       |         1 |          1 |               1 |                  1 |                     1 |                     1 |
+--------------+----------------+--------------+-----------+------------+-----------------+--------------------+-----------------------+-----------------------+
1 row in set (0.000 sec)

```

## Check source of truth:

```
SELECT entity_id, email, uuid
     FROM customer_entity
     ORDER BY entity_id DESC
     LIMIT 10;
+-----------+---------------------------+--------------------------------------+
| entity_id | email                     | uuid                                 |
+-----------+---------------------------+--------------------------------------+
|       491 | ************************* | 3ce1458a-c109-4e64-b4ea-f3881fd2332e |
|       490 | ************************* | 34a12bfb-a61f-410c-9d59-9be725cd739f |
|       487 | ************************* | e30a6707-1fa5-4519-8ae3-e98dbe9555af |
+-----------+---------------------------+--------------------------------------+

```

## Check Grid:

```
 SELECT entity_id, email, uuid
       FROM customer_grid_flat
       ORDER BY entity_id DESC
       LIMIT 10;
+-----------+---------------------------+--------------------------------------+
| entity_id | email                     | uuid                                 |
+-----------+---------------------------+--------------------------------------+
|       491 | ************************* | 3ce1458a-c109-4e64-b4ea-f3881fd2332e |
|       490 | ************************* | 34a12bfb-a61f-410c-9d59-9be725cd739f |
|       487 | ************************* | e30a6707-1fa5-4519-8ae3-e98dbe9555af |
+-----------+---------------------------+--------------------------------------+

```

### GraphQL query Guest request:

```graphql
BASE_URL="https://example.com"
EMAIL="test@example.com"
PASSWORD="************************"

curl -s -X POST "$BASE_URL/graphql" \
  -H "Content-Type: application/json" \
  --data-binary '{
    "query": "query { customer { email uuid } }"
  }' | jq

```

### GraphQL query Guest request result:

```
{
  "errors": [
    {
      "message": "The current customer isn't authorized.",
      "locations": [
        {
          "line": 1,
          "column": 9
        }
      ],
      "path": [
        "customer"
      ],
      "extensions": {
        "category": "graphql-authorization"
      }
    }
  ],
  "data": {
    "customer": null
  }
}

```

### Get Customer Token:

```bash
TOKEN=$(curl -s -X POST "$BASE_URL/graphql" \
  -H "Content-Type: application/json" \
  --data-binary "{
    \"query\": \"mutation { generateCustomerToken(email: \\\"$EMAIL\\\", password: \\\"$PASSWORD\\\") { token } }\"
  }" | jq -r '.data.generateCustomerToken.token')

echo "$TOKEN"

```

### Authenticated request:

```
curl -s -X POST "$BASE_URL/graphql" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  --data-binary '{
    "query": "query { customer { email uuid } }"
  }' | jq
```

### Authenticated request result:

```
{
  "data": {
    "customer": {
      "email": "test@example.com",
      "uuid": "e30a6707-1fa5-4519-8ae3-e98dbe9555af"
    }
  }
}

```

### Check UUID format request:

```
curl -s -X POST "$BASE_URL/graphql" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  --data-binary '{
    "query": "query { customer { uuid } }"
  }' | jq -r '.data.customer.uuid'
```

### Check UUID format request result:

```
e30a6707-1fa5-4519-8ae3-e98fbe95f5af

```
