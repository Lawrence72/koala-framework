<?php

namespace Koala\Logic;

use Koala\Application;
use Koala\Database\Database;

abstract class BaseLogic
{
    protected Database $database;

    public function __construct(protected Application $app)
    {
        $this->database = $app->Database;
    }
}
