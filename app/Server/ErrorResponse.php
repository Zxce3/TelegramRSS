<?php

namespace TelegramRSS\Server;

use Amp\Http\Server\ErrorHandler;
use Amp\Http\Server\Request;
use Amp\Http\Server\Response;

class ErrorResponse implements ErrorHandler
{

    public function handleError(int $status, ?string $reason = null, ?Request $request = null): Response
    {
        return new Response(
            $status,
            Server::JSON_HEADER,
            json_encode(
                [
                    'success' => false,
                    'errors' => [
                        [
                            'code' => $status,
                            'message' => $reason,
                        ]
                    ]
                ],
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
            ) . "\n"
        );
    }
}