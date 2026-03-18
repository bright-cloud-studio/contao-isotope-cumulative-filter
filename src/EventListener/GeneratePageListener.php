<?php

namespace Bcs\IsotopeCumulativeFilterBundle\EventListener;

use Contao\PageModel;
use Contao\LayoutModel;
use Contao\PageRegular;
use Contao\Environment;
use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;

#[AsHook('generatePage')]
class GeneratePageListener
{
    public function __invoke(PageModel $pageModel, LayoutModel $layout, object $pageRegular): void
    {
        // Check if the request contains 'isorc' or 'cumulativefilter' parameters
        if (preg_match('/[?&](isorc|cumulativefilter)=/', Environment::get('request'))) {
            $pageModel->robots = 'noindex,nofollow';
        }
    }
}
