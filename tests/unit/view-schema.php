<?php

namespace AdminNeo;

$dialect = $argv[1] ?? 'pgsql';
define('ME', '');

if ($dialect == 'pgsql') {
	require __DIR__ . '/../../admin/core/Connection.php';
	require __DIR__ . '/../../admin/core/Result.php';
	require __DIR__ . '/../../admin/core/Drivers.php';
	require __DIR__ . '/../../admin/core/Driver.php';
	$_GET = ['pgsql' => '', 'ext' => ''];
	require __DIR__ . '/../../admin/drivers/pgsql.inc.php';

	/** Isolates identifier formatting from connection and type initialization. */
	class ViewSchemaDriver extends PgSqlDriver
	{
		/**
		 * {@inheritDoc}
		 * @see PgSqlDriver::__construct()
		 */
		public function __construct()
		{
		}
	}

	$driver = new ViewSchemaDriver();
	$instance = new \ReflectionProperty(Driver::class, 'instance');
	$instance->setAccessible(true);
	$instance->setValue(null, $driver);
} else {
	define('DIALECT', $dialect);

	/** Emulates the other drivers' identifier quoting without loading a connection. */
	function idf_escape(string $name): string
	{
		if (DIALECT == 'sql') {
			return '`' . str_replace('`', '``', $name) . '`';
		}
		if (DIALECT == 'mssql') {
			return '[' . str_replace(']', ']]', $name) . ']';
		}
		return '"' . str_replace('"', '""', $name) . '"';
	}

	/** Preserves the MS SQL driver's existing qualification and other drivers' bare names. */
	function table(string $name): string
	{
		return (DIALECT == 'mssql' ? '[dbo].' : '') . idf_escape($name);
	}
}

/** Stops submissions at the redirect boundary, before rendering or database writes. */
class ViewSchemaRedirect extends \Exception
{
}

/** Supplies the original view type for PostgreSQL's replace-versus-recreate decision. */
function table_status1(string $name): array
{
	return ['Engine' => $GLOBALS['view_engine']];
}

/** Leaves translation initialization outside this SQL generation test. */
function lang(string $message): string
{
	return $message;
}

/** Captures the exact command passed to execution and the success message. */
function query_redirect(string $query, string $location, string $message): void
{
	$GLOBALS['view_queries'] = [$query];
	throw new ViewSchemaRedirect();
}

/** Captures all create, drop, cleanup and temporary-view commands before execution. */
function drop_create(
	string $drop, string $create, string $drop_created, string $test, string $drop_test,
	string $location, string $message_drop, string $message_alter, string $message_create, string $old_name, string $new_name
): void {
	$GLOBALS['view_queries'] = [$drop, $create, $drop_created, $test, $drop_test];
	throw new ViewSchemaRedirect();
}

/** Keeps the included page's local variables separate from the test cases. */
function submit_view_schema(): void
{
	include __DIR__ . '/../../admin/view.inc.php';
}

$errors = 0;
$cases = [
	['create', '', 'report', 'view', false, false],
	['replace', 'report', 'report', 'view', false, false],
	['rename', 'report', 'renamed', 'view', false, false],
	['drop', 'report', 'report', 'view', false, true],
	['create materialized', '', 'report', 'view', true, false],
	['replace materialized', 'report', 'report', 'materialized view', true, false],
	['convert to materialized', 'report', 'report', 'view', true, false],
	['convert to ordinary', 'report', 'report', 'materialized view', false, false],
	['quoted name', 'view"name', 'view"name', 'view', false, false],
];
foreach (['commercial' => '"commercial".', 'Odd" Schema' => '"Odd"" Schema".'] as $schema => $quoted_schema) {
	foreach ($cases as [$label, $original, $name, $engine, $materialized, $drop]) {
		$_GET = ['view' => $original, 'ns' => $schema];
		$_POST = ['name' => $name, 'select' => 'SELECT 1 AS value', 'materialized' => $materialized, 'drop' => $drop];
		if ($dialect == 'pgsql') {
			$driver->setCurrentSchema($schema);
		}
		$GLOBALS['view_engine'] = $engine;
		$GLOBALS['view_queries'] = [];
		try {
			submit_view_schema();
		} catch (ViewSchemaRedirect $exception) {
		}

		$prefix = (DIALECT == 'pgsql' ? $quoted_schema : (DIALECT == 'mssql' ? '[dbo].' : ''));
		$type = ($materialized ? 'MATERIALIZED VIEW' : 'VIEW');
		$orig_type = (DIALECT == 'pgsql' && $original != '' ? strtoupper($engine) : 'VIEW');
		$as = " AS\nSELECT 1 AS value";
		if (!$drop && $original == $name && DIALECT != 'sqlite' && $type == 'VIEW' && $orig_type == 'VIEW') {
			$expected = [(DIALECT == 'mssql' ? 'ALTER' : 'CREATE OR REPLACE') . ' VIEW ' . $prefix . idf_escape($name) . $as];
		} else {
			$expected = [
				"DROP $orig_type " . $prefix . idf_escape($original),
				"CREATE $type " . $prefix . idf_escape($name) . $as,
				"DROP $type " . $prefix . idf_escape($name),
				"CREATE $type " . $prefix . idf_escape('adminneo_TEMP') . $as,
				"DROP $type " . $prefix . idf_escape('adminneo_TEMP'),
			];
		}
		$actual = preg_replace('~adminneo_[0-9a-f]+~', 'adminneo_TEMP', $GLOBALS['view_queries']);
		if ($actual !== $expected) {
			echo 'FAIL: ' . json_encode([DIALECT, $label, $schema, 'expected' => $expected, 'actual' => $actual]) . "\n";
			$errors++;
		}
	}
}

echo (count($cases) * 2) . ' cases, ' . $errors . " failures (" . DIALECT . ")\n";
exit($errors ? 1 : 0);
