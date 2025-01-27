<?php

namespace Koala\Response;

class JsonResponse implements ResponseInterface
{
	public function __construct(
		protected array $data,
		protected int $status = 200
	) {}

	/**
	 * 
	 * @return void 
	 */
	public function send(): void
	{
		header('Content-Type: application/json');
		$status = $this->data['status'] ?? $this->status;
		$this->data['status'] = $status;
		http_response_code($status);
		echo json_encode($this->data);
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
	 * @return int 
	 */
	public function getStatus(): int
	{
		return $this->status;
	}
}
