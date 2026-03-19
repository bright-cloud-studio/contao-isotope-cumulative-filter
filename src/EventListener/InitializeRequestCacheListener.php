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
use Symfony\Component\HttpFoundation\RedirectResponse;

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
 * Strict hash-only enforcement:
 *   The system operates exclusively on hash-based isorc values.  Any isorc
 *   value that is not a valid 32-character MD5 hex hash (including plain
 *   integer IDs such as isorc=1) is treated as invalid and triggers a
 *   301 redirect to the clean page URL.  This prevents integer IDs from
 *   accidentally matching real tl_iso_requestcache rows that were created
 *   before or outside this bundle.
 *
 * Invalid / unknown hash handling:
 *   When a 32-character hex value is present in the URL but has no matching row
 *   in tl_iso_requestcache (cache purged, stale bookmark, manually forged URL,
 *   or bot brute-forcing filter combinations), the visitor is 301-redirected to
 *   the same page with the isorc parameter stripped entirely.
 *
 *   A 301 (Moved Permanently) is used deliberately:
 *     - Search engines that have already indexed the bad URL are told to
 *       permanently forget it and transfer any residual link equity to the
 *       clean page URL.
 *     - Crawlers that brute-force hash variations receive no new content —
 *       each bad hash always resolves to the same canonical page URL, so
 *       there is nothing to index and no incentive to keep probing.
 *     - Legitimate users (e.g. someone sharing a stale bookmarked filter
 *       URL after a cache purge) land gracefully on the unfiltered page
 *       rather than a blank / broken product list.
 *
 *   A 302 (temporary) would not tell search engines to drop the URL, and a
 *   410 (Gone) would be overly harsh — the page itself is valid, only this
 *   particular filter state no longer exists.
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

        // If isorc is not present in the URL at all, nothing to do.
        if ('' === $isorc) {
            return;
        }

        // Reject anything that is not a valid 32-character MD5 hex hash.
        // This includes plain integer IDs (e.g. isorc=1) which could
        // accidentally match real tl_iso_requestcache rows — those must
        // no longer be allowed through.
        if (!preg_match('/^[0-9a-f]{32}$/i', $isorc)) {
            $this->redirectWithoutIsorc();
        }

        // Valid hash format — look it up in the database.
        $row = Database::getInstance()
            ->prepare('SELECT id FROM tl_iso_requestcache WHERE config_hash = ? LIMIT 1')
            ->execute($isorc)
            ->fetchAssoc();

        if ($row) {
            // Known hash — rewrite in-place so Isotope::getRequestCache() finds
            // the row normally via its integer primary key.
            $_GET['isorc'] = (string) $row['id'];
            return;
        }

        // Unknown hash (cache purged, stale link, forged value, etc.):
        // 301-redirect to the clean page URL with isorc stripped.
        $this->redirectWithoutIsorc();
    }

    /**
     * Build the current URL with isorc (and stale page_iso* pagination params)
     * removed, then issue a 301 redirect and exit.
     *
     * cumulativeFilter query parameters are intentionally preserved — they are
     * not filter-state identifiers and must survive the redirect.
     */
    private function redirectWithoutIsorc(): never
    {
        $currentUrl = Environment::get('uri');

        // Remove isorc and any page_iso* pagination parameters from the query
        // string.  cumulativeFilter params are left untouched.
        $cleanUrl = preg_replace_callback(
            '/([?&])(?:isorc|page_iso[^=&]*)=[^&]*(&|$)/',
            static function (array $m): string {
                // If the removed param was preceded by '?' and followed by '&',
                // promote the next param to the query string start.
                // If preceded by '&', drop the trailing '&' separator too.
                return '?' === $m[1] && '&' === $m[2] ? '?' : ($m[2] ?: '');
            },
            $currentUrl
        );

        // Remove any dangling '?' or '&' left after stripping all params.
        $cleanUrl = rtrim($cleanUrl, '?&');

        $response = new RedirectResponse($cleanUrl, 301);
        $response->headers->set('Cache-Control', 'no-store');
        $response->send();
        exit;
    }
}
