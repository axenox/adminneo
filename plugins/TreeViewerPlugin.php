<?php

namespace AdminNeo;

/**
 * Adds a tree browser for following foreign-key relations from rows in the data selection table.
 *
 * The plugin injects a compact action next to each row's edit controls. Clicking it opens a modal
 * with the selected row and lets the user expand direct foreign keys (this row points to another
 * table) and reverse foreign keys (other tables point to this row) without leaving the current
 * selection page.
 *
 * Foreign-key metadata is embedded into selection responses so the JavaScript part can also render
 * relations for rows loaded lazily inside the modal. The data itself is still loaded through normal
 * AdminNeo select pages, preserving existing permissions and value formatting.
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
class TreeViewerPlugin extends Plugin
{
	/**
	 * Loads the JavaScript and stylesheet on select pages only.
	 */
	public function printToHead(): ?bool
	{
		if (!isset($_GET['select'])) {
			return null;
		}

		echo "<link rel='stylesheet' href='", link_files('tree-viewer.css', [
			'../plugins/tree-viewer/tree-viewer.css',
		]), "'>\n";
		echo script_src(link_files('tree-viewer.js', [
			'../plugins/tree-viewer/tree-viewer.js',
		]), true);

		return null;
	}

	/**
	 * Embeds the database's foreign-key graph into select-page markup for the tree viewer script.
	 *
	 * @param list<string[]> $rows All selection rows that are going to be printed.
	 * @param array[] $foreignKeys Column foreign keys for the selected table, unused because the tree
	 *     viewer needs the complete graph of the current database/schema.
	 *
	 * @return array The unchanged row data.
	 */
	public function fillForeignDescriptions(array $rows, array $foreignKeys): array
	{
		if (!isset($_GET['select'])) {
			return $rows;
		}

		$json = json_encode($this->getForeignKeysList(), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
		echo '<script type="application/json" id="adminneo-tree-viewer-foreign-keys">' . $json . '</script>';

		return $rows;
	}

	/**
	 * Returns all foreign keys from visible tables in a JavaScript-friendly normalized structure.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private function getForeignKeysList(): array
	{
		$currentDatabase = Admin::get()->getDatabase();
		$currentSchema = $_GET['ns'] ?? '';
		$list = [];

		foreach (table_status('', true) as $table => $status) {
			$sourceTable = $status['Name'] ?? $table;
			$sourceSchema = $status['nspname'] ?? $currentSchema;

			foreach (Admin::get()->getForeignKeys($sourceTable) as $foreignKey) {
				$list[] = [
					'sourceDb' => $currentDatabase,
					'sourceSchema' => $sourceSchema,
					'sourceTable' => $sourceTable,
					'sourceColumns' => array_values($foreignKey['source'] ?? []),
					'targetDb' => $foreignKey['db'] ?: $currentDatabase,
					'targetSchema' => $foreignKey['ns'] ?? $currentSchema,
					'targetTable' => $foreignKey['table'] ?? '',
					'targetColumns' => array_values($foreignKey['target'] ?? []),
				];
			}
		}

		return $list;
	}
}
