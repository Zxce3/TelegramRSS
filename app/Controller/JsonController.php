<?php

namespace TelegramRSS\Controller;

use Amp\Http\Server\ClientException;
use Amp\Http\Server\Request;
use Amp\Http\Server\RequestHandler;
use Amp\Http\Server\Response;
use Amp\Http\Server\Router;
use UnexpectedValueException;

class JsonController extends BaseController implements RequestHandler
{
    const LIMIT = 10;

    const CONTENT_TYPE = 'application/json;charset=utf-8';

    public function handleRequest(Request $request): Response
    {
        try {
            $this->validatePeer($request);

            $response = new Response();
            $response->setHeader('Content-Type', self::CONTENT_TYPE);
            $response->setBody(
                $this->client->getHistoryHtml(
                    [
                        'peer' => $this->getChannel($request),
                        'limit' => $this->getLimit($request),
                        'add_offset' => ($this->getPage($request) - 1) * $this->getLimit($request),
                    ]
                )->getBody()
            );
        } catch (UnexpectedValueException $e) {
            throw new ClientException($request->getClient(), $e->getMessage(), $e->getCode(), $e);
        }

        return $response;
    }

    private function getPage(Request $request): int
    {
        return $request->getAttribute(Router::class)['page'] ?? 1;
    }

    private function getLimit(Request $request): int
    {
        $limit = (int)($request->getAttributes()['limit'] ?? self::LIMIT);
        return max(1, min($limit, 100));
    }

}