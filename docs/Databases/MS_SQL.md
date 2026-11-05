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

## Runtime statistics

Runtime statistics are available with the native SQLSRV extension, not the PDO
connections. The driver instruments the original execution with
`SET STATISTICS IO ON` and `SET STATISTICS TIME ON`; it does not execute a second
copy of the query. It restores both session settings after collection, including
when the query fails.

See [runtime statistics](../Architecture.md#runtime-statistics) for the shared
UI, statement restrictions and result format.