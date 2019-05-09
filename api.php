<?php

/**
 * The WP Redis Job Queue public API
 */

namespace WpRedisJobQueue;

function push(array $job) {
  $client = apply_filters('wp_redis_job_queue/client', null);

  do_action('wp_redis_job_queue/before_push', $job);

  try {
    $client->lpush('queue', serialize($job));
  } catch (\Exception $e) {
    do_action('wp_redis_job_queue/push_exception', $e, $job);
    return;
  }

  do_action('wp_redis_job_queue/after_push', $job);
}
