<?php

namespace TelegramRSS\Controller;

use Amp\Http\Server\ClientException;
use Amp\Http\Server\Request;
use Amp\Http\Server\RequestHandler;
use Amp\Http\Server\Response;
use Amp\Http\Server\Router;
use UnexpectedValueException;

class MediaController extends BaseController implements RequestHandler
{

    public function handleRequest(Request $request): Response
    {
        try {
            $this->validatePeer($request);

            if ($this->getPreview($request)) {
                $response = $this->client->getMediaPreview(
                    [
                        'peer' => $this->getChannel($request),
                        'id' => [$this->getMessage($request)],
                    ],
                    $request->getHeaders()
                );
            } else {
                $response = $this->client->getMedia(
                    [
                        'peer' => $this->getChannel($request),
                        'id' => [$this->getMessage($request)],
                    ],
                    $request->getHeaders()
                );
            }
        } catch (UnexpectedValueException $e) {
            throw new ClientException($request->getClient(), $e->getMessage(), $e->getCode(), $e);
        }

        return new Response($response->getStatus(), $response->getHeaders(), $response->getBody());
    }

    private function getMessage(Request $request)
    {
        return $request->getAttribute(Router::class)['message']
            ?? throw new ClientException($request->getClient(), 'Need to specify message id');
    }

    private function getPreview(Request $request): bool {
        return ($request->getAttribute(Router::class)['preview'] ?? '') === 'preview';
    }

}