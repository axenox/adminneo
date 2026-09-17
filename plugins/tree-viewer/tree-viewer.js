(() => {
	'use strict';

	const treeIcon = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true" class="tree-viewer-icon"><title>file-tree</title><path d="M3,3H9V7H3V3M15,10H21V14H15V10M15,17H21V21H15V17M13,13H7V18H13V20H7L5,20V9H7V11H13V13Z" /></svg>';

	class SelectionQuery {
		constructor() {
			this.tableName = '';
			this.whereConditions = {};
		}
	}

	class SelectionData {
		constructor() {
			this.headers = [];
			this.body = [];
			this.directForeignKeys = {};
			this.reverseForeignKeys = {};
		}
	}

	class AdminNeoTreeConnector {
		constructor(searchParams) {
			this.baseParams = new URLSearchParams();
			this.connectionDb = searchParams.get('db') || '';
			this.connectionSchema = searchParams.get('ns') || '';

			searchParams.forEach((value, key) => {
				if (!['select', 'where', 'page', 'modify', 'columns', 'order', 'desc', 'limit', 'text_length'].some((prefix) => key === prefix || key.startsWith(prefix + '['))) {
					this.baseParams.append(key, value);
				}
			});
		}

		getSelectionData(selectionQuery, callback, errorCallback) {
			fetch(this.urlFromSelectionQuery(selectionQuery), { credentials: 'same-origin' })
				.then((response) => response.text())
				.then((pageHtml) => {
					const tableElement = AdminNeoTreeConnector.getTableFromSelectionHtml(pageHtml);
					const foreignKeys = AdminNeoTreeConnector.getForeignKeysFromHtml(pageHtml);
					if (!tableElement) {
						callback(AdminNeoTreeConnector.addForeignKeysToTableData(selectionQuery, new SelectionData(), foreignKeys));
						return;
					}

					const selectionData = this.extractDataFromTableElement(tableElement);
					callback(AdminNeoTreeConnector.addForeignKeysToTableData(selectionQuery, selectionData, foreignKeys));
				})
				.catch((error) => {
					if (errorCallback) {
						errorCallback(error);
					}
				});
		}

		static getTableFromSelectionHtml(pageHtml) {
			const doc = new DOMParser().parseFromString(pageHtml, 'text/html');
			return doc.querySelector('#table');
		}

		static getForeignKeysFromHtml(pageHtml) {
			const doc = new DOMParser().parseFromString(pageHtml, 'text/html');
			const json = doc.querySelector('#adminneo-tree-viewer-foreign-keys');
			if (!json) {
				return [];
			}
			try {
				return JSON.parse(json.textContent || '[]');
			} catch (error) {
				return [];
			}
		}

		extractDataFromTableElement(tableElement) {
			const selectionData = new SelectionData();
			const headerCells = tableElement.querySelectorAll('thead tr:last-child th, thead tr:last-child td');
			const dataHeaderCells = Array.from(headerCells).filter((cell) => !cell.classList.contains('actions') && !cell.classList.contains('tree-viewer-action-header'));

			dataHeaderCells.forEach((cell) => {
				selectionData.headers.push(AdminNeoTreeConnector.getColumnNameFromHeaderCell(cell));
			});

			tableElement.querySelectorAll('tbody tr').forEach((row) => {
				const bodyRow = {};
				let columnIndex = 0;
				row.querySelectorAll('td').forEach((cell) => {
					if (cell.classList.contains('actions') || cell.classList.contains('tree-viewer-action-cell')) {
						return;
					}
					const header = selectionData.headers[columnIndex++];
					if (header !== undefined) {
						bodyRow[header] = AdminNeoTreeConnector.getCleanCellText(cell);
					}
				});
				if (Object.keys(bodyRow).length) {
					selectionData.body.push(bodyRow);
				}
			});

			return selectionData;
		}

		static addForeignKeysToTableData(selectionQuery, selectionData, foreignKeys) {
			foreignKeys.forEach((foreignKey) => {
				if (foreignKey.sourceTable === selectionQuery.tableName && AdminNeoTreeConnector.sameSchema(foreignKey.sourceSchema, selectionQuery.schemaName)) {
					foreignKey.sourceColumns.forEach((column) => {
						selectionData.directForeignKeys[column] = foreignKey;
					});
				}
				if (foreignKey.targetTable === selectionQuery.tableName && AdminNeoTreeConnector.sameSchema(foreignKey.targetSchema, selectionQuery.schemaName)) {
					foreignKey.targetColumns.forEach((column) => {
						if (!selectionData.reverseForeignKeys[column]) {
							selectionData.reverseForeignKeys[column] = [];
						}
						selectionData.reverseForeignKeys[column].push(foreignKey);
					});
				}
			});
			return selectionData;
		}

		static sameSchema(left, right) {
			return (left || '') === (right || '');
		}

		/**
		 * Returns the database column key represented by a select-result header cell.
		 *
		 * AdminNeo headers contain helper scripts for search/sorting inside the TH. Reading innerText
		 * directly can therefore leak JavaScript snippets such as "onclick = partial(selectSearch…)" into
		 * the tree popup. Prefer the machine-readable TH id (th[<bracket escaped column>]) that AdminNeo
		 * renders for selected columns and only fall back to cleaned visible text for computed columns.
		 */
		static getColumnNameFromHeaderCell(cell) {
			const idMatch = (cell.id || '').match(/^th\[(.*)]$/);
			if (idMatch) {
				return AdminNeoTreeConnector.unescapeBracketIdentifier(idMatch[1]);
			}

			return AdminNeoTreeConnector.getCleanCellText(cell);
		}

		/**
		 * Returns visible data text from a parsed AdminNeo cell without embedded helper scripts.
		 */
		static getCleanCellText(cell) {
			const clone = cell.cloneNode(true);
			clone.querySelectorAll('script, style, .column, .tree-viewer-reverse-links').forEach((element) => element.remove());
			return (clone.textContent || '').trim();
		}

		/**
		 * Decodes AdminNeo's bracket_escape() representation used in element IDs and form names.
		 */
		static unescapeBracketIdentifier(identifier) {
			return identifier
				.replace(/:4/g, '"')
				.replace(/:3/g, '[')
				.replace(/:2/g, ']')
				.replace(/:1/g, ':');
		}

		urlFromSelectionQuery(selectionQuery) {
			const params = new URLSearchParams(this.baseParams.toString());
			if (selectionQuery.dbName && selectionQuery.dbName !== this.connectionDb) {
				params.set('db', selectionQuery.dbName);
			}
			if (selectionQuery.schemaName !== undefined && selectionQuery.schemaName !== null) {
				params.set('ns', selectionQuery.schemaName);
			}
			params.set('select', selectionQuery.tableName);

			let index = 0;
			Object.keys(selectionQuery.whereConditions).forEach((conditionName) => {
				params.set(`where[${index}][col]`, conditionName);
				params.set(`where[${index}][op]`, '=');
				params.set(`where[${index}][val]`, selectionQuery.whereConditions[conditionName]);
				index++;
			});

			return `${window.location.pathname}?${params.toString()}`;
		}

		selectionQueryFromUrl(url) {
			const parsed = new URL(url, window.location.href);
			const params = parsed.searchParams;
			const selectionQuery = new SelectionQuery();
			selectionQuery.dbName = params.get('db') || this.connectionDb;
			selectionQuery.schemaName = params.get('ns') || this.connectionSchema;

			if (params.get('select') !== null) {
				selectionQuery.tableName = params.get('select');
				for (let i = 0; params.get(`where[${i}][col]`) !== null; i++) {
					if (params.get(`where[${i}][op]`) === '=') {
						selectionQuery.whereConditions[params.get(`where[${i}][col]`)] = params.get(`where[${i}][val]`);
					}
				}
			}

			if (params.get('edit') !== null) {
				selectionQuery.tableName = params.get('edit');
				params.forEach((value, key) => {
					if (key.startsWith('where[')) {
						selectionQuery.whereConditions[key.replace(/^where\[/, '').replace(/]$/, '')] = value;
					}
				});
			}

			return selectionQuery;
		}
	}

	class HtmlGenerator {
		constructor(connector) {
			this.connector = connector;
		}

		getModalElement() {
			const modal = HtmlGenerator.getTemplateAsElement('<div id="tree-modal" class="tree-viewer-modal" role="dialog" aria-modal="true" aria-labelledby="tree-modal-title">' +
				'<div class="tree-viewer-modal-header"><h2 id="tree-modal-title">Tree browser</h2><button type="button" class="button light close" title="Close">×</button></div>' +
				'<div class="tree-viewer-modal-content"></div>' +
			'</div>');
			modal.querySelector('.close').addEventListener('click', () => modal.classList.remove('open'));
			modal.addEventListener('click', (event) => {
				if (event.target === modal) {
					modal.classList.remove('open');
				}
			});
			return modal;
		}

		createTableElementFromSelectionData(selectionQuery, selectionData) {
			const wrapper = document.createElement('div');
			wrapper.className = 'scrollable';
			const tableElement = HtmlGenerator.getTemplateAsElement('<table class="nowrap checkable">' +
				'<thead><tr><th class="tree-viewer-node-title" colspan="1"><span class="table-name-caption"></span> <a class="modify-all" target="_blank">Modify</a> <a class="close" href="#!">Close</a></th></tr><tr class="headers"></tr></thead>' +
				'<tbody></tbody>' +
			'</table>');
			wrapper.appendChild(tableElement);

			tableElement.querySelector('.tree-viewer-node-title').colSpan = Math.max(selectionData.headers.length, 1);
			tableElement.querySelector('.table-name-caption').innerText = HtmlGenerator.tableCaptionFromSelectionQuery(selectionQuery);

			if (selectionData.body.length === 0) {
				tableElement.querySelector('tbody').innerHTML = '<tr><td class="tree-viewer-empty"><i>Empty result</i></td></tr>';
				tableElement.querySelector('.modify-all').remove();
				return wrapper;
			}

			tableElement.querySelector('.modify-all').href = this.connector.urlFromSelectionQuery(selectionQuery) + '&modify=1';
			const theadRow = tableElement.querySelector('.headers');
			selectionData.headers.forEach((header) => {
				const th = document.createElement('th');
				th.innerText = header;
				theadRow.appendChild(th);
			});

			const tbody = tableElement.querySelector('tbody');
			selectionData.body.forEach((row) => {
				const dataRow = document.createElement('tr');
				tbody.appendChild(dataRow);
				selectionData.headers.forEach((header) => {
					const td = document.createElement('td');
					dataRow.appendChild(td);

					const directForeignKey = selectionData.directForeignKeys[header];
					if (directForeignKey) {
						const link = HtmlGenerator.getTemplateAsElement('<a class="direct-foreign-key" href="#!"></a>');
						link.innerText = row[header];
						const query = new SelectionQuery();
						query.dbName = directForeignKey.targetDb;
						query.schemaName = directForeignKey.targetSchema;
						query.tableName = directForeignKey.targetTable;
						directForeignKey.targetColumns.forEach((column, index) => {
							query.whereConditions[column] = row[directForeignKey.sourceColumns[index]];
						});
						link.href = this.connector.urlFromSelectionQuery(query);
						td.appendChild(link);
					} else {
						td.appendChild(document.createTextNode(row[header]));
					}

					const reverseForeignKeys = selectionData.reverseForeignKeys[header];
					if (reverseForeignKeys) {
						const toggle = HtmlGenerator.getTemplateAsElement('<a href="#!" title="Tables with foreign keys pointing to this row"> Relations</a>');
						const linksContainer = HtmlGenerator.getTemplateAsElement('<div class="tree-viewer-reverse-links"></div>');
						toggle.addEventListener('click', (event) => {
							event.preventDefault();
							linksContainer.classList.toggle('open');
						});
						td.appendChild(toggle);

						reverseForeignKeys.forEach((foreignKey) => {
							const link = HtmlGenerator.getTemplateAsElement('<a class="reverse-foreign-key" href="#!"></a>');
							link.innerText = `${foreignKey.sourceTable}.${foreignKey.sourceColumns[0]}`;
							const query = new SelectionQuery();
							query.dbName = foreignKey.sourceDb;
							query.schemaName = foreignKey.sourceSchema;
							query.tableName = foreignKey.sourceTable;
							foreignKey.sourceColumns.forEach((column, index) => {
								query.whereConditions[column] = row[foreignKey.targetColumns[index]];
							});
							link.href = this.connector.urlFromSelectionQuery(query);
							linksContainer.appendChild(link);
						});
						td.appendChild(linksContainer);
					}
				});
			});

			return wrapper;
		}

		static tableCaptionFromSelectionQuery(selectionQuery) {
			const where = Object.keys(selectionQuery.whereConditions).map((conditionName) => `${conditionName} = ${selectionQuery.whereConditions[conditionName]}`);
			return `${selectionQuery.tableName} (${where.join(', ')})`;
		}

		static getTemplateAsElement(htmlTemplate) {
			const div = document.createElement('div');
			div.innerHTML = htmlTemplate;
			return div.children[0];
		}
	}

	class AdminNeoTreeView {
		constructor() {
			this.connector = new AdminNeoTreeConnector(new URLSearchParams(window.location.search));
			this.htmlGenerator = new HtmlGenerator(this.connector);
		}

		init() {
			const table = document.querySelector('#table');
			if (!table || table.dataset.treeViewerInitialized) {
				return;
			}
			table.dataset.treeViewerInitialized = '1';
			this.addTreeViewColumnToTable(table);
		}

		addTreeViewColumnToTable(table) {
			table.querySelectorAll('tr').forEach((tr) => {
				if (tr.querySelectorAll('th').length > 0) {
					const cell = document.createElement('th');
					cell.className = 'tree-viewer-action-header';
					cell.title = 'Tree browser';
					cell.innerHTML = treeIcon;
					this.insertActionCell(tr, cell);
					return;
				}

				const editLink = tr.querySelector('a.edit');
				const cell = document.createElement('td');
				cell.className = 'tree-viewer-action-cell';
				if (editLink) {
					const link = document.createElement('a');
					link.href = '#!';
					link.className = 'button light tree-viewer-button';
					link.title = 'Open tree browser';
					link.innerHTML = treeIcon;
					link.addEventListener('click', (event) => this.displayModal(event));
					cell.appendChild(link);
				}
				this.insertActionCell(tr, cell);
			});
		}

		insertActionCell(row, cell) {
			const actionCell = row.querySelector('.actions');
			if (actionCell && actionCell.nextSibling) {
				row.insertBefore(cell, actionCell.nextSibling);
			} else if (actionCell) {
				row.appendChild(cell);
			} else {
				row.insertBefore(cell, row.firstChild);
			}
		}

		displayModal(event) {
			event.preventDefault();
			event.stopPropagation();
			let treeModal = document.querySelector('#tree-modal');
			if (!treeModal) {
				treeModal = this.htmlGenerator.getModalElement();
				document.body.appendChild(treeModal);
			}

			const modalContent = treeModal.querySelector('.tree-viewer-modal-content');
			modalContent.innerHTML = '';
			const editUrl = event.currentTarget.closest('tr').querySelector('a.edit').href;
			const selectionQuery = this.connector.selectionQueryFromUrl(editUrl);
			treeModal.classList.add('open');
			this.openSelectionIntoContainer(selectionQuery, modalContent);
		}

		openSelectionIntoContainer(selectionQuery, containerElement) {
			const loading = document.createElement('div');
			loading.className = 'tree-viewer-loading';
			loading.innerText = 'Loading…';
			containerElement.appendChild(loading);

			this.connector.getSelectionData(selectionQuery, (selectionData) => {
				loading.remove();
				const selection = document.createElement('div');
				selection.className = 'tree-viewer-selection';
				const table = this.htmlGenerator.createTableElementFromSelectionData(selectionQuery, selectionData);
				selection.appendChild(table);

				table.querySelector('.close').addEventListener('click', (event) => {
					event.stopPropagation();
					event.preventDefault();
					if (selection.parentNode.className === 'tree-viewer-modal-content') {
						document.querySelector('#tree-modal').classList.remove('open');
					}
					selection.remove();
				});

				const subSelectionsBox = document.createElement('div');
				subSelectionsBox.className = 'tree-viewer-sub-selections';
				selection.appendChild(subSelectionsBox);

				table.querySelectorAll('a').forEach((link) => {
					link.addEventListener('click', (event) => {
						if (event.currentTarget.classList.contains('direct-foreign-key') || event.currentTarget.classList.contains('reverse-foreign-key')) {
							event.stopPropagation();
							event.preventDefault();
							this.openSelectionIntoContainer(this.connector.selectionQueryFromUrl(event.currentTarget.href), subSelectionsBox);
						}
					});
				});

				containerElement.appendChild(selection);
			}, (error) => {
				loading.className = 'tree-viewer-error';
				loading.innerText = `Could not load related rows: ${error.message}`;
			});
		}
	}

	window.addEventListener('DOMContentLoaded', () => {
		new AdminNeoTreeView().init();
	});
})();
