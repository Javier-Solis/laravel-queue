<?php

namespace Enqueue\LaravelQueue;

use Illuminate\Container\Container;
use Illuminate\Contracts\Queue\Job as JobContract;
use Illuminate\Queue\Jobs\Job as BaseJob;
use Interop\Queue\PsrConsumer;
use Interop\Queue\PsrContext;
use Interop\Queue\PsrMessage;
use Interop\Queue\DeliveryDelayNotSupportedException;

class Job extends BaseJob implements JobContract
{
    /**
     * @var PsrContext
     */
    private $psrContext;

    /**
     * @var PsrConsumer
     */
    private $psrConsumer;

    /**
     * @var PsrMessage
     */
    private $psrMessage;

    /**
     * @param Container   $container
     * @param PsrContext  $psrContext
     * @param PsrConsumer $psrConsumer
     * @param PsrMessage  $psrMessage
     * @param string      $connectionName
     */
    public function __construct(Container $container, PsrContext $psrContext, PsrConsumer $psrConsumer, PsrMessage $psrMessage, $connectionName)
    {
        $this->container = $container;
        $this->psrContext = $psrContext;
        $this->psrConsumer = $psrConsumer;
        $this->psrMessage = $psrMessage;
        $this->connectionName = $connectionName;
    }

    public function getJobId()
    {
        return $this->psrMessage->getMessageId();
    }

    /**
     * {@inheritdoc}
     */
    public function delete()
    {
        parent::delete();

        $this->psrConsumer->acknowledge($this->psrMessage);
    }

    /**
     * {@inheritdoc}
     */
    public function release($delay = 0)
    {
        parent::release($delay);

        $requeueMessage = clone $this->psrMessage;
        $requeueMessage->setProperty('x-attempts', $this->attempts() + 1);

        $producer = $this->psrContext->createProducer();

        try {
            $producer->setDeliveryDelay($this->secondsUntil($delay) * 1000);
        } catch (DeliveryDelayNotSupportedException $e) {
        }

        $this->psrConsumer->acknowledge($this->psrMessage);
        $producer->send($this->psrConsumer->getQueue(), $requeueMessage);
    }

    /**
     * {@inheritdoc}
     */
    public function getQueue()
    {
        return $this->psrConsumer->getQueue()->getQueueName();
    }

    /**
     * {@inheritdoc}
     */
    public function attempts()
    {
        return $this->psrMessage->getProperty('x-attempts', 1);
    }

    /**
     * {@inheritdoc}
     */
    public function getRawBody()
    {
        return $this->psrMessage->getBody();
    }
}
