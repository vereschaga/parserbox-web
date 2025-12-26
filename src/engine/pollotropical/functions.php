<?php

require_once __DIR__ . '/../pieology/functions.php';

class TAccountCheckerPollotropical extends TAccountCheckerPieologyPunchhDotCom
{
    public $code = "pollotropical";
    public $reCaptcha = true;

    public function parseExtendedProperties()
    {
        $this->logger->notice(__METHOD__);

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR
            && $this->http->FindSingleNode('//strong[contains(text(), "Please agree on given terms and conditions.")]')
            && $this->http->FindPreg('/I agree to the Pollo Tropical/')
            && $this->http->currentUrl() == 'https://iframe.punchh.com/customers/edit.iframe?slug=pollotropical'
        ) {
            $this->throwAcceptTermsMessageException();
        }
    }
}
