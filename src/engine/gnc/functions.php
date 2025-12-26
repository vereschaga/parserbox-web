<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerGnc extends TAccountChecker
{
    use ProxyList;

    public function InitBrowser(): void
    {
        parent::InitBrowser();
        $this->KeepState = true;

        if ($this->attempt > 0) {
            $this->http->SetProxy($this->setProxyDOP());
        }
    }

    public function IsLoggedIn(): bool
    {
        return $this->loginSuccessful();
    }

    public function LoadLoginForm(): bool
    {
        $this->http->removeCookies();
        $this->http->RetryCount = 0;
        $this->http->GetURL('https://www.gnc.com/');

        if (str_contains($this->http->currentUrl(), 'recaptcha.html')) {
            throw new CheckRetryNeededException();
        }
        $csrfToken = $this->http->FindSingleNode("(//input[@name='csrf_token']/@value)[1]");

        if (empty($csrfToken)) {
            return false;
        }
        $this->http->PostURL('https://www.gnc.com/on/demandware.store/Sites-GNC2-Site/default/Login-OAuthLoginForm?OAuthProvider=Salesforce', [
            'dwfrm_login_login' => 'Sign In',
            'csrf_token'        => $csrfToken,
        ]);

        if (str_contains($this->http->currentUrl(), 'recaptcha.html')) {
            throw new CheckRetryNeededException();
        }

        if (!$this->handleRedirect()) {
            return false;
        }

        parse_str(parse_url($this->http->currentUrl(), PHP_URL_QUERY), $output);
        $startURL = $output['startURL'] ?? null;
        $fwuid = $this->http->FindPreg('/"fwuid":"([\w-]+)/');
        $loginApp2 = $this->http->FindPreg('/siteforce:loginApp2":"([\w-]+)/');

        if (!$startURL || $this->http->Response['code'] != 200 || !$fwuid || !$loginApp2) {
            return $this->checkErrors();
        }

        $data = [
            "message"      => '{"actions":[{"id":"114;a","descriptor":"aura://ComponentController/ACTION$reportFailedAction","callingDescriptor":"UNKNOWN","params":{"failedAction":"markup://force:ldsBindings","failedId":"2115636674","clientError":" [LWC component\'s @wire target property or method threw an error during value provisioning. Original error:\n[usableNetURL is not defined]]","additionalData":"{}","clientStack":"P.wiredMapping()@https://login-register.gnc.com/s/sfsites/auraFW/javascript/YWYyQV90T3g3VDhySzNWUm1kcF9WUVY4bi1LdGdMbklVbHlMdER1eVVlUGcyNDYuMTUuNS0zLjAuNA/aura_prod.js:142:52\n{anonymous}()@https://login-register.gnc.com/s/sfsites/auraFW/javascript/YWYyQV90T3g3VDhySzNWUm1kcF9WUVY4bi1LdGdMbklVbHlMdER1eVVlUGcyNDYuMTUuNS0zLjAuNA/aura_prod.js:51:16758\npo()@https://login-register.gnc.com/s/sfsites/auraFW/javascript/YWYyQV90T3g3VDhySzNWUm1kcF9WUVY4bi1LdGdMbklVbHlMdER1eVVlUGcyNDYuMTUuNS0zLjAuNA/aura_prod.js:51:42864\n{anonymous}()@https://login-register.gnc.com/s/sfsites/auraFW/javascript/YWYyQV90T3g3VDhySzNWUm1kcF9WUVY4bi1LdGdMbklVbHlMdER1eVVlUGcyNDYuMTUuNS0zLjAuNA/aura_prod.js:51:16735\nR.a [as callback]()@https://login-register.gnc.com/s/sfsites/auraFW/javascript/YWYyQV90T3g3VDhySzNWUm1kcF9WUVY4bi1LdGdMbklVbHlMdER1eVVlUGcyNDYuMTUuNS0zLjAuNA/aura_prod.js:51:16796\nR.emit()@https://login-register.gnc.com/components/force/ldsBindings.js:1:4101\nn()@https://login-register.gnc.com/components/force/ldsBindings.js:1:3980","componentStack":"[c:termsAndConditions]","stacktraceIdGen":"markup://force:ldsBindings$R.emit$ [LWC component\'s @wire t","level":"ERROR"}},{"id":"117;a","descriptor":"aura://ApexActionController/ACTION$execute","callingDescriptor":"UNKNOWN","params":{"namespace":"","classname":"LoginControllerNew","method":"login","params":{"username":"' . $this->AccountFields['Login'] . '","password":"' . $this->AccountFields['Pass'] . '","startURL":"startURL=' . $startURL . '","lang":"en_US"},"cacheable":false,"isContinuation":false}}]}',
            "aura.context" => '{"mode":"PROD","fwuid":"' . $fwuid . '","app":"siteforce:loginApp2","loaded":{"APPLICATION@markup://siteforce:loginApp2":"' . $loginApp2 . '"},"dn":[],"globals":{},"uad":false}',
            "aura.pageURI" => "/s/login/?ec=302&inst=UZ&language=en_US&startURL=" . urlencode($startURL),
            "aura.token"   => "null",
        ];

        $headers = [
            'Accept'               => '*/*',
            'Content-Type'         => 'application/x-www-form-urlencoded;charset=UTF-8',
            'X-Sfdc-Lds-Endpoints' => 'ApexActionController.execute:LoginControllerNew.login',
            'X-Sfdc-Page-Scope-Id' => '9c571912-1af4-488e-8252-91fb501003c7',
        ];
        $this->http->PostURL('https://login-register.gnc.com/s/sfsites/aura?r=5&aura.ApexAction.execute=1&aura.Component.reportFailedAction=1', $data, $headers);
        $this->http->RetryCount = 2;

        return true;
    }

    public function Login(): bool
    {
        $response = $this->http->JsonLog(null, 3, false, 'state');

        $returnValue = $response->actions[1]->returnValue->returnValue ?? null;
        $cacheable = $response->actions[1]->returnValue->cacheable ?? true;

        if ($url = $this->http->FindPreg('/System.PageReference\[(.+?)\]/', false, $returnValue)) {
            $this->http->GetURL($url);
            $this->handleRedirect();
            $this->handleRedirect();
            $this->handleRedirect();

            return true;
        }

        if ($cacheable === false) {
            $this->sendNotification('check cacheable // MI');

            throw new CheckException('Your password and email address do not match. Please try again or Reset Your Password', ACCOUNT_INVALID_PASSWORD);
        }

        if (strstr($returnValue, 'You have entered an invalid email address or password.')) {
            throw new CheckException('Oh no! You have entered an invalid email address or password. Please make sure the information you entered is correct or simply click "I forgot my password" to reset your password.', ACCOUNT_INVALID_PASSWORD);
        }

        if ($returnValue) {
            $this->logger->error("[Error]: {$returnValue}");

            if (str_contains($returnValue, 'Please enter a valid Email Address')) {
                throw new CheckException($returnValue, ACCOUNT_INVALID_PASSWORD);
            }

            if (str_contains($returnValue, 'We could not find the matching email address')) {
                throw new CheckException($returnValue, ACCOUNT_INVALID_PASSWORD);
            }

            $this->DebugInfo = $returnValue;
        }

        return $this->checkErrors();
    }

    public function Parse(): void
    {
        $this->http->GetURL('https://www.gnc.com/account');

        // Balance - Points
        $this->SetBalance($this->http->FindSingleNode('//span[@class="gnc-available-points"][1]/span[1]'));

        // Status
        $level = $this->http->FindSingleNode("//div[@class='acr-main__image']/picture/img/@src", null, false, '#images/loyalty-tiers/(.+?\.png)#');

        switch ($level) {
            case 'myGNC-members-Rewards-Logo-mobile.png':
                $this->SetProperty('Status', 'Member');

                break;

            case 'myGNC-silver-Rewards-Logo-mobile.png':
                $this->SetProperty('Status', 'Silver');

                break;

            case 'myGNC-pro-Rewards-Logo-mobile.png':
                $this->SetProperty('Status', 'Pro');

                break;

            default:
                $this->sendNotification("$level // MI");

                break;
        }

        // Spend $200 and become
        $this->SetProperty('SpendToNextLevel', $this->http->FindSingleNode("//span[contains(@class,'next-reward-amount')]", null, false, "/(.\d+)/"));
        // Next elite level
        $nextLevel = $this->http->FindSingleNode("//div[contains(@class,'acr-main__next-reward')]/picture/img/@src", null, false, '#/images/loyalty-tiers/(.+?\.png)#');

        switch ($nextLevel) {
            case 'members-new-tier-info-tab.png':
                $nextLevel = 'Silver';

                break;

            case 'silver-new-tier-info-tab.png':
                $nextLevel = 'Gold';

                break;

            default:
                if (!empty($nextLevel)) {
                    $this->sendNotification("$nextLevel // MI");
                }

                break;
        }
        $this->SetProperty('NextEliteLevel', $nextLevel);

        // $XX Rewards
        $reward = $this->http->FindSingleNode('//section[@id="rewards-section"]//span[@class="gnc-brand-color"]');

        if (!is_null($reward)) {
            $this->AddSubAccount([
                'Code'        => 'reward',
                'DisplayName' => $reward . ' Rewards',
                'Balance'     => null,
            ]);
        }
        // Member since
        $this->SetProperty('MemberSince', $this->http->FindSingleNode('//span[@class="acr-main__member-since--date"][1]'));
        // Name
        $this->SetProperty('Name', beautifulName($this->http->FindSingleNode('//div[@class="account-tiles-wrapper"][1]//div[@class="tile-info"][1]/div[1]')));
    }

    /**
     * Some redirects on this site need JS, so we emulate them.
     *
     * @return bool was handled successfully or not
     */
    private function handleRedirect(): bool
    {
        $this->logger->notice(__METHOD__);
        $url =
            $this->http->FindPreg("/url\s*=\s*'([^']+)/")
            ?? $this->http->FindPreg("/window\.location\.replace\((?:\"|\')([^\"\']+)/")
        ;

        if (!$url) {
            return false;
        }

        $this->http->NormalizeURL($url);
        $this->http->GetURL($url);

        return true;
    }

    private function loginSuccessful(): bool
    {
        $this->logger->notice(__METHOD__);
        $this->http->RetryCount = 0;
        $this->http->GetURL('https://www.gnc.com/account-rewards', [], 20);
        $this->http->RetryCount = 2;
        $email = $this->http->FindSingleNode('//input[@id="sfmcUserEmail"]/@value');
        $this->logger->debug("[Email]: {$email}");

        if (!$email || strtolower($email) != strtolower($this->AccountFields['Login'])) {
            return false;
        }

        return true;
    }

    private function checkErrors(): bool
    {
        $this->logger->notice(__METHOD__);

        return false;
    }
}
