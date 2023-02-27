<?php

namespace TelegramRSS\Server;

use Amp\Http\Server\ErrorHandler;
use Amp\Http\Server\Request;
use Amp\Http\Server\RequestHandler\ClosureRequestHandler;
use Amp\Http\Server\SocketHttpServer;
use Amp\Http\Server\StaticContent\DocumentRoot;
use Amp\Http\Status;

use TelegramRSS\Client;
use TelegramRSS\Controller\JsonController;

use TelegramRSS\Controller\MediaController;

use function Amp\Http\Server\Middleware\stack;

class Router
{
    private \Amp\Http\Server\Router $router;

    public function __construct(Client $client, SocketHttpServer $server, ErrorHandler $errorHandler )
    {
        $this->router = new \Amp\Http\Server\Router($server, $errorHandler);
        $this->setRoutes($client);
        $this->router->setFallback(new DocumentRoot($server, $errorHandler, ROOT_DIR . '/public'));
    }

    public function getRouter(): \Amp\Http\Server\Router
    {
        return $this->router;
    }

    private function setRoutes(Client $client): void
    {
        $authorization = new Authorization();


        foreach (['GET', 'POST'] as $method) {
            $this->router->addRoute($method, '/json/{channel}[/[{page}[/]]]', stack(new JsonController($client), $authorization));
            $this->router->addRoute($method, '/media/{channel}/{message}[/[{preview:preview}[/]]]', stack(new MediaController($client), $authorization));
//            $this->router->addRoute($method, '/rss/{session:.*?[^/]}/{method}[/]', $apiHandler);
//
//            $this->router->addRoute($method, '/media/{method}[/]', $systemApiHandler);
        }
    }


}