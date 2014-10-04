<?php

namespace Homer;

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

        $body = $html->filter('body');
        $body = count($body) ? $body->text() : '';
        $body = strip_tags($body);
        if (!$body) {
            return;
        }

        //ignore notices http://php.net/manual/ru/function.pg-send-query-params.php
        @pg_send_query_params($this->db, 'INSERT INTO indexes (url, title, body) VALUES ($1, $2, $3)', [$url, $title, $body]);
    }
} 