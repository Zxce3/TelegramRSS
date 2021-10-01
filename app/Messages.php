<?php

namespace TelegramRSS;


class Messages {
    private const TELEGRAM_URL = 'https://t.me/';

    private $list = [];
    private \stdClass $telegramResponse;
    private $channelUrl;
    private $username;
    private $client;

    private const MEDIA_TYPES = [
        'messageMediaDocument',
        'messageMediaPhoto',
        'messageMediaVideo',
        'messageMediaWebPage',
    ];

    /**
     * Messages constructor.
     * @param \stdClass $telegramResponse
     * @param Client $client
     */
    public function __construct(\stdClass $telegramResponse, Client $client) {
        $this->telegramResponse = $telegramResponse;
        $this->client = $client;
        $this->setUsername();
        $this->parseMessages();
    }

    private function setUsername() {
        if (!$this->channelUrl) {
            $chat = $this->telegramResponse->chats[0];
            $this->username = $chat->username ?? $this->client->getId($chat);
            if (!$this->username) {
                return null;
            }
            $this->channelUrl = static::TELEGRAM_URL . $this->username . '/';
        }
    }

    private function parseMessages(): self {
        if ($messages = $this->telegramResponse->messages ?? []) {
            $groupedMessages = [];
            foreach ($messages as $key => $message) {
                if (
                    !empty($message->grouped_id) &&
                    !empty($messages[$key + 1]->grouped_id) &&
                    $messages[$key + 1]->grouped_id === $message->grouped_id
                ) {
                    $groupedMessages[] = $message;
                    continue;
                }
                $description = $message->message ?? '';
                if ($description || $this->hasMedia($message)) {
                    $info = $this->getMediaInfo($message);
                    $parsedMessage = [
                        'url' => $this->getMessageUrl($message->id),
                        'title' => null,
                        'description' => $description,
                        'media' => [$info],
                        'preview' => [
                            [
                                'href' => $info->url ?? null,
                                'image' => $this->getMediaUrl($message, $info, true),
                            ]
                        ],
                        'timestamp' => $message->date ?? '',
                    ];

                    if ($groupedMessages = array_reverse($groupedMessages)) {
                        foreach ($groupedMessages as $media) {
                            $info = $this->getMediaInfo($media);
                            $preview = [
                                'href' => $info->url ?? null,
                                'image' => $this->getMediaUrl($media, $info, true),
                            ];
                            if ($preview['href'] && $preview['image']) {
                                $parsedMessage['preview'][] = $preview;
                                $parsedMessage['media'][] = $info;
                            }
                        }
                        $groupedMessages = [];
                    }

                    if (!empty($message->media->webpage)) {
                        $parsedMessage['webpage'] = [
                            'site_name' => $message->media->webpage->site_name ?? null,
                            'title' => $message->media->webpage->title ?? null,
                            'description' => $message->media->webpage->description ?? null,
                            'preview' => reset($parsedMessage['preview'])['image'] ?? null,
                            'url' => $message->media->webpage->url ?? null,
                        ];
                        $parsedMessage['preview'] = [];
                    }

                    $parsedMessage = $this->setTitle($parsedMessage, $message);

                    $this->list[$message->id] = $parsedMessage;
                }
            }
        }
        return $this;
    }

    private function setTitle(array $parsedMessage, \stdClass $message): array {

        $descriptionText = strip_tags($parsedMessage['description']);

        if (mb_strlen($descriptionText) > 50) {
            //Get first sentence from decription
            preg_match('/(?<sentence>.*?\b\W*(?:\.|\?|\!|\n))/ui', $descriptionText, $matches);

            $parsedMessage['title'] = $matches['sentence'] ?? null;
            $parsedMessage['title'] = trim($parsedMessage['title']);

            if ($parsedMessage['title']) {
                return $parsedMessage;
            }

            //Get first 100 symbols from description
            $parsedMessage['title'] = mb_strimwidth($descriptionText, 0, 100, ' [...]');
            return $parsedMessage;
        }

        if (!empty($message->media)) {
            $mime = $message->media->document->mime_type ?? '';
            if (strpos($mime, 'video') !== false) {
                $parsedMessage['title'] = '[Video]';
            } elseif ($message->media->_ === 'messageMediaPhoto') {
                $parsedMessage['title'] = '[Photo]';
            } else {
                $parsedMessage['title'] = '[Media]';
            }
        }

        return $parsedMessage;
    }

    /**
     * @param string $messageId
     * @return string|null
     */
    private function getMessageUrl($messageId = '') {
        return $this->channelUrl . $messageId;
    }

    private function getMediaInfo($message): ?\stdClass {
        if (!$this->hasMedia($message)) {
            return null;
        }
        if (!empty($message->media->webpage->photo)) {
            $media = $message->media->webpage->photo;
        } else {
            $media = $message->media;
        }
        $info = $this->client->getMediaInfo($media);
        if (!empty($info->size) && !empty($info->mime)) {
            $info->url = $this->getMediaUrl($message, $info, false);
            return $info;
        }

        return null;
    }

    private function hasMedia($message) {
        if (
            empty($message->media) ||
            !in_array($message->media->{'_'}, static::MEDIA_TYPES, true) ||
            (
                property_exists($message->media, 'photo') &&
                empty($message->media->photo)
            ) ||
            (
                !empty($message->media->webpage) &&
                empty($message->media->webpage->photo)
            )
        ) {
            return false;
        }

        return true;
    }

    private function getMediaUrl(\stdClass $message, ?\stdClass $info, bool $preview = false) {
        if (!$this->hasMedia($message)) {
            return null;
        }

        $url = Config::getInstance()->get('url');
        $url .= "/media/{$this->username}/{$message->id}";

        if ($preview) {
            $url .= '/preview/thumb.jpeg';
        } elseif (!empty($info->name) && !empty($info->ext)) {
            $filename = mb_substr(trim($info->name), 0, 50);
            $filename = urlencode("{$filename}{$info->ext}");
            $url .= "/$filename";
        }
        return $url;
    }

    /**
     * @return array
     */
    public function get(): array {
        return $this->list;
    }

}