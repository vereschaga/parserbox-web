<?php

require_once __DIR__ . '/../pieology/functions.php';

class TAccountCheckerQuiznos extends TAccountCheckerPieologyPunchhDotCom
{
    /*
     * like as papaginos, burgerville, gordonb, genghisgrill, california, carinos, cosi, oberweis, restaurants
     *
     * like as huhot, canes, whichwich, boloco
     *
     * like as freebirds, maxermas  // refs #16823
     */

    public $code = "quiznos";
    public $reCaptcha = true;
    public $seleniumAuth = true;

    public function parseExtendedProperties()
    {
        $this->logger->notice(__METHOD__);

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR
            && $this->http->FindSingleNode('//strong[contains(text(), "Favorite Location can\'t be blank") or contains(text(), "Please enter a valid postal zip code.")]')
            && $this->http->FindPreg('/I agree to the Quiznos/')
            && $this->http->currentUrl() == 'https://iframe.punchh.com/customers/edit.iframe?slug=quiznos'
        ) {
            $this->throwProfileUpdateMessageException();
        }
    }
}
