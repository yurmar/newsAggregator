<?php

declare(strict_types=1);

namespace App\Command;

use App\Message\ImportNewsMessage;
use App\Repository\NewsSourceRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(name: 'app:news:import', description: 'Запустить импорт новостей из всех активных источников')]
class ImportNewsCommand extends Command
{
    public function __construct(
        private readonly NewsSourceRepository $sourceRepository,
        private readonly MessageBusInterface $bus,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('source-id', null, InputOption::VALUE_OPTIONAL, 'ID конкретного источника для импорта');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $sourceId = $input->getOption('source-id');

        if ($sourceId) {
            $sources = array_filter([$this->sourceRepository->find((int) $sourceId)]);
        } else {
            $sources = $this->sourceRepository->findActive();
        }

        if (empty($sources)) {
            $io->warning('Активных источников не найдено');
            return Command::SUCCESS;
        }

        foreach ($sources as $source) {
            $this->bus->dispatch(new ImportNewsMessage($source->getId()));
            $io->text(sprintf('Задача импорта отправлена: <info>%s</info>', $source->getName()));
        }

        $io->success(sprintf('Отправлено %d задач импорта в очередь', count($sources)));
        return Command::SUCCESS;
    }
}
