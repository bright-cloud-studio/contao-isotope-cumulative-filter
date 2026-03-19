# Contao Isotope Cumulative Filter

A Contao bundle that extends Isotope's built-in Cumulative Filter module to use deterministic, content-based URL parameters — preventing search engine robots from crawling an infinite stream of unique filter URLs.

## The Problem

Isotope's native Cumulative Filter stores each unique filter combination as a row in `tl_iso_requestcache` and exposes the row's auto-increment integer ID in the URL as `?isorc=<id>`. Because every unique filter combination produces a new, never-before-seen integer ID, search engine robots encounter an endless supply of distinct URLs and can crawl indefinitely — consuming crawl budget and potentially causing indexing issues.

## The Solution

This bundle replaces the auto-increment integer in `?isorc=` with a deterministic MD5 hash of the filter configuration. The same combination of filters always produces the same hash, so robots never encounter a URL they haven't already seen. Pages with active filter parameters are also marked `noindex,nofollow` automatically.

The hash is resolved back to the corresponding database row ID transparently before Isotope's native lookup runs, so Isotope core requires no modifications.

## Requirements

- PHP `^7.4 || ^8.0`
- Contao `^4.13 || ^5.3`
- Isotope eCommerce (with the `iso_cumulativefilter` frontend module)

## Installation

```bash
composer require bright-cloud-studio/contao-isotope-cumulative-filter
```

After installation, run a Contao database update if prompted.

## Usage

In the Contao backend, configure your filter frontend module as you normally would, but select **Cumulative Filter (BCS)** as the module type instead of Isotope's default **Cumulative Filter**. No other configuration is required.

## How It Works

### `BcsCumulativeFilter` (Module)

Extends Isotope's `CumulativeFilter` class. Intercepts the filter save request, persists the filter state via `RequestCache::saveNewConfiguration()`, then reads the `config_hash` column written by Isotope's own `preSave()` logic and redirects to `?isorc=<hash>` instead of `?isorc=<id>`.

### `InitializeRequestCacheListener` (Hook: `getPageLayout`)

Fires before Isotope initialises. When `?isorc=` contains a 32-character hex hash, it looks up the corresponding integer ID in `tl_iso_requestcache` and rewrites `$_GET['isorc']` in place. Isotope's unmodified `RequestCache::findByIdAndStore()` then proceeds normally. If the hash is not found (cache purged or stale URL), the parameter is removed entirely so Isotope does not mark the page as invalid.

### `GeneratePageListener` (Hook: `generatePage`)

Detects requests containing `isorc` or `cumulativefilter` query parameters and sets the page's robots directive to `noindex,nofollow`, keeping filtered pages out of search engine indexes.

## License

LGPL-3.0-or-later. See [LICENSE](LICENSE) for full terms.

## Credits

Developed by [Bright Cloud Studio](https://brightcloudstudio.com).
