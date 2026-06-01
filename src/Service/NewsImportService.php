<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Article;
use App\Entity\NewsSource;
use App\Entity\User;
use App\Repository\ArticleRepository;
use App\Repository\UserRepository;
use App\Service\Adapter\NewsSourceAdapterInterface;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

class NewsImportService
{
    /** @var NewsSourceAdapterInterface[] */
    private array $adapters;

    public function __construct(
        #[TaggedIterator('app.news_source_adapter')]
        iterable $adapters,
        private readonly EntityManagerInterface $em,
        private readonly ArticleRepository $articleRepository,
        private readonly UserRepository $userRepository,
        private readonly NotificationService $notificationService,
        private readonly LoggerInterface $logger,
    ) {
        $this->adapters = iterator_to_array($adapters);
    }

    public function importFromSource(NewsSource $source): int
    {
        $adapter = $this->findAdapter($source);
        if ($adapter === null) {
            $this->logger->warning('No adapter found for source', [
                'source' => $source->getName(),
                'type'   => $source->getType(),
                'url'    => $source->getUrl(),
            ]);
            return 0;
        }

        try {
            $articles = $adapter->fetch($source);
        } catch (\Throwable $e) {
            $this->logger->error('Adapter fetch error', [
                'source' => $source->getName(),
                'error'  => $e->getMessage(),
            ]);
            return 0;
        }

        $imported = 0;

        foreach ($articles as $data) {
            if (!$data->externalUrl || $this->articleRepository->existsByUrl($data->externalUrl)) {
                continue;
            }

            $article = new Article();
            $article->setTitle($data->title);
            $article->setExternalUrl($data->externalUrl);
            $article->setSummary($data->summary);
            $article->setContent($data->content);
            $article->setImageUrl($data->imageUrl);
            $article->setPublishedAt($data->publishedAt ?? new \DateTimeImmutable());
            $article->setSource($source);
            $article->setCategory($source->getDefaultCategory());

            $this->em->persist($article);
            $imported++;

            if ($imported % 50 === 0) {
                $this->em->flush();
            }
        }

        $this->em->flush();

        $source->setLastFetchedAt(new \DateTimeImmutable());
        $this->em->flush();

        if ($imported > 0) {
            $this->notificationService->broadcast(
                'news_imported',
                sprintf('Импортировано %d новых статей из «%s»', $imported, $source->getName()),
            );
            $this->notifyAllUsers($source->getName(), $imported);
        }

        $this->logger->info('Import complete', [
            'source'   => $source->getName(),
            'imported' => $imported,
        ]);

        return $imported;
    }

    private function findAdapter(NewsSource $source): ?NewsSourceAdapterInterface
    {
        foreach ($this->adapters as $adapter) {
            if ($adapter->supports($source)) {
                return $adapter;
            }
        }
        return null;
    }

    private function notifyAllUsers(string $sourceName, int $count): void
    {
        $users = $this->userRepository->findAll();
        foreach ($users as $user) {
            $this->notificationService->notify(
                $user,
                sprintf('Импортировано %d новых статей из «%s»', $count, $sourceName),
                'news',
            );
        }
    }
}
