<?php
/**
 * Founder Reports Bridge
 * Fetches founder interviews from founderreports.com using their sitemap
 */
class FounderReportsBridge extends BridgeAbstract
{
    const NAME = 'Founder Reports';
    const URI = 'https://founderreports.com';
    const DESCRIPTION = 'Returns founder interviews from Founder Reports';
    const MAINTAINER = 'Curator Bot';
    const CACHE_TIMEOUT = 3600; // 1 hour

    public function collectData()
    {
        // Fetch the interview sitemap which contains all interview URLs with last-modified dates
        $sitemapUrl = self::URI . '/interview-sitemap.xml';
        $xml = getContents($sitemapUrl);
        $sitemap = simplexml_load_string($xml);

        // Register the namespace for proper XML parsing
        $sitemap->registerXPathNamespace('ns', 'http://www.sitemaps.org/schemas/sitemap/0.9');

        // Extract interview URLs
        $interviews = [];
        foreach ($sitemap->url as $url) {
            $loc = (string)$url->loc;
            $lastmod = (string)$url->lastmod;

            // Only include interview pages (skip the /interviews/ directory page)
            if (strpos($loc, '/interview/') !== false && substr($loc, -12) !== '/interviews/') {
                $interviews[] = [
                    'url' => $loc,
                    'lastmod' => strtotime($lastmod)
                ];
            }
        }

        // Sort by last modified date (newest first)
        usort($interviews, function($a, $b) {
            return $b['lastmod'] - $a['lastmod'];
        });

        // Take only the 20 most recent interviews
        $interviews = array_slice($interviews, 0, 20);

        // Fetch each interview page to get title and description
        foreach ($interviews as $interview) {
            $item = [];
            $item['uri'] = $interview['url'];
            $item['timestamp'] = $interview['lastmod'];

            try {
                $html = getSimpleHTMLDOM($interview['url']);

                // Extract title from meta tags
                $titleMeta = $html->find('meta[property="og:title"]', 0);
                if ($titleMeta) {
                    $item['title'] = $titleMeta->content;
                } else {
                    $h1 = $html->find('h1', 0);
                    $item['title'] = $h1 ? trim($h1->plaintext) : 'Founder Interview';
                }

                // Extract description from meta tags
                $descMeta = $html->find('meta[property="og:description"]', 0);
                if ($descMeta) {
                    $item['content'] = $descMeta->content;
                } else {
                    $descMetaTag = $html->find('meta[name="description"]', 0);
                    $item['content'] = $descMetaTag ? $descMetaTag->content : '';
                }

                // Extract author if available
                $authorMeta = $html->find('meta[name="author"]', 0);
                if ($authorMeta) {
                    $item['author'] = $authorMeta->content;
                }

                // Extract image if available
                $imageMeta = $html->find('meta[property="og:image"]', 0);
                if ($imageMeta && isset($imageMeta->content)) {
                    $item['enclosures'] = [$imageMeta->content];
                }

                $this->items[] = $item;
            } catch (Exception $e) {
                // Skip interviews that fail to load
                continue;
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
