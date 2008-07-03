<?php

/*
Name: Memcached
Description: Memcached backend for the WP Object Cache.
Version: 1.0
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

function wp_cache_incr($key, $n = 1, $flag = '') {
	global $wp_object_cache;

	return $wp_object_cache->incr($key, $n, $flag);
}

function wp_cache_decr($key, $n = 1, $flag = '') {
	global $wp_object_cache;

	return $wp_object_cache->decr($key, $n, $flag);
}

function wp_cache_close() {
	global $wp_object_cache;

	return $wp_object_cache->close();
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

	if ( defined('WP_INSTALLING') == false )
		return $wp_object_cache->set($key, $data, $flag, $expire);
	else
		return true;
}

function wp_cache_add_global_groups( $groups ) {
	global $wp_object_cache;

	$wp_object_cache->add_global_groups($groups);
}

function wp_cache_add_non_persistent_groups( $groups ) {
	global $wp_object_cache;

	$wp_object_cache->add_non_persistent_groups($groups);
}

class WP_Object_Cache {
	var $global_groups = array ('users', 'userlogins', 'usermeta', 'site-options', 'site-lookup', 'blog-lookup', 'blog-details', 'rss');
	var $autoload_groups = array ('options');
	var $cache = array ();
	var $rmc = array();
	var $cache_enabled = true;
	var $default_expiration = 0;
	var $no_mc_groups = array( 'comment', 'counts' );
	var $blog_flushes = array();
	var $global_flushes = null;

	function add($id, $data, $group = 'default', $expire = 0) {
		$key = $this->key($id, $group);

		if ( in_array($group, $this->no_mc_groups) ) {
			$this->cache[$key] = $data;
			return true;
		}

		$expire = ($expire == 0) ? $this->default_expiration : $expire;
		$result = $this->mc->add($key, $data, $expire);
		if ( false !== $result )
			$this->cache[$key] = $data;
		return $result;
	}

	function add_global_groups($groups) {
		if ( ! is_array($groups) )
			$groups = (array) $groups;

		$this->global_groups = array_merge($this->global_groups, $groups);
		$this->global_groups = array_unique($this->global_groups);
	}

	function add_non_persistent_groups($groups) {
		if ( ! is_array($groups) )
			$groups = (array) $groups;

		$this->no_mc_groups = array_merge($this->no_mc_groups, $groups);
		$this->no_mc_groups = array_unique($this->no_mc_groups);
	}

	function close() {
		$this->mc->disconnect_all();
	}

	function decr($id, $n, $group) {
		$key = $this->key($id, $group);

		$value = $this->mc->decr($key, $n);

		$this->cache[$key] = $value;

		return $value;
	}

	function delete($id, $group = 'default') {
		$key = $this->key($id, $group);

		if ( in_array($group, $this->no_mc_groups) ) {
			unset($this->cache[$key]);
			return true;
		}

		$result = $this->mc->delete($key);

		// Update remote servers.
		if ( count($this->rmc) > 0 ) {
 			foreach ( $this->rmc as $i => $mc )
 				$this->rmc[$i]->set($key, 'checkthedatabaseplease', 3);
		}
		if ( false !== $result )
			unset($this->cache[$key]);

		return $result;
	}

	function flush( $group = '' ) {
		$this->set_flushes( $group, $this->get_flushes($group) + 1 );

		return true;
	}

	function get($id, $group = 'default') {
		$key = $this->key($id, $group);

		if ( isset($this->cache[$key]) )
			$value = $this->cache[$key];
		else if ( in_array($group, $this->no_mc_groups) )
			$value = false;
		else
			$value = $this->mc->get($key);

		if ( NULL === $value )
			$value = false;

		$this->cache[$key] = $value;

		if ( 'checkthedatabaseplease' == $value )
			$value = false;

		return $value;
	}

	function get_flushes( $group = '' ) {
		global $blog_id;

		if ( in_array($group, $this->global_groups) ) {
			if ( is_array( $this->global_flushes ) && isset( $this->global_flushes[$group] ) )
				return $this->global_flushes[$group];

			$this->global_flushes = $this->mc->get('global_flushes');

			if ( is_array( $this->global_flushes ) && isset( $this->global_flushes[$group] ) )
				return $this->global_flushes[$group];

			$this->set_flushes($group, 1);

			return $this->global_flushes[$group];
		} else {
			if ( is_array( $this->blog_flushes[$blog_id] ) && isset( $this->blog_flushes[$blog_id][$group] ) )
				return $this->blog_flushes[$blog_id][$group];

			$this->blog_flushes[$blog_id] = $this->mc->get("blog_flushes[$blog_id]");

			if ( is_array( $this->blog_flushes[$blog_id] ) && isset( $this->blog_flushes[$blog_id][$group] ) )
				return $this->blog_flushes[$blog_id][$group];

			$this->set_flushes($group, 1);

			return $this->blog_flushes[$blog_id][$group];
		}
	}

	function incr($id, $n, $group) {
		$key = $this->key($id, $group);

		$value = $this->mc->incr($key, $n);

		$this->cache[$key] = $value;

		return $value;
	}

	function key($key, $group) {
		global $blog_id;

		if ( empty($group) )
			$group = 'default';

		if ( in_array($group, $this->global_groups) )
			$prefix = '';
		else
			$prefix = $blog_id . ':';

		$flushes = $this->get_flushes();
		$group_flushes = $this->get_flushes($group);

		return preg_replace('/\s+/', '', "$prefix$flushes:$group:$group_flushes:$key");
	}

	function replace($id, $data, $group = 'default', $expire = 0) {
		$key = $this->key($id, $group);
		$expire = ($expire == 0) ? $this->default_expiration : $expire;
		$result = $this->mc->replace($key, $data, $expire);
		if ( false !== $result )
			$this->cache[$key] = $data;
		return $result;
	}

	function set($id, $data, $group = 'default', $expire = 0) {
		$key = $this->key($id, $group);
		if ( isset($this->cache[$key]) && ('checkthedatabaseplease' == $this->cache[$key]) )
			return false;
		$this->cache[$key] = $data;

		if ( in_array($group, $this->no_mc_groups) )
			return true;

		$expire = ($expire == 0) ? $this->default_expiration : $expire;
		$result = $this->mc->set($key, $data, $expire);
		return $result;
	}

	function set_flushes( $group, $flushes ) {
		global $blog_id;

		if ( in_array($group, $this->global_groups) ) {
			if ( !is_array( $this->global_flushes ) )
				$this->global_flushes = array( '' => 1 );

			$this->global_flushes[$group] = $flushes;

			$this->mc->set('global_flushes', $this->global_flushes, 0);
		} else {
			if ( !is_array( $this->blog_flushes[$blog_id] ) )
				$this->blog_flushes[$blog_id] = array( '' => 1 );

			$this->blog_flushes[$blog_id][$group] = $flushes;

			$this->mc->set("blog_flushes[$blog_id]", $this->blog_flushes[$blog_id], 0);
		}
		
		return $flushes;
	}

	function stats() {
		echo "<p>\n";
		echo "<strong>Cache Gets:</strong> {$this->mc->stats['get']}<br/>\n";
		echo "<strong>Cache Adds:</strong> {$this->mc->stats['add']}<br/>\n";
		echo "<strong>Cache Replaces:</strong> {$this->mc->stats['replace']}<br/>\n";
		echo "</p>\n";

		if ( ! empty($this->cache) ) {
			echo "<pre>\n";
			print_r($this->cache);
			echo "</pre>\n";
		}
	}

	function WP_Object_Cache() {
		global $memcached_servers;
		global $remote_memcached_clusters;

		if ( isset($memcached_servers) )
			$servers = $memcached_servers;
		else
			$servers = array('127.0.0.1:11211');

		$this->mc = new memcached(array(
				'servers' => $servers,
				'debug'   => false,
				'compress_threshold' => 10240,
				'persistant' => true));

		if ( isset($remote_memcached_clusters) )
			foreach ( $remote_memcached_clusters as $servers )
		  		$this->rmc[] = new memcached(array(
						'servers' => $servers,
						'debug'   => false,
						'compress_threshold' => 10240,
						'persistant' => true));

	}
}
?>
