<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config.php';

$loop = React\EventLoop\Factory::create();

$dnsResolverFactory = new React\Dns\Resolver\Factory();
$dnsResolver = $dnsResolverFactory->createCached(HOMER_RESOLVER_ADDRESS, $loop);

$factory = new React\HttpClient\Factory();
$client = $factory->create($loop, $dnsResolver);

$db = new PDO(HOMER_DNS, 'homer', '123', [
    PDO::ATTR_PERSISTENT => true,
    1002 => "SET NAMES utf8",
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
]);
$queue = new Homer\Queue($db);
$search = new Homer\Search($db);
$limiter = new Homer\Locker();

$loop->addPeriodicTimer(HOMER_TIMER, function ($timer) use ($client, $queue, $search, $limiter) {
    while ($row = $queue->pop()) {
        if ($limiter->isAvailable($row['url'])) {
            $loader = new Homer\Loader($client, $queue, $search);
            if ($loader->load($row['url'], $row['deep'])) {
                $limiter->lock($row['url']);
                echo "Loading $row[url]\n";
                break;
            }
        }
    }

    $limiter->release(HOMER_LIMITER_TIME);
});

if (HOMER_STAT) {
    $socket = new React\Socket\Server($loop);
    $http = new React\Http\Server($socket, $loop);
    $stat = new Homer\Statistic();

    $loop->addPeriodicTimer(HOMER_TIMER, function ($timer) use ($stat) {
        $stat->add('memory', memory_get_usage(true) / (1024 * 1024));
    });

    $http->on('request', array($stat, 'app'));
    $socket->listen(HOMER_HTTP_PORT);
}

$loop->run();