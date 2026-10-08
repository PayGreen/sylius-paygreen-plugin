<?php

declare(strict_types=1);

namespace PayGreen\SyliusPayumPlugin\Tests\Double;

use Http\Client\HttpClient;
use Nyholm\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

final class FakeHttpClient implements HttpClient
{
    /**
     * @var list<RequestInterface>
     */
    public array $requests = [];

    /**
     * @param list<ResponseInterface> $responses
     */
    public function __construct(private array $responses = [])
    {
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->requests[] = $request;

        return array_shift($this->responses) ?? new Response(200, [], '{}');
    }
}
