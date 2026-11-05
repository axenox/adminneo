<?php

namespace AdminNeo;

require __DIR__ . '/../../admin/core/Connection.php';
require __DIR__ . '/../../admin/core/Result.php';
require __DIR__ . '/../../admin/core/Drivers.php';
require __DIR__ . '/../../admin/core/Driver.php';
require __DIR__ . '/../../admin/core/Plugin.php';
require __DIR__ . '/../../admin/core/Origin.php';
require __DIR__ . '/../../admin/core/Admin.php';
require __DIR__ . '/../../admin/core/Config.php';
require __DIR__ . '/../../admin/core/Settings.php';
require __DIR__ . '/../../admin/include/functions.inc.php';
require __DIR__ . '/../../admin/include/editing.inc.php';

$_GET = ['pgsql' => '', 'ext' => '', 'ns' => ''];
$_SERVER['REQUEST_URI'] = '/schema-test';
define('AdminNeo\DB', in_array('--server', $argv) ? '' : 'adminneo_test');
define('AdminNeo\SERVER', 'fixture');
define('AdminNeo\VERSION', 'test');
define('AdminNeo\HTTPS', false);
require __DIR__ . '/../../admin/drivers/pgsql.inc.php';

// Match bootstrap's intentional missing-array-key handling, but fail on other diagnostics.
set_error_handler(function ($severity, $message, $file, $line) {
	if (preg_match('~^Undefined (array key|index|offset)~', $message)) {
		return true;
	}
	throw new \ErrorException($message, 0, $severity, $file, $line);
});

/** Keeps SQL generation independent of session-backed translation initialization. */
function lang(string $message): string
{
	return $message;
}

/** Returns deterministic catalog rows at the database boundary. */
class SchemaResult extends Result
{
	/** @var array[] */
	private $rows;

	/** @var int */
	private $offset = 0;

	/** Initializes a result without opening PostgreSQL. */
	public function __construct(array $rows)
	{
		parent::__construct(count($rows));
		$this->rows = $rows;
	}

	/**
	 * {@inheritDoc}
	 * @see Result::fetchAssoc()
	 */
	public function fetchAssoc()
	{
		return $this->rows[$this->offset++] ?? false;
	}

	/**
	 * {@inheritDoc}
	 * @see Result::fetchRow()
	 */
	public function fetchRow()
	{
		$row = $this->fetchAssoc();
		return $row === false ? false : array_values($row);
	}

	/**
	 * {@inheritDoc}
	 * @see Result::fetchField()
	 */
	public function fetchField()
	{
		return (object)['name' => 'value'];
	}
}

/** Simulates PostgreSQL catalog reads and records SQL without database writes. */
class SchemaConnection extends Connection
{
	/** @var string */
	public $schema = 'public';

	/** @var string[] */
	public $queries = [];

	/** @var bool */
	public $failSchema = false;

	/**
	 * {@inheritDoc}
	 * @see Connection::open()
	 */
	public function open(string $server, string $username, string $password): bool
	{
		$this->version = '18';
		return true;
	}

	/**
	 * {@inheritDoc}
	 * @see Connection::selectDatabase()
	 */
	public function selectDatabase(string $name): bool
	{
		$this->schema = 'public';
		return true;
	}

	/**
	 * {@inheritDoc}
	 * @see Connection::quote()
	 */
	public function quote(string $string): string
	{
		return "'" . str_replace("'", "''", $string) . "'";
	}

	/**
	 * {@inheritDoc}
	 * @see Connection::query()
	 */
	public function query(string $query, bool $unbuffered = false)
	{
		$this->queries[] = $query;
		if (preg_match('~^SET search_path TO "(.*)"$~s', $query, $match)) {
			if ($this->failSchema) {
				$this->error = 'Schema selection failed.';
				return false;
			}
			$this->schema = str_replace('""', '"', $match[1]);
			return true;
		}
		if ($query == 'SELECT current_schema()') {
			return new SchemaResult([[$this->schema]]);
		}
		if ($query == "SELECT 'pg_table_size'::regproc") {
			return new SchemaResult([['pg_table_size']]);
		}
		if ($query == 'SELECT user') {
			return new SchemaResult([['fixture']]);
		}
		if (strpos($query, 'SELECT pg_get_viewdef(') === 0) {
			return new SchemaResult([['SELECT 1 AS value;']]);
		}
		if (strpos($query, 'FROM pg_class c') !== false) {
			$rows = [];
			foreach (['items' => 'table', 'report' => 'view'] as $name => $engine) {
				if (strpos($query, 'AND relname = ') !== false && strpos($query, 'AND relname = ' . $this->quote($name)) === false) {
					continue;
				}
				$rows[] = [
					'Name' => $name, 'Engine' => $engine, 'nspname' => $this->schema,
					'Create_options' => '', 'Data_length' => 0, 'Index_length' => 0,
					'Comment' => '', 'Oid' => '', 'Rows' => 1, 'Partition' => 0,
				];
			}
			return new SchemaResult($rows);
		}
		if (strpos($query, 'FROM pg_attribute a') !== false) {
			return new SchemaResult([[
				'field' => 'value', 'full_type' => 'integer', 'attndims' => 0, 'default' => '',
				'attnotnull' => 0, 'primary' => 0, 'comment' => '', 'attidentity' => '', 'attgenerated' => '',
			]]);
		}
		if (preg_match('~FROM (pg_type t|pg_attribute |pg_index\b|pg_constraint\b|pg_partitioned_table\b|pg_catalog\.pg_indexes\b|INFORMATION_SCHEMA\.CHECK_CONSTRAINTS\b)~', $query)) {
			return new SchemaResult([]);
		}
		if (strpos($query, 'SELECT * FROM ') === 0) {
			return new SchemaResult([['value' => '1']]);
		}
		if (preg_match('~^(CREATE|ALTER|DROP|TRUNCATE|UPDATE|INSERT|DELETE)\b~', $query)) {
			return true;
		}
		throw new \LogicException('Unexpected fixture query: ' . $query);
	}
}

/** Uses the real dump implementation while isolating HTTP output headers. */
class SchemaAdmin extends Admin
{
	/**
	 * {@inheritDoc}
	 * @see Origin::__construct()
	 */
	public function __construct()
	{
		$this->config = new Config(['embeddedMode' => true]);
		$this->settings = new Settings($this->config);
		$this->admin = $this;
	}

	/**
	 * {@inheritDoc}
	 * @see Admin::sendDumpFormatHeaders()
	 */
	public function sendDumpFormatHeaders(string $identifier, bool $multiTable = false): string
	{
		return 'sql';
	}

	/**
	 * {@inheritDoc}
	 * @see Admin::sendDumpOutputHeaders()
	 */
	public function sendDumpOutputHeaders(string $identifier, string $extension): void
	{
	}

	/**
	 * {@inheritDoc}
	 * @see Origin::getSchemas()
	 */
	public function getSchemas(bool $noSystem = false): array
	{
		return ['commercial', 'Odd" Schema'];
	}
}

$failures = [];
$checks = 0;

/** Records failures without sending output before the dump's cookie headers. */
function check_schema(string $label, $actual, $expected): void
{
	$GLOBALS['checks']++;
	if ($actual !== $expected) {
		$GLOBALS['failures'][] = $label . ': ' . json_encode(['expected' => $expected, 'actual' => $actual]);
	}
}

/** Runs the real export route with local variables isolated from the test assertions. */
function export_schemas(): string
{
	ob_start();
	try {
		include __DIR__ . '/../../admin/dump.inc.php';
		return ob_get_contents();
	} finally {
		ob_end_clean();
	}
}

$connection = SchemaConnection::create();
$connection->open('', '', '');
$admin = new SchemaAdmin();
$instance = new \ReflectionProperty(Origin::class, 'instance');
$instance->setAccessible(true);
$instance->setValue(null, $admin);
$driver = PgSqlDriver::create($connection, $admin);

check_schema('Unselected schema', table('items'), '"items"');
foreach (['commercial' => '"commercial".', 'Odd" Schema' => '"Odd"" Schema".', 'schema.with.dot' => '"schema.with.dot".'] as $schema => $prefix) {
	$_GET['ns'] = 'request-schema';
	check_schema('Primary switch', set_schema($schema), true);
	check_schema('Active schema', get_schema(), $schema);
	check_schema('Request schema remains untouched', $_GET['ns'], 'request-schema');
	$count = count($connection->queries);
	check_schema('Qualified table', table('items'), $prefix . '"items"');
	check_schema('Quoted object name', table('view"name.with.dot'), $prefix . '"view""name.with.dot"');
	check_schema('Quoting does not query the database', count($connection->queries), $count);

	$connection->failSchema = true;
	check_schema('Failed switch returns failure', set_schema('blocked'), false);
	check_schema('Failed switch preserves qualification', table('items'), $prefix . '"items"');
	$connection->failSchema = false;

	$secondary = SchemaConnection::createSecondary();
	$secondary->open('', '', '');
	check_schema('Secondary switch', set_schema('secondary', $secondary), true);
	check_schema('Secondary selection is isolated', table('items'), $prefix . '"items"');

	$start = count($connection->queries);
	alter_table('before', 'after', [], [], null, '', '', '', null);
	check_schema('Bare rename target', array_slice($connection->queries, $start), ['ALTER TABLE ' . $prefix . '"before" RENAME TO "after"']);
	$start = count($connection->queries);
	$driver->update('items', ['"value"' => '2'], ' WHERE "value" = 1');
	$driver->delete('items', ' WHERE "value" = 2');
	truncate_tables(['items', 'report']);
	check_schema('Qualified shared data commands', array_slice($connection->queries, $start), [
		'UPDATE ' . $prefix . '"items" SET' . "\n" . '"value" = 2 WHERE "value" = 1',
		'DELETE FROM ' . $prefix . '"items" WHERE "value" = 2',
		'TRUNCATE ' . $prefix . '"items", ' . $prefix . '"report"',
	]);

	foreach ([$schema, 'target" schema'] as $target) {
		$_POST = ['overwrite' => false];
		$start = count($connection->queries);
		check_schema('Copy succeeds', copy_tables(['items'], ['report'], $target), true);
		$commands = array_values(array_filter(array_slice($connection->queries, $start), function ($query) {
			return preg_match('~^(CREATE|INSERT)\b~', $query);
		}));
		$target_prefix = ($target == $schema ? $prefix : '"target"" schema".');
		$suffix = ($target == $schema ? '_copy' : '');
		check_schema('Qualified copy commands', $commands, [
			'CREATE TABLE ' . $target_prefix . '"items' . $suffix . '" (LIKE ' . $prefix . '"items" INCLUDING ALL)',
			'INSERT INTO ' . $target_prefix . '"items' . $suffix . '" SELECT * FROM ' . $prefix . '"items"',
			'CREATE VIEW ' . $target_prefix . '"report' . $suffix . '" AS SELECT 1 AS value;',
		]);
	}
	$start = count($connection->queries);
	move_tables(['items'], ['report'], 'target" schema');
	$commands = array_values(array_filter(array_slice($connection->queries, $start), function ($query) {
		return strpos($query, 'ALTER ') === 0;
	}));
	check_schema('Qualified move commands', $commands, [
		'ALTER TABLE ' . $prefix . '"items" SET SCHEMA "target"" schema"',
		'ALTER VIEW ' . $prefix . '"report" SET SCHEMA "target"" schema"',
	]);
}

foreach (['DROP+CREATE', 'CREATE', ''] as $style) {
	$_GET = ['dump' => '', 'ns' => ''];
	$_POST = [
		'format' => 'sql', 'output' => 'text', 'db_style' => '', 'table_style' => $style,
		'data_style' => 'INSERT', 'auto_increment' => false, 'types' => false, 'routines' => false,
		'events' => false, 'triggers' => false, 'schemas' => ['commercial', 'Odd" Schema'],
		'tables' => [], 'data' => [], 'databases' => ['first', 'second'],
	];
	$dump = export_schemas();
	check_schema('Export does not change schema-selection flags', $_GET['ns'], '');
	$copies = (DB == '' ? 2 : 1);
	foreach (['"commercial".', '"Odd"" Schema".'] as $prefix) {
		check_schema('Exported inserts in every schema', substr_count($dump, 'INSERT INTO ' . $prefix . '"items"'), $copies);
		check_schema('Exported views in every schema', substr_count($dump, 'CREATE VIEW ' . $prefix . '"report"'), $style ? $copies : 0);
		check_schema('Qualified view placeholder', substr_count($dump, 'CREATE TABLE ' . $prefix . '"report"'), $style ? $copies : 0);
		if ($style) {
			check_schema('Qualified placeholder cleanup', substr_count($dump, 'DROP TABLE IF EXISTS ' . $prefix . '"report"'), $copies);
		}
	}
}

restore_error_handler();
foreach ($failures as $failure) {
	echo 'FAIL: ' . $failure . "\n";
}
echo $checks . ' checks, ' . count($failures) . ' failures (' . (DB == '' ? 'server export' : 'database export') . ")\n";
exit($failures ? 1 : 0);
