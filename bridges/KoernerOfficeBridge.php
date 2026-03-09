<?php
/**
 * The Koerner Office Bridge
 * Fetches posts from Chris Koerner's newsletter using their sitemap
 */
class KoernerOfficeBridge extends BridgeAbstract
{
    const NAME = 'The Koerner Office';
    const URI = 'https://newsletter.chrisjkoerner.com';
    const DESCRIPTION = 'Returns business and side hustle posts from The Koerner Office newsletter';
    const MAINTAINER = 'Curator Bot';
    const CACHE_TIMEOUT = 3600; // 1 hour

    public function collectData()
    {
        // Fetch the sitemap which contains all post URLs with last-modified dates
        $sitemapUrl = self::URI . '/sitemap.xml';
        $xml = getContents($sitemapUrl);
        $sitemap = simplexml_load_string($xml);

        // Extract post URLs (those starting with /p/)
        $posts = [];
        foreach ($sitemap->url as $url) {
            $loc = (string)$url->loc;
            $lastmod = (string)$url->lastmod;

            // Only include post pages (/p/)
            if (strpos($loc, '/p/') !== false) {
                $posts[] = [
                    'url' => $loc,
                    'lastmod' => strtotime($lastmod)
                ];
            }
        }

        // Sort by last modified date (newest first)
        usort($posts, function($a, $b) {
            return $b['lastmod'] - $a['lastmod'];
        });

        // Take only the 20 most recent posts
        $posts = array_slice($posts, 0, 20);

        // Fetch each post page to get title and description
        foreach ($posts as $post) {
            $item = [];
            $item['uri'] = $post['url'];
            $item['timestamp'] = $post['lastmod'];

            try {
                $html = getSimpleHTMLDOM($post['url']);

                // Extract title from meta tags or h1
                $titleMeta = $html->find('meta[property="og:title"]', 0);
                if ($titleMeta) {
                    $item['title'] = $titleMeta->content;
                } else {
                    $h1 = $html->find('h1', 0);
                    $item['title'] = $h1 ? trim($h1->plaintext) : 'The Koerner Office Post';
                }

                // Extract description from meta tags
                $descMeta = $html->find('meta[property="og:description"]', 0);
                if ($descMeta) {
                    $item['content'] = $descMeta->content;
                } else {
                    $descMetaTag = $html->find('meta[name="description"]', 0);
                    $item['content'] = $descMetaTag ? $descMetaTag->content : '';
                }

                // Extract author
                $item['author'] = 'Chris Koerner';

                // Extract image if available
                $imageMeta = $html->find('meta[property="og:image"]', 0);
                if ($imageMeta && isset($imageMeta->content)) {
                    $item['enclosures'] = [$imageMeta->content];
                }

                $this->items[] = $item;
            } catch (Exception $e) {
                // Skip posts that fail to load
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
