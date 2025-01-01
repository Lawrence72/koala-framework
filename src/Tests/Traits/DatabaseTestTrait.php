<?php

namespace Koala\Tests\Traits;

use Koala\Database\Database;
use PHPUnit\Framework\MockObject\MockObject;

trait DatabaseTestTrait
{
    /** @var Database&MockObject */
    protected Database $mockDatabase;

    protected function setupMockDatabase(): void
    {
        $this->mockDatabase = $this->createMock(Database::class);
    }

    protected function mockDatabaseFetchAll(string $query, array $returnValue): void
    {
        $this->mockDatabase
            ->expects($this->once())
            ->method('fetchAll')
            ->with($query)
            ->willReturn($returnValue);
    }

    protected function mockDatabaseFetchRow(string $query, array $params, mixed $returnValue): void
    {
        $this->mockDatabase
            ->expects($this->once())
            ->method('fetchRow')
            ->with($query, $params)
            ->willReturn($returnValue);
    }

    protected function injectMockDatabase(object $instance): void
    {
        $reflection = new \ReflectionClass($instance);
        $databaseProperty = $reflection->getProperty('database');
        $databaseProperty->setAccessible(true);
        $databaseProperty->setValue($instance, $this->mockDatabase);
    }
}
