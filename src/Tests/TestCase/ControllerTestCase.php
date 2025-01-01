<?php

namespace Koala\Tests\TestCase;

use Koala\Application;
use Koala\Request\Request;
use Koala\Response\Response;
use Koala\Response\JsonResponse;
use Koala\Response\SimpleResponse;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Koala\Tests\Traits\DatabaseTestTrait;

abstract class ControllerTestCase extends TestCase
{
    use DatabaseTestTrait;

    protected Application $app;
    protected Request $request;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app = new Application();
        $this->request = new Request();
        $this->setupMockDatabase();
    }

    protected function createMockResponse(): Response&MockObject
    {
        return $this->createMock(Response::class);
    }

    protected function mockViewResponse(Response&MockObject $mockResponse, string $template, array $data, SimpleResponse $returnValue): void
    {
        $mockResponse->method('view')
            ->with($template, $data)
            ->willReturn($returnValue);
    }

    protected function mockJsonResponse(Response&MockObject $mockResponse, array $data, ?int $status = 200): void
    {
        $mockJsonResponse = $this->createMock(JsonResponse::class);

        $mockResponse->method('json')
            ->with(
                $this->equalTo($data),
                $this->equalTo($status)
            )
            ->willReturn($mockJsonResponse);
    }
}
