pgHomer
=====
This is a simple internet search engine.
* Front End - Silex application with interface based on Bootstrap.
* Daemon - internet spider built on React PHP.

The main differences between original Homer
* PostgreSQL only (should work fine on v9.0+), but utilizes async queries (PHP 5.6+) and advanced full-text search
* Additional stats
* Complete results viewer
* Slightly faster and more clever crawler (be careful to avoid DoS, see HOMER_TIMER_FAST param) 



Install
-------
Download or clone this repository. Open dir in terminal. And run Composer.
```
php composer.phar install
```
Copy config.php.dist to config.php. Open and edit configuration.

SQLite database by default stores in open/homer.db.
Be sure what php have write access to it and run next command:
```
php install.php
```
When delete this file.

Demon
-----
To start demon run next command:
```
php demon.php
```

Statistic
---------
Homer have built-in usage statistic and logging via websockets.
![Stats](http://i.imgur.com/eDSoa9A.png)

Configuration
-------------
* HOMER_RESOLVER_ADDRESS - IP address for dns resolver (default set to Yandex: 77.88.8.8);
* HOMER_TIMER - tick timer in seconds to poll index tasks.
* HOMER_TIMER_FAST - tick timer to poll url queue. The lower - the faster spider but may cause DoS the site
* HOMER_DEEP - How deep demon will be following by hyperlinks.
* HOMER_KEEP_HOST - Keep deep search only if host equals with parent page.
* HOMER_LIMITER_TIME - How much time do not reindex already indexed pages.
* HOMER_STAT - Turn on or off statistic.
* HOMER_HTTP_HOST - Host of http server there statistic can be loaded (default: localhost)
* HOMER_HTTP_PORT - Post of http server 1333.

License
-------
Licensed under the MIT license: http://www.opensource.org/licenses/mit-license.php