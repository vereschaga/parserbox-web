<?php

namespace AwardWallet\Common\Selenium;

class RecordedXHR
{

    public RecordedRequest $request;
    public RecordedResponse $response;

    public function __construct(array $event)
    {
        $this->request = new RecordedRequest($event['request']);
        $this->response = new RecordedResponse($event['response']);
    }

    public function __toString()
    {
        return json_encode((array) $this, JSON_PRETTY_PRINT);
    }

}