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
		http_response_code($this->status);
		echo json_encode([
			'status' => $this->status,
			'data' => $this->data
		]);
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
