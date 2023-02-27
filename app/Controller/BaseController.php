<?php

namespace TelegramRSS\Controller;

use Amp\Http\Server\ClientException;
use Amp\Http\Server\Request;
use Amp\Http\Server\RequestHandler;
use Amp\Http\Server\Response;
use Amp\Http\Server\Router;
use TelegramRSS\Client;
use TelegramRSS\Config;
use InvalidArgumentException;

abstract class BaseController implements RequestHandler
{
    protected Client $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    abstract public function handleRequest(Request $request): Response;

    protected function validatePeer(Request $request): void
    {
        if (Config::getInstance()->get('access.only_public_channels')) {
            if (preg_match('/[^\w\-@]/', $this->getChannel($request))) {
                throw new ClientException($request->getClient(), 'WRONG NAME', 404);
            }

            if (preg_match('/bot$/i', $this->getChannel($request))) {
                throw new ClientException($request->getClient(),'BOTS NOT ALLOWED', 403);
            }
        }

        $info = $this->client->getInfo($this->getChannel($request));
        $isChannel = in_array($info['type'], ['channel', 'supergroup']);
        if (
            Config::getInstance()->get('access.only_public_channels') &&
            !$isChannel
        ) {
            throw new ClientException($request->getClient(),'This is not a public channel', 403);
        }
    }


    protected function getChannel(Request $request): string
    {
        return $request->getAttribute(Router::class)['channel']
            ?? throw new ClientException($request->getClient(),'Need to specify channel');
    }

}