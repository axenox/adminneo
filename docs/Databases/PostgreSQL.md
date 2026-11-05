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

## Runtime statistics

The PostgreSQL driver runs an instrumented copy of the query using
`EXPLAIN (ANALYZE, BUFFERS, TIMING OFF, FORMAT JSON)`. This executes the query
again, so the UI warns about the second execution and its overhead.
Runtime statistics are not offered for CockroachDB.

See [runtime statistics](../Architecture.md#runtime-statistics) for the shared
UI, statement restrictions and result format.