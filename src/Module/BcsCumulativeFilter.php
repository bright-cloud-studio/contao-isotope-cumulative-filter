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
use Haste\Util\Url;
use Isotope\Module\CumulativeFilter;
use Isotope\Isotope;
use Isotope\RequestCache\CsvFilter;
use Isotope\RequestCache\Filter;

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
    
        $objCache = Isotope::getRequestCache()->saveNewConfiguration();
    
        // Recompute the hash using the same method Isotope uses in preSave(),
        // because config_hash is never written back onto the model instance.
        $config = [
            'filters'  => $objCache->getFilters()   ?: null,
            'sortings' => $objCache->getSortings()  ?: null,
            'limits'   => $objCache->getLimits()    ?: null,
        ];
        $hash = md5(serialize($config));
    
        Controller::redirect(
            Environment::get('base') . Url::addQueryString(
                'isorc=' . $hash,
                Url::removeQueryStringCallback(
                    static fn ($v, $k) => 'cumulativefilter' !== $k && !str_starts_with($k, 'page_iso'),
                    ($this->jumpTo ?: null)
                )
            )
        );
    }

    private function addFilter(array $filters, string $attribute, string $value): array
    {
        if ($this->isCsv($attribute)) {
            $filter = CsvFilter::attribute($attribute)->contains($value);
        } else {
            $filter = Filter::attribute($attribute)->isEqualTo($value);
        }

        if (!$this->isMultiple($attribute) || self::QUERY_OR === $this->iso_cumulativeFields[$attribute]['queryType']) {
            $group = 'cumulative_' . $attribute;
            $filter->groupBy($group);

            if (self::QUERY_AND === $this->iso_cumulativeFields[$attribute]['queryType']) {
                foreach ($filters as $k => $oldFilter) {
                    if ($oldFilter->getGroup() == $group) {
                        unset($filters[$k]);
                    }
                }
            }
        }

        $filters[$this->generateFilterKey($attribute, $value)] = $filter;

        return $filters;
    }

    private function generateFilterKey(string $field, string $value): string
    {
        return $field . '=' . $value;
    }
}
