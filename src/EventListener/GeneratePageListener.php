<?php

/**
 * @copyright  Bright Cloud Studio
 * @author     Bright Cloud Studio
 * @package    Contao Isotope Cumulative Filter
 * @license    LGPL-3.0+
 * @see        https://github.com/bright-cloud-studio/contao-isotope-cumulative-filter
 */

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
