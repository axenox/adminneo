# Drivers for databases

Database drivers adapt AdminNeo's shared features to each database system.
Their contract has two parts:

- **Driver classes** extend the [Driver base class](../admin/core/Driver.php),
  which provides common behavior and overridable methods for operations such as
  selecting, inserting, updating and deleting data.
- **Helper functions**, such as `table()` and `fields()`, are defined separately
  by the active driver in the `AdminNeo` namespace. They are not methods of the
  base class and have no shared interface declaration.

Driver implementations live in [admin/drivers](../admin/drivers/). Shared code
uses both the active driver instance, available through `Driver::get()`, and
these helper functions. The contracts below document the important helpers
without duplicating documentation across individual driver implementations.

## Available drivers

The table summarizes the main capabilities of all bundled drivers. Availability
depends on the server version, database flavor and PHP extension; use
`support($feature)` for the active connection rather than treating this overview
as a guarantee. MySQL/MariaDB and PostgreSQL/CockroachDB each share one driver.

| Database | Driver ID | Main supported features |
| --- | --- | --- |
| [MySQL / MariaDB](Databases/MySQL.md) | `mysql` | SQL, tables and indexes, views, routines, triggers, events, privileges, copying, exports and runtime statistics. |
| [PostgreSQL / CockroachDB](Databases/PostgreSQL.md) | `pgsql` | Schemas, SQL, tables and indexes, views, routines, triggers, user-defined types, copying, exports and transactional DDL; runtime statistics for PostgreSQL. |
| [Microsoft SQL Server](Databases/MS_SQL.md) | `mssql` | Schemas, SQL, tables and indexes, views, routines, triggers, copying, exports and transactional DDL; runtime statistics with SQLSRV. |
| SQLite | `sqlite` | SQL, tables and indexes, views, table/view triggers, exports and transactional DDL. |
| Oracle (beta) | `oracle` | Schemas, SQL, tables and columns, indexes, views and process lists. |
| MongoDB (alpha) | `mongo` | Database and collection browsing, document insertion/editing/deletion and indexes. |
| Elasticsearch | `elastic` | Index and mapping management, document browsing, insertion, editing and deletion. |
| SimpleDB | `simpledb` | SQL-like SELECT commands, domain/item browsing and item insertion/editing/deletion. |
| ClickHouse (alpha) | `clickhouse` | SQL, table/column management, data browsing/editing and server status. |

## Feature support

Not all drivers support every AdminNeo feature. The `support($feature)` helper
reports which features are available and controls which UI features are shown.
When adding a feature that applies to multiple drivers, add a support key so
shared code can check for it. The available keys are listed below.

| Key | Description |
| --- | --- |
| `check` | Read and edit CHECK constraints. |
| `columns` | Create and alter table columns. |
| `comment` | Read and edit table and column comments. |
| `copy` | Copy tables between databases or schemas; the implementation helper is `copy_tables()`. |
| `database` | Create, alter, and drop databases. |
| `descidx` | Define descending index columns. |
| `drop_col` | Drop existing columns while editing a table. |
| `dump` | Export databases, tables, and supported database objects. |
| `event` | Create and alter scheduled database events. |
| `fast_status` | Use the driver's fast table-status query. |
| `indexes` | Create, alter, and drop table indexes. |
| `kill` | Terminate a running database process. |
| `partial_indexes` | Define partial or filtered indexes. |
| `privileges` | View and manage database privileges. |
| `move_col` | Reorder columns while editing a table. |
| `procedure` | Create procedures in addition to functions. |
| `processlist` | View currently running database processes and queries. |
| `routine` | List and edit stored routines. |
| `routine_fields` | Edit routines using separate parameter and body fields. |
| `routine_script` | Edit routines as complete SQL scripts. |
| `scheme` | Work with database schemas instead of databases as the main namespace. |
| `sequence` | Create and alter sequences. |
| `sql` | Execute SQL commands. |
| `status` | Display database or server status information. |
| `table` | Create and alter tables, and expose table-specific operations. |
| `transaction_ddl` | Use transactions when dropping and recreating database objects. |
| `trigger` | Create and alter table triggers. |
| `type` | Create and alter user-defined types. |
| `variables` | Display server variables. |
| `view` | Create and alter views. |
| `view_trigger` | Create and alter triggers on views. |
| `runtime_statistics` | Collect actual execution and I/O statistics for read-only SQL statements. |
| `materializedview` | Support materialized views. |

## Driver and connection contracts

### Transactions and DDL

`Driver::begin()`, `commit()` and `rollback()` return `bool`, record the statement
for display, and delegate to the active connection. Connection implementations
use their native transaction API where required; do not replace these calls with
raw SQL without checking the extension's behavior. `Connection::inTransaction()`
reports transaction state where available; `false` can also mean the extension
cannot determine it. A connection's `rollback()` may be called without an active
transaction and must handle that case appropriately.

The `transaction_ddl` support key is separate from transaction availability.
PostgreSQL, SQLite and MS SQL advertise it so the shared `drop_create()` path
can replace an object within a transaction instead of first creating a temporary
test object. A failed replacement is rolled back. Drivers without this capability
retain the temporary-object or create-before-drop paths.

### Batch results and warnings

After `multiQuery()`, consume each result using `storeResult()` and advance with
`nextResult()`. A `true` return from `nextResult()` can indicate a failed next
statement, not just a successful result: inspect `getError()` for each result.
This lets the SQL command page report errors from later statements in a batch.

`hasMoreResults()` reports pending results where the extension can detect them.
`getWarningsCount()` returns the most recent query's warning count where supported.
The MySQL SQL command page uses the count while further results are pending rather
than executing `SHOW WARNINGS` on a connection that is still processing the batch.

### Search validation and SQL fragments

`Driver::isSearchable(array $where, array $field): bool` checks whether a condition
is valid for one column during an all-columns search. Shared code handles numeric
and date validation; drivers handle database-specific cases such as MySQL bit
values and PostgreSQL UUID, array, range, network and time values. Invalid column
comparisons are omitted so one incompatible type does not fail the whole search.

`Connection::setUntrusted(true)` marks queries containing user-supplied SQL filter
fragments. Drivers must restrict modifying statements and multiple statements
appropriately; secondary connections inherit the flag. This is not a general
read-only connection guarantee. See [security restrictions](security.md#sql-filter-fragments).

### Types, indexes and SQL parsing

- `setUserTypes($types)` accepts IDs mapped to names. `getUserTypes()` returns names
  and `getUserTypeId($name)` resolves a name back to its ID. Do not recover type IDs
  from `getTypes()`: that collection describes maximum lengths instead.
- `supportsIndex($tableStatus)` controls index display;
  `supportsAlterIndex($tableStatus)` separately controls index editing.
- `lineComment()` returns a regular expression matching the beginning of a line
  comment for SQL command parsing. It must not match an empty string.

## Important helper methods

Shared features use these helpers instead of building database-specific SQL.
Most are provided by the active driver; `q()`, `idf_unescape()` and
`table_status1()` are shared wrappers. This list covers the main contracts,
not every driver function.

### Identifiers and values

- `idf_escape($name)` - Quotes one bare identifier, such as a column name or a rename target; does not add a schema.
- `table($name)` - Returns a safely quoted table or view reference, including the selected schema when the driver supports schemas and one is selected.
  Quote the schema and object name separately, for example `"commercial"."report"` in PostgreSQL.
  Without a selected schema, return the quoted object name alone. Pass a bare, unquoted name; callers must not add schema prefixes themselves.
- `idf_unescape($name)` - Removes identifier quoting and restores escaped quote characters.
- `q($value)` - Quotes a string value through the active connection; do not use it for identifiers.

### Features and metadata

- `support($feature)` - Checks whether the active driver supports a named feature before using its helpers or showing its UI.
- `tables_list()` - Lists table and view names with their types in the selected database/schema.
- `table_status($name = "", $fast = false)` - Returns object metadata keyed by name, for one object or all objects when the name is empty.
- `table_status1($name, $fast = false)` - Returns one object's metadata; falls back to its name alone when no metadata is found.
- `is_view($status)` - Checks whether an object's metadata describes a view, including materialized views where supported.
- `fields($table)` - Returns column metadata keyed by column name, including types, defaults, nullability and editing permissions.
- `indexes($table)` - Returns index metadata, including index type and indexed columns.
- `foreign_keys($table)` - Returns foreign-key metadata, including source columns, referenced objects and update/delete rules.
- `view($name)` - Returns a view's definition for editing or export, with its query in the `select` entry.

### Schemas

Use these helpers only when `support("scheme")` is true. Schema selection and
qualification are driver responsibilities, not shared-page logic.

- `schemas()` - Lists available schema names in the selected database.
- `get_schema()` - Returns the current schema name according to the active driver.
- `set_schema($schema, $connection = null)` - Selects the schema context; drivers with connection-local schemas can target a supplied connection.

### SQL generation

- `limit($query, $where, $limit, $offset = 0)` - Builds a query fragment with the driver's row-limit and offset syntax; does not execute it.
- `limit1($table, $query, $where)` - Builds a fragment for a single-row data change using the driver's supported syntax.
- `create_sql($table, $auto_increment, $style)` - Builds table or view creation SQL for export; does not execute the generated script.