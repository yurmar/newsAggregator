<?php

declare(strict_types=1);

namespace App\Service\Adapter;

use App\Dto\ArticleData;
use App\Entity\NewsSource;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Импортирует топовые истории Hacker News через публичный Firebase API.
 * NewsSource.url должен быть: https://hacker-news.firebaseio.com/v0
 */
final class HackerNewsApiAdapter implements NewsSourceAdapterInterface
{
    private const LIMIT = 30;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
    ) {}

    public function supports(NewsSource $source): bool
    {
        return $source->getType() === NewsSource::TYPE_API
            && str_contains($source->getUrl(), 'hacker-news.firebaseio.com');
    }

    public function fetch(NewsSource $source): array
    {
        $base = rtrim($source->getUrl(), '/');

        try {
            $idsContent = $this->httpClient->request('GET', "$base/topstories.json", [
                'timeout' => 10,
                'headers' => ['Accept' => 'application/json'],
            ])->getContent();
        } catch (\Throwable $e) {
            $this->logger->error('HackerNews API: failed to fetch top stories', ['error' => $e->getMessage()]);
            return [];
        }

        $ids = json_decode($idsContent, true);
        if (!is_array($ids)) {
            return [];
        }

        $ids = array_slice($ids, 0, self::LIMIT);

        // Запускаем все запросы параллельно
        $responses = [];
        foreach ($ids as $id) {
            $responses[$id] = $this->httpClient->request('GET', "$base/item/$id.json", [
                'timeout' => 10,
                'headers' => ['Accept' => 'application/json'],
            ]);
        }

        $articles = [];
        foreach ($responses as $id => $response) {
            try {
                $item = json_decode($response->getContent(), true);
            } catch (\Throwable $e) {
                $this->logger->warning('HackerNews API: failed to fetch item', ['id' => $id, 'error' => $e->getMessage()]);
                continue;
            }

            if (!is_array($item) || ($item['type'] ?? '') !== 'story' || empty($item['title'])) {
                continue;
            }

            // Для каждой HN-истории уникальным внешним URL является ссылка на обсуждение
            $externalUrl = "https://news.ycombinator.com/item?id=$id";

            $content = null;
            $summary = null;

            if (!empty($item['text'])) {
                // Ask HN / Show HN — в поле text хранится HTML-контент
                $text = strip_tags(html_entity_decode($item['text'], ENT_QUOTES | ENT_HTML5));
                $text = preg_replace('/\s+/', ' ', trim($text));
                $content = $text ?: null;
                $summary = $content ? (mb_strlen($content) > 500 ? mb_substr($content, 0, 500) . '…' : $content) : null;
            } elseif (!empty($item['url'])) {
                // Обычная ссылочная история
                $summary = sprintf('Score: %d | %s', $item['score'] ?? 0, $item['url']);
            }

            $publishedAt = isset($item['time'])
                ? new \DateTimeImmutable('@' . $item['time'])
                : new \DateTimeImmutable();

            $articles[] = new ArticleData(
                title: html_entity_decode($item['title'], ENT_QUOTES | ENT_HTML5),
                externalUrl: $externalUrl,
                summary: $summary,
                content: $content,
                publishedAt: $publishedAt,
            );
        }

        return $articles;
    }
}
