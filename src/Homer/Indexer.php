<?php

namespace Homer;

use Symfony\Component\CssSelector\CssSelector;
use Symfony\Component\DomCrawler\Crawler;

class Indexer
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    public function index($url, Crawler $html)
    {
        $title = $html->filter('title');
        if (!count($title)) {
            return;
        }

        $title = $title->text();

        $this->filterHTML($html, ['script', 'style']);

        $body = $html->filter('body');
        $body = count($body) ? $body->text() : '';

        if (!$body) {
            return;
        }

        $body = strip_tags($body);

        @pg_send_query_params($this->db, 'INSERT INTO indexes (url, title, body) VALUES ($1, $2, $3)', [$url, $title, $body]);

        while (true) {
            switch (pg_flush($this->db)) {
                case true: // All data flushed ... woohoo!
                    break 2;
                case 0: // Data still remains to flush. Loop again.
                    break;
                case false:
                    echo "pg_flush() error\n";
                    break 2;
            }

        }
    }

    /**
     * @param Crawler $html
     * @param $selectorsToRemove
     */
    private function filterHTML(Crawler $html, $selectorsToRemove)
    {
        $document = new \DOMDocument('1.0', 'UTF-8');
        $root = $document->appendChild($document->createElement('_root'));
        $html->rewind();
        $root->appendChild($document->importNode($html->current(), true));
        $domxpath = new \DOMXPath($document);

        foreach ($selectorsToRemove as $selector) {
            $crawlerInverse = $domxpath->query(CssSelector::toXPath($selector));
            foreach ($crawlerInverse as $elementToRemove) {
                $parent = $elementToRemove->parentNode;
                $parent->removeChild($elementToRemove);
            }
        }
        $html->clear();
        $html->add($document);
    }
} 