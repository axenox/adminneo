<?php

namespace AdminNeo;

require __DIR__ . '/../../admin/core/Driver.php';
require __DIR__ . '/../../admin/include/functions.inc.php';
require __DIR__ . '/../../admin/include/html.inc.php';
require __DIR__ . '/../../admin/include/editing.inc.php';

define('AdminNeo\DIALECT', 'mssql');

/**
 * Keeps labels independent of session-backed translation initialization.
 */
function lang(string $message): string
{
	return $message;
}

/**
 * Supplies the dropdown configuration without opening a database connection.
 */
class MaxTypesDriver extends Driver
{
	/**
	 * Isolates type rendering from connection initialization.
	 */
	public function __construct()
	{
		$this->types = ['Strings' => [
			'varchar' => 8000, 'varchar(max)' => 'max',
			'nvarchar' => 4000, 'nvarchar(max)' => 'max',
			'varbinary' => 8000, 'varbinary(max)' => 'max',
		]];
	}
}

$instance = new \ReflectionProperty(Driver::class, 'instance');
$instance->setAccessible(true);
$instance->setValue(null, new MaxTypesDriver());

$errors = 0;
foreach (['varchar', 'nvarchar', 'varbinary'] as $type) {
	foreach (['max', 'MAX', '50'] as $length) {
		$field = ['type' => $type, 'length' => $length, 'unsigned' => '', 'collation' => ''];
		$expectedType = $length == '50' ? $type : "$type(max)";
		ob_start();
		edit_type('fields[0]', $field, []);
		$html = ob_get_clean();
		if (strpos($html, '<option selected>' . $expectedType) === false
			|| !preg_match('~name="fields\[0\]\[length\]" value="' . ($length == '50' ? '50' : '') . '"~', $html)
			|| process_type($field) !== ' ' . $type . '(' . strtolower($length) . ')') {
			echo "FAIL: $type length $length round trip\n";
			$errors++;
		}
	}
	foreach (['', '50', 'max'] as $length) {
		$field = ['type' => "$type(max)", 'length' => $length, 'unsigned' => '', 'collation' => ''];
		if (process_type($field) !== " $type(max)") {
			echo "FAIL: $type(max) with stale length $length\n";
			$errors++;
		}
	}
}

$field = ['type' => 'nvarchar', 'length' => 'max', 'unsigned' => '', 'collation' => 'Latin1_General_CI_AS'];
ob_start();
edit_type('fields[0]', $field, ['Latin1_General_CI_AS']);
$document = new \DOMDocument();
$document->loadHTML('<table><tr>' . ob_get_clean() . '</tr></table>');
$xpath = new \DOMXPath($document);
$collation = $xpath->query('//select[@name="fields[0][collation]"]')->item(0);
if (!$collation || $collation->getAttribute('class') == 'hidden'
	|| process_type($field) !== ' nvarchar(max) COLLATE Latin1_General_CI_AS') {
	echo "FAIL: nvarchar(max) collation\n";
	$errors++;
}

exit($errors ? 1 : 0);