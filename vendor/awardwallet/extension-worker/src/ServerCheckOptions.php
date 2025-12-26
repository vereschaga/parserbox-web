<?php

namespace AwardWallet\ExtensionWorker;

class ServerCheckOptions
{

    public \SeleniumFinderRequest $request;
    public \SeleniumOptions $options;

    public function __construct(\SeleniumFinderRequest $request, \SeleniumOptions $options)
    {
        $this->request = $request;
        $this->options = $options;
    }

}