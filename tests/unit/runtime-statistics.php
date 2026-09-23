<?php

namespace AdminNeo;

require __DIR__ . "/../../admin/core/Driver.php";

class RuntimeStatisticsDriver extends Driver
{
	public function __construct()
	{
	}
}

$driver = new RuntimeStatisticsDriver();
$tests = [
	"SELECT 1" => true,
	"  /* comment */ -- comment\nSELECT * FROM test" => true,
	"(SELECT 1)" => true,
	"WITH changed AS (DELETE FROM test RETURNING *) SELECT * FROM changed" => false,
	"UPDATE test SET value = 1" => false,
	"DELETE FROM test" => false,
	"INSERT INTO test VALUES (1)" => false,
];

$errors = 0;
foreach ($tests as $query => $expected) {
	$actual = $driver->isRuntimeStatisticsQuery($query);
	if ($actual !== $expected) {
		echo "⚠️ " . str_replace("\n", " ", $query) . " results in " . ($actual ? "true" : "false") . "\n";
		$errors++;
	}
}

exit($errors ? 1 : 0);