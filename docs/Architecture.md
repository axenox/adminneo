# AdminNeo architecture

AdminNeo is a full-featured database management tool written in PHP (based on Adminer). The defining architectural feature is that all source files compile down to **a single deployable PHP file**. EditorNeo is a companion end-user data editor that shares much of the same codebase.

Supported databases: MySQL/MariaDB, PostgreSQL/CockroachDB, MS SQL, SQLite, Oracle, MongoDB, SimpleDB, Elasticsearch and ClickHouse. Drivers for other databases can be added.

## Plugin system

The plugin architecture is the primary extension mechanism:

- `Plugin` (abstract) — base class all plugins extend. Gets injected with `$admin`, `$config`, `$settings`, `$locale`.
- `Origin` (extends `Plugin`) — the base class with all overridable methods. `admin/core/Admin.php` and `editor/core/Admin.php` each independently define their own `class Admin extends Origin` (there is no separate `Editor` class — EditorNeo's customization classes also extend `Admin`).
- `Pluginer` — wraps an `Origin` instance; intercepts every public method call and dispatches to registered plugins first. For "append" methods (e.g., `getErrors`, `getFieldFunctions`), results from all plugins are merged. For others, the first plugin that returns a non-null value wins; the `Origin` instance is the fallback.

Custom instances are created via `Admin::create($config, $plugins)` (inherited from `Origin::create()`, using late static binding). The entry point for customization is a PHP file that defines `adminneo_instance()` returning this instance, then includes the compiled file.

## Routing

`admin/index.php` routes requests by checking `$_GET` keys (e.g., `$_GET["table"]`, `$_GET["select"]`, `$_GET["sql"]`). Each route includes the corresponding `.inc.php` file. Pages call `page_header()` at the start and `page_footer()` at the end, except when a page `exit`s early to skip the footer (e.g. `admin/download.inc.php`).

| Query key | Page | Description |
| --- | --- | --- |
| `settings` | `settings.inc.php` | Configure AdminNeo settings. This page is handled before the database connection is initialized. |
| *(none)* | `db.inc.php` | Browse the current database or schema, including its tables, views, and available database actions. |
| `download` | `download.inc.php` | Stream a generated file or export for download. |
| `table` | `table.inc.php` | Inspect a table or view, including its columns, indexes, and related metadata. |
| `schema` | `schema.inc.php` | Display a visual diagram of the tables and relationships in a database schema. |
| `dump` | `dump.inc.php` | Export a database or table as SQL or another supported format. |
| `privileges` | `privileges.inc.php` | View and manage database, table, and other object privileges. |
| `sql` | `sql.inc.php` | Enter and execute SQL commands, or import SQL when `import` is set. |
| `edit` | `edit.inc.php` | Edit an existing row or create a new row in a table. |
| `create` | `create.inc.php` | Create or alter a table. |
| `indexes` | `indexes.inc.php` | Create, alter, or remove indexes for a table. |
| `database` | `database.inc.php` | Create or alter a database. |
| `scheme` | `scheme.inc.php` | Create or alter a schema. |
| `call` | `call.inc.php` | Call a stored procedure or function. The legacy `callf` key is normalized to `call`. |
| `foreign` | `foreign.inc.php` | Create or alter a foreign key for a table. |
| `view` | `view.inc.php` | Create or alter a view. |
| `event` | `event.inc.php` | Create or alter a scheduled database event. |
| `procedure` | `procedure.inc.php` | Create or alter a stored procedure or function. The legacy `function` key is normalized to `procedure`. |
| `sequence` | `sequence.inc.php` | Create or alter a sequence. |
| `type` | `type.inc.php` | Create or alter a user-defined type. |
| `check` | `check.inc.php` | Create or alter a check constraint. |
| `trigger` | `trigger.inc.php` | Create or alter a trigger. |
| `user` | `user.inc.php` | Create or alter a database user and its privileges. |
| `processlist` | `processlist.inc.php` | View currently running database processes and queries. |
| `select` | `select.inc.php` | Browse, filter, sort, paginate, and optionally edit rows from a table or view. |
| `variables` | `variables.inc.php` | View server status information and system variables. |
| `script` | `script.inc.php` | Display or execute a generated database script. |

## Database drivers

Each driver lives in `admin/drivers/<name>.inc.php`. Drivers:
1. Register themselves with `Drivers::add(name, label, extensions)`.
2. Define constants `DRIVER` and `DIALECT` when active (`isset($_GET[$driver_name])`).
3. Implement a `Connection` subclass and a `Driver` subclass.
4. The abstract `Driver` class defines the interface; methods like `support($feature)` control which UI features are shown for that driver.

Not all drivers support all features of AdminNeo. Each driver "knows", what features it supports. Below is a list of available keys for the `support($feature)` method.

When adding new features, that potentially make sense for multiple drivers, make sure to add new support keys. 

| Key | Description |
| --- | --- |
| `check` | Check table integrity. |
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
| `scheme` | Work with database schemas instead of databases as the main namespace. |
| `sequence` | Create and alter sequences. |
| `sql` | Execute SQL commands. |
| `status` | Display database or server status information. |
| `table` | Create and alter tables, and expose table-specific operations. |
| `trigger` | Create and alter table triggers. |
| `type` | Create and alter user-defined types. |
| `variables` | Display server variables. |
| `view` | Create and alter views. |
| `view_trigger` | Create and alter triggers on views. |
| `materializedview` | Support materialized views. |

## Directory structure

| Path                  | Purpose                                                                                      |
|-----------------------|----------------------------------------------------------------------------------------------|
| `admin/`              | AdminNeo source (dev entry: `admin/index.php`)                                               |
| `admin/core/`         | Core classes: `Admin`, `Config`, `Settings`, `Driver`, `Plugin`, `Pluginer`, `Locale`, etc.  |
| `admin/drivers/`      | One file per supported database                                                              |
| `admin/include/`      | Shared functions, HTML helpers, auth, encryption, bootstrap                                  |
| `admin/translations/` | Language files returning PHP arrays                                                          |
| `admin/themes/`       | CSS theme files (variants: blue/green/orange/purple/red)                                     |
| `editor/`             | EditorNeo (shares `admin/` drivers and most of `admin/include/`)                             |
| `bin/`                | Build scripts                                                                                |
| `plugins/`            | Bundled optional plugins                                                                     |
| `compiled/`           | Output of compilation (entirely gitignored, local build artifact)                            |
| `examples/`           | Customization and plugin usage examples                                                      |
| `externals/`          | Optional third-party assets (e.g. TinyMCE) used by some example plugins; gitignored          |
| `tests/`              | Katalon test suites and helper PHP files                                                     |
| `tests/unit/`         | Unit tests                                                                                   |
| `vendor/vrana/`       | Committed third-party libs used at runtime and build time (e.g. `jush` for SQL highlighting) |

## Configuration

`adminneo-config.php` (placed next to the entry point) returns a PHP array of config options. Key options include `colorVariant`, `navigationMode`, `servers`, `hiddenDatabases`, `defaultPasswordHash`. See `admin/core/Config.php` for all supported keys and `examples/adminneo-custom.php` for a full example.

`adminneo-plugins.php` (placed next to the entry point) returns an array of `Plugin` instances.

## Namespace

All PHP code lives under the `AdminNeo\` namespace.

## Translations

Translations use technical language and terms related to database systems. Czech language (`admin/translations/cs.inc.php`) is considered as correct because it was created by the author. All machine-translated texts are marked with trailing comment naming the AI model, e.g. `'Vacuum' => 'Počisti', // by Claude Fable 5`. In case of multiline translation, mark is placed on the last line.

When implementing a feature that introduces new texts, add only the Czech (`cs.inc.php`) and Slovak (`sk.inc.php`) translations together with the code changes, along with `_template.inc.php`. The remaining languages are translated in a separate commit, e.g. `Translations: Translate 'Dual on hover' text`.

To find missing translations, run `php bin/update-translations.php` — it adds new texts with a `null` value. In `en.inc.php` only plural texts are added, so a new plural shows up there once `_template.inc.php` holds multiple forms for it. Flags must follow the language argument.

To validate translations, run `php bin/update-translations.php --clean` – it doesn't add new texts. It checks:

- **Placeholders** — types and order must match the English text.
- **Sentence-final punctuation** — must match whether the English text ends with a period: none for `he`, `।` for `bn`/`hi`, `。` for `ja`/`zh*`, `.` elsewhere.
- **Plural forms** — 4 for `sl`, 3 for `cs sk pl lt lv ro bs hr ru sr uk`, 2 otherwise. Languages with no numeral-driven agreement (ja, zh, ko, vi, tr, th, et) use a plain string instead of an array; identical plural forms are reported as an error.