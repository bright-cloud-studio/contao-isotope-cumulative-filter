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
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Normalises the ?isorc= query parameter before Isotope's findByIdAndStore() runs.
 *
 * Three cases are handled:
 *
 *   1. isorc is an MD5 hash  → translate to integer ID in $_GET so Isotope works.
 *      (Standard flow for BcsCumulativeFilter.)
 *
 *   2. isorc is an integer ID → look up the hash and 301-redirect to the hash URL.
 *      Handles Isotope's stock CategoryFilter (iso_categoryfilter) and any other
 *      filter module that has not been extended to emit hashes directly.
 *      The extra round-trip is normally eliminated in Contao 5 by the companion
 *      ReplaceIsorcInRedirectListener (which upgrades the redirect before it leaves
 *      the server), but this case is kept as a reliable fallback.
 *
 *   3. isorc is anything else (malformed, unknown hash, unknown ID) → strip it and
 *      redirect to the clean URL.
 *
 * MUST fire before Module::__construct() triggers Isotope::initialize().
 * initializeSystem fires early enough; getPageLayout does NOT (too late).
 */
#[AsHook('initializeSystem')]
class InitializeRequestCacheListener
{
    public function __invoke(): void
    {
        $isorc = $_GET['isorc'] ?? '';

        if ('' === $isorc) {
            return;
        }

        // ── Case 1: already a hash ────────────────────────────────────────────
        if (preg_match('/^[0-9a-f]{32}$/i', $isorc)) {
            $row = Database::getInstance()
                ->prepare('SELECT id FROM tl_iso_requestcache WHERE config_hash = ? LIMIT 1')
                ->execute($isorc)
                ->fetchAssoc();

            if ($row) {
                $_GET['isorc'] = (string) $row['id'];
                return;
            }

            // Unknown hash — strip and redirect.
            $this->redirectWithoutIsorc();
        }

        // ── Case 2: plain integer ID (e.g. from stock iso_categoryfilter) ────
        if (preg_match('/^\d+$/', $isorc)) {
            $row = Database::getInstance()
                ->prepare('SELECT config_hash FROM tl_iso_requestcache WHERE id = ? LIMIT 1')
                ->execute((int) $isorc)
                ->fetchAssoc();

            if (isset($row['config_hash']) && '' !== $row['config_hash']) {
                $this->redirectWithHash($row['config_hash']);
                // never reached
            }

            // Unknown integer ID — strip and redirect.
            $this->redirectWithoutIsorc();
        }

        // ── Case 3: malformed value ───────────────────────────────────────────
        $this->redirectWithoutIsorc();
    }

    // ─── Private helpers ──────────────────────────────────────────────────────

    /**
     * 301-redirect the current URL with isorc replaced by the given hash.
     */
    private function redirectWithHash(string $hash): void
    {
        $currentUrl = Environment::get('uri');

        $newUrl = preg_replace(
            '/([?&]isorc=)[^&]+/',
            '${1}' . $hash,
            $currentUrl
        );

        $response = new RedirectResponse($newUrl, 301);
        $response->headers->set('Cache-Control', 'no-store');
        $response->send();
        exit;
    }

    /**
     * 301-redirect to the current URL with isorc (and page_iso* pagination) stripped.
     */
    private function redirectWithoutIsorc(): void
    {
        $currentUrl = Environment::get('uri');

        $cleanUrl = preg_replace_callback(
            '/([?&])(?:isorc|page_iso[^=&]*)=[^&]*(&|$)/',
            static function (array $m): string {
                return '?' === $m[1] && '&' === $m[2] ? '?' : ($m[2] ?: '');
            },
            $currentUrl
        );

        $cleanUrl = rtrim($cleanUrl, '?&');

        $response = new RedirectResponse($cleanUrl, 301);
        $response->headers->set('Cache-Control', 'no-store');
        $response->send();
        exit;
    }
}
