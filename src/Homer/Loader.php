<?php
/* (c) Anton Medvedev <anton@elfet.ru>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Homer;

use React\Http\Request;
use React\HttpClient\Client;
use React\HttpClient\Response;
use React\Stream\BufferedSink;
use Symfony\Component\DomCrawler\Crawler;

class Loader
{
    /**
     * @var string
     */
    private $url;

    /**
     * @var int
     */
    private $deep;

    /**
     * @var Client
     */
    private $client;

    /**
     * @var Queue
     */
    private $queue;

    /**
     * @var Search
     */
    private $indexer;

    /**
     * @var Request
     */
    private $request;

    /**
     * @var Response
     */
    private $response;

    /**
     * @var bool
     */
    private $done = false;

    public function __construct(Client $client, Queue $queue, Indexer $indexer)
    {
        $this->client = $client;
        $this->queue = $queue;
        $this->indexer = $indexer;
    }

    public function load($url, $deep)
    {
        if (null !== $this->url) {
            throw new \RuntimeException("This Loader object already loading an url.");
        }

        $url = filter_var($url, FILTER_VALIDATE_URL);

        if (false === $url) {
            return false;
        }

        $staticRes = 'jpe?g|gif|png|ico|zip|tgz|gz|rar|bz2|dic|xls|exe|pdf|ppt|txt|tar|mid|midi|wav|bmp|rtf|js|swf|flv|mp3|ttf|woff';
        if (preg_match('~\.(?:'.$staticRes.')(?:\??.*)$~uis', $url)) {
            return false;
        }

        if (preg_match('~(?:javascript|mailto):~uis', $url)) {
            return false;
        }


        $this->url = $url;
        $this->deep = $deep;

        ConnectionCounter::incConnection();
        $this->request = $this->client->request('GET', $url,
            ['User-Agent: Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.9.2.12) Gecko/20101026 Firefox/3.6.12']
        );
        $this->request->on('response', [$this, 'onResponse']);
        $this->request->end();

        return true;
    }

    public function onResponse(Response $response)
    {
        $this->response = $response;
        BufferedSink::createPromise($response)->then([$this, 'onLoad']);
    }

    public function onLoad($body)
    {
        ConnectionCounter::decConnection();
        $this->done = true;
        $headers = $this->response->getHeaders();

        if (isset($headers['Location'])) {
            if ($this->deep > 0) {
                $this->pushQueue($headers['Location'], $this->deep - 1);
            }

            return;
        }

        $html = new Crawler();
        $html->addHtmlContent($body);

        $this->indexer->index($this->url, $html);

        if ($this->deep > 0) {
            $base = parse_url($this->url);

            $links = $html->filter('a');
            $baseHref = $html->filter('base');

            if (count($baseHref)) {
                $baseHref = $baseHref->attr('href');
            } else {
                $baseHref = null;
            }

            $links->each(function (Crawler $link) use ($base, $baseHref) {
                $href = explode('#', $link->attr('href'))[0];
                $href = trim($href);

                if (empty($href)) {
                    return;
                }

                if ('/' === $href) {
                    return;
                }

                if (preg_match('/^https?:\/\//i', $href)) {
                    $url = $href;
                } elseif (0 === mb_strpos($href, '/')) {
                    $url = $base['scheme']
                        . '://'
                        . $base['host']
                        . $href;
                } else {
                    $path = '/';
                    if (0 === mb_strpos($href, '.')) {
                        $href = mb_substr($href, 1);
                    }

                    if ($baseHref) {
                        $path = $baseHref;
                    } elseif (isset($base['path'])) {
                        $path = $base['path'];
                        $segments = explode('/', rtrim($path, '/'));
                        $path = end($segments);
                    }

                    $url = $base['scheme']
                        . '://'
                        . $base['host']
                        . $path
                        . $href;
                }

                $segments = explode('.', parse_url($url, PHP_URL_HOST));
                $domainPart1 = array_pop($segments);
                $domainPart2 = array_pop($segments);
                $urlhost = $domainPart2.'.'.$domainPart1;

                if (HOMER_KEEP_HOST && mb_strpos($base['host'], $urlhost) === false) {
                    return;
                }

                $this->pushQueue($url, $this->deep - 1);
            });
        }
    }

    private function pushQueue($url, $deep)
    {
        $this->queue->pushMemory($url, $deep);
    }

    public function getDone()
    {
        return $this->done;
    }
}