<?php

namespace AdminNeo;

/**
 * MS SQL routine helpers kept outside the generic routine driver hooks for the first iteration.
 *
 * AdminNeo's generic routine editor decomposes routines into parameters, return type and body.
 * That is risky for T-SQL table-valued functions and more complex procedure options, because SQL
 * Server stores and accepts the full CREATE script. These helpers therefore expose the same routine
 * pages with script-first metadata: lists are read from sys.objects/sys.sql_modules, editing executes
 * CREATE OR ALTER, and testing starts from a generated T-SQL snippet.
 */

/**
 * Builds a T-SQL identifier for a schema-scoped routine.
 */
function mssql_routine_identifier(string $name, ?string $schema = null): string
{
	if ($schema === null) {
		$parts = explode('.', str_replace(['][', '[', ']'], ['.', '', ''], $name), 2);
		if (count($parts) == 2) {
			return idf_escape($parts[0]) . '.' . idf_escape($parts[1]);
		}
		$schema = get_schema();
	}

	return idf_escape($schema) . '.' . idf_escape($name);
}

/**
 * Splits a possibly schema-qualified routine name into schema and object name.
 *
 * @return array{0:string, 1:string}
 */
function mssql_routine_parts(string $name): array
{
	$plain = str_replace(['][', '[', ']'], ['.', '', ''], $name);
	$parts = explode('.', $plain, 2);

	return count($parts) == 2 ? [$parts[0], $parts[1]] : [get_schema(), $name];
}

/**
 * Returns a display-ready SQL Server type declaration from sys.parameters/sys.types metadata.
 */
function mssql_routine_type(array $row): string
{
	$type = $row['type'] ?: '';
	if (preg_match('~^(nchar|nvarchar)$~i', $type)) {
		$length = ((int) $row['max_length'] == -1 ? 'max' : (string) ((int) $row['max_length'] / 2));
		return "$type($length)";
	}
	if (preg_match('~^(char|varchar|binary|varbinary)$~i', $type)) {
		$length = ((int) $row['max_length'] == -1 ? 'max' : (string) (int) $row['max_length']);
		return "$type($length)";
	}
	if (preg_match('~^(decimal|numeric)$~i', $type)) {
		return "$type($row[precision],$row[scale])";
	}
	if (preg_match('~^(datetime2|datetimeoffset|time)$~i', $type) && $row['scale'] !== null) {
		return "$type($row[scale])";
	}

	return $type;
}

/**
 * Returns the SQL Server object types covered by the first routine implementation.
 */
function mssql_routine_types_sql(string $routineType = ''): string
{
	if ($routineType == 'PROCEDURE') {
		return "'P'";
	}
	if ($routineType == 'FUNCTION') {
		return "'FN', 'IF', 'TF'";
	}

	return "'P', 'FN', 'IF', 'TF'";
}

/**
 * Gets information about a stored procedure or function from the current SQL Server schema.
 *
 * @param string $name Routine name, optionally schema-qualified.
 * @param 'FUNCTION'|'PROCEDURE' $type Generic AdminNeo routine type.
 */
function routine($name, $type)
{
	if ($name == '') {
		return [];
	}

	[$schema, $routineName] = mssql_routine_parts($name);
	$result = Connection::get()->query("SELECT o.object_id, o.name, s.name AS schema_name, o.type, sm.definition
FROM sys.objects o
JOIN sys.schemas s ON s.schema_id = o.schema_id
JOIN sys.sql_modules sm ON sm.object_id = o.object_id
WHERE s.name = " . q($schema) . " AND o.name = " . q($routineName) . " AND o.type IN (" . mssql_routine_types_sql($type) . ")");
	$object = is_object($result) ? $result->fetchAssoc() : [];

	if (!$object) {
		return [];
	}

	$fields = [];
	$returns = [];
	foreach (get_rows("SELECT p.parameter_id, p.name AS field, t.name AS type, p.max_length, p.precision, p.scale, p.is_output
FROM sys.parameters p
JOIN sys.types t ON t.user_type_id = p.user_type_id
WHERE p.object_id = " . q($object['object_id']) . "
ORDER BY p.parameter_id") as $parameter) {
		$fullType = mssql_routine_type($parameter);
		$field = [
			'field' => ltrim($parameter['field'], '@'),
			'type' => $parameter['type'],
			'length' => preg_match('~\((.*)\)$~', $fullType, $match) ? $match[1] : '',
			'unsigned' => '',
			'null' => true,
			'full_type' => $fullType,
			'inout' => ($parameter['is_output'] ? 'OUT' : 'IN'),
			'collation' => '',
		];
		if ((int) $parameter['parameter_id'] === 0) {
			$returns = $field;
		} else {
			$fields[] = $field;
		}
	}

	if (!$returns && in_array($object['type'], ['IF', 'TF'], true)) {
		$returns = ['type' => 'TABLE', 'length' => '', 'unsigned' => '', 'collation' => ''];
	}

	return [
		'schema' => $object['schema_name'],
		'name' => $object['name'],
		'object_type' => $object['type'],
		'fields' => $fields,
		'returns' => $returns,
		'definition' => $object['definition'],
		'language' => '',
		'comment' => null,
	];
}

/**
 * Lists T-SQL stored procedures, scalar functions and table-valued functions in the current schema.
 */
function routines()
{
	return get_rows("SELECT
	o.name AS SPECIFIC_NAME,
	o.name AS ROUTINE_NAME,
	CASE WHEN o.type = 'P' THEN 'PROCEDURE' ELSE 'FUNCTION' END AS ROUTINE_TYPE,
	CASE
		WHEN o.type = 'P' THEN ''
		WHEN o.type IN ('IF', 'TF') THEN 'TABLE'
		ELSE COALESCE((
			SELECT TOP (1)
				CASE
					WHEN t.name IN ('nchar', 'nvarchar') THEN t.name + '(' + CASE WHEN p.max_length = -1 THEN 'max' ELSE CONVERT(varchar(20), p.max_length / 2) END + ')'
					WHEN t.name IN ('char', 'varchar', 'binary', 'varbinary') THEN t.name + '(' + CASE WHEN p.max_length = -1 THEN 'max' ELSE CONVERT(varchar(20), p.max_length) END + ')'
					WHEN t.name IN ('decimal', 'numeric') THEN t.name + '(' + CONVERT(varchar(20), p.precision) + ',' + CONVERT(varchar(20), p.scale) + ')'
					ELSE t.name
				END
			FROM sys.parameters p
			JOIN sys.types t ON t.user_type_id = p.user_type_id
			WHERE p.object_id = o.object_id AND p.parameter_id = 0
		), '')
	END AS DTD_IDENTIFIER,
	CAST(NULL AS nvarchar(max)) AS ROUTINE_COMMENT,
	o.type_desc AS TYPE_DESC
FROM sys.objects o
JOIN sys.schemas s ON s.schema_id = o.schema_id
JOIN sys.sql_modules sm ON sm.object_id = o.object_id
WHERE s.name = " . q(get_schema()) . "
  AND o.type IN ('P', 'FN', 'IF', 'TF')
ORDER BY CASE WHEN o.type = 'P' THEN 0 ELSE 1 END, o.name");
}

/**
 * SQL Server routines are T-SQL scripts, so no language selector is needed.
 */
function routine_languages()
{
	return [];
}

/**
 * Returns the schema-qualified escaped identifier for a SQL Server routine.
 */
function routine_id($name, $row)
{
	return mssql_routine_identifier($name, $row['schema'] ?? null);
}

/**
 * Creates NULL placeholders for generated routine test SQL.
 *
 * Stored procedures use named arguments for readability, while SQL Server functions are called
 * positionally. OUTPUT parameters are deliberately skipped in the generated starter SQL.
 */
function mssql_routine_call_parameters(array $routine, bool $named): string
{
	$params = [];
	foreach ($routine['fields'] ?? [] as $field) {
		if (($field['inout'] ?? '') == 'OUT') {
			continue;
		}
		$params[] = ($named ? '@' . ltrim($field['field'], '@') . ' = ' : '') . 'NULL';
	}

	return implode(', ', $params);
}

/**
 * Generates an executable SQL snippet for testing a procedure, scalar function or TVF.
 */
function mssql_routine_test_sql(string $name, array $routine, bool $function): string
{
	$id = routine_id($name, $routine);
	$params = mssql_routine_call_parameters($routine, !$function);
	if (!$function) {
		return 'EXEC ' . $id . ($params ? ' ' . $params : '');
	}
	if (($routine['object_type'] ?? '') === 'FN') {
		return 'SELECT ' . $id . '(' . $params . ') AS result';
	}

	return 'SELECT * FROM ' . $id . '(' . $params . ')';
}

/**
 * Prints the SQL Server routines section below the normal database overview.
 */
function mssql_print_routines_section(): void
{
	if (DIALECT != 'mssql' || DB == '' || $_GET['ns'] === '') {
		return;
	}

	echo "<h2 id='routines'>" . lang('Routines') . "</h2>\n";
	$rows = routines();
	if (!$rows) {
		echo "<p class='message'>" . lang('No rows.') . "\n";
	} else {
		echo "<table>\n<thead><tr><th>" . lang('Name') . "</th><td>" . lang('Type') . "</td><td>" . lang('Return type') . "</td><td></td></tr></thead>\n";
		foreach ($rows as $row) {
			$isFunction = ($row['ROUTINE_TYPE'] != 'PROCEDURE');
			echo '<tr><th><a href="', h(ME . ($isFunction ? 'callf=' : 'call=') . urlencode($row['SPECIFIC_NAME'])), '">', h($row['ROUTINE_NAME']), '</a></th>',
				'<td>', h($row['ROUTINE_TYPE']), '</td>',
				'<td>', h($row['DTD_IDENTIFIER']), '</td>',
				'<td><a href="', h(ME . ($isFunction ? 'function=' : 'procedure=') . urlencode($row['SPECIFIC_NAME'])), '">', lang('Alter'), '</a></td></tr>';
		}
		echo "</table>\n";
	}

	echo '<p class="links"><a href="', h(ME), 'procedure=">', icon('function-add'), lang('Create procedure'), "</a> ",
		'<a href="', h(ME), 'function=">', icon('function-add'), lang('Create function'), "</a></p>\n";
}
