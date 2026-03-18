<?php

namespace Bcs\IsotopeCumulativeFilterBundle\EventListener;

use Contao\PageModel;
use Contao\LayoutModel;
use Contao\PageRegular;
use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;

#[AsHook('generatePage')]
class GeneratePageListener
{
    public function __invoke(PageModel $pageModel, LayoutModel $layout, PageRegular $pageRegular): void
    {
        // your logic here
        echo "Hook hooked hookfully!";
        die();
    }
}
