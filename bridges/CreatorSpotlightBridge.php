<?php
/**
 * Creator Spotlight Bridge
 * Fetches profiles from creatorspotlight.com/profiles
 */
class CreatorSpotlightBridge extends BridgeAbstract
{
    const NAME = 'Creator Spotlight';
    const URI = 'https://www.creatorspotlight.com';
    const DESCRIPTION = 'Returns profiles from Creator Spotlight';
    const MAINTAINER = 'Curator Bot';
    const CACHE_TIMEOUT = 3600; // 1 hour

    public function collectData()
    {
        $html = getSimpleHTMLDOM(self::URI . '/profiles');

        // Since this is a dynamic React/JavaScript site, we may need to parse JSON-LD data
        // or look for specific elements. Let's try to find profile cards.

        // Look for common patterns in creator profile sites
        $items = $html->find('article, .profile-card, .creator-card, [class*="profile"], [class*="creator"]');

        if (empty($items)) {
            // Fallback: Try to find any links that might be profiles
            $items = $html->find('a[href*="/profile"], a[href*="/creator"]');
        }

        foreach ($items as $element) {
            $item = [];

            // Try to extract title from various possible elements
            $titleElement = $element->find('h1, h2, h3, h4, .title, .name', 0);
            if ($titleElement) {
                $item['title'] = trim($titleElement->plaintext);
            } else {
                $item['title'] = 'New Creator Profile';
            }

            // Try to extract description
            $descElement = $element->find('p, .description, .bio', 0);
            if ($descElement) {
                $item['content'] = trim($descElement->plaintext);
            }

            // Extract link
            $linkElement = $element->find('a', 0);
            if ($linkElement) {
                $item['uri'] = $this->getURI() . $linkElement->href;
            } else if ($element->tag === 'a') {
                $item['uri'] = $this->getURI() . $element->href;
            }

            // Only add items that have at least a title and URI
            if (isset($item['title']) && isset($item['uri'])) {
                $item['timestamp'] = time(); // Use current time since we don't have publish dates
                $this->items[] = $item;
            }

            // Limit to 20 items to avoid overload
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
