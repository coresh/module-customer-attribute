## The **Coresh_CustomerAttribute** module adds a customer `uuid` attribute to Magento 2.

It automatically:

- assigns unique UUIDs to existing customers;
- assigns UUIDs to new customers;
- prevents manual UUID changes;
- displays UUIDs in the Admin customer grid;
- exposes UUIDs through GraphQL only for authenticated customers.

Its main purpose is to give each customer a stable, unique, secure external identifier without exposing the internal `customer_id`.

## Installation

Install the module via Composer:

```bash
composer require coresh/module-customer-attribute
```

Enable the module and apply Magento setup changes:

```bash
bin/magento module:enable Coresh_CustomerAttribute
bin/magento setup:upgrade
bin/magento cache:flush
```

For production mode, also run:

```bash
bin/magento setup:di:compile
bin/magento setup:static-content:deploy -f
```

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
