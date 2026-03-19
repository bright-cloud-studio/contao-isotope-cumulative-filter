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
use Contao\Input;
use Haste\Util\Url;
use Isotope\Module\CumulativeFilter;
use Isotope\Isotope;
use Isotope\RequestCache\CsvFilter;
use Isotope\RequestCache\Filter;
use Isotope\RequestCache\Sort;

class BcsCumulativeFilter extends CumulativeFilter
{
    /**
     * Override compile() so we can intercept the filter-save request before
     * the parent's compile() calls its *private* saveFilter() method (which
     * we cannot override because PHP private methods are not polymorphic).
     *
     * When a cumulativefilter action is present for this module we:
     *   1. Apply the filter change to the request cache ourselves.
     *   2. Persist it with saveNewConfiguration().
     *   3. Recompute the deterministic MD5 hash (same logic as RequestCache::preSave)
     *      because saveNewConfiguration() never writes config_hash back onto the
     *      returned model instance.
     *   4. Redirect with ?isorc=<hash> instead of ?isorc=<integer id>.
     *
     * For every other request (no filter action, or action for a different module)
     * we fall through to the parent compile() unchanged.
     */
    protected function compile(): void
    {
        $arrFilter = explode(';', base64_decode(Input::get('cumulativefilter', true)), 4);

        // Only intercept when this action belongs to our module instance and
        // the attribute is registered — mirrors the parent's own guard condition.
        if ($arrFilter[0] == $this->id && isset($this->iso_cumulativeFields[$arrFilter[2]])) {
            $action    = $arrFilter[1];
            $attribute = $arrFilter[2];
            $value     = $arrFilter[3];

            if ('add' === $action) {
                $filters = $this->buildAddFilter($this->activeFilters, $attribute, $value);
                Isotope::getRequestCache()->setFiltersForModule($filters, $this->id);

                // Preserve the parent's sorting side-effect on 'add'.
                if ('' === Isotope::getRequestCache()->getFirstSortingFieldForModule($this->id)) {
                    Isotope::getRequestCache()->setSortingForModule(
                        $this->iso_listingSortField,
                        'DESC' === $this->iso_listingSortDirection ? Sort::descending() : Sort::ascending(),
                        $this->id
                    );
                }
            } else {
                Isotope::getRequestCache()->removeFilterForModule(
                    $this->buildFilterKey($attribute, $value),
                    $this->id
                );

                // Preserve the parent's sorting side-effect on 'remove'.
                Isotope::getRequestCache()->removeSortingForModule(
                    $this->iso_listingSortField,
                    $this->id
                );
            }

            $objCache = Isotope::getRequestCache()->saveNewConfiguration();

            // RequestCache::preSave() computes config_hash and writes it to the
            // database but never assigns it back to the model instance, so
            // $objCache->config_hash is always null after the call returns.
            // We reproduce the identical hash computation here.
            $config = [
                'filters'  => $objCache->getFilters()  ?: null,
                'sortings' => $objCache->getSortings() ?: null,
                'limits'   => $objCache->getLimits()   ?: null,
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

            return; // never reached — redirect() exits — but makes intent clear
        }

        // No filter action for this module: delegate entirely to the parent.
        parent::compile();
    }

    // -------------------------------------------------------------------------
    // Private helpers (duplicated from parent because parent's are private)
    // -------------------------------------------------------------------------

    private function buildAddFilter(array $filters, string $attribute, string $value): array
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

        $filters[$this->buildFilterKey($attribute, $value)] = $filter;

        return $filters;
    }

    private function buildFilterKey(string $field, string $value): string
    {
        return $field . '=' . $value;
    }
}
