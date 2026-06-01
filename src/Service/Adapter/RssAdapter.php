<?php

declare(strict_types=1);

namespace App\Service\Adapter;

use App\Dto\ArticleData;
use App\Entity\NewsSource;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class RssAdapter implements NewsSourceAdapterInterface
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
    ) {}

    public function supports(NewsSource $source): bool
    {
        return $source->getType() === NewsSource::TYPE_RSS;
    }

    public function fetch(NewsSource $source): array
    {
        try {
            $response = $this->httpClient->request('GET', $source->getUrl(), [
                'timeout' => 15,
                'headers' => ['User-Agent' => 'NewsAggregator/1.0 (+https://github.com/newsagg)'],
            ]);
            $xml = new \SimpleXMLElement($response->getContent());
        } catch (\Throwable $e) {
            $this->logger->error('RSS fetch failed', ['source' => $source->getName(), 'error' => $e->getMessage()]);
            return [];
        }

        $items = $xml->channel->item ?? $xml->entry ?? [];
        $articles = [];

        foreach ($items as $item) {
            $url = $this->extractUrl($item);
            if (!$url) {
                continue;
            }

            $title = html_entity_decode((string) ($item->title ?? 'Без заголовка'), ENT_QUOTES | ENT_HTML5);
            $summary = $this->extractSummary($item);
            $content = $this->extractContent($item);
            $imageUrl = $this->extractImage($item);
            $publishedAt = $this->parseDate((string) ($item->pubDate ?? $item->published ?? $item->updated ?? ''));

            $articles[] = new ArticleData(
                title: trim($title),
                externalUrl: $url,
                summary: $summary,
                content: $content,
                imageUrl: $imageUrl,
                publishedAt: $publishedAt,
            );
        }

        return $articles;
    }

    private function extractUrl(\SimpleXMLElement $item): ?string
    {
        // RSS <link>
        $link = (string) ($item->link ?? '');

        // Atom <link href="...">
        if ($link === '') {
            foreach ($item->link as $linkNode) {
                $rel = (string) ($linkNode['rel'] ?? 'alternate');
                if ($rel === 'alternate' || $rel === '') {
                    $link = (string) ($linkNode['href'] ?? '');
                    if ($link !== '') {
                        break;
                    }
                }
            }
        }

        // Atom <id> as fallback
        if ($link === '') {
            $link = (string) ($item->id ?? '');
        }

        return $link !== '' ? $link : null;
    }

    private function extractSummary(\SimpleXMLElement $item): ?string
    {
        // Try Atom <summary>
        $raw = (string) ($item->summary ?? $item->description ?? '');
        if ($raw === '') {
            return null;
        }
        $text = strip_tags(html_entity_decode($raw, ENT_QUOTES | ENT_HTML5));
        $text = preg_replace('/\s+/', ' ', trim($text));
        return $text !== '' ? mb_substr($text, 0, 500) . (mb_strlen($text) > 500 ? '…' : '') : null;
    }

    private function extractContent(\SimpleXMLElement $item): ?string
    {
        // content:encoded (standard RSS full-text namespace)
        $contentNs = $item->children('http://purl.org/rss/1.0/modules/content/');
        if (isset($contentNs->encoded) && (string) $contentNs->encoded !== '') {
            $html = (string) $contentNs->encoded;
            return $this->htmlToText($html);
        }

        // Atom <content>
        if (isset($item->content) && (string) $item->content !== '') {
            return $this->htmlToText((string) $item->content);
        }

        return null;
    }

    private function extractImage(\SimpleXMLElement $item): ?string
    {
        // <enclosure type="image/...">
        if (isset($item->enclosure)) {
            $type = (string) ($item->enclosure['type'] ?? '');
            if (str_starts_with($type, 'image/')) {
                $url = (string) ($item->enclosure['url'] ?? '');
                if ($url !== '') {
                    return $url;
                }
            }
        }

        // media:content (Yahoo Media RSS)
        $media = $item->children('http://search.yahoo.com/mrss/');
        if (isset($media->content)) {
            foreach ($media->content as $mc) {
                $medium = (string) ($mc['medium'] ?? '');
                $url = (string) ($mc['url'] ?? '');
                if ($url !== '' && ($medium === 'image' || str_starts_with((string) ($mc['type'] ?? ''), 'image/'))) {
                    return $url;
                }
            }
            // Take first media:content regardless
            if (isset($media->content[0])) {
                $url = (string) ($media->content[0]['url'] ?? '');
                if ($url !== '') {
                    return $url;
                }
            }
        }

        return null;
    }

    private function htmlToText(string $html): ?string
    {
        $text = strip_tags(html_entity_decode($html, ENT_QUOTES | ENT_HTML5));
        $text = preg_replace('/\s+/', ' ', trim($text));
        return $text !== '' ? $text : null;
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
