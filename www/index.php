<?php
use Kilte\Silex\Pagination\PaginationServiceProvider;
use Silex\Provider\TwigServiceProvider;

require_once dirname(__DIR__) . '/config.php';

$app = new Silex\Application();
$app->register(new Silex\Provider\UrlGeneratorServiceProvider())
    ->register(new PaginationServiceProvider)
    ->register(new TwigServiceProvider, array('twig.path' => __DIR__ . '/view/'));

$app['debug'] = true;

$app['db'] = $app->share(function () {
    return new PDO(DB_SCHEME.'dbname='.HOMER_DB.';host=localhost', HOMER_DBUSER, HOMER_DBPASS, [
        1002 => "SET NAMES utf8",
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
});

$app['queue'] = $app->share(function () use ($app) {
    return new Homer\Queue($app['db']);
});

$app->get('/', function () use ($app) {
    $search = $app['request']->get('search', false);
    $page = $app['request']->get('page', 1);
    $searcher = new Homer\Search($app['db']);
    $count = $searcher->getCount($search);
    /** @var \Kilte\Pagination\Pagination $pagination */
    $pagination = $app['pagination']($count, $page);

    $result = $searcher->search($search, $pagination->offset(), $pagination->limit());

    foreach ($result as &$row) {
        $descr = '';
        foreach (explode(' ', $search) as $word) {
            $descr .= $searcher->highlight($row['body'], $word);
        }
        $row['body'] = $descr;
    }

    $pages = $pagination->build();
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