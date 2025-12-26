<?php

require_once __DIR__ . '/../pieology/functions.php';

class TAccountCheckerModpizza extends TAccountCheckerPieologyPunchhDotCom
{
    /*
     * like as papaginos, burgerville, gordonb, genghisgrill, california, carinos, cosi, oberweis, restaurants
     *
     * like as huhot, canes, whichwich, boloco
     *
     * like as freebirds, maxermas  // refs #16823
     */

    public $code = "modpizza";
    public $reCaptcha = true;
    public $seleniumAuth = true;

    public function parseExtendedProperties()
    {
        $this->logger->notice(__METHOD__);

        if (
            $this->http->FindSingleNode('//strong[contains(text(), "Please agree on given terms and conditions.")]')
        ) {
            throw new CheckException("In order to get the account updated please accept terms and conditions here: https://iframe.punchh.com/whitelabel/modpizza.", ACCOUNT_PROVIDER_ERROR);
        }

        /*
        $this->http->GetURL("https://orders.modpizza.com/account");

        if ($this->http->Response['code'] == 403) {
            $this->http->SetProxy(null);
            $this->http->removeCookies();
            $this->http->GetURL("https://orders.modpizza.com/account");
        }

        if ($this->http->ParseForm("user-form")) {
            $this->http->SetInputValue('user[email]', $this->AccountFields['Login']);
            $this->http->SetInputValue("user[password]", $this->AccountFields['Pass']);
            $captcha = $this->parseReCaptcha();
            if ($captcha !== false)
                $this->http->SetInputValue('g-recaptcha-response', $captcha);
            $this->http->PostForm();
            // Captcha verification failed
            if ($this->reCaptcha &&
                ($message = $this->http->FindSingleNode('//div[contains(@class, "alert-danger")]//strong[contains(text(), "Captcha verification failed")]'))
            ) {
                $this->recognizer->reportIncorrectlySolvedCAPTCHA();
                throw new CheckRetryNeededException(3, 10, self::CAPTCHA_ERROR_MSG);
            }
        }

        if ($this->http->currentUrl() != "https://orders.modpizza.com/account") {
            $this->http->GetURL("https://orders.modpizza.com/account");
        }

        // MOD Rewards x-...
        $this->SetProperty('Card', $this->http->FindSingleNode("//li[contains(text(), 'MOD Rewards x-')]/text()[1]", null, true, "/MOD Rewards\s*([^\(:]+)/"));

        if (isset($this->Properties['Card']) && trim($this->Properties['Card']) == 'x-') {
            $this->logger->notice("remove wrong value");
            unset($this->Properties['Card']);
        }

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            $memberId = $this->http->FindPreg('#<span class="loyalty\-membership" data\-membershipid="(\d+)"></span>#');
            if (!$memberId)
                return;
            $this->http->GetURL("https://orders.modpizza.com/account/checkloyaltybalance/{$memberId}");
            // Balance - Current Points
            $this->SetBalance($this->http->FindPreg('#<balance>([\d.,]+) points?</balance><suffix>#'));
        }
        */
    }
}
