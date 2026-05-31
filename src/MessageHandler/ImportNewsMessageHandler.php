<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\ImportNewsMessage;
use App\Repository\NewsSourceRepository;
use App\Service\NewsImportService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class ImportNewsMessageHandler
{
    public function __construct(
        private readonly NewsSourceRepository $sourceRepository,
        private readonly NewsImportService $importService,
        private readonly LoggerInterface $logger,
    ) {}

    public function __invoke(ImportNewsMessage $message): void
    {
        $source = $this->sourceRepository->find($message->sourceId);
        if (!$source || !$source->isActive()) {
            return;
        }

        $this->logger->info('Starting news import', ['source' => $source->getName()]);
        $count = $this->importService->importFromSource($source);
        $this->logger->info('News import complete', ['source' => $source->getName(), 'imported' => $count]);
    }
}
