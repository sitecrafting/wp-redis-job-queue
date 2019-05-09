<?php

namespace WpRedisJobQueue\Cli;

use WpRedisJobQueue;

use Predis\Client;
use WP_CLI;
use WP_CLI\Utils;
use WP_Post;

class QueueCommand {
  /**
   * The Predis client for talking to our Redis instance(s)
   *
   * @var Predis\Client
   */
  protected $client;

  /**
   * Constructor.
   *
   * @param Predis\Client $client any Predis client instance
   */
  public function __construct(Client $client) {
    $this->client = $client;
  }

  /**
   * Process a single job off the queue, and complete it
   *
   * ## OPTIONS
   *
   * [--delay=<delay>]
   * : Millisecond delay between each check
   * ---
   * default: 0
   * ---
   *
   * ## EXAMPLES
   *
   *     wp queue process
   *
   *     wp queue process --delay=1000
   *
   * @when after_wp_load
   */
  public function process( array $args, array $options ) {
    // get delay in microseconds
    $delay = ((int) $options['delay'] ?? 0) * 1000;

    while (true) {
      $job = unserialize($this->client->rpop('queue'));
      if (empty($job)) {
        WP_CLI::debug('no jobs on the queue');
        if ($delay) usleep($delay);
        continue;
      }

      $this->do_job($job);

      if ($delay) usleep($delay);
    }
  }

  /**
   * Peek at the head of the job queue.
   *
   * [--count=<count>]
   * : The number of items to peek at
   * ---
   * default: 1
   * ---
   *
   * ## EXAMPLES
   *
   *     wp queue peek
   *
   *     wp queue peek --count=3
   *
   * @when after_wp_load
   */
  public function peek( array $args, array $options ) {
    // Where to end our LRANGE command.
    // Make sure it's no less than zero.
    $end = max(0, $options['count'] - 1);

    $items = $this->client->lrange('queue', 0, $end) ?? null;

    foreach ($items as $item) {
      WP_CLI::success(var_export(unserialize($item), true));
    }
  }


  /**
   * Push a test job onto the queue that simply prints a success message of
   * "Hello, <name>!"
   *
   * ## OPTIONS
   *
   * [--name=<name>]
   * : The name of the person to say hello to.
   * ---
   * default: World
   * ---
   *
   * ## EXAMPLES
   *
   *     wp queue test-push
   *
   *     wp queue test-push --name=Jack
   *
   *     wp queue say-hello [--name=Bob]
   *
   * @subcommand test-push
   * @alias say-hello
   * @when after_wp_load
   */
  public function test_push(array $_, array $options) {
    $job = [
      'callback'     => [WP_CLI::class, 'success'],
      'args'         => ["Hello, {$options['name']}!"],
      'scheduled_at' => gmdate('Y-m-d H:i:s'),
    ];

    try {
      WpRedisJobQueue\push($job);
    } catch (\Exception $e) {
      WP_CLI::error($e->getMessage());
    }
  }


  protected function do_job(array $job) {
    if (!is_callable($job['callback'] ?? null)) {
      return;
    }

    WP_CLI::debug(var_export($job, true));

    do_action('wp_redis_job_queue/before_job', $job);

    $args = $job['args'] ?? [];

    try {
      $result = call_user_func_array( $job['callback'], $args );
    } catch (\Exception $e) {
      do_action('wp_redis_job_queue/job_exception', $e, $job);
      return;
    }

    do_action('wp_redis_job_queue/after_job', $job, $result);
  }
}
