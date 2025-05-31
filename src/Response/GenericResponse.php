<?php

namespace Koala\Response;

class GenericResponse implements ResponseInterface
{
	protected array $headers = [];
	protected string $content;
	protected int $status;

	public function __construct(string $content = '', int $status = 200)
	{
		$this->content = $content;
		$this->status = $status;
	}

	public function setHeader(string $name, string $value): self
	{
		$this->headers[$name] = $value;
		return $this;
	}

	public function setStatusCode(int $status): self
	{
		$this->status = $status;
		return $this;
	}

	public function setContent(string $content): self
	{
		$this->content = $content;
		return $this;
	}

	public function send(): void
	{
		foreach ($this->headers as $name => $value) {
			header($name . ': ' . $value);
		}
		http_response_code($this->status);
		echo $this->content;
	}
}
