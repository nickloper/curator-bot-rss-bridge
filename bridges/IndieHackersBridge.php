<?php
/**
 * Indie Hackers Bridge
 * Fetches case study stories from indiehackers.com/stories
 */
class IndieHackersBridge extends BridgeAbstract
{
    const NAME = 'Indie Hackers';
    const URI = 'https://www.indiehackers.com';
    const DESCRIPTION = 'Returns case study stories from Indie Hackers';
    const MAINTAINER = 'Curator Bot';
    const CACHE_TIMEOUT = 3600; // 1 hour

    public function collectData()
    {
        // Fetch the stories page
        $html = getSimpleHTMLDOM(self::URI . '/stories');

        // Find all story links - they follow the pattern /post/[ID]
        $links = $html->find('a[href*="/post/"]');

        // Collect unique post URLs
        $postUrls = [];
        foreach ($links as $link) {
            $url = $link->href;

            // Ensure it's a post URL and not already collected
            if (preg_match('#/post/[a-zA-Z0-9]+$#', $url) && !in_array($url, $postUrls)) {
                $postUrls[] = $url;
            }
        }

        // Limit to first 12 URLs to fetch (faster, reduces timeout risk)
        $postUrls = array_slice($postUrls, 0, 12);

        $stories = [];

        // Fetch each story page to get details
        foreach ($postUrls as $postUrl) {
            try {
                $fullUrl = self::URI . $postUrl;
                $storyHtml = getSimpleHTMLDOM($fullUrl);

                $item = [];
                $item['uri'] = $fullUrl;

                // Extract data from JSON-LD structured data (primary source)
                $scripts = $storyHtml->find('script[type="application/ld+json"]');
                $jsonData = null;

                foreach ($scripts as $script) {
                    $data = json_decode($script->innertext, true);
                    if ($data && isset($data['@type']) && $data['@type'] === 'Article') {
                        $jsonData = $data;
                        break;
                    }
                }

                // Extract title
                if ($jsonData && isset($jsonData['headline'])) {
                    $item['title'] = $jsonData['headline'];
                } else {
                    $titleMeta = $storyHtml->find('meta[property="og:title"]', 0);
                    if ($titleMeta) {
                        $item['title'] = $titleMeta->content;
                    } else {
                        $h1 = $storyHtml->find('h1', 0);
                        $item['title'] = $h1 ? trim($h1->plaintext) : 'Indie Hacker Story';
                    }
                }

                // Extract description
                if ($jsonData && isset($jsonData['description'])) {
                    $item['content'] = $jsonData['description'];
                } else {
                    $descMeta = $storyHtml->find('meta[property="og:description"]', 0);
                    if ($descMeta) {
                        $item['content'] = $descMeta->content;
                    } else {
                        $descMetaTag = $storyHtml->find('meta[name="description"]', 0);
                        $item['content'] = $descMetaTag ? $descMetaTag->content : '';
                    }
                }

                // Extract publication date
                if ($jsonData && isset($jsonData['datePublished'])) {
                    $item['timestamp'] = strtotime($jsonData['datePublished']);
                } else {
                    // No date found, skip this story
                    continue;
                }

                // Extract author
                if ($jsonData && isset($jsonData['author']['name'])) {
                    $item['author'] = $jsonData['author']['name'];
                }

                // Extract image if available
                if ($jsonData && isset($jsonData['image']['url'])) {
                    $item['enclosures'] = [$jsonData['image']['url']];
                } else {
                    $imageMeta = $storyHtml->find('meta[property="og:image"]', 0);
                    if ($imageMeta && isset($imageMeta->content)) {
                        $item['enclosures'] = [$imageMeta->content];
                    }
                }

                $stories[] = $item;

            } catch (Exception $e) {
                // Skip stories that fail to load
                continue;
            }
        }

        // Sort stories by timestamp (newest first)
        usort($stories, function($a, $b) {
            return $b['timestamp'] - $a['timestamp'];
        });

        // Return all fetched stories (sorted by date)
        $this->items = $stories;
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
