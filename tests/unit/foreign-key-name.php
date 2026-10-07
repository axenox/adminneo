<?php

namespace AdminNeo;

define('DIALECT', 'sql');
define('ME', '');

/** Supplies the named-constraint setting without initializing a database connection. */
class Admin
{
	public static $namedConstraints = true;

	/** Supplies the configuration boundary used by the submission handler. */
	public static function get(): self
	{
		return new self();
	}

	/** Keeps the test independent of application initialization. */
	public function getConfig(): self
	{
		return $this;
	}

	/** Allows both default and named-constraint SQL to be checked. */
	public function isUseNamedConstraintsEnabled(): bool
	{
		return self::$namedConstraints;
	}
}

/** Marks the redirect boundary so the test does not render the editor. */
class ForeignKeyRedirect extends \Exception
{
}

/** Preserves the strict identifier contract that exposed the missing-name bug. */
function idf_escape(string $idf): string
{
	return '`' . str_replace('`', '``', $idf) . '`';
}

/** Quotes the test table without reading metadata. */
function table(string $table): string
{
	return idf_escape($table);
}

/** Records statements instead of executing database writes. */
function queries(string $sql): bool
{
	$GLOBALS['queries'][] = $sql;
	return true;
}

/** Isolates constraint naming from column formatting. */
function format_foreign_key(array $row): string
{
	return ' FOREIGN KEY (`parent_id`) REFERENCES `parent` (`id`)';
}

/** Avoids initializing translations for redirect messages. */
function lang(string $message): string
{
	return $message;
}

/** Stops successful submissions before page rendering, like the real redirect. */
function queries_redirect(string $location, string $message, bool $result): void
{
	throw new ForeignKeyRedirect();
}

$errors = 0;
foreach ([false, true] as $namedConstraints) {
	Admin::$namedConstraints = $namedConstraints;
	foreach ([null, '', 'existing_fk'] as $constraintName) {
		foreach ([false, true] as $drop) {
			if ($drop && $constraintName !== 'existing_fk') {
				continue;
			}
			$_GET = ['foreign' => 'child'];
			if ($constraintName !== null) {
				$_GET['name'] = $constraintName;
			}
			$_POST = ['source' => ['parent_id'], 'target' => ['id'], 'table' => 'parent', 'add' => '', 'change' => '', 'change-js' => '', 'drop' => $drop];
			$GLOBALS['queries'] = [];
			try {
				include __DIR__ . '/../../admin/foreign.inc.php';
			} catch (ForeignKeyRedirect $exception) {
			}
			$expected = [];
			if ($constraintName === 'existing_fk') {
				$expected[] = 'ALTER TABLE `child` DROP FOREIGN KEY `existing_fk`';
			}
			if (!$drop) {
				$expectedName = $constraintName === 'existing_fk' ? 'existing_fk' : 'FK_child_parent';
				$expected[] = 'ALTER TABLE `child` ADD' . ($namedConstraints ? ' CONSTRAINT `' . $expectedName . '`' : '') . format_foreign_key($_POST);
			}
			if ($GLOBALS['queries'] !== $expected) {
				echo 'FAIL: ' . json_encode([$namedConstraints, $constraintName, $drop, $GLOBALS['queries']]) . "\n";
				$errors++;
			}
		}
	}
}

exit($errors ? 1 : 0);