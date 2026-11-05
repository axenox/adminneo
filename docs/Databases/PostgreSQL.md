# PostgreSQL driver for AdminNeo

The `pgsql` driver supports PostgreSQL and CockroachDB through the native PgSQL
extension or PDO_PgSQL. Feature availability varies by server version and flavor.
See the [driver overview and contracts](../Drivers.md) for shared capabilities
and helper functions.

## SQL identifiers and schemas

The driver remembers the schema selected by a successful `set_schema()` call on
the primary connection. Its `table()` helper quotes the schema and object name
separately, without querying the database for every name. Before a schema is
selected it returns an unqualified name. Switching a secondary connection does
not change primary-connection qualification. Request parameters stay unchanged
so multi-schema exports keep their original selection scope.

Use `table()` for object references and `idf_escape()` for bare identifiers.
In particular, PostgreSQL's `RENAME TO` target must remain unqualified even
when the existing object's name includes a schema.

This qualifies generated object references, not SQL text inside view definitions
or user-entered commands. PostgreSQL still resolves those through `search_path`.

## User-defined types

The type list includes enums, composite types, domains and base types, plus range
types on PostgreSQL 9.2 and later. Automatically generated array types and table
row types are excluded. Extension-owned types are excluded by default. CockroachDB
uses the enum-only listing path; do not assume PostgreSQL's full type support there.

The type editor reads SQL definitions for enums, composites, domains and ranges.
Domains use `CREATE DOMAIN` and `ALTER DOMAIN` rather than `CREATE TYPE` and
`ALTER TYPE`. Definitions of base types cannot currently be reconstructed.
SQL exports include reconstructable user-defined type definitions, not only enums.

Renaming a type and adding enum values while retaining the existing values in
their original order do not recreate it. Enum additions use `ALTER TYPE ... ADD
VALUE`, with `BEFORE` when needed. These statements run outside an explicit
transaction for compatibility with PostgreSQL versions before 12.

Other definition changes use a transactional drop-and-create operation because
PostgreSQL has no general `CREATE OR REPLACE TYPE`. Existing dependencies can
prevent dropping a type; the editor does not silently remove them with `CASCADE`.

## Transactional object changes

The driver advertises `transaction_ddl`. View, routine and type replacements
handled by the shared `drop_create()` helper run in a transaction instead of
validating a temporary object first. Failed replacements roll back rather than
leaving the original object dropped. See the [transaction contract](../Drivers.md#transactions-and-ddl).

## Runtime statistics

The PostgreSQL driver runs an instrumented copy of the query using
`EXPLAIN (ANALYZE, BUFFERS, TIMING OFF, FORMAT JSON)`. This executes the query
again, so the UI warns about the second execution and its overhead.
Runtime statistics are not offered for CockroachDB.

See [runtime statistics](../Architecture.md#runtime-statistics) for the shared
UI, statement restrictions and result format.