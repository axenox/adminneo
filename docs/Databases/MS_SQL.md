# Microsoft SQL Server driver for AdminNeo

The `mssql` driver supports SQLSRV, PDO_SQLSRV and PDO_DBLIB connections.
See the [driver overview and contracts](../Drivers.md) for shared capabilities
and helper functions.

## Native connection options

Servers using the native SQLSRV extension can set `connectionOptions` in their
`config` array. These values are passed to `sqlsrv_connect()` before AdminNeo
adds the selected database, UTF-8 character set, non-empty credentials, and
first-class `sslEncrypt` or `sslTrustServerCertificate` settings. This supports
authentication modes such as `ActiveDirectoryMsi` without changing standalone
defaults.

## Column types

Column metadata uses upstream's base type and separate length, for example
`type = nvarchar` and `length = max`. The fork retains explicit `varchar(max)`,
`nvarchar(max)`, and `varbinary(max)` dropdown entries. The type editor maps
canonical metadata to these entries for display, and SQL generation maps an
explicit entry back to its base type and `max` length. This keeps metadata
consumers compatible with upstream and prevents a stale length input from
generating duplicate lengths.

## View definitions

The driver reads view SQL through `OBJECT_DEFINITION(OBJECT_ID(...))` with the
selected schema and view name quoted separately. This preserves definitions
longer than the 4,000-character limit of `INFORMATION_SCHEMA.VIEWS` and resolves
the selected schema rather than only the login's default schema. This behavior
is included upstream, not a remaining fork-specific implementation.

## Transactions and batch errors

The native SQLSRV connection uses `sqlsrv_begin_transaction()`, `sqlsrv_commit()`
and `sqlsrv_rollback()`. A separate SQL `BEGIN TRANSACTION` batch is not equivalent:
with Multiple Active Result Sets enabled by default, that transaction can be rolled
back when its batch ends. PDO_DBLIB instead uses SQL transaction statements and
checks `@@TRANCOUNT` before rollback to avoid extension crashes after a server-side
transaction failure.

The driver advertises `transaction_ddl` for shared transactional object replacement.
SQLSRV also reports failures when advancing to later batch results, allowing the
SQL command page to display errors beyond the first statement. See the shared
[transaction and batch-result contracts](../Drivers.md#driver-and-connection-contracts).

## Runtime statistics

Runtime statistics are available with the native SQLSRV extension, not the PDO
connections. The driver instruments the original execution with
`SET STATISTICS IO ON` and `SET STATISTICS TIME ON`; it does not execute a second
copy of the query. It restores both session settings after collection, including
when the query fails.

See [runtime statistics](../Architecture.md#runtime-statistics) for the shared
UI, statement restrictions and result format.