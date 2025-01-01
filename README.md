# Koala Framework

A lightweight PHP framework focusing on simplicity and flexibility. It provides a robust foundation for building web applications with features like dependency injection, routing, database abstraction, and middleware support.

## Table of Contents
- [Introduction](#introduction)
- [Getting Started](#getting-started)
- [Core Concepts](#core-concepts)
- [Components](#components)
- [Usage Guide](#usage-guide)

## Getting Started

### Installation
```bash
composer require lawrence72/koala-framework
```

### Basic Setup
1. Create an entry point (public/index.php):
```php
<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Koala\Application;
use App\Config\Routes;

$app = new Application();
Routes::register($app->getRouter());
$app->start(__DIR__ . '/../config.php');
```

2. Create a configuration file (config.php):
```php
<?php
return [
    'paths' => [
        'base_directory' => __DIR__,
        'app_directory' => __DIR__ . '/app'
    ],
    'database' => [
        'driver' => 'mysql',
        'host' => 'localhost',
        'port' => 3306,
        'dbname' => 'your_database',
        'username' => 'your_username',
        'password' => 'your_password',
        'charset' => 'utf8mb4'
    ],
    'autoload' => [
        'paths' => [
            'Controllers',
            'Logic',
            'Middleware'
        ]
    ]
];
```

## Core Concepts

### Application Structure
The framework follows a modular structure with clear separation of concerns:
- Controllers: Handle HTTP requests
- Logic: Business logic layer
- Middleware: Request/Response filters
- Config: Application configuration
- Database: Data access layer
- Router: URL routing
- Container: Dependency injection

### Dependency Injection
The framework uses a service container for dependency management:
```php
class YourController {
    public function __construct(
        protected Application $app,
        protected YourLogic $logic
    ) {}
}
```

### Routing
Routes can be defined with groups and middleware:
```php
public static function register(Router $router): void
{
    $router->group('/users', function ($router) {
        $router->addRoute('GET', '', UserController::class, 'index');
        $router->addRoute('GET', '/@id[0-9]', UserController::class, 'show');
    }, ['middleware' => [[AuthMiddleware::class, 'handle']]]);
}
```

## Components

### Database
Supports multiple database drivers:
- MySQL
- PostgreSQL
- SQLite
- SQL Server

#### Data Retrieval Methods
- `fetchAll(string $sql, array $params = [])`: Fetch multiple rows
- `fetchRow(string $sql, array $params = [])`: Fetch a single row
- `fetchField(string $sql, array $params = [])`: Fetch single field from first row

#### Data Modification
- `runQuery(string $sql, array $params = [])`: Execute any query (INSERT, UPDATE, DELETE, etc.)

#### Examples:
```php
// Fetch all users
$users = $this->database->fetchAll("SELECT * FROM users");

// Fetch single user
$user = $this->database->fetchRow("SELECT * FROM users WHERE id = ?", [$id]);

// Fetch just the email field
$email = $this->database->fetchField("SELECT email FROM users WHERE id = ?", [$id]);

// Insert new user
$this->database->runQuery(
    "INSERT INTO users (name, email) VALUES (?, ?)",
    ['John Doe', 'john@example.com']
);
```

### Request Handling

#### GET Parameters
- `getQueryParams()`: Get all GET parameters as array
- `getQueryParam($key, $default = null)`: Get specific GET parameter by key

#### POST Data
- `getPostParams()`: Get all POST data as array
- `getPostParam($key, $default = null)`: Get specific POST parameter by key

#### JSON Data
- `getJsonParams()`: Get all JSON data as array
- `getJsonParam($key, $default = null)`: Get specific JSON parameter by key

#### Example Usage:
```php
// Get specific parameters
$userId = $request->getQueryParam('user_id', 0);  // with default value
$name = $request->getPostParam('name');
$email = $request->getJsonParam('email');

// Get all parameters
$allQueryParams = $request->getQueryParams();
$allPostData = $request->getPostParams();
```

### Utility Classes

#### Session Management
```php
use Koala\Utils\Session;

// Basic usage
$this->session->set('user_id', 123);
$userId = $this->session->get('user_id');

// Flash messages
$this->session->setFlash('Profile updated!', 'success');
$messages = $this->session->getFlash();
```

#### Cookie Management
```php
use Koala\Utils\Cookie;

// Basic usage
$cookie->set('user_pref', 'dark_mode', 3600);  // 1 hour expiry
$preference = $cookie->get('user_pref');

// Advanced options
$cookie->set(
    'user_token',
    'abc123',
    3600,        // Time
    '/',        // Path
    'domain.com', // Domain
    true,      // Secure
    true       // HTTP Only
);
```

#### Data Sanitization
```php
use Koala\Utils\Sanitize;

$sanitizer = new Sanitize();

// Basic string cleaning
$clean = $sanitizer->clean($userInput);

// Allow specific HTML tags
$allowedTags = ['b', 'i', 'a'];
$cleanHtml = $sanitizer->clean($userInput, $allowedTags);
```

The sanitizer handles:
- String trimming
- HTML special characters
- Allowed HTML tags
- Non-printable characters removal
- Nested arrays and objects
- Custom character encoding

## Usage Examples

### Creating a Controller
```php
namespace App\Controllers;

use Koala\Request\Request;
use Koala\Response\Response;
use Koala\Response\ResponseInterface;

class UserController {
    public function index(Request $request, Response $response, $args): ResponseInterface
    {
        return $response->view('users/index', [
            'users' => $this->logic->getAllUsers()
        ]);
    }
}
```

### Creating Business Logic
```php
namespace App\Logic;

use Koala\Logic\BaseLogic;

class UserLogic extends BaseLogic {
    public function getAllUsers(): array {
        return $this->database->fetchAll("SELECT * FROM users");
    }
}
```

### Implementing Middleware
```php
namespace App\Middleware;

use Koala\Request\Request;

class AuthMiddleware {
    public function handle(Request $request, callable $next) {
        // Authentication logic
        return $next();
    }
}
```
