<?php

declare(strict_types=1);

namespace App\Service\Adapter;

use App\Dto\ArticleData;
use App\Entity\NewsSource;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.news_source_adapter')]
interface NewsSourceAdapterInterface
{
    public function supports(NewsSource $source): bool;

    /** @return ArticleData[] */
    public function fetch(NewsSource $source): array;
}
