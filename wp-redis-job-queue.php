<?php

/**
 * Plugin Name: WP Redis Job Queue
 * Description: A simple Redis-based job queue for WordPress
 * https://github.com/nrk/predis/wiki
 */

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/api.php';

use Predis\Client;
use WpRedisJobQueue\Cli\QueueCommand;

add_filter('wp_redis_job_queue/create_params', function($params) {
  // Detect Redist hostname and port automatically
  if (getenv('CACHE_HOST')) {
    $host = getenv('CACHE_HOST');
  } elseif (defined('WP_REDIS_JOB_QUEUE_HOST')) {
    $host = WP_REDIS_JOB_QUEUE_HOST;
  } else {
    trigger_error('No Redis host detected!'
      . ' You must define either the CACHE_HOST environment variable,'
      . ' a WP_REDIS_JOB_QUEUE_HOST constant, or hook into the wp_redis_job_queue/create_params'
      . ' filter to define a host for Predis to connect to.'
    );
  }

  if (getenv('CACHE_PORT')) {
    $port = getenv('CACHE_PORT');
  } elseif (defined('WP_REDIS_JOB_QUEUE_PORT')) {
    $port = WP_REDIS_JOB_QUEUE_PORT;
  } else {
    trigger_error('No Redis port detected!'
      . ' You must define either the CACHE_PORT environment variable,'
      . ' a WP_REDIS_JOB_QUEUE_PORT constant, or hook into the wp_redis_job_queue/create_params'
      . ' filter to define a host for Predis to connect to.'
    );
  }

  if ( ! (isset($host) && isset($port)) ) {
    return [];
  }

  $params = [
    'host' => $host,
    'port' => $port,
  ];

  if (getenv('CACHE_PASSWORD')) {
    $params['password'] = getenv('CACHE_PASSWORD');
  } elseif (defined('WP_REDIS_JOB_QUEUE_PASSWORD')) {
    $params['password'] = WP_REDIS_JOB_QUEUE_PASSWORD;
  }

  return $params;
});

// Create a Predis instance
add_filter('wp_redis_job_queue/client', function($client) {
  return $client ?: new Client(apply_filters('wp_redis_job_queue/create_params', null));
});

if (defined('WP_CLI') && WP_CLI) {
  WP_CLI::add_command(
    'queue',
    // create a Predis client for our command to use
    new QueueCommand(apply_filters('wp_redis_job_queue/client', null))
  );
}
