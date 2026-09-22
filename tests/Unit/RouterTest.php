<?php

declare(strict_types=1);

namespace Ironflow\Tests\Unit;

use Ironflow\Container;
use Ironflow\Exceptions\HttpException;
use Ironflow\Routing\Route;
use Ironflow\Routing\Router;
use Ironflow\Tests\Unit\Fixtures\RouterArticleStub;

// ── Tests ─────────────────────────────────────────────────────────────────────

beforeEach(function () {
    $this->router = new Router(new Container());
});

test('GET route registered and retrievable by name', function () {
    $this->router->get('/hello', fn () => 'hi')->name('hello');
    $route = $this->router->getRoutes()->getByName('hello');
    expect($route)->not->toBeNull();
    expect($route->getUri())->toBe('/hello');
});

test('route matches URI and extracts params', function () {
    $route  = new Route('GET', '/users/{id}', fn () => null);
    $params = $route->match('/users/42');
    expect($params)->toBeArray();
    expect($params['id'])->toBe('42');
});

test('route returns null on no match', function () {
    $route = new Route('GET', '/users/{id}', fn () => null);
    expect($route->match('/posts/42'))->toBeNull();
});

test('optional param matches without segment', function () {
    $route  = new Route('GET', '/page/{num?}', fn () => null);
    $params = $route->match('/page');
    expect($params)->toBeArray();
    expect($params)->not->toHaveKey('num');
});

test('where constraint allows numeric only', function () {
    $route = (new Route('GET', '/items/{id}', fn () => null))->where('id', '[0-9]+');
    expect($route->match('/items/5'))->not->toBeNull();
    expect($route->match('/items/abc'))->toBeNull();
});

test('generateUrl fills in parameters', function () {
    $route = new Route('GET', '/users/{id}/profile', fn () => null);
    expect($route->generateUrl(['id' => '7']))->toBe('/users/7/profile');
});

test('group prefix prepended to routes', function () {
    $this->router->group(['prefix' => '/api'], function ($r) {
        $r->get('/users', fn () => null)->name('api.users');
    });
    $route = $this->router->getRoutes()->getByName('api.users');
    expect($route)->not->toBeNull();
    expect($route->getUri())->toBe('/api/users');
});

test('405 thrown on wrong HTTP method', function () {
    $this->router->get('/only-get', fn () => null);
    try {
        $this->router->getRoutes()->match('POST', '/only-get');
        $this->fail('Expected HttpException');
    } catch (HttpException $e) {
        expect($e->getStatusCode())->toBe(405);
    }
});

test('404 thrown on missing route', function () {
    try {
        $this->router->getRoutes()->match('GET', '/nonexistent-path-xyz');
        $this->fail('Expected HttpException');
    } catch (HttpException $e) {
        expect($e->getStatusCode())->toBe(404);
    }
});

test('resource registers seven named routes', function () {
    $this->router->resource('articles', RouterArticleStub::class);
    $names = [
        'articles.index', 'articles.create', 'articles.store',
        'articles.show',  'articles.edit',   'articles.update', 'articles.destroy',
    ];
    foreach ($names as $name) {
        expect($this->router->getRoutes()->getByName($name))
            ->not->toBeNull("Route {$name} not found");
    }
});

test('named URL generation', function () {
    $this->router->get('/profile/{user}', fn () => null)->name('profile');
    expect($this->router->route('profile', ['user' => 'alice']))->toBe('/profile/alice');
});

test('getCurrentRoute is null until dispatch() matches a route', function () {
    expect($this->router->getCurrentRoute())->toBeNull();
});

test('getCurrentRoute reflects the most recently dispatched route', function () {
    $this->router->get('/ping', fn () => new \Ironflow\Http\Response('pong'))->name('ping');

    $this->router->dispatch(\Ironflow\Http\Request::create('/ping'));

    expect($this->router->getCurrentRoute())->not->toBeNull();
    expect($this->router->getCurrentRoute()->getName())->toBe('ping');
});
