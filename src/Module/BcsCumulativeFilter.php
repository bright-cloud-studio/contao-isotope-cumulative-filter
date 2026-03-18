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
        // Build the new filter state exactly as the parent does
        if ('add' === $action) {
            $filters = $this->addFilter($this->activeFilters, $attribute, $value);
            Isotope::getRequestCache()->setFiltersForModule($filters, $this->id);
        } else {
            Isotope::getRequestCache()->removeFilterForModule(
                $this->generateFilterKey($attribute, $value),
                $this->id
            );
        }

        // Generate a deterministic hash of the current filter state
        // so the same selection always maps to the same isorc ID
        $filterState = Isotope::getRequestCache()->getAllFilters();
        $hash = substr(md5(serialize($filterState)), 0, 12);

        // Check if a cache entry with this hash already exists
        $existing = RequestCache::findOneBy('config_hash', $hash);

        if (null !== $existing) {
            $cacheId = $existing->id;
        } else {
            $objCache = Isotope::getRequestCache()->saveNewConfiguration();
            // Store the hash on the record (requires a migration adding config_hash column)
            $objCache->config_hash = $hash;
            $objCache->save();
            $cacheId = $objCache->id;
        }

        Controller::redirect(
            Environment::get('base') . \Haste\Util\Url::addQueryString(
                'isorc=' . $cacheId,
                \Haste\Util\Url::removeQueryStringCallback(
                    static fn ($v, $k) => 'cumulativefilter' !== $k && !str_starts_with($k, 'page_iso'),
                    ($this->jumpTo ?: null)
                )
            )
        );
    }
}
