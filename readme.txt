=== Memcached Object Cache ===
Contributors: ryan
Tags: cache, memcached
Stable tag: 2.0

Use memcached and the PECL memcached extension to provide a backing store for the WordPres object cache.

== Description ==
Memcached Object Cache provides a persistent backend for the WordPress object cache. A memcached server and the PECL memcached extension are required.

== Installation ==
1. Install [memcached](http://danga.com/memcached) on at least one server. Note the connection info. The default is `127.0.0.1:11211`.

1. Install the [PECL memcached extension](http://pecl.php.net/package/memcache) 

1. Copy object-cache.php to wp-content



