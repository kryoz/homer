<?php
use Kilte\Silex\Pagination\PaginationServiceProvider;
use Silex\Provider\TwigServiceProvider;

require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/config.php';

$app = new Silex\Application();
$app->register(new Silex\Provider\UrlGeneratorServiceProvider())
    ->register(new PaginationServiceProvider)
    ->register(new TwigServiceProvider, array('twig.path' => __DIR__ . '/view/'));

$app['debug'] = true;

$app['db'] = $app->share(function () {
    return new PDO(HOMER_DNS, 'homer', '123', [
        1002 => "SET NAMES utf8",
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
});

$app['queue'] = $app->share(function () use ($app) {
    return new Homer\Queue($app['db']);
});

$app['search'] = $app->share(function () use ($app) {
    return new Homer\Search($app['db']);
});

$app->get('/', function () use ($app) {
    $search = $app['request']->get('search', false);
    $page = $app['request']->get('page', 1);
    /** @var Homer\Search $searcher */
    $searcher = $app['search'];
    $count = $searcher->getCount($search);
    /** @var \Kilte\Pagination\Pagination $pagination */
    $pagination = $app['pagination']($count, $page);
    $pages      = $pagination->build();

    $result = $searcher->search($search, $pagination->offset(), $pagination->limit());

    $wrapLength = 100;
    foreach ($result as &$row) {
        $descr = '';
        foreach (explode(' ', $search) as $word) {
            $body = $row['body'];
            $pos = mb_strpos($body, $word);
            $len = mb_strlen($word);
            $body = '...'.mb_substr($body, $pos - $wrapLength, 2*$wrapLength+$len).'...';
            $descr .= preg_replace('~('.$word.')~uis', '<b>$1</b>', $body);
        }
        $row['body'] = $descr;
    }

    ob_start();
    include 'view/index.phtml';
    return ob_get_clean();

})->bind('search');

$app->post('/add', function () use ($app) {
    $url = filter_var($app['request']->get('url', ''), FILTER_VALIDATE_URL);
    if ($url) {
        $app['queue']->push($url, HOMER_DEEP);
    }
    return $app
        ->redirect(
            $app['url_generator']->generate('search', ['success' => $url !== false]
                )
        );
})->bind('add');

$app->get('/statistic', function () use ($app) {
    ob_start();
    include 'view/statistic.phtml';
    return ob_get_clean();
})->bind('statistic');

$app->run();