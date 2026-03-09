<?php
/**
 * Main Street Minute Bridge
 * Fetches Main Street Minute articles from Contrarian Thinking
 */
class MainStreetMinuteBridge extends BridgeAbstract
{
    const NAME = 'Main Street Minute';
    const URI = 'https://www.contrarianthinking.co';
    const DESCRIPTION = 'Returns Main Street Minute articles from Contrarian Thinking';
    const MAINTAINER = 'Curator Bot';
    const CACHE_TIMEOUT = 3600; // 1 hour

    public function collectData()
    {
        // Fetch the sitemap which contains all article URLs
        $sitemapUrl = self::URI . '/sitemap.xml';
        $xml = getContents($sitemapUrl);
        $sitemap = simplexml_load_string($xml);

        // Register namespace for proper XML parsing
        $sitemap->registerXPathNamespace('ns', 'http://www.sitemaps.org/schemas/sitemap/0.9');

        // Extract newsletter article URLs
        $articles = [];
        foreach ($sitemap->url as $url) {
            $loc = (string)$url->loc;

            // Only include newsletter article pages
            if (strpos($loc, '/newsletter-articles/') !== false) {
                $articles[] = $loc;
            }
        }

        // Sitemap is alphabetical, not chronological
        // We'll check articles sequentially until we find enough Main Street Minute ones
        $mainStreetArticles = [];

        // Loop through all articles until we find 15 Main Street Minute articles
        foreach ($articles as $articleUrl) {
            // Stop if we already have 15 Main Street Minute articles
            if (count($mainStreetArticles) >= 15) {
                break;
            }
            try {
                $html = getSimpleHTMLDOM($articleUrl);

                // Look for the "Main Street Minute" category heading (h3 tag)
                $isMainStreet = false;

                // Check all h3 tags for "Main Street Minute"
                $h3Tags = $html->find('h3');
                foreach ($h3Tags as $h3) {
                    if (stripos($h3->plaintext, 'Main Street Minute') !== false) {
                        $isMainStreet = true;
                        break;
                    }
                }

                // Skip if not a Main Street Minute article
                if (!$isMainStreet) {
                    continue;
                }

                $item = [];
                $item['uri'] = $articleUrl;

                // Extract title from meta tags or h1
                $titleMeta = $html->find('meta[property="og:title"]', 0);
                if ($titleMeta) {
                    $item['title'] = $titleMeta->content;
                } else {
                    $h1 = $html->find('h1', 0);
                    $item['title'] = $h1 ? trim($h1->plaintext) : 'Main Street Minute Article';
                }

                // Extract description from meta tags
                $descMeta = $html->find('meta[property="og:description"]', 0);
                if ($descMeta) {
                    $item['content'] = $descMeta->content;
                } else {
                    $descMetaTag = $html->find('meta[name="description"]', 0);
                    $item['content'] = $descMetaTag ? $descMetaTag->content : '';
                }

                // Extract publication date - look for date text in the page
                // Webflow doesn't always have proper meta tags for dates
                $dateElements = $html->find('time, .date, .published-date');
                $dateFound = false;

                foreach ($dateElements as $dateEl) {
                    $dateText = trim($dateEl->plaintext);
                    if (!empty($dateText)) {
                        $timestamp = strtotime($dateText);
                        if ($timestamp) {
                            $item['timestamp'] = $timestamp;
                            $dateFound = true;
                            break;
                        }
                    }
                }

                // If no date found, try to find date-like text near the title
                if (!$dateFound) {
                    $datePattern = '/\b(January|February|March|April|May|June|July|August|September|October|November|December)\s+\d{1,2},?\s+\d{4}\b/i';
                    $pageText = $html->plaintext;
                    if (preg_match($datePattern, $pageText, $matches)) {
                        $item['timestamp'] = strtotime($matches[0]);
                        $dateFound = true;
                    }
                }

                // Default to current time if no date found
                if (!$dateFound) {
                    $item['timestamp'] = time();
                }

                // Extract author
                $authorMeta = $html->find('meta[name="author"]', 0);
                if ($authorMeta) {
                    $item['author'] = $authorMeta->content;
                } else {
                    $item['author'] = 'Team Contrarian';
                }

                // Extract image
                $imageMeta = $html->find('meta[property="og:image"]', 0);
                if ($imageMeta && isset($imageMeta->content)) {
                    $item['enclosures'] = [$imageMeta->content];
                }

                $mainStreetArticles[] = $item;

            } catch (Exception $e) {
                // Skip articles that fail to load
                continue;
            }
        }

        // Sort by timestamp (newest first)
        usort($mainStreetArticles, function($a, $b) {
            return $b['timestamp'] - $a['timestamp'];
        });

        $this->items = $mainStreetArticles;
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
