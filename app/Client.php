<?php

namespace TelegramRSS;


use Amp\Http\Client\HttpClient;
use Amp\Http\Client\HttpClientBuilder;
use Amp\Http\Client\Request;

use Amp\Http\Client\Response;

use UnexpectedValueException;

use function Amp\delay;

class Client
{
    private const RETRY = 5;
    private const RETRY_INTERVAL = 3;
    private ?bool $isPremium = null;
    public const MESSAGE_CLIENT_UNAVAILABLE = 'Telegram connection error...';
    private string $apiUrl;
    private HttpClient $client;

    /**
     * Client constructor.
     *
     * @param string $address
     * @param int $port
     */
    public function __construct(string $address = '', int $port = 0)
    {
        $address = $address ?: Config::getInstance()->get('client.address');
        $port = $port ?: Config::getInstance()->get('client.port');
        $this->apiUrl = "http://$address:$port";
        $this->client = (new HttpClientBuilder())
            ->retry(0)
            ->build()
        ;
    }

    public function getHistoryHtml(array $data): Response
    {
        $data = array_merge(
            [
                'peer' => '',
                'limit' => 10,
            ],
            $data
        );
        return $this->get('getHistoryHtml', ['data' => $data]);
    }

    public function getMedia(array $data, array $headers)
    {
        $data = array_merge(
            [
                'peer' => '',
                'id' => [0],
                'size_limit' => Config::getInstance()->get('media.max_size'),
            ],
            $data
        );

        return $this->get('getMedia', ['data' => $data], $headers, 'media');
    }

    public function getMediaPreview(array $data, array $headers)
    {
        $data = array_merge(
            [
                'peer' => '',
                'id' => [0],
            ],
            $data
        );

        return $this->get('getMediaPreview', ['data' => $data], $headers, 'media');
    }

    public function getMediaInfo(object $message)
    {
        return $this->get('getDownloadInfo', ['message' => $message]);
    }

    public function getInfo(string $peer): array
    {
        return json_decode(
            $this->get('getInfo', $peer)->getBody()->buffer(),
            true,
            10,
            JSON_THROW_ON_ERROR
        )['response'] ?? throw new \RuntimeException('Cant decode telegram answer', 500);
    }

    public function search(string $username): ?\stdClass
    {
        $username = ltrim($username, '@');
        $peers = $this->get('contacts.search', [
            'data' => [
                'q' => "@{$username}",
                'limit' => 1,
            ],
        ]);

        foreach (array_merge($peers->chats, $peers->users) as $peer) {
            if (strtolower($peer->username ?? '') === strtolower($username)) {
                return $peer;
            }
        }
        return null;
    }

    public function getId($chat)
    {
        return $this->get('getId', [$chat]);
    }

    public function getSponsoredMessages($peer)
    {
        if ($this->isPremium === null) {
            $self = $this->get('getSelf');
            $this->isPremium = $self->premium ?? null;
        }
        $messages = [];
        if (!$this->isPremium) {
            $messages = (array)$this->get('getSponsoredMessages', $peer);
            foreach ($messages as $message) {
                $id = $this->getId($message->from_id);
                $message->peer = $this->getInfo($id);
            }
        }
        return $messages;
    }

    public function viewSponsoredMessage($peer, $message)
    {
        return $this->get('viewSponsoredMessage', ['peer' => $peer, 'message' => $message]);
    }

    /**
     * @param string $method
     * @param mixed $parameters
     * @param array $headers
     * @param string $responseType
     * @param int $retry
     *
     * @return object
     * @throws \Exception
     */
    private function get(
        string $method,
        $parameters = [],
        array $headers = [],
        string $responseType = 'json',
        $retry = 0
    ): Response {
        unset(
            $headers['host'],
            $headers['remote_addr'],
            $headers['x-forwarded-for'],
            $headers['connection'],
            $headers['cache-control'],
            $headers['upgrade-insecure-requests'],
            $headers['accept-encoding'],
        );
        if ($retry) {
            //Делаем попытку реконекта
            echo 'Client crashed and restarting. Resending request.' . PHP_EOL;
            Log::getInstance()->add('Client crashed and restarting. Resending request.');
            delay(self::RETRY_INTERVAL);
        }

        $request = new Request(
            $this->apiUrl . "/api/$method",
            'POST',
            json_encode($parameters, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE)
        );
        $request->setHeaders(array_merge(['Content-Type' => 'application/json'], $headers));
        $request->setTransferTimeout(600);
        $request->setBodySizeLimit(5 * (1024 ** 3)); // 5Gb
        $request->setTcpConnectTimeout(0.1);
        $request->setTlsHandshakeTimeout(0.1);
        try {
            $response = $this->client->request($request);
        } catch (\Throwable $e) {
            throw new UnexpectedValueException(static::MESSAGE_CLIENT_UNAVAILABLE, 500, $e);
        }


        if (!in_array($response->getStatus(), [200, 206, 302], true)) {
            $errorMessage = '';
            $errorCode = 400;
            if (str_contains($response->getHeader('Content-Type'), 'application/json')) {
                $data = json_decode($response->getBody()->buffer(), true);
                $errorMessage = $data['errors'][0]['message'] ?? $errorMessage;
                $errorCode = $data['errors'][0]['code'] ?? $errorCode;
            }

            if (!$errorMessage && $retry < static::RETRY) {
                return $this->get($method, $parameters, $headers, $responseType, ++$retry);
            }
            if ($errorMessage) {
                throw new UnexpectedValueException($errorMessage, $errorCode);
            }
            throw new UnexpectedValueException(static::MESSAGE_CLIENT_UNAVAILABLE, $response->getStatus());
        }

        return $response;
    }
}