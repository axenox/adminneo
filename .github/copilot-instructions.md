# AI agent instructions for AdminNeo

This is a fork of the AdminNeo PHP database management system. The fork is intended to be integrated into the ExFace no-code platform for business web apps (and potentially other PHP frameworks) rather than being used as a standalone single-file application.

The fork is intended to be kept backwards compatible and will receive updates from the original. New features added to the fork will be contributed back to the original project if they are deemed useful.

Read the architecture documentation in `docs/Architecture.md`.

## Integration

The integration of AdminNeo into ExFace is handled by the `axenox/ide` package. It includes the `axenox\IDE\Facades\IDEFacade`, which is our central router for different cloud IDE related tools - in particular Adminneo. Our UI will open a URL to the facade in an IFrame. The facade will parse the URL, determine the desired DB connection, load its settings and configure Adminneo using this information. It the launches Adminneo for the given connection completely ready-to-run - users do not need to log in or select the database.

Before using Adminneo we had a similar integration with Adminer. We used a bundled modified version of adminer, which is still available in `axenox/ide/Adminer` as a fall-back. Now we switched to Adminneo and chose to fork it to ensure the ability to update in future.

## Global objectives

* Integration with ExFace (and possibly other PHP apps)
    * Fully automated login using database credentials obtained from host app (e.g. ExFace database connections) - no user interaction
    * Ability to run behind a PHP router facade
    * Ability to run in IFrames
* Easy future updates from original Adminneo
* Option to safely pull-request fixes and added features back to the original

### Contribution policy

We expect to add features and modifications to the database drivers of Adminneo as well to its UI. Depending on the situation, you will need to decide, if the changes should be placed in the integration app `axenox/ide `or our fork of Adminneo. Here are a couple of examples.

* bug fixes should be always made in the fork. We will mostly pull requested back to the original adminneo repo.
* Not all DB drivers have the same functionality. Implementations of new/missing features of DB drivers should be done in the fork too. We aim to turn them into PRs later
* If a required modification changes the original behavior of a DB driver, it should be toggled by a config option and OFF by default. Such modifications should be explicitly turned on in the `IDEFacade` of `axenox/ide`. This makes sure, changes to Adminneo can be contributed back to the original as optional features.
* modifications to the Adminneo application itself (UI, navigation, etc.) will mostly stay in the fork and not be contributed back to the original.
* Changes, that are bound to the Power UI platform explicitly, should be placed in the IDE app and implemented as plugins ideally. 

## Feature focus

Within the Power UI platform Adminneo is used to design and maintain databases serving as data source for business web apps. These apps mostly deal with complex data structures and large numbers of tables. Tables mostly have a standardized structure with standardized column names and other conventions.

We focus on MS SQL, MySQL and PostgreSQL. 

### Working with the schema

Schema changes made by Adminneo will normally need to be saved in migration files for automated deployment on other environments: i.e. DEV -> TEST -> PROD.
This usage context results in a couple of common requirements for the DB administration tool.

* Exporting tables, views, etc. as SQL is very important
* Tables are rarely created from scratch - instead an existing table is copied without data
* EXPLAIN tools

## Development rules

- Do not compile AdminNeo into a single file - the result is not committed anyway. The compile process does not produce any useful output for debugging.
- Let AdminNeo generate well-escaped SQL statements: always use `idf_escape()`, `table()`, `schema()` and similar functions when building SQL queries.

## Documentation

### Global documentation

The `docs` folder contains documentation for AdminNeo in its current state (including changes done in this fork). Update the docs files every time you make significant changes to AdminNeo.

### Upstream merges

Documentation maintenance is part of every upstream merge, even when Git reports
no documentation conflicts. Most pages in `docs/` are maintained only in this
fork, so a clean merge does not mean they are current.

- Identify the actual merged upstream revision and release from Git history and
    `CHANGELOG.md`. Compare the incoming changes with the pre-merge fork revision.
- Review all Markdown pages under `docs/`, including database guides, against the
    incoming changes. Check documented APIs, hook signatures and dispatch behavior,
    driver capabilities and version/extension limits, configuration, UI workflows,
    exports, transactions, security restrictions, and host integration.
- Verify affected guidance against the final merged implementation, not just the
    upstream changelog or either side of a conflict. Update affected pages and their
    navigation in the same change; preserve unrelated fork documentation.
- Update `docs/Changes_in_this_fork.md`: move accepted fixes out of the active fork
    list, describe any retained extensions, record the upstream revision and conflict
    decisions, and replace committed `TDB`/`TBD` placeholders with verified hashes.
- Review documentation links, examples and the final diff. Report what was updated
    and any remaining validation limits. Documentation review does not justify a
    single-file compilation or substitute for behavior tests required by code changes.

### Fork specific features

The `docs/Changes_in_this_fork.md` file contains a log of all changes made in this fork of AdminNeo compared to the original. Update this file every time you make a change or fix to the fork. Maintain a feature-oriented log. Since you cannot know the next commit hash, describe your changes and write `TDB` as placeholder instead of the hash. Every time you read the file and see `TDB` or `TBD`, look through the last commits and replace committed placeholders with verified commit hashes. Leave placeholders only for changes that have not been committed. This ensures that the change history remains accurate and traceable.
