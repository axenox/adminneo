# MySQL and MariaDB driver for AdminNeo

The `mysql` driver supports MySQL and MariaDB through MySQLi or PDO_MySQL.
Feature availability varies by server version and flavor. See the
[driver overview and contracts](../Drivers.md) for shared capabilities and
helper functions.

## SQL command results and warnings

The SQL command page consumes multiple results with both MySQLi and PDO_MySQL and
reports errors from later statements in a batch. With MySQLi, while further results
are pending it displays the warning count instead of querying `SHOW WARNINGS`, which
cannot run until those results have been consumed. Full warning details are shown
when they can be retrieved without interrupting the batch.

## Searching bit columns

All-columns searches validate values before comparing them with bit columns.
Ordinary comparisons use binary digits; incompatible text values are skipped for
these columns rather than failing the entire search. Operators with their own
handling, such as `IN`, null checks and SQL conditions, follow their existing paths.

## Runtime statistics

Runtime statistics are available for MySQL 8.0.18 and later, and MariaDB 10.1
and later. The driver runs an instrumented copy of the query using
`EXPLAIN ANALYZE` on MySQL or `ANALYZE` on MariaDB. This executes the query again,
so the UI warns about the second execution and its overhead.

See [runtime statistics](../Architecture.md#runtime-statistics) for the shared
UI, statement restrictions and result format.
