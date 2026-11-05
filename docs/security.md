Reporting security issues
=========================

To report a security issue, please [open a draft security advisory](https://github.com/adminneo-org/adminneo/security/advisories).

Security issues are handled with top priority. Once acknowledged, a fix should be available and new version released
as soon as possible. Security advisories will be made public after a fix and new version have been released,
or the advisory has been declined.

Supported versions
------------------

Only the last published version and the last development version (last commit) are supported.

SQL filter fragments
--------------------

The SQL operator in selection filters accepts a SQL fragment. Filters can be
supplied by GET requests without a CSRF token, so a SQL filter without a
token-validated POST marks the connection as untrusted. Secondary connections
inherit that state. Driver implementations restrict these queries using their
available APIs; for example, native PostgreSQL uses a single-statement protocol
inside a read-only transaction.

This flag is not a general read-only sandbox and does not replace database
permissions or CSRF protection for write operations. Driver and extension behavior
differs. Hosts and plugins must not clear the flag to make an otherwise rejected
filter execute. See the [connection contract](Drivers.md#search-validation-and-sql-fragments).

SQLite file access
------------------

The SQL command page rejects `ATTACH` and `VACUUM ... INTO`, including commands
prefixed by a byte-order mark, comments, whitespace or empty statements. The same
check applies when exporting a SQL command result. Selecting an existing database
through the database switcher remains supported; it is not equivalent to executing
an arbitrary attachment command.

Where supported, the SQLite connection also installs an authorizer to deny file
attachments and file-writing `VACUUM INTO` operations at the connection level.
This requires PHP 8.0 or later with SQLite3, or PHP 8.5 or later with PDO_SQLite.
Do not assume this additional protection exists on older runtimes. Keep operating
system file permissions and access to the administration UI restricted.

TableLoginPlugin credentials
---------------------------

`TableLoginPlugin` sends its stored database credentials only to a server in the
`servers` configuration. When no servers are configured, only the configured
`defaultServer` with `defaultDriver` is eligible. A different server entered in
the login form must not receive those stored credentials. Configure these targets
explicitly when upgrading an integration that previously allowed arbitrary hosts.

When the plugin has no stored credentials, users can still connect with their own
credentials. This exception must not be used to pass a shared privileged account
to a user-selected server. The [5.9 changelog](../CHANGELOG.md) records the related
upstream security fixes.
