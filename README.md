# IronFlow Framework

![IronFlow](https://raw.githubusercontent.com/ironflow-framework/framework/main/.github/assets/logo.png)

The technical core of IronFlow: dependency container, HMVC modules, HTTP routing, CLI, ORM, security, and application services.

[![CI](https://img.shields.io/github/actions/workflow/status/ironflow-framework/framework/ci.yml?branch=main&style=flat-square&label=CI)](https://github.com/ironflow-framework/framework/actions/workflows/ci.yml)
[![PHP 8.2+](https://img.shields.io/badge/PHP-8.2%2B-777BB4?style=flat-square&logo=php&logoColor=white)](https://php.net)
[![License MIT](https://img.shields.io/badge/license-MIT-22c55e?style=flat-square)](LICENSE)
[![Version](https://img.shields.io/badge/version-2.0.0-6366f1?style=flat-square)](https://github.com/ironflow-framework/framework/releases)

This repository contains the framework core. It is not a ready-to-use application project: for starting an application, it is recommended to use the associated skeleton repository.

## Table of contents

- [About](#about)
- [Requirements](#requirements)
- [Installation](#installation)
- [Framework architecture](#framework-architecture)
- [Container and dependency injection](#container-and-dependency-injection)
- [Modules](#modules)
- [HTTP routing](#http-routing)
- [ORM and database](#orm-and-database)
- [Security and middleware](#security-and-middleware)
- [CLI](#cli)
- [Repository structure](#repository-structure)
- [Testing and quality](#testing-and-quality)
- [Contributing](#contributing)

---

## About

IronFlow is a PHP 8.2+ framework designed for modular, scalable, and testable applications. The project core includes the following building blocks:

- dependency container with automatic resolution via reflection;
- module system with explicit dependencies;
- HTTP kernel and console kernel;
- router with groups, named routes, and middleware;
- lightweight ORM based on models, relationships, and migrations;
- authentication and authorization system;
- Twig template engine;
- event handling, jobs, queue workers, and scheduling.

The framework follows a modern architectural direction: clear separation of concerns, explicit dependencies, isolated modules, and service injection instead of global resolution.

---

## Requirements

- PHP 8.2 or newer
- Composer 2+
- Common extensions: `pdo`, `mbstring`, `json`
- An application project using the recommended skeleton

> The framework core is distributed as a Composer package. The skeleton remains the simplest way to bootstrap a complete and coherent application.

---

## Installation

### Recommended option: use the skeleton

```bash
composer create-project ironflow/skeleton mon-app
```

### Direct package option

```bash
composer require ironflow/framework
```

---

## Framework architecture

The core is structured around a few central classes:

- `Application`: application runtime entry point; loads the environment, initializes base services, and boots modules;
- `Container`: IoC container with binding, singleton support, automatic instantiation, and inter-module access validation;
- `ModuleManager`: registers modules, validates imports, sorts them topologically, and triggers the `register()` and `boot()` phases;
- `Router`: manages HTTP routes, middleware, groups, and resources;
- `Kernel`: HTTP and console kernel;
- `Model`: base model class for application data objects, schema access, relations, and write operations;
- `Template\Engine`: Twig rendering engine;
- `Dispatcher`: internal event bus.

### Folder structure

```text
src/
├── Application.php
├── Container.php
├── Console/
├── Database/
├── Http/
├── Module/
├── Routing/
├── Middleware/
├── Template/
├── Auth/
├── Events/
├── Queue/
├── Support/
└── ...

tests/
├── Unit/
├── Feature/
└── ...
```

---

## Container and dependency injection

IronFlow relies on a dependency container that resolves services through reflection on PHP types. It supports:

- automatic resolution by type hint;
- explicit binding via `bind()` or `singleton()`;
- pre-created instances via `instance()`;
- named injection via the `#[Inject('...')]` attribute;
- access validation between modules based on declared exports.

Example:

```php
use Ironflow\Attributes\Inject;
use Ironflow\Events\Dispatcher;

class PostService
{
    public function __construct(
        private readonly PostRepository $posts,
        private readonly Dispatcher $events,
        #[Inject('config.app.name')] private readonly string $appName,
    ) {
    }
}
```

The container also handles transitive dependencies and contextual resolution based on the calling module, which helps protect access to module providers.

---

## Modules

A module is the main building block of IronFlow. Each module is declared via the `#[Module]` attribute and has a two-phase lifecycle:

1. `register()`: register bindings and module services;
2. `boot()`: load routes, Twig namespaces, event listeners, and other runtime elements.

Example:

```php
use Ironflow\Module\Attributes\Module;
use Ironflow\Module\BaseModule;

#[Module(
    name: 'blog',
    imports: [AuthModule::class],
    providers: [PostService::class],
    exports: [PostService::class],
    listeners: [
        PostPublished::class => [SendNewsletterListener::class],
    ],
)]
class BlogModule extends BaseModule
{
}
```

### Module validation

The `ModuleManager` validates:

- missing dependencies (`imports` not registered);
- dependency cycles;
- boot order using topological sorting.

This ensures a module only starts when its dependencies are correctly declared and resolvable.

---

## HTTP routing

IronFlow’s router is fluent and framework-oriented. It supports:

- standard HTTP methods (`GET`, `POST`, `PUT`, `PATCH`, `DELETE`);
- route groups with `prefix`, `middleware`, and `namespace`;
- REST resource management;
- URL generation by route name;
- controller parameter resolution and HTTP request handling.

Example:

```php
$router->group(['prefix' => '/admin', 'middleware' => ['auth']], function () use ($router) {
    $router->get('/posts', [PostController::class, 'index'])->name('posts.index');
    $router->post('/posts', [PostController::class, 'store'])->name('posts.store');
});
```

Routing also relies on the container to instantiate controllers and inject the required dependencies.

---

## ORM and database

IronFlow includes a model-oriented ORM without exposing a generic query builder as the primary design layer. Model classes can define attributes, casts, relationships, and scopes.

Example:

```php
class Post extends Model
{
    protected string $table = 'posts';

    protected array $fillable = ['title', 'body', 'author_id'];

    protected array $casts = [
        'published_at' => 'datetime',
        'metadata' => 'json',
    ];
}
```

Included features:

- relationships such as `hasMany`, `belongsTo`, `belongsToMany`, `hasOne`, and `hasManyThrough`;
- query scopes;
- eager loading;
- migrations;
- soft deletes;
- support for PHP enums when applicable.

---

## Security and middleware

The framework exposes a shared middleware pipeline for HTTP requests. Middlewares can be declared in a classic style (`handle`) or using a hook pattern (`processRequest`, `processResponse`, `processException`).

This enables handling:

- authentication;
- CORS;
- CSRF;
- data sanitization;
- sessions;
- throttling;
- HTTP header protection.

Security configuration is typed through `ShieldConfig`, and HTTP concerns are separated from application logic to keep the system more readable and maintainable.

---

## CLI

The core includes a set of Symfony Console commands useful for generating modules, managing migrations, inspecting routes, and starting the development server.

Typical commands:

```bash
php forge list
php forge serve
php forge make:module Blog
php forge make:controller PostController --module=Blog --resource
php forge make:model Post --module=Blog --migration
php forge route:list
php forge module:graph
php forge migrate
php forge migrate --fresh --seed
```

The command system is extensible and can also register module-specific commands.

---

## Repository structure

```text
.
├── CHANGELOG.md
├── composer.json
├── LICENSE
├── phpstan.neon
├── phpunit.xml
├── README.md
├── src/
├── tests/
└── vendor/
```

This repository is a framework package. For a complete application project, it is preferable to use the corresponding skeleton, which provides the base runtime structure, modules, and configuration files.

---

## Testing and quality

The project is configured for automated validation:

```bash
composer test
composer analyse
```

The scripts are defined in `composer.json`, including:

- `pest` for tests;
- `phpstan` for static analysis;
- a quality standard consistent with a modern PHP framework core.

---

## Contributing

Contributions are welcome in line with the project conventions:

- PHP 8.2+ code with strict typing;
- PSR-12 and coherent conventions;
- tests for functional changes;
- respect for compatibility and versioning semantics;
- explicit PRs with a clear description of changes.

### Recommended workflow

```bash
git clone https://github.com/ironflow-framework/framework.git
cd framework
composer install
composer test
```

Then open a PR with a concise description of the feature or bug fix.

---

IronFlow aims to provide a solid foundation for modular, testable, and maintainable PHP applications without relying on excessive abstractions or opaque conventions.
