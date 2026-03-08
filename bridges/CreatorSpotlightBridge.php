<?php
/**
 * Creator Spotlight Bridge
 * Fetches creator profiles from creatorspotlight.com using their sitemap
 */
class CreatorSpotlightBridge extends BridgeAbstract
{
    const NAME = 'Creator Spotlight';
    const URI = 'https://www.creatorspotlight.com';
    const DESCRIPTION = 'Returns creator profile case studies and interviews from Creator Spotlight';
    const MAINTAINER = 'Curator Bot';
    const CACHE_TIMEOUT = 3600; // 1 hour

    public function collectData()
    {
        // Fetch the sitemap which contains all profile URLs with last-modified dates
        $sitemapUrl = self::URI . '/sitemap.xml';
        $xml = getContents($sitemapUrl);
        $sitemap = simplexml_load_string($xml);

        // Extract profile URLs (those starting with /p/)
        $profiles = [];
        foreach ($sitemap->url as $url) {
            $loc = (string)$url->loc;
            $lastmod = (string)$url->lastmod;

            // Only include profile pages (/p/)
            if (strpos($loc, '/p/') !== false) {
                $profiles[] = [
                    'url' => $loc,
                    'lastmod' => strtotime($lastmod)
                ];
            }
        }

        // Sort by last modified date (newest first)
        usort($profiles, function($a, $b) {
            return $b['lastmod'] - $a['lastmod'];
        });

        // Take only the 20 most recent profiles
        $profiles = array_slice($profiles, 0, 20);

        // Fetch each profile page to get title and description
        foreach ($profiles as $profile) {
            $item = [];
            $item['uri'] = $profile['url'];
            $item['timestamp'] = $profile['lastmod'];

            try {
                $html = getSimpleHTMLDOM($profile['url']);

                // Extract title from meta tags or h1
                $titleMeta = $html->find('meta[property="og:title"]', 0);
                if ($titleMeta) {
                    $item['title'] = $titleMeta->content;
                } else {
                    $h1 = $html->find('h1', 0);
                    $item['title'] = $h1 ? trim($h1->plaintext) : 'Creator Profile';
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

                $this->items[] = $item;
            } catch (Exception $e) {
                // Skip profiles that fail to load
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
