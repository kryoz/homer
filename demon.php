<?php

use Homer\ConnectionCounter;

require_once __DIR__ . '/config.php';

$loop = React\EventLoop\Factory::create();

$dnsResolverFactory = new React\Dns\Resolver\Factory();
$dnsResolver = $dnsResolverFactory->createCached(HOMER_RESOLVER_ADDRESS, $loop);

$factory = new React\HttpClient\Factory();
$client = $factory->create($loop, $dnsResolver);

$db = new PDO(DB_SCHEME.'dbname='.HOMER_DB.';host=localhost', HOMER_DBUSER, HOMER_DBPASS, [
    PDO::ATTR_PERSISTENT => true,
    1002 => "SET NAMES utf8",
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
]);
$queue = new Homer\Queue($db);
$locker = new Homer\Locker();

$dbAsync = pg_pconnect('host=localhost port=5432 dbname='.HOMER_DB.' user='.HOMER_DBUSER.' password='.HOMER_DBPASS, PGSQL_CONNECT_ASYNC);
$indexer = new Homer\Indexer($dbAsync);

$loop->addPeriodicTimer(HOMER_TIMER, function ($timer) use ($queue) {
    while ($row = $queue->pop()) {
        $queue->pushMemory($row['url'], HOMER_DEEP);
        echo "Received task at $row[url]\n";
    }
});

$loop->addPeriodicTimer(HOMER_TIMER_FAST, function ($timer) use ($client, $queue, $indexer, $locker, $dbAsync) {
    while ($row = $queue->popMemory()) {
        if ($locker->isAvailable($row['url'])) {
            $loader = new Homer\Loader($client, $queue, $indexer);
            if ($loader->load($row['url'], $row['deep'])) {
                $locker->lock($row['url']);
                echo "Loading $row[url]\n";
                break;
            }
        }
    }

    $locker->release(HOMER_LIMITER_TIME);
});

if (HOMER_STAT) {
    $socket = new React\Socket\Server($loop);
    $http = new React\Http\Server($socket, $loop);
    $stat = new Homer\Statistic();

    $loop->addPeriodicTimer(HOMER_TIMER, function ($timer) use ($stat) {
        $stat->add('memory', memory_get_usage(true) / (1024 * 1024));
        $stat->add('connections', ConnectionCounter::getCount());
        $stat->add('queue', ConnectionCounter::getQueueCount());
    });

    $http->on('request', [$stat, 'app']);
    $socket->listen(HOMER_HTTP_PORT);
}

$loop->run();