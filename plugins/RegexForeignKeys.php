<?php

namespace AdminNeo;

/**
 * Infers single-column foreign keys from column names matched by a regular expression.
 *
 * The expression can expose named "table" and "key" groups. If it does not, the first two
 * capturing groups are interpreted as table and key. A relation is returned only when the target
 * table and column exist in the current schema and both columns have the same full data type.
 * Database-defined foreign keys are preserved.
 *
 * Last changed in release: !compile: version
 *
 * @link https://www.adminneo.org/plugins/#usage
 *
 * @author Accenture Power UI Team
 *
 * @license https://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
 */
class RegexForeignKeys extends Plugin
{
	/** @var string */
	private $pattern;

	/** @var ?array<string,string> */
	private $tables = null;

	/** @var array<string,array[]> */
	private $fields = [];

	public function __construct(string $pattern)
	{
		$this->pattern = $pattern;
	}

	public function getForeignKeys(string $table): array
	{
		$foreignKeys = foreign_keys($table);
		$sourceFields = fields($table);

		foreach ($sourceFields as $column => $sourceField) {
			if (!@preg_match($this->pattern, $column, $matches)) {
				continue;
			}

			if (isset($matches['table'], $matches['key'])) {
				$targetTable = $matches['table'];
				$targetColumn = $matches['key'];
			} elseif (isset($matches[1], $matches[2])) {
				$targetTable = $matches[1];
				$targetColumn = $matches[2];
			} else {
				continue;
			}

			if ($targetTable === '' || $targetColumn === '' || !isset($this->getTables()[$targetTable])) {
				continue;
			}

			$targetFields = $this->getFields($targetTable);
			if (!isset($targetFields[$targetColumn]) || !$this->hasSameType($sourceField, $targetFields[$targetColumn])) {
				continue;
			}

			$alreadyDefined = false;
			foreach ($foreignKeys as $foreignKey) {
				if (in_array($column, $foreignKey['source'] ?? [], true)) {
					$alreadyDefined = true;
					break;
				}
			}
			if ($alreadyDefined) {
				continue;
			}

			$foreignKeys[] = [
				'db' => '',
				'ns' => $_GET['ns'] ?? '',
				'table' => $targetTable,
				'source' => [$column],
				'target' => [$targetColumn],
				'on_delete' => '',
				'on_update' => '',
			];
		}

		return $foreignKeys;
	}

	/**
	 * @return array<string,string>
	 */
	private function getTables(): array
	{
		if ($this->tables === null) {
			$this->tables = tables_list();
		}

		return $this->tables;
	}

	/**
	 * @return array[]
	 */
	private function getFields(string $table): array
	{
		if (!isset($this->fields[$table])) {
			$this->fields[$table] = fields($table);
		}

		return $this->fields[$table];
	}

	private function hasSameType(array $source, array $target): bool
	{
		$sourceType = preg_replace('~\s+~', ' ', strtolower(trim($source['full_type'] ?? $source['type'] ?? '')));
		$targetType = preg_replace('~\s+~', ' ', strtolower(trim($target['full_type'] ?? $target['type'] ?? '')));

		return $sourceType !== '' && $sourceType === $targetType;
	}
}
