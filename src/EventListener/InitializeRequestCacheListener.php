<?php

/**
 * @copyright  Bright Cloud Studio
 * @author     Bright Cloud Studio
 * @package    Contao Isotope Cumulative Filter
 * @license    LGPL-3.0+
 * @see        https://github.com/bright-cloud-studio/contao-isotope-cumulative-filter
 */

namespace Bcs\IsotopeCumulativeFilterBundle\EventListener;

use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use Contao\Database;
use Contao\Environment;
use Contao\LayoutModel;
use Contao\PageModel;
use Contao\PageRegular;
use Haste\Util\Url;

/**
 * Translates a deterministic config_hash in the 'isorc' URL parameter back to
 * the corresponding integer database ID before Isotope's native lookup runs.
 *
 * Background:
 *   Isotope stores filter state as rows in tl_iso_requestcache and exposes the
 *   row's auto-increment integer ID via ?isorc=<id>.  Because every unique filter
 *   combination gets a new ID, search engine robots see an infinite stream of
 *   unique URLs and can crawl endlessly.
 *
 *   BcsCumulativeFilter solves this by writing ?isorc=<config_hash> instead —
 *   the MD5 hash is deterministic (same filters → same hash, forever), so robots
 *   never encounter a URL they haven't seen before.
 *
 *   This listener bridges the gap: it detects when 'isorc' looks like a hash
 *   (32 hex characters) rather than an integer, finds the matching row by hash,
 *   and rewrites $_GET['isorc'] to the integer ID so Isotope's unmodified
 *   lookup (RequestCache::findByIdAndStore) continues to work without any
 *   changes to Isotope core.
 *
 * Hook: getPageLayout — fires before frontend modules compile and before
 * Isotope::initialize() consumes the 'isorc' parameter.
 */
#[AsHook('getPageLayout')]
class InitializeRequestCacheListener
{
    public function __invoke(PageModel $pageModel, LayoutModel $layout, object $pageRegular): void
    {
        $isorc = $_GET['isorc'] ?? '';

        // Only act when the value looks like a 32-character MD5 hex hash.
        // Integer IDs pass through untouched so existing bookmarks / links keep
        // working during any transition period.
        if (!preg_match('/^[0-9a-f]{32}$/i', $isorc)) {
            return;
        }

        $row = Database::getInstance()
            ->prepare('SELECT id FROM tl_iso_requestcache WHERE config_hash = ? LIMIT 1')
            ->execute($isorc)
            ->fetchAssoc();

        if ($row) {
            // Rewrite in-place so Isotope::getRequestCache() finds the row normally.
            $_GET['isorc'] = (string) $row['id'];
        } else {
            // Hash is not in the database (cache was purged, or the URL is stale/forged).
            // Redirect the browser to the same page without the stale isorc parameter
            // so the URL is clean and Isotope does not see an invalid cache entry.
            $cleanUrl = Url::removeQueryString(['isorc']);

            header('HTTP/1.1 302 Found');
            header('Location: ' . Environment::get('base') . ltrim($cleanUrl, '/'));
            exit;
        }
    }
}
