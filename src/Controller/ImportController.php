<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\NewsSource;
use App\Message\ImportNewsMessage;
use App\Repository\NewsSourceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/import', name: 'app_import_')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class ImportController extends AbstractController
{
    public function __construct(
        private readonly NewsSourceRepository $sourceRepository,
        private readonly EntityManagerInterface $em,
        private readonly MessageBusInterface $bus,
    ) {}

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('import/index.html.twig', [
            'sources' => $this->sourceRepository->findBy([], ['name' => 'ASC']),
        ]);
    }

    #[Route('/source/add', name: 'source_add', methods: ['POST'])]
    public function sourceAdd(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('source_add', $request->request->get('_token'))) {
            $this->addFlash('error', 'Недействительный CSRF-токен.');
            return $this->redirectToRoute('app_import_index');
        }

        $name = trim($request->request->getString('name'));
        $url  = trim($request->request->getString('url'));
        $type = $request->request->getString('type', NewsSource::TYPE_RSS);

        if ($name === '' || $url === '') {
            $this->addFlash('error', 'Название и URL обязательны.');
            return $this->redirectToRoute('app_import_index');
        }

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            $this->addFlash('error', 'Некорректный URL: ' . htmlspecialchars($url));
            return $this->redirectToRoute('app_import_index');
        }

        $allowed = [NewsSource::TYPE_RSS, NewsSource::TYPE_API, NewsSource::TYPE_HTML];
        if (!in_array($type, $allowed, true)) {
            $type = NewsSource::TYPE_RSS;
        }

        $source = new NewsSource();
        $source->setName($name);
        $source->setUrl($url);
        $source->setType($type);

        $this->em->persist($source);
        $this->em->flush();

        $this->addFlash('success', sprintf('Источник «%s» добавлен.', $source->getName()));
        return $this->redirectToRoute('app_import_index');
    }

    #[Route('/delete/{id}', name: 'delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(int $id, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('source_delete_' . $id, $request->request->get('_token'))) {
            $this->addFlash('error', 'Недействительный CSRF-токен.');
            return $this->redirectToRoute('app_import_index');
        }

        $source = $this->sourceRepository->find($id);
        if ($source) {
            $name = $source->getName();
            $this->em->remove($source);
            $this->em->flush();
            $this->addFlash('success', sprintf('Источник «%s» удалён.', $name));
        }

        return $this->redirectToRoute('app_import_index');
    }

    #[Route('/toggle/{id}', name: 'toggle', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function toggle(int $id, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('source_toggle_' . $id, $request->request->get('_token'))) {
            $this->addFlash('error', 'Недействительный CSRF-токен.');
            return $this->redirectToRoute('app_import_index');
        }

        $source = $this->sourceRepository->find($id);
        if ($source) {
            $source->setIsActive(!$source->isActive());
            $this->em->flush();
        }

        return $this->redirectToRoute('app_import_index');
    }

    #[Route('/run/{id}', name: 'run', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function run(int $id, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('import_run', $request->request->get('_token'))) {
            $this->addFlash('error', 'Недействительный CSRF-токен.');
            return $this->redirectToRoute('app_import_index');
        }

        $source = $this->sourceRepository->find($id);
        if (!$source) {
            $this->addFlash('error', 'Источник не найден.');
            return $this->redirectToRoute('app_import_index');
        }

        if (!$source->isActive()) {
            $this->addFlash('warning', sprintf('Источник «%s» неактивен.', $source->getName()));
            return $this->redirectToRoute('app_import_index');
        }

        $this->bus->dispatch(new ImportNewsMessage($source->getId()));
        $this->addFlash('success', sprintf('Импорт «%s» поставлен в очередь.', $source->getName()));

        return $this->redirectToRoute('app_import_index');
    }

    #[Route('/run-all', name: 'run_all', methods: ['POST'])]
    public function runAll(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('import_run_all', $request->request->get('_token'))) {
            $this->addFlash('error', 'Недействительный CSRF-токен.');
            return $this->redirectToRoute('app_import_index');
        }

        $sources = $this->sourceRepository->findActive();
        if (empty($sources)) {
            $this->addFlash('warning', 'Активных источников не найдено.');
            return $this->redirectToRoute('app_import_index');
        }

        foreach ($sources as $source) {
            $this->bus->dispatch(new ImportNewsMessage($source->getId()));
        }

        $this->addFlash('success', sprintf(
            'Поставлено в очередь %d %s. Статьи появятся через несколько секунд.',
            count($sources),
            $this->pluralize(count($sources), 'источник', 'источника', 'источников')
        ));

        return $this->redirectToRoute('app_import_index');
    }

    private function pluralize(int $n, string $one, string $few, string $many): string
    {
        $n  = abs($n) % 100;
        $n1 = $n % 10;
        if ($n >= 11 && $n <= 14) {
            return $many;
        }
        if ($n1 === 1) {
            return $one;
        }
        if ($n1 >= 2 && $n1 <= 4) {
            return $few;
        }
        return $many;
    }
}
