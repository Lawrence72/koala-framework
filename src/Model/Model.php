<?php

namespace Koala\Model;

use Koala\Application;
use Koala\Utils\Collection;
use RuntimeException;

class Model
{
	protected array|Collection $data = [];
	protected array $modified_fields = [];
	protected string $table_name = '';
	protected string $primary_key = 'id';
	protected array $selected_fields = [];
	protected array $default_fields = [];
	protected string $where_clause = '';
	protected array $where_params = [];
	protected string $order_by = '';
	protected array $group_by = [];
	protected string $having = '';
	protected ?int $limit_value = null;
	protected ?int $offset_value = null;
	protected ?string $query_type = null;

	const QUERY_TYPE_LOAD = 'load';
	const QUERY_TYPE_FIND = 'find';
	const QUERY_TYPE_SAVE = 'save';

	public function __construct(protected Application $app)
	{
		if (empty($this->table_name)) {
			throw new \RuntimeException('Model must define table_name');
		}

		$this->selected_fields = array_unique(array_merge([$this->primary_key], $this->default_fields));
	}

	/**
	 * 
	 * @return string 
	 */
	public function getTableName(): string
	{
		return $this->table_name;
	}

	/**
	 * 
	 * @return string 
	 */
	public function getPrimaryKey(): string
	{
		return $this->primary_key;
	}

	/**
	 * 
	 * @return array 
	 */
	public function getData(): array
	{
		return $this->data;
	}

	/**
	 * 
	 * @param array $fields 
	 * @return Model 
	 */
	public function fields(array $fields): self
	{
		$this->selected_fields = array_unique(array_merge([$this->primary_key], $fields));
		return $this;
	}

	/**
	 * 
	 * @param string $where 
	 * @param array $params 
	 * @return Model 
	 * @throws RuntimeException 
	 */
	public function load(string $where, array $params = []): self
	{
		if ($this->query_type !== null) {
			throw new \RuntimeException('Cannot call load() after ' . $this->query_type . '()');
		}
		$this->where_clause = $where;
		$this->where_params = $params;
		$this->limit_value = 1;
		$this->query_type = self::QUERY_TYPE_LOAD;
		return $this;
	}

	/**
	 * 
	 * @param string $where 
	 * @param array $params 
	 * @return Model 
	 * @throws RuntimeException 
	 */
	public function find(string $where = '', array $params = []): self
	{
		if ($this->query_type !== null) {
			throw new \RuntimeException('Cannot call find() after ' . $this->query_type . '()');
		}
		$this->where_clause = $where;
		$this->where_params = $params;
		$this->query_type = self::QUERY_TYPE_FIND;
		return $this;
	}

	/**
	 * 
	 * @param string $order_clause 
	 * @return Model 
	 */
	public function order(string $order_clause): self
	{
		$this->order_by = $order_clause;
		return $this;
	}

	/**
	 * 
	 * @param string $columns 
	 * @return Model 
	 */
	public function group(string $columns): self
	{
		$this->group_by = $columns;
		return $this;
	}

	/**
	 * 
	 * @param string $condition 
	 * @return Model 
	 */
	public function having(string $condition): self
	{
		$this->having = $condition;
		return $this;
	}

	/**
	 * 
	 * @param int $limit 
	 * @param null|int $offset 
	 * @return Model 
	 * @throws RuntimeException 
	 */
	public function limit(int $limit, ?int $offset = null): self
	{
		if ($this->query_type === self::QUERY_TYPE_LOAD) {
			throw new \RuntimeException('Cannot set limit on load()');
		}
		$this->limit_value = $limit;
		$this->offset_value = $offset;
		return $this;
	}


	/**
	 * 
	 * @return Collection|array|null 
	 * @throws RuntimeException 
	 */
	public function execute(): Collection|array|null
	{
		if ($this->query_type === null) {
			throw new \RuntimeException('Must call either load() or find() before execute()');
		}
		if ($this->query_type === self::QUERY_TYPE_SAVE) {
			throw new \RuntimeException('Cannot call execute() after save()');
		}
		$columns = implode(', ', $this->selected_fields);
		$sql = "SELECT {$columns} FROM {$this->table_name}";

		if (!empty($this->where_clause)) {
			$sql .= " WHERE {$this->where_clause}";
		}

		if ($this->group_by) {
			$sql .= " GROUP BY {$this->group_by}";
		}

		if ($this->having) {
			$sql .= " HAVING {$this->having}";
		}

		if ($this->order_by) {
			$sql .= " ORDER BY {$this->order_by}";
		}

		if ($this->limit_value !== null) {
			$sql .= " LIMIT {$this->limit_value}";
			if ($this->offset_value !== null) {
				$sql .= " OFFSET {$this->offset_value}";
			}
		}

		if ($this->query_type === self::QUERY_TYPE_LOAD) {
			$result = $this->app->database->fetchRow($sql, $this->where_params);
			if ($result) {
				$this->data = $result;
			}
			return $result;
		}

		return $this->app->database->fetchAll($sql, $this->where_params);
	}

	/**
	 * 
	 * @return Model 
	 * @throws RuntimeException 
	 */
	public function save(): self
	{
		if ($this->query_type !== null) {
			throw new \RuntimeException('Cannot call save() after ' . $this->query_type . '()');
		}
		$this->query_type = self::QUERY_TYPE_SAVE;
		if (isset($this->data[$this->primary_key])) {
			$sets = [];
			$params = [];
			foreach ($this->modified_fields as $field) {
				$sets[] = "{$field} = ?";
				$params[] = $this->data[$field];
			}

			if (!empty($sets)) {
				$params[] = $this->data[$this->primary_key];
				$sql = "UPDATE {$this->table_name} SET " .
					implode(', ', $sets) .
					" WHERE {$this->primary_key} = ?";
				$this->app->database->runQuery($sql, $params);
			}
		} else {
			$fields = array_keys($this->data);
			$placeholders = array_fill(0, count($fields), '?');
			$params = array_values($this->data);

			$sql = "INSERT INTO {$this->table_name} (" .
				implode(', ', $fields) .
				") VALUES (" .
				implode(', ', $placeholders) . ")";
			$this->app->database->runQuery($sql, $params);
		}

		return $this;
	}

	/**
	 * 
	 * @return bool 
	 */
	public function isHydrated(): bool
	{
		return !empty($this->data) && isset($this->data[$this->primary_key]);
	}

	/**
	 * 
	 * @param string $name 
	 * @return mixed 
	 */
	public function __get(string $name)
	{
		return $this->data[$name] ?? null;
	}

	/**
	 * 
	 * @param string $name 
	 * @param mixed $value 
	 * @return void 
	 */
	public function __set(string $name, $value)
	{
		$this->data[$name] = $value;
		$this->modified_fields[] = $name;
	}
}
