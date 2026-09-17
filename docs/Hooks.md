# Hooks available for plugins

Plugins customize AdminNeo and EditorNeo by implementing public methods declared by `Origin`. The methods are grouped by purpose below. Their exact signatures are defined in `admin/core/Origin.php`; the standard implementations are in `admin/core/Admin.php` and `editor/core/Admin.php`.

## Dispatch rules

`Pluginer` discovers implemented methods when the Admin instance is created. For most value-returning hooks, plugins are called in registration order and the first non-`null` result is used. If every plugin returns `null`, the standard `Admin` implementation handles the call. A plugin can therefore decline a call by returning `null` when the method's return type permits it.

Methods returning `void` are augmentation hooks: every plugin implementing the method is called, followed by the standard implementation. Output-producing plugins should print only their additional markup unless a separate replacement hook is documented.

The following array hooks are additive rather than replacements:

- `getErrors()`
- `getFieldFunctions(array $field)`
- `getDumpOutputs()`
- `getDumpFormats()`
- `getSettingsRows(int $groupId)`

For these methods, the standard result is collected first and plugin results are added in registration order using PHP's array union operator. Use unique keys because a plugin cannot replace a key already returned by the standard implementation or an earlier plugin.

The framework methods `inject()` and `getConfig()` are not hooks. Plugins receive the active admin instance, configuration, settings, and locale through the protected `$admin`, `$config`, `$settings`, and `$locale` properties after injection. `getSettings()` remains hookable, although plugins normally use the injected `$settings` property directly.

## Initialization and errors

| Hook | Purpose |
| --- | --- |
| `getSettings(): Settings` | Returns the active user settings object. |
| `init(): void` | Runs immediately before authentication. Use it for request-level plugin initialization. |
| `addError(string $error): void` | Receives an HTML-formatted error being added at runtime. The standard implementation stores it for later display. |
| `getErrors(): array` | Adds HTML-formatted messages to the errors displayed by AdminNeo. This is an additive hook. |

## Authentication and connection

| Hook | Purpose |
| --- | --- |
| `getCredentials(): array` | Returns the database connection tuple `[$server, $username, $password]`. |
| `verifyDefaultPassword(string $password)` | Verifies the configured fallback password for databases that do not require their own password. Return `true` or a plain-text error. |
| `authenticate(string $username, string $password)` | Applies additional authentication. Return `true` on success, `false` for an unknown error, or an HTML-formatted error message. |
| `getPrivateKey(bool $create = false)` | Returns the secret used for permanent login, or `false` when it cannot be obtained. |
| `getBruteForceKey(): string` | Returns the identifier used to group failed login attempts, commonly a client IP address. |
| `getServerName(string $server, bool $resolveDefault = true, ?string $fallback = null): string` | Formats the server name shown in breadcrumbs and navigation. |
| `getDatabase(): ?string` | Selects the current database. |
| `getDatabases(bool $flush = true): array` | Returns the visible databases. The standard implementation applies configured hiding rules. |
| `getSchemas(bool $noSystem = false): array` | Returns the visible schemas, optionally excluding system schemas. |
| `getCollations(array $keepValues = []): array` | Returns available collations while preserving the explicitly requested values during filtering. |
| `getQueryTimeout(): int` | Returns the maximum query execution time in seconds; `0` means no plugin-defined limit. |

## Operators and service identity

| Hook | Purpose |
| --- | --- |
| `getOperators(): array` | Returns operators offered by selection filters. |
| `getLikeOperator(): ?string` | Returns the SQL `LIKE` operator. This compatibility hook is deprecated; use the driver API in new code. |
| `getRegexpOperator(): ?string` | Returns the SQL regular-expression operator. This compatibility hook is deprecated. |
| `getServiceTitle(): string` | Returns the HTML-formatted product title displayed in the interface. |

## HTTP headers and page head

| Hook | Purpose |
| --- | --- |
| `sendHeaders(): void` | Sends additional HTTP response headers. |
| `updateCspHeader(array &$csp): void` | Mutates the Content-Security-Policy directives before the header is sent. Values are passed by reference. |
| `printFavicons(): void` | Prints favicon and application-icon links into the document head. |
| `printToHead(): void` | Prints additional markup, styles, or scripts into the document head. |
| `getCssUrls(): array` | Returns stylesheet URLs to load. Returning an array replaces the standard list. |
| `isLightModeForced(): bool` | Determines whether the light color scheme is forced. |
| `isDarkModeForced(): bool` | Determines whether the dark color scheme is forced. |
| `getJsUrls(): array` | Returns JavaScript URLs to load. Returning an array replaces the standard list. |

## Login and session controls

| Hook | Purpose |
| --- | --- |
| `printLoginForm(): void` | Prints additional login-form content before the standard form implementation. |
| `getLoginFormRow(string $fieldName, string $label, string $field): string` | Composes one login-form field, allowing plugins to change the surrounding markup. |
| `printLogout(): void` | Prints content before the standard username and logout control. |

## Navigation and settings

| Hook | Purpose |
| --- | --- |
| `printDatabaseMenu(): void` | Prints content before the standard database-level action menu. |
| `printNavigation(?string $missing): void` | Prints content before the standard main navigation header. `$missing` identifies a missing connection requirement such as `auth`. |
| `printDatabaseSwitcher(?string $missing): void` | Prints content before the standard server, database, and schema switchers. |
| `printTablesFilter(): void` | Prints content before the standard table-list filter. |
| `printTableList(array $tables): void` | Prints content before the standard table and view list. |
| `getRoutines(): array` | Returns routines in the current schema. The standard result is cached for the request. |
| `printRoutineList(array $routines): void` | Prints a routine list in the main navigation. The standard AdminNeo implementation renders the native list; EditorNeo has no native list. |
| `getSettingsRows(int $groupId): array` | Adds keyed HTML rows to a settings group: `1` for overall UI, `2` for UI elements, and `3` for other settings. This is an additive hook. |

## Object names, comments, and relations

| Hook | Purpose |
| --- | --- |
| `getTableName(array $tableStatus): string` | Returns an HTML-formatted table name, or an empty string to hide the table. |
| `getFieldName(array $field, int $order = 0): string` | Returns an HTML-formatted field name; `$order` describes its position in an index. |
| `formatComment(?string $comment): string` | Formats a database comment as safe HTML. |
| `printTableMenu(array $tableStatus, ?array $insertParams): void` | Prints content before the standard table action menu. |
| `getForeignKeys(string $table): array` | Returns the table's foreign keys in the same structure as `foreign_keys()`. |
| `getBackwardKeys(string $table, string $tableName): array` | Returns tables and constraints that reference the current table. |
| `printBackwardKeys(array $backwardKeys, array $row): void` | Prints content before the standard links from a row to records that reference it. |
| `getTableDescriptionFieldName(string $table): string` | Selects the field used as a human-readable description of records in a referenced table. |
| `fillForeignDescriptions(array $rows, array $foreignKeys): array` | Adds human-readable descriptions of foreign-key values to result rows. |
| `getForeignColumnInfo(array $foreignKeys, string $column): ?array` | Returns metadata for the foreign key containing a column, or `null` when none applies. |

## SQL and message formatting

| Hook | Purpose |
| --- | --- |
| `formatSelectQuery(string $query, float $start, bool $failed = false): string` | Formats a query and elapsed time after a selection request. |
| `formatMessageQuery(string $query, string $time, bool $failed = false): string` | Formats a query appended to a success or error message. |
| `formatSqlCommandQuery(string $query): string` | Formats a query shown on the SQL command page. |
| `printAfterSqlCommand(): void` | Prints additional controls immediately before the Execute button on the SQL command page. |

## Table structure

| Hook | Purpose |
| --- | --- |
| `printTableStructure(array $fields): void` | Prints content before the standard table-column structure. |
| `printTablePartitions(array $partitionInfo): void` | Prints content before the standard partition information. |
| `printRelatedTables(array $tables): void` | Prints content before the standard list of related tables. |
| `printTableIndexes(array $indexes, array $tableStatus): void` | Prints content before the standard index list. |

## Selecting and displaying data

| Hook | Purpose |
| --- | --- |
| `getFieldValueLink($val, ?array $field): ?string` | Returns a URL for a displayed field value, or `null` for no link. `$field` is `null` for aggregate expressions. |
| `formatSelectionValue(?string $val, ?string $link, ?array $field, ?string $original): string` | Formats one value in a selection result as HTML. |
| `formatFieldValue($value, array $field): ?string` | Formats a field value for display outside the main selection table, or returns `null` to use standard handling where supported. |
| `printSelectionColumns(array $select, array $columns): void` | Prints content before the standard column-selection controls. |
| `printSelectionSearch(array $where, array $columns, array $indexes): void` | Prints content before the standard filter controls. |
| `printSelectionOrder(array $order, array $columns, array $indexes): void` | Prints content before the standard sorting controls. |
| `printSelectionLimit(int $limit): void` | Prints content before the standard row-limit control. |
| `printSelectionLength(?string $textLength): void` | Prints content before the standard text-truncation control. |
| `printSelectionAction(array $indexes): void` | Prints content before the standard bulk-action controls. |
| `isDataEditAllowed(): bool` | Determines whether inserting, editing, and deleting data is allowed for the current database. |
| `processSelectionColumns(array $columns, array $indexes): array` | Converts the submitted column-selection controls into select expressions. |
| `processSelectionSearch(array $fields, array $indexes): array` | Converts submitted filter controls into selection conditions. |
| `processSelectionOrder(array $fields, array $indexes): array` | Converts submitted sorting controls into order expressions. |
| `processSelectionLimit(): int` | Returns the submitted row limit; `0` means unlimited. |
| `processSelectionLength(): string` | Returns the submitted text-display length setting. |

The `printSelection*()` and matching `processSelection*()` hooks form pairs. A plugin adding or replacing a control generally needs to implement both its rendering and request-processing hook.

## Editing field values

| Hook | Purpose |
| --- | --- |
| `getFieldFunctions(array $field): array` | Adds keyed SQL functions offered for a field value. This is an additive hook. |
| `getFieldInput(?string $table, array $field, string $attrs, $value, ?string $function): string` | Returns the HTML input used to edit a field. `$table` is `null` when calling a routine. |
| `getFieldInputHint(?string $table, array $field, ?string $value): string` | Returns HTML displayed as a hint beside a field input. |
| `processFieldInput(array $field, string $value, string $function = "")` | Converts submitted field input into the SQL expression or value used for writing. |
| `detectJson(string $fieldType, &$value, ?bool $pretty = null): bool` | Detects JSON and optionally reformats `$value` in place. Return whether the field or value is JSON. |

## Server information

| Hook | Purpose |
| --- | --- |
| `getServerVariables(): array` | Returns server configuration variables as name/value rows. |
| `getStatusVariables(): array` | Returns server status variables as name/value rows. |

## Export and import

| Hook | Purpose |
| --- | --- |
| `getDumpOutputs(): array` | Adds keyed export destinations or compression outputs. This is an additive hook. |
| `getDumpFormats(): array` | Adds keyed export formats. This is an additive hook. |
| `sendDumpHeaders(string $identifier, bool $multiTable = false): string` | Sends export response headers and returns the generated filename. |
| `dumpDatabase(string $database): void` | Prints additional database-level structure during an export. |
| `dumpTable(string $table, string $style, int $viewType = 0): void` | Prints additional output before the standard table or view structure export. |
| `dumpData(string $table, string $style, string $query): void` | Prints additional output before the standard table-data export. |
| `getImportFilePath(): string` | Returns the server-side file path used as the import source. |

## Custom schema renderer

The method `printDatabaseSchema(): ?bool` can replace the database schema page. A plugin prints the complete replacement page body and returns `true`; the schema route then returns to `admin/index.php`, which still prints the standard footer. A plugin that is not applicable returns `null` so another plugin can handle the page. The standard implementation returns `false`, causing `schema.inc.php` to render AdminNeo's native draggable schema.

This hook deliberately contains no diagram-library-specific behavior. For example, the Power UI integration registers its external `MermaidSchemaPlugin`, while standalone and compiled AdminNeo installations retain the native renderer unless they explicitly register another plugin. A renderer is responsible for safely escaping its output and loading any external assets through the normal head hooks.