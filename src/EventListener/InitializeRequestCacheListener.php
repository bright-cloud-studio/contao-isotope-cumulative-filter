<?php

namespace Bcs\IsotopeCumulativeFilterBundle\EventListener;

use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use Contao\Database;
use Contao\Environment;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Translates ?isorc=<md5hash> → $_GET['isorc'] = integer_id before Isotope's
 * findByIdAndStore() runs.
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

        // Reject non-hash values (plain integer IDs, etc.) — redirect to clean URL.
        if (!preg_match('/^[0-9a-f]{32}$/i', $isorc)) {
            $this->redirectWithoutIsorc();
        }

        // Look up the integer ID by hash.
        $row = Database::getInstance()
            ->prepare('SELECT id FROM tl_iso_requestcache WHERE config_hash = ? LIMIT 1')
            ->execute($isorc)
            ->fetchAssoc();

        if ($row) {
            $_GET['isorc'] = (string) $row['id'];
            return;
        }

        // Unknown hash — redirect to clean URL.
        $this->redirectWithoutIsorc();
    }

    private function redirectWithoutIsorc(): never
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
