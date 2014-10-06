<?php

use Homer\ConnectionCounter;
use Homer\wsApp;
use Ratchet\Http\HttpServer;
use Ratchet\Server\IoServer;
use Ratchet\WebSocket\WsServer;
use React\Socket\Server as SocketServer;

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

$wsApp = new WsApp;

function addlog(WsApp $app, array $msg)
{
    foreach($app->getConnections() as $conn) {
        $conn->send(json_encode($msg));
    }
}

if (HOMER_STAT) {
    $stat = new Homer\Statistic();

    $socket = new SocketServer($loop);
    $socket->listen(HOMER_HTTP_PORT);

    $server = new IoServer(
        new HttpServer(new WsServer($wsApp)),
        $socket
    );

    $loop->addPeriodicTimer(HOMER_TIMER, function ($timer) use ($stat, $wsApp) {
        $stat->add('memory', memory_get_usage(true) / (1024 * 1024));
        $stat->add('connections', ConnectionCounter::getCount());
        $stat->add('queue', ConnectionCounter::getQueueCount());

        addlog($wsApp, ['stats' => $stat->getStats()]);
    });
}

$loop->addPeriodicTimer(HOMER_TIMER, function ($timer) use ($queue, $wsApp) {
    while ($row = $queue->pop()) {
        $queue->pushMemory($row['url'], HOMER_DEEP);
        addlog($wsApp, ['console' => "Received task at $row[url]\n"]);
    }
});

$loop->addPeriodicTimer(HOMER_TIMER_FAST, function ($timer) use ($client, $queue, $indexer, $locker, $dbAsync, $wsApp) {
    while ($row = $queue->popMemory()) {
        if ($locker->isAvailable($row['url'])) {
            $loader = new Homer\Loader($client, $queue, $indexer);
            if ($loader->load($row['url'], $row['deep'])) {
                $locker->lock($row['url']);
                addlog($wsApp, ['console' => "Loading $row[url]"]);
                break;
            }
        }
    }

    $locker->release(HOMER_LIMITER_TIME);
});

$loop->run();