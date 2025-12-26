<?php

namespace AwardWallet\Common\Selenium;

class RecordedResponse
{
    /**
     * @var string
     */
    public $status;
    /**
     * @var string
     */
    public $time;
    /**
     * @var string
     */
    public $headers;
    /**
     * @var string|array
     */
    public $body;

    public function __construct(array $response)
    {
        $this->status = $response['status'];
        $this->time = $response['time'];
        $this->headers = $response['headers'];
        $this->body = $response['body'] ?? null;
    }

    /**
     * @return string
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * @return string
     */
    public function getTime()
    {
        return $this->time;
    }

    /**
     * @return string
     */
    public function getHeaders()
    {
        return $this->headers;
    }

    /**
     * @return array|string
     */
    public function getBody()
    {
        return $this->body;
    }

}