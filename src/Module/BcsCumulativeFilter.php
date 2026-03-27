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
use Contao\Database;
use Contao\Environment;
use Haste\Input\Input;
use Haste\Util\Url;
use Isotope\Module\CumulativeFilter;
use Isotope\Isotope;
use Isotope\RequestCache\CsvFilter;
use Isotope\RequestCache\Filter;
use Isotope\RequestCache\Sort;

class BcsCumulativeFilter extends CumulativeFilter
{
    /**
     * Override compile() to intercept the filter-save request before the
     * parent's private saveFilter() runs, so we can redirect with a
     * deterministic MD5 hash instead of a database auto-increment ID.
     *
     * Flow:
     *   1. Detect the cumulativefilter action for this module (same guard as parent).
     *   2. Apply the filter change to the request cache.
     *   3. Persist it with saveNewConfiguration() — this writes the row (or finds the
     *      existing one) and stores config_hash in the database.
     *   4. Read config_hash back from the database using the returned row ID.
     *      (We read from DB rather than recomputing because RequestCache::preSave()
     *      is protected and the exact serialisation must stay in sync with core.)
     *   5. Redirect to ?isorc=<config_hash>.
     *
     * For every other request we fall through to the unmodified parent compile().
     */
    protected function compile(): void
    {
        $decoded = base64_decode(Input::get('cumulativefilter', true));

        if (false === $decoded) {
            parent::compile();
            return;
        }
        
        $arrFilter = explode(';', $decoded, 4);
        
        if (count($arrFilter) < 4) {
            parent::compile();
            return;
        }

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

            // Read the hash that RequestCache::preSave() wrote to the database.
            // We do NOT recompute it here — that would risk a mismatch if the
            // core serialisation ever changes.
            $row = Database::getInstance()
                ->prepare('SELECT config_hash FROM tl_iso_requestcache WHERE id=? LIMIT 1')
                ->execute($objCache->id)
                ->fetchAssoc();

            // Fall back to integer ID only if the DB read somehow fails.
            $hash = $row['config_hash'] ?? $objCache->id;

            Controller::redirect(
                Environment::get('base') . Url::addQueryString(
                    'isorc=' . $hash,
                    Url::removeQueryStringCallback(
                        static fn ($v, $k) => 'cumulativefilter' !== $k && 'isorc' !== $k && !str_starts_with($k, 'page_iso'),
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
        // isCsv() and isMultiple() are inherited from CumulativeFilter (protected)
        
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
