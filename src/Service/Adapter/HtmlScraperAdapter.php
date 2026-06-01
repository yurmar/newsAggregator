<?php

declare(strict_types=1);

namespace App\Service\Adapter;

use App\Dto\ArticleData;
use App\Entity\NewsSource;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Универсальный HTML-парсер новостей.
 * Парсит страницу-листинг: находит ссылки на статьи,
 * затем для каждой статьи извлекает данные через Open Graph и семантические теги.
 *
 * NewsSource.type = 'html', NewsSource.url = URL страницы с новостями.
 */
final class HtmlScraperAdapter implements NewsSourceAdapterInterface
{
    private const MAX_ARTICLES = 10;
    private const REQUEST_OPTIONS = ['timeout' => 15, 'headers' => ['User-Agent' => 'NewsAggregator/1.0']];

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
    ) {}

    public function supports(NewsSource $source): bool
    {
        return $source->getType() === NewsSource::TYPE_HTML;
    }

    public function fetch(NewsSource $source): array
    {
        try {
            $html = $this->httpClient->request('GET', $source->getUrl(), self::REQUEST_OPTIONS)->getContent();
        } catch (\Throwable $e) {
            $this->logger->error('HtmlScraper: failed to fetch listing page', [
                'source' => $source->getName(),
                'error' => $e->getMessage(),
            ]);
            return [];
        }

        $articleUrls = $this->extractArticleLinks($html, $source->getUrl());
        if (empty($articleUrls)) {
            $this->logger->warning('HtmlScraper: no article links found', ['source' => $source->getName()]);
            return [];
        }

        // Параллельные запросы к страницам статей
        $responses = [];
        foreach (array_slice($articleUrls, 0, self::MAX_ARTICLES) as $url) {
            $responses[$url] = $this->httpClient->request('GET', $url, self::REQUEST_OPTIONS);
        }

        $articles = [];
        foreach ($responses as $url => $response) {
            try {
                $pageHtml = $response->getContent();
            } catch (\Throwable $e) {
                $this->logger->debug('HtmlScraper: failed to fetch article', ['url' => $url, 'error' => $e->getMessage()]);
                continue;
            }

            $data = $this->extractArticleData($pageHtml, $url);
            if ($data !== null) {
                $articles[] = $data;
            }
        }

        return $articles;
    }

    /** @return string[] */
    private function extractArticleLinks(string $html, string $baseUrl): array
    {
        $dom = new \DOMDocument();
        @$dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'), LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new \DOMXPath($dom);

        // Приоритетные контейнеры: <article>, <main>, затем общие заголовочные теги
        $query = '//article//a[@href] | //main//a[@href] | //h1/a[@href] | //h2/a[@href] | //h3/a[@href]';
        $nodes = $xpath->query($query);

        $seen = [];
        $urls = [];

        foreach ($nodes as $node) {
            /** @var \DOMElement $node */
            $href = trim($node->getAttribute('href'));
            if ($href === '' || str_starts_with($href, '#') || str_starts_with($href, 'javascript:')) {
                continue;
            }

            $absolute = $this->toAbsoluteUrl($href, $baseUrl);
            if ($absolute === null || isset($seen[$absolute])) {
                continue;
            }

            // Фильтруем ссылки на ту же страницу и на нетекстовые ресурсы
            if ($absolute === $baseUrl) {
                continue;
            }
            $ext = strtolower(pathinfo(parse_url($absolute, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'svg', 'pdf', 'zip', 'css', 'js'], true)) {
                continue;
            }

            $seen[$absolute] = true;
            $urls[] = $absolute;
        }

        return $urls;
    }

    private function extractArticleData(string $html, string $url): ?ArticleData
    {
        $dom = new \DOMDocument();
        @$dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'), LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new \DOMXPath($dom);

        $title = $this->getMeta($xpath, 'og:title')
            ?? $this->getMeta($xpath, 'twitter:title')
            ?? $this->getNodeText($xpath, '//h1')
            ?? $this->getNodeText($xpath, '//title');

        if ($title === null || trim($title) === '') {
            return null;
        }

        $summary = $this->getMeta($xpath, 'og:description')
            ?? $this->getMeta($xpath, 'description')
            ?? $this->getMeta($xpath, 'twitter:description');

        $imageUrl = $this->getMeta($xpath, 'og:image')
            ?? $this->getMeta($xpath, 'twitter:image');

        $publishedRaw = $this->getMeta($xpath, 'article:published_time')
            ?? $this->getMeta($xpath, 'datePublished')
            ?? $this->getNodeAttr($xpath, '//*[@itemprop="datePublished"]', 'content')
            ?? $this->getNodeAttr($xpath, '//time[@datetime]', 'datetime');

        $publishedAt = $this->parseDate($publishedRaw ?? '');

        // Полный текст: предпочитаем <article>, потом <main>, потом <body>
        $content = $this->extractTextContent($xpath, '//article')
            ?? $this->extractTextContent($xpath, '//main')
            ?? $this->extractTextContent($xpath, '//body');

        return new ArticleData(
            title: trim(html_entity_decode($title, ENT_QUOTES | ENT_HTML5)),
            externalUrl: $url,
            summary: $summary !== null ? mb_substr(trim($summary), 0, 500) : null,
            content: $content,
            imageUrl: $imageUrl,
            publishedAt: $publishedAt,
        );
    }

    private function getMeta(\DOMXPath $xpath, string $property): ?string
    {
        // <meta property="og:..." content="..."> или <meta name="..." content="...">
        $query = "//meta[@property='$property']/@content | //meta[@name='$property']/@content";
        $nodes = $xpath->query($query);
        if ($nodes && $nodes->length > 0) {
            $val = trim($nodes->item(0)->nodeValue ?? '');
            return $val !== '' ? $val : null;
        }
        return null;
    }

    private function getNodeText(\DOMXPath $xpath, string $query): ?string
    {
        $nodes = $xpath->query($query);
        if ($nodes && $nodes->length > 0) {
            $text = trim($nodes->item(0)->textContent ?? '');
            return $text !== '' ? $text : null;
        }
        return null;
    }

    private function getNodeAttr(\DOMXPath $xpath, string $query, string $attr): ?string
    {
        $nodes = $xpath->query($query);
        if ($nodes && $nodes->length > 0) {
            /** @var \DOMElement $node */
            $node = $nodes->item(0);
            if ($node instanceof \DOMElement) {
                $val = trim($node->getAttribute($attr));
                return $val !== '' ? $val : null;
            }
        }
        return null;
    }

    private function extractTextContent(\DOMXPath $xpath, string $query): ?string
    {
        $nodes = $xpath->query($query);
        if (!$nodes || $nodes->length === 0) {
            return null;
        }
        $text = preg_replace('/\s+/', ' ', trim($nodes->item(0)->textContent ?? ''));
        return $text !== '' ? mb_substr($text, 0, 8000) : null;
    }

    private function toAbsoluteUrl(string $href, string $baseUrl): ?string
    {
        if (str_starts_with($href, 'http://') || str_starts_with($href, 'https://')) {
            return $href;
        }

        $parts = parse_url($baseUrl);
        if (!$parts) {
            return null;
        }

        $scheme = $parts['scheme'] ?? 'https';
        $host = $parts['host'] ?? '';

        if (str_starts_with($href, '//')) {
            return $scheme . ':' . $href;
        }

        if (str_starts_with($href, '/')) {
            return $scheme . '://' . $host . $href;
        }

        // Относительный путь
        $basePath = isset($parts['path']) ? rtrim(dirname($parts['path']), '/') : '';
        return $scheme . '://' . $host . $basePath . '/' . ltrim($href, '/');
    }

    private function parseDate(string $date): \DateTimeImmutable
    {
        if ($date === '') {
            return new \DateTimeImmutable();
        }
        try {
            return new \DateTimeImmutable($date);
        } catch (\Exception) {
            return new \DateTimeImmutable();
        }
    }
}
