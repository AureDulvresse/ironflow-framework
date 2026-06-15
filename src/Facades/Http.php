<?php

declare(strict_types=1);

namespace Ironflow\Facades;

use Ironflow\Support\Facade;

/**
 * @method static \Ironflow\Http\HttpResponse get(string $url, array $query = [])
 * @method static \Ironflow\Http\HttpResponse post(string $url, array $options = [])
 * @method static \Ironflow\Http\HttpResponse put(string $url, array $options = [])
 * @method static \Ironflow\Http\HttpResponse patch(string $url, array $options = [])
 * @method static \Ironflow\Http\HttpResponse delete(string $url, array $options = [])
 * @method static \Ironflow\Http\HttpClient withToken(string $token, string $type = 'Bearer')
 * @method static \Ironflow\Http\HttpClient withHeaders(array $headers)
 * @method static \Ironflow\Http\HttpClient acceptJson()
 * @method static \Ironflow\Http\HttpClient asForm()
 * @method static \Ironflow\Http\HttpClient timeout(float $seconds)
 * @method static \Ironflow\Http\HttpClient retry(int $times, int $delayMs = 200)
 */
class Http extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Ironflow\Http\HttpClient::class;
    }
}
