<?php

/**
 * @copyright  Bright Cloud Studio
 * @author     Bright Cloud Studio
 * @package    Contao Isotope Cumulative Filter
 * @license    LGPL-3.0+
 * @see        https://github.com/bright-cloud-studio/contao-isotope-cumulative-filter
 */

namespace Bcs\IsotopeCumulativeFilterBundle\EventListener;

use Contao\Database;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Upgrades any outgoing redirect that contains ?isorc=<integer> to use the
 * deterministic MD5 hash instead, before the response leaves the server.
 *
 * WHY THIS EXISTS
 * ───────────────
 * BcsCumulativeFilter intercepts its own save-and-redirect cycle and already
 * emits ?isorc=<hash>. But Isotope's stock CategoryFilter (iso_categoryfilter)
 * and any other unextended filter module still emit ?isorc=<integer>.
 *
 * This listener catches those redirects at the Symfony kernel.response level
 * and rewrites the Location header to the hash form — eliminating the extra
 * round-trip that would otherwise be caused by InitializeRequestCacheListener's
 * Case 2 fallback (integer → hash redirect on the next request).
 *
 * CONTAO 5 ONLY
 * ─────────────
 * In Contao 5, Controller::redirect() throws a RedirectResponseException which
 * propagates through Symfony's kernel and fires kernel.response, so this listener
 * can intercept it. In Contao 4.13, Controller::redirect() calls exit() directly,
 * bypassing the kernel — the InitializeRequestCacheListener Case 2 fallback
 * handles that scenario instead (one extra round-trip, but functionally correct).
 *
 * PRIORITY
 * ────────
 * -100 ensures we run after Contao's own response subscribers but before the
 * response is sent. Any value between -256 and 0 is safe here.
 */
#[AsEventListener(event: KernelEvents::RESPONSE, priority: -100)]
class ReplaceIsorcInRedirectListener
{
    public function __invoke(ResponseEvent $event): void
    {
        $response = $event->getResponse();

        // Only act on redirect responses.
        if (!$response->isRedirection()) {
            return;
        }

        $location = $response->headers->get('Location', '');

        // Only act when isorc is a plain integer (not already a hash).
        if (!preg_match('/[?&]isorc=(\d+)(?:&|$)/', $location, $matches)) {
            return;
        }

        $id = (int) $matches[1];

        $row = Database::getInstance()
            ->prepare('SELECT config_hash FROM tl_iso_requestcache WHERE id = ? LIMIT 1')
            ->execute($id)
            ->fetchAssoc();

        if (!isset($row['config_hash']) || '' === $row['config_hash']) {
            // No hash on record — leave the redirect untouched; the
            // InitializeRequestCacheListener will strip the unknown ID on arrival.
            return;
        }

        $newLocation = preg_replace_callback(
            '/([?&]isorc=)\d+/',
            static fn(array $m) => $m[1] . $row['config_hash'],
            $location
        );

        $response->headers->set('Location', $newLocation);
    }
}
