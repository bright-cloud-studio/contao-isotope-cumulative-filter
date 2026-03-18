<?php

/**
 * @copyright  Bright Cloud Studio
 * @author     Bright Cloud Studio
 * @package    Contao Isotope Cumulative Filter
 * @license    LGPL-3.0+
 * @see        https://github.com/bright-cloud-studio/contao-isotope-cumulative-filter
 */

namespace Bcs\IsotopeCumulativeFilterBundle\Module;

use Contao\Controller;
use Contao\Environment;
use Isotope\Module\CumulativeFilter;
use Isotope\Isotope;
use Isotope\Model\RequestCache;
use Isotope\RequestCache\Filter;
use Contao\System;

class BcsCumulativeFilter extends CumulativeFilter
{
    protected function saveFilter(string $action, string $attribute, string $value): void
    {
        if ('add' === $action) {
            $filters = $this->addFilter($this->activeFilters, $attribute, $value);
            Isotope::getRequestCache()->setFiltersForModule($filters, $this->id);
        } else {
            Isotope::getRequestCache()->removeFilterForModule(
                $this->generateFilterKey($attribute, $value),
                $this->id
            );
        }
    
        // Let Isotope's own saveNewConfiguration() handle deduplication by config_hash+store_id
        $objCache = Isotope::getRequestCache()->saveNewConfiguration();
    
        Controller::redirect(
            Environment::get('base') . \Haste\Util\Url::addQueryString(
                'isorc=' . $objCache->id,
                \Haste\Util\Url::removeQueryStringCallback(
                    static fn ($v, $k) => 'cumulativefilter' !== $k && !str_starts_with($k, 'page_iso'),
                    ($this->jumpTo ?: null)
                )
            )
        );
    }
}
