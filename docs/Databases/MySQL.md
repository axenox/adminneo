# MySQL and MariaDB driver for AdminNeo

The `mysql` driver supports MySQL and MariaDB through MySQLi or PDO_MySQL.
Feature availability varies by server version and flavor. See the
[driver overview and contracts](../Drivers.md) for shared capabilities and
helper functions.

## Runtime statistics

Runtime statistics are available for MySQL 8.0.18 and later, and MariaDB 10.1
and later. The driver runs an instrumented copy of the query using
`EXPLAIN ANALYZE` on MySQL or `ANALYZE` on MariaDB. This executes the query again,
so the UI warns about the second execution and its overhead.

See [runtime statistics](../Architecture.md#runtime-statistics) for the shared
UI, statement restrictions and result format.
