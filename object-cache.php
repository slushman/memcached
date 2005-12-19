<?php

/*
Name: Memcached
Description: Memcached backend for the WP Object Cache.
Version: 0.1
URI: http://dev.wp-plugins.org/browser/memcached/
Author: Ryan Boren


Install this file to wp-content/object-cache.php along with
memcached-client.php.
*/

include( ABSPATH . "wp-content/memcached-client.php" );

function wp_cache_add($key, $data, $flag = '', $expire = 0) {
	global $wp_object_cache;

	return $wp_object_cache->add($key, $data, $flag, $expire);
}

function wp_cache_close() {
	return true;
}

function wp_cache_delete($id, $flag = '') {
	global $wp_object_cache;

	return $wp_object_cache->delete($id, $flag);
}

function wp_cache_flush() {
	global $wp_object_cache;

	return $wp_object_cache->flush();
}

function wp_cache_get($id, $flag = '') {
	global $wp_object_cache;

	return $wp_object_cache->get($id, $flag);
}

function wp_cache_init() {
	global $wp_object_cache;

	$wp_object_cache = new WP_Object_Cache();
}

function wp_cache_replace($key, $data, $flag = '', $expire = 0) {
	global $wp_object_cache;

	return $wp_object_cache->replace($key, $data, $flag, $expire);
}

function wp_cache_set($key, $data, $flag = '', $expire = 0) {
	global $wp_object_cache;

	return $wp_object_cache->set($key, $data, $flag, $expire);
}

class WP_Object_Cache {
	var $global_groups = array ('users', 'usermeta');

	function add($id, $data, $group = 'default', $expire = 0) {
		$key = $this->key($id, $group);
		return $this->mc->add($key, $data, $expire);
	}

	function delete($id, $group = 'default') {
		$key = $this->key($id, $group);
		return $this->mc->delete($key);
	}

	function flush() {
		return true;
	}

	function get($id, $group = 'default') {
		$key = $this->key($id, $group);
		$value = $this->mc->get($key);
		/* echo "Cache key: $key<br/>";
		echo 'Cache value:<br/>';
		var_dump($value);
		echo '<br/>'; */
		if ( NULL === $value )
			$value = false;
		return $value;
	}

	function key($key, $group) {	
		global $blog_id;

		if ( empty($group) )
			$group = 'default';

		if (false !== array_search($group, $this->global_groups))
			$prefix = '';
		else
			$prefix = $blog_id . ':';

		return "$prefix$group:$key";
	}

	function replace($id, $data, $group = 'default', $expire = 0) {
		$key = $this->key($id, $group);
		return $this->mc->replace($key, $data, $expire);
	}

	function set($id, $data, $group = 'default', $expire = 0) {
		$key = $this->key($id, $group);
		return $this->mc->set($key, $data, $expire);
	}

	function stats() {
		echo "<p>";
		echo "<strong>Cache Gets:</strong> {$this->mc->stats['get']}<br/>";
		echo "<strong>Cache Adds:</strong> {$this->mc->stats['add']}<br/>";
		echo "<strong>Cache Replaces:</strong> {$this->mc->stats['replace']}<br/>";
		echo "</p>";
	}

	function WP_Object_Cache() {
		global $memcached_servers;
		if ( isset($memcached_servers) )
			$servers = $memcached_servers;
		else
			$servers = array('127.0.0.1:11211');

  		$this->mc = new memcached(array(
               'servers' => $servers,
               'debug'   => false,
               'compress_threshold' => 10240,
               'persistant' => true));
	}
}
?>
