<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Article;
use App\Entity\NewsSource;
use App\Entity\User;
use App\Repository\ArticleRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class NewsImportService
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly EntityManagerInterface $em,
        private readonly ArticleRepository $articleRepository,
        private readonly UserRepository $userRepository,
        private readonly NotificationService $notificationService,
        private readonly LoggerInterface $logger,
    ) {}

    public function importFromSource(NewsSource $source): int
    {
        $imported = match ($source->getType()) {
            NewsSource::TYPE_RSS => $this->importRss($source),
            NewsSource::TYPE_API => $this->importApi($source),
            default => 0,
        };

        $source->setLastFetchedAt(new \DateTimeImmutable());
        $this->em->flush();

        if ($imported > 0) {
            $this->notifyAllUsers($source->getName(), $imported);
        }

        return $imported;
    }

    private function importRss(NewsSource $source): int
    {
        try {
            $response = $this->httpClient->request('GET', $source->getUrl(), [
                'timeout' => 15,
                'headers' => ['User-Agent' => 'NewsAggregator/1.0'],
            ]);
            $xml = new \SimpleXMLElement($response->getContent());
        } catch (\Throwable $e) {
            $this->logger->error('RSS import failed', ['source' => $source->getName(), 'error' => $e->getMessage()]);
            return 0;
        }

        $imported = 0;
        $items = $xml->channel->item ?? $xml->entry ?? [];

        foreach ($items as $item) {
            $url = (string) ($item->link ?? $item->id ?? '');
            if (!$url || $this->articleRepository->existsByUrl($url)) {
                continue;
            }

            $article = new Article();
            $article->setTitle(html_entity_decode((string) ($item->title ?? 'Без заголовка'), ENT_QUOTES | ENT_HTML5));
            $article->setExternalUrl($url);
            $article->setSummary($this->stripAndTruncate((string) ($item->description ?? $item->summary ?? ''), 500));
            $article->setSource($source);
            $article->setCategory($source->getDefaultCategory());

            $pubDate = (string) ($item->pubDate ?? $item->published ?? $item->updated ?? '');
            $article->setPublishedAt($this->parseDate($pubDate));

            // Ищем картинку в enclosure или media:content
            $enclosure = $item->enclosure;
            if ($enclosure && (string) $enclosure['type'] !== '' && str_starts_with((string) $enclosure['type'], 'image/')) {
                $article->setImageUrl((string) $enclosure['url']);
            }

            $this->em->persist($article);
            $imported++;

            // Сбрасываем пачками по 50, чтобы не держать всё в памяти
            if ($imported % 50 === 0) {
                $this->em->flush();
                $this->em->clear(Article::class);
            }
        }

        $this->em->flush();
        return $imported;
    }

    private function importApi(NewsSource $source): int
    {
        // Точка расширения для REST API-источников
        $this->logger->info('API import not implemented yet for source', ['source' => $source->getName()]);
        return 0;
    }

    private function notifyAllUsers(string $sourceName, int $count): void
    {
        $users = $this->userRepository->findAll();
        foreach ($users as $user) {
            $this->notificationService->notify(
                $user,
                sprintf('Импортировано %d новых статей из "%s"', $count, $sourceName),
                'news',
            );
        }
    }

    private function stripAndTruncate(string $html, int $length): string
    {
        $text = strip_tags($html);
        return mb_strlen($text) > $length ? mb_substr($text, 0, $length) . '…' : $text;
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
