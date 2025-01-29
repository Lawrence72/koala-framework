<?php

namespace Koala\Utils;

use ArrayAccess;
use Countable;
use Iterator;
use JsonSerializable;

class Collection implements ArrayAccess, Iterator, Countable, JsonSerializable
{
	protected array $data;

	/**
	 * @param array $data
	 * @return void
	 */
	public function __construct(array $data)
	{
		$this->data = $data;
	}

	/**
	 * @param string $name
	 * @param mixed $value
	 * @return void
	 */
	public function __set(string $name, mixed $value): void
	{
		$this->data[$name] = $value;
	}

	/**
	 * @param string $name
	 * @return mixed
	 */
	public function __get(string $name): mixed
	{
		return $this->data[$name] ?? null;
	}

	/**
	 * @param mixed $offset
	 * @return mixed
	 */
	public function offsetGet(mixed $offset): mixed
	{
		$value = $this->data[$offset] ?? null;
		if (is_array($value)) {
			return new Collection($value);
		}
		return $value;
	}

	/**
	 * @param mixed $offset
	 * @param mixed $value
	 * @return void
	 */
	public function offsetSet(mixed $offset, mixed $value): void
	{
		if (is_null($offset)) {
			$this->data[] = $value;
		} else {
			$this->data[$offset] = $value;
		}
	}

	/**
	 * @param mixed $offset
	 * @return bool
	 */
	public function offsetExists(mixed $offset): bool
	{
		return isset($this->data[$offset]);
	}

	/**
	 * @param mixed $offset
	 * @return void
	 */
	public function offsetUnset(mixed $offset): void
	{
		unset($this->data[$offset]);
	}

	/**
	 * @return mixed
	 */
	public function current(): mixed
	{
		$value = current($this->data);
		if (is_array($value)) {
			return new Collection($value);
		}
		return $value;
	}

	/**
	 * @return int|string|null
	 */
	public function key(): int|string|null
	{
		return key($this->data);
	}

	/**
	 * @return void
	 */
	public function next(): void
	{
		next($this->data);
	}

	/**
	 * @return void
	 */
	public function rewind(): void
	{
		reset($this->data);
	}

	/**
	 * @return bool
	 */
	public function valid(): bool
	{
		return key($this->data) !== null;
	}

	/**
	 * @return array
	 */
	public function jsonSerialize(): array
	{
		return $this->data;
	}

	/**
	 * 
	 * @return int 
	 */
	public function count(): int
	{
		return count($this->data);
	}

	/**
	 * @param string $name
	 * @return bool
	 */
	public function __isset(string $name): bool
	{
		if (!isset($this->data[$name])) {
			return false;
		}

		$value = $this->data[$name];

		if ($value === null) {
			return false;
		}

		if (is_string($value)) {
			return $value !== '' && $value !== '0';
		}

		if (is_numeric($value)) {
			return $value != 0;
		}

		if (is_bool($value)) {
			return $value;
		}

		if (is_array($value)) {
			return !empty($value);
		}

		if (is_object($value) && $value instanceof Countable) {
			return count($value) > 0;
		}

		return true;
	}
}
