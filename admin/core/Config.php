<?php

namespace AdminNeo;

class Config
{
	public const NavigationSimple = "simple";
	public const NavigationDual = "dual";
	public const NavigationReversed = "reversed";

	/** @var array */
	private $params;

	/** @var Server[] */
	private $servers = [];

	public function __construct(array $params)
	{
		$this->params = $params; // !compile: custom config

		if (isset($this->params["servers"])) {
			foreach ($this->params["servers"] as $key => $server) {
				$serverObj = new Server($server, is_string($key) ? $key : null);

				$this->params["servers"][$key] = $serverObj;
				$this->servers[$serverObj->getKey()] = $serverObj;
			}
		}
	}

	public function getTheme(): string
	{
		return $this->params["theme"] ?? "default";
	}

	public function getColorVariant(): string
	{
		return $this->params["colorVariant"] ?? "blue";
	}

	/**
	 * @return string[]
	 */
	public function getCssUrls(): array
	{
		return $this->parseList($this->params["cssUrls"] ?? []);
	}

	/**
	 * @return string[]
	 */
	public function getJsUrls(): array
	{
		return $this->parseList($this->params["jsUrls"] ?? []);
	}

	public function getNavigationMode(): string
	{
		return $this->params["navigationMode"] ?? self::NavigationSimple;
	}

	/**
	 * @deprecated
	 */
	public function isNavigationSimple(): bool
	{
		return $this->getNavigationMode() == self::NavigationSimple;
	}

	/**
	 * @deprecated
	 */
	public function isNavigationDual(): bool
	{
		return $this->getNavigationMode() == self::NavigationDual;
	}

	/**
	 * @deprecated
	 */
	public function isNavigationReversed(): bool
	{
		return $this->getNavigationMode() == self::NavigationReversed;
	}

	public function isSelectionPreferred(): bool
	{
		return $this->params["preferSelection"] ?? false;
	}

	public function isJsonValuesDetection(): bool
	{
		return $this->params["jsonValuesDetection"] ?? false;
	}

	public function isJsonValuesAutoFormat(): bool
	{
		return $this->params["jsonValuesAutoFormat"] ?? false;
	}

	public function isRelationLinks(): bool
	{
		return $this->params["relationLinks"] ?? false;
	}

	public function getRecordsPerPage(): int
	{
		return (int)($this->params["recordsPerPage"] ?? 50);
	}

	public function getEnumAsSelectThreshold(): ?int
	{
		if (array_key_exists("enumAsSelectThreshold", $this->params)) {
			return $this->params["enumAsSelectThreshold"] !== null ? (int)$this->params["enumAsSelectThreshold"] : null;
		} else {
			return 5;
		}
	}

	public function isVersionVerificationEnabled(): bool
	{
		return $this->params["versionVerification"] ?? true;
	}

	public function isSqlAutocompletionEnabled(): bool
	{
		return $this->params["sqlAutocompletion"] ?? true;
	}

	/**
	 * Whether mid-render output flushing (ob_flush()/flush()) is allowed.
	 *
	 * Streaming progress feedback (e.g. the running query or the query kill-timeout script) to the
	 * browser requires flushing. When AdminNeo is embedded and its output is captured by a host
	 * application, an explicit flush() prematurely commits the HTTP headers (mod_php sends them on
	 * every flush()), which breaks the host's own response. Set to false to buffer the whole page.
	 */
	public function isOutputFlushingEnabled(): bool
	{
		return $this->params["outputFlushing"] ?? true;
	}

	/**
	 * Whether copying a table also copies its data.
	 *
	 * Only affects drivers that support disabling it (currently MS SQL). When false, a copy creates
	 * the table structure (columns, primary key) without any rows.
	 */
	public function isCopyDataEnabled(): bool
	{
		return $this->params["copyData"] ?? true;
	}

	/**
	 * Whether the seed (start value) of a new MS SQL IDENTITY column is taken from that column's
	 * default value in the table designer. Leave the column's "default" dropdown empty so the value
	 * is consumed as the seed here instead of being emitted as a DEFAULT clause.
	 */
	public function isIdentitySeedFromDefaultEnabled(): bool
	{
		return $this->params["identitySeedFromDefault"] ?? false;
	}

	/**
	 * Whether drivers should generate explicit, deterministic names for new constraints and indexes.
	 *
	 * The default is false to preserve upstream AdminNeo behaviour. Embedded IDE configurations can
	 * enable this to produce migration-friendly DDL such as PK_table, UQ_table_column,
	 * IX_table_column, FK_table_target and DF_table_column instead of database-generated names.
	 */
	public function isUseNamedConstraintsEnabled(): bool
	{
		return $this->params["useNamedConstraints"] ?? false;
	}

	public function getHiddenDatabases(): array
	{
		return $this->parseList($this->params["hiddenDatabases"] ?? []);
	}

	public function getHiddenSchemas(): array
	{
		return $this->parseList($this->params["hiddenSchemas"] ?? []);
	}

	public function getVisibleCollations(): array
	{
		return $this->parseList($this->params["visibleCollations"] ?? []);
	}

	public function getDefaultDriver(array $drivers): string
	{
		$driver = $this->params["defaultDriver"] ?? null;

		return $driver && isset($drivers[$driver]) ? $driver : key($drivers);
	}

	public function getDefaultServer(): ?string
	{
		$server = $this->params["defaultServer"] ?? null;
		if ($server === null) {
			return null;
		}

		/** @var Server $serverObj */
		$serverObj = $this->params["servers"][$server] ?? null;
		if ($serverObj) {
			return $serverObj->getKey();
		}

		return $server;
	}

	public function getDefaultDatabase(): ?string
	{
		return $this->params["defaultDatabase"] ?? null;
	}

	public function getDefaultPasswordHash(): ?string
	{
		return $this->params["defaultPasswordHash"] ?? null;
	}

	public function getSslKey(): ?string
	{
		return $this->params["sslKey"] ?? null;
	}

	public function getSslCertificate(): ?string
	{
		return $this->params["sslCertificate"] ?? null;
	}

	public function getSslCaCertificate(): ?string
	{
		return $this->params["sslCaCertificate"] ?? null;
	}

	public function getSslTrustServerCertificate(): ?bool
	{
		return $this->params["sslTrustServerCertificate"] ?? null;
	}

	public function getSslEncrypt(): ?bool
	{
		return $this->params["sslEncrypt"] ?? null;
	}

	public function getSslMode(): ?string
	{
		return $this->params["sslMode"] ?? null;
	}

	public function hasServers(): bool
	{
		return isset($this->params["servers"]);
	}

	/**
	 * @return string[]
	 */
	public function getServerPairs(array $drivers): array
	{
		$singleDriver = null;

		foreach ($this->servers as $server) {
			if (!isset($drivers[$server->getDriver()])) {
				continue;
			}

			if (!$singleDriver) {
				$singleDriver = $server->getDriver();
			} elseif ($server->getDriver() != $singleDriver) {
				$singleDriver = null;
				break;
			}
		}

		$serverPairs = [];

		foreach ($this->servers as $key => $server) {
			if (!isset($drivers[$server->getDriver()])) {
				continue;
			}

			$serverName = $server->getName();

			if ($singleDriver && $serverName) {
				$serverPairs[$key] = $serverName;
			} else {
				$serverPairs[$key] = $drivers[$server->getDriver()] . ($serverName != "" ? " - $serverName" : "");
			}
		}

		return $serverPairs;
	}

	public function getServer(string $serverKey): ?Server
	{
		return $this->servers[$serverKey] ?? null;
	}

	public function applyServer(string $server): void
	{
		$server = $this->getServer($server);
		if (!$server) {
			return;
		}

		$this->params = array_merge($this->params, $server->getConfigParams());
	}

	private function parseList($list): array
	{
		if (is_array($list)) {
			return $list;
		}

		return preg_split('~\s*,\s*~', (string)$list);
	}
}

if (!function_exists(__NAMESPACE__ . '\\adminneo_named_constraint_name')) {
	/**
	 * Builds deterministic, migration-friendly constraint/index names for SQL drivers.
	 *
	 * The generated name consists of the requested prefix, table name and either the participating
	 * columns or the referenced table. Non-identifier characters are normalized to underscores and a
	 * stable hash suffix is appended if the result would exceed the active database's identifier
	 * length limit. Including the table part keeps names unique enough for multi-schema databases
	 * where each schema has its own constraint namespace.
	 */
	function adminneo_named_constraint_name(string $prefix, string $table, array $columns = [], string $targetTable = ""): string
	{
		$parts = [$prefix, $table];
		if ($targetTable !== "") {
			$parts[] = $targetTable;
		} else {
			$parts = array_merge($parts, $columns);
		}
		$name = preg_replace('~_+~', '_', trim(preg_replace('~[^A-Za-z0-9]+~', '_', implode('_', $parts)), '_'));
		$maxLength = (defined(__NAMESPACE__ . '\\DIALECT') && DIALECT == 'pgsql' ? 63 : (defined(__NAMESPACE__ . '\\DIALECT') && DIALECT == 'mssql' ? 128 : 64));
		if (strlen($name) > $maxLength) {
			$hash = substr(sha1($name), 0, 8);
			$name = substr($name, 0, $maxLength - 9) . '_' . $hash;
		}
		return $name;
	}
}
