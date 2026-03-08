<?php
/**
 * Boring Cash Cow Bridge
 * Fetches case study reports from boringcashcow.com
 */
class BoringCashCowBridge extends BridgeAbstract
{
    const NAME = 'Boring Cash Cow';
    const URI = 'https://boringcashcow.com';
    const DESCRIPTION = 'Returns case study reports of profitable small businesses from Boring Cash Cow';
    const MAINTAINER = 'Curator Bot';
    const CACHE_TIMEOUT = 3600; // 1 hour

    public function collectData()
    {
        // Fetch the reports archive page
        $html = getSimpleHTMLDOM(self::URI . '/report');

        // Find all report links - they follow the pattern /view/slug-name
        $links = $html->find('a[href*="/view/"]');

        // Track processed URLs to avoid duplicates
        $processedUrls = [];

        foreach ($links as $link) {
            $url = $link->href;

            // Skip if we've already processed this URL
            if (in_array($url, $processedUrls)) {
                continue;
            }

            // Skip if not a valid report URL
            if (strpos($url, '/view/') === false) {
                continue;
            }

            $processedUrls[] = $url;

            $item = [];

            // Build full URL if relative
            if (strpos($url, 'http') !== 0) {
                $item['uri'] = self::URI . $url;
            } else {
                $item['uri'] = $url;
            }

            // Extract title - it should be in the link text
            $title = trim($link->plaintext);
            if (!empty($title)) {
                $item['title'] = $title;
            } else {
                // Fallback: use slug from URL
                $slug = basename($url);
                $item['title'] = ucwords(str_replace('-', ' ', $slug));
            }

            // Try to find description near the link
            // Look at the parent element and find nearby text
            $parent = $link->parent();
            if ($parent) {
                // Try to find a description element
                $desc = $parent->find('p, div.description, div.excerpt', 0);
                if ($desc) {
                    $item['content'] = trim($desc->plaintext);
                }
            }

            // Try to find an image associated with this report
            if ($parent) {
                $img = $parent->find('img', 0);
                if ($img && isset($img->src)) {
                    $imgUrl = $img->src;
                    // Make sure image URL is absolute
                    if (strpos($imgUrl, 'http') !== 0) {
                        $imgUrl = self::URI . $imgUrl;
                    }
                    $item['enclosures'] = [$imgUrl];
                }
            }

            // Since no dates are available, use current time
            // Reports appear in order on the page, so earlier = newer
            $item['timestamp'] = time() - (count($this->items) * 3600); // Stagger by 1 hour each

            $this->items[] = $item;

            // Limit to 20 reports to avoid overload
            if (count($this->items) >= 20) {
                break;
            }
        }
    }

    public function getURI()
    {
        return self::URI;
    }

    public function getName()
    {
        return self::NAME;
    }
}
