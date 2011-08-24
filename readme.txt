=== Memcached Object Cache ===
Contributors: ryan, sivel
Tags: cache, memcached
Requires at least: 3.0
Tested up to: 3.2.1
Stable tag: 2.0.1

Use memcached and the PECL memcache extension to provide a backing store for the WordPress object cache.

== Description ==
Memcached Object Cache provides a persistent backend for the WordPress object cache. A memcached server and the PECL memcache extension are required.

== Installation ==
1. Install [memcached](http://danga.com/memcached) on at least one server. Note the connection info. The default is `127.0.0.1:11211`.

1. Install the [PECL memcache extension](http://pecl.php.net/package/memcache) 

1. Copy object-cache.php to wp-content

== Changelog ==

= 2.0.2 =
* Break references by cloning objects
* Keep local cache in sync with memcached when using incr and decr
* Handle limited environments where is_multisite() is not defined
* Fix setting and getting 0
* PHP 5.2.4 is now required
