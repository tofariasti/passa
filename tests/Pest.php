<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Feature e Unit compartilham o TestCase da aplicação e o RefreshDatabase.
| Os testes rodam no Postgres de .env.testing, o mesmo servidor que você usa
| em desenvolvimento, em um banco separado.
|
*/

pest()->group('feature')->in('Feature');
pest()->group('unit')->in('Unit');

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature', 'Unit');

pest()->printer()->compact();

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
*/

expect()->extend('toBeOne', fn () => $this->toBe(1));

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
*/

function postNetwork(string $uri, string $body, ?int $timestamp = null, ?string $secret = null, bool $sign = true): TestResponse
{
    return callNetwork('POST', $uri, $body, $timestamp, $secret, $sign);
}

function getNetwork(string $uri, ?int $timestamp = null, ?string $secret = null, bool $sign = true): TestResponse
{
    return callNetwork('GET', $uri, '', $timestamp, $secret, $sign);
}

function callNetwork(string $method, string $uri, string $body = '', ?int $timestamp = null, ?string $secret = null, bool $sign = true): TestResponse
{
    $timestamp ??= now()->getTimestamp();
    $server = [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'application/json',
        'HTTP_X_NETWORK_TIMESTAMP' => (string) $timestamp,
    ];

    if ($sign) {
        $secret ??= (string) config('services.network.secret');
        $server['HTTP_X_NETWORK_SIGNATURE'] = 'sha256='.hash_hmac('sha256', $timestamp.'.'.$body, $secret);
    }

    return test()->call($method, $uri, server: $server, content: $body);
}

function authorizationBody(array $overrides = []): string
{
    return authorizationBodyJson($overrides);
}

function authorizationBodyJson(array $overrides): string
{
    $payload = array_replace([
        'id' => 'aut_default',
        'card_token' => 'tok_ana',
        'amount_cents' => 1_000,
        'currency' => 'BRL',
        'mcc' => '5812',
        'merchant' => ['name' => 'Padaria', 'city' => 'Porto Alegre', 'country' => 'BR'],
        'occurred_at' => '2026-10-08T18:00:00Z',
        'note' => 'ignored',
    ], $overrides);

    return json_encode($payload, JSON_THROW_ON_ERROR);
}
