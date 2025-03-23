<?php
/**
 * Amazon SQS 队列
 */

namespace Illuminate\Queue;

use Aws\Sqs\SqsClient;
use Illuminate\Contracts\Queue\Queue as QueueContract;
use Illuminate\Queue\Jobs\SqsJob;

class SqsQueue extends Queue implements QueueContract
{
    /**
     * The Amazon SQS instance.
	 * Amazon SQS实例
     *
     * @var \Aws\Sqs\SqsClient
     */
    protected $sqs;

    /**
     * The name of the default queue.
	 * 默认队列名
     *
     * @var string
     */
    protected $default;

    /**
     * The queue URL prefix.
	 * 队列URL前缀
     *
     * @var string
     */
    protected $prefix;

    /**
     * Create a new Amazon SQS queue instance.
	 * 创建新的Amazon SQS队列实例
     *
     * @param  \Aws\Sqs\SqsClient  $sqs
     * @param  string  $default
     * @param  string  $prefix
     * @return void
     */
    public function __construct(SqsClient $sqs, $default, $prefix = '')
    {
        $this->sqs = $sqs;
        $this->prefix = $prefix;
        $this->default = $default;
    }

    /**
     * Get the size of the queue.
	 * 得到队列大小
     *
     * @param  string|null  $queue
     * @return int
     */
    public function size($queue = null)
    {
        $response = $this->sqs->getQueueAttributes([
            'QueueUrl' => $this->getQueue($queue),
            'AttributeNames' => ['ApproximateNumberOfMessages'],
        ]);

        $attributes = $response->get('Attributes');

        return (int) $attributes['ApproximateNumberOfMessages'];
    }

    /**
     * Push a new job onto the queue.
     *
     * @param  string  $job
     * @param  mixed  $data
     * @param  string|null  $queue
     * @return mixed
     */
    public function push($job, $data = '', $queue = null)
    {
        return $this->pushRaw($this->createPayload($job, $queue ?: $this->default, $data), $queue);
    }

    /**
     * Push a raw payload onto the queue.
	 * 推入原始有效负载队列
     *
     * @param  string  $payload
     * @param  string|null  $queue
     * @param  array  $options
     * @return mixed
     */
    public function pushRaw($payload, $queue = null, array $options = [])
    {
        return $this->sqs->sendMessage([
            'QueueUrl' => $this->getQueue($queue), 'MessageBody' => $payload,
        ])->get('MessageId');
    }

    /**
     * Push a new job onto the queue after a delay.
	 * 推入新作业至队列在延迟后
     *
     * @param  \DateTimeInterface|\DateInterval|int  $delay
     * @param  string  $job
     * @param  mixed  $data
     * @param  string|null  $queue
     * @return mixed
     */
    public function later($delay, $job, $data = '', $queue = null)
    {
        return $this->sqs->sendMessage([
            'QueueUrl' => $this->getQueue($queue),
            'MessageBody' => $this->createPayload($job, $queue ?: $this->default, $data),
            'DelaySeconds' => $this->secondsUntil($delay),
        ])->get('MessageId');
    }

    /**
     * Pop the next job off of the queue.
	 * 弹出下一个作业从队列中
     *
     * @param  string|null  $queue
     * @return \Illuminate\Contracts\Queue\Job|null
     */
    public function pop($queue = null)
    {
        $response = $this->sqs->receiveMessage([
            'QueueUrl' => $queue = $this->getQueue($queue),
            'AttributeNames' => ['ApproximateReceiveCount'],
        ]);

        if (! is_null($response['Messages']) && count($response['Messages']) > 0) {
            return new SqsJob(
                $this->container, $this->sqs, $response['Messages'][0],
                $this->connectionName, $queue
            );
        }
    }

    /**
     * Get the queue or return the default.
	 * 得到队列并返回默认
     *
     * @param  string|null  $queue
     * @return string
     */
    public function getQueue($queue)
    {
        $queue = $queue ?: $this->default;

        return filter_var($queue, FILTER_VALIDATE_URL) === false
                        ? rtrim($this->prefix, '/').'/'.$queue : $queue;
    }

    /**
     * Get the underlying SQS instance.
	 * 得到底层SQS实例
     *
     * @return \Aws\Sqs\SqsClient
     */
    public function getSqs()
    {
        return $this->sqs;
    }
}
