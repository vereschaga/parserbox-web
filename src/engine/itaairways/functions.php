<?php

class TAccountCheckerItaairways extends TAccountChecker
{
    private const REWARDS_PAGE_URL = 'https://www.volare.ita-airways.com/myloyalty/s/?language=en_US';

    private $token = null;
    private $userID = null;

    public static function GetAccountChecker($accountInfo)
    {
        require_once __DIR__ . "/TAccountCheckerItaairwaysSelenium.php";

        return new TAccountCheckerItaairwaysSelenium();
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setDefaultHeader("Accept-Encoding", "gzip, deflate, br");
    }

    public function IsLoggedIn()
    {
        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL('https://www.volare.ita-airways.com/myloyalty/s/login/?language=en_US&ec=302&startURL=%2Fmyloyalty%2Fs%2F');
        $fwuid = $this->http->FindPreg('/"fwuid":"([^"]+)/');
        $loginApp2 = $this->http->FindPreg('/loginApp2":"([^"]+)/');
        $this->State['context'] = '{"mode":"PROD","fwuid":"' . $fwuid . '","app":"siteforce:communityApp","loaded":{"APPLICATION@markup://siteforce:communityApp":"' . $loginApp2 . '"},"dn":[],"globals":{},"uad":false}';

        if ($this->http->Response['code'] != 200) {
            return $this->checkErrors();
        }

        $headers = [
            'Accept'               => '*/*',
            'Content-Type'         => 'application/x-www-form-urlencoded; charset=UTF-8',
        ];
        $data = [
            "message"      => '{"actions":[{"id":"58;a","descriptor":"aura://ApexActionController/ACTION$execute","callingDescriptor":"UNKNOWN","params":{"namespace":"","classname":"LightningLoginFormController","method":"login","params":{"username":"' . $this->AccountFields['Login'] . '","password":"' . $this->AccountFields['Pass'] . '","startUrl":"/myloyalty/s/","parameters":{"language":"en_US","startURL":"/myloyalty/s/","ec":"302"}},"cacheable":false,"isContinuation":false}}]}',
            "aura.context" => $this->State['context'],
            "aura.pageURI" => "/myloyalty/s/login/?language=en_US&startURL=%2Fmyloyalty%2Fs%2F&ec=302",
            "aura.token"   => "null",
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://www.volare.ita-airways.com/myloyalty/s/sfsites/aura?r=6&aura.ApexAction.execute=1", $data, $headers);
        $this->http->RetryCount = 2;

        return true;
    }

    public function Login()
    {
        $response = $this->http->JsonLog($this->http->FindPreg("/^\*\/(.+)\/\*ERROR\*\/$/"));

        /*
        if (isset($response->exceptionMessage)
            && $newContext = $this->http->FindPreg('/Framework has been updated\. Expected: ([\w-]+) Actual/', false, $response->exceptionMessage)
        ) {
            $this->DebugInfo = 'need to update \$context';
            $this->logger->error("Context outdated. Trying to parse new context from exception. Result: $newContext");

            if (!$newContext) {
                return false;
            }
            $this->State['context'] = '{"mode":"PROD","fwuid":"' . $newContext . '","app":"siteforce:loginApp2","loaded":{"APPLICATION@markup://siteforce:loginApp2":"Ay3xGFcvv3fYV4sGG2n9Bw"},"dn":[],"globals":{},"uad":false}';
            $data = [
                "message"      => '{"actions":[{"id":"58;a","descriptor":"aura://ApexActionController/ACTION$execute","callingDescriptor":"UNKNOWN","params":{"namespace":"","classname":"LightningLoginFormController","method":"login","params":{"username":"' . $this->AccountFields['Login'] . '","password":"' . $this->AccountFields['Pass'] . '","startUrl":"/myloyalty/s/","parameters":{"language":"en_US","startURL":"/myloyalty/s/","ec":"302"}},"cacheable":false,"isContinuation":false}}]}',
                "aura.context" => $this->State['context'],
                "aura.pageURI" => "/myloyalty/s/login/?language=en_US&startURL=%2Fmyloyalty%2Fs%2F&ec=302",
                "aura.token"   => "null",
            ];
            $this->http->RetryCount = 0;
            $this->http->PostURL("https://www.volare.ita-airways.com/myloyalty/s/sfsites/aura?r=6&aura.ApexAction.execute=1", $data, [
                'Accept'               => '*
        /*',
                'Content-Type'         => 'application/x-www-form-urlencoded; charset=UTF-8',
            ]);
            $this->http->RetryCount = 2;
            $response = $this->http->JsonLog($this->http->FindPreg("/^\*\/(.+)\/\*ERROR\*\/$/"));
        }
        */

        if (isset($response->actions[0]->returnValue->returnValue)) {
            $this->http->GetURL($response->actions[0]->returnValue->returnValue);

            if ($this->loginSuccessful()) {
                return true;
            }
        }

        if (isset($response->actions[0]->error[0]->message)) {
            $message = $response->actions[0]->error[0]->message;
            $this->logger->error("[Error]: {$message}");

            if ($message == 'Your login attempt has failed. Make sure the username and password are correct.') {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if ($message == 'Your access is disabled. Contact your site administrator.') {
                throw new CheckException("The login attempt failed. Make sure your username and password are correct.", ACCOUNT_INVALID_PASSWORD);
            }

            if (strstr($message, 'Account bloccato per mancata attivazione del profilo.')) {
                throw new CheckException($message, ACCOUNT_LOCKOUT);
            }

            $this->DebugInfo = $message;
        }// if (isset($response->actions[0]->error[0]->message))

        if (strstr($this->http->currentUrl(), 'setupid=ChangePassword')) {
            $this->throwProfileUpdateMessageException();
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $data = [
            "message"      => '{"actions":[{"id":"129;a","descriptor":"aura://ApexActionController/ACTION$execute","callingDescriptor":"UNKNOWN","params":{"namespace":"","classname":"ITA_LoyaltyMemberService","method":"getLoyaltyMember","cacheable":false,"isContinuation":false}},{"id":"130;a","descriptor":"aura://ApexActionController/ACTION$execute","callingDescriptor":"UNKNOWN","params":{"namespace":"","classname":"b2c_showcaseController","method":"getAccountFromUserId","params":{"userId":"' . $this->userID . '"},"cacheable":false,"isContinuation":false}},{"id":"131;a","descriptor":"aura://ApexActionController/ACTION$execute","callingDescriptor":"UNKNOWN","params":{"namespace":"","classname":"ITA_LoyaltyMemberService","method":"getLoyaltyTransactions","params":{"filter":{"page":1,"pageSize":5,"filter":{}}},"cacheable":false,"isContinuation":false}},{"id":"138;a","descriptor":"aura://ApexActionController/ACTION$execute","callingDescriptor":"UNKNOWN","params":{"namespace":"","classname":"HeaderCommunityController","method":"getCurrentUser","params":{"userId":"' . $this->userID . '"},"cacheable":false,"isContinuation":false}},{"id":"139;a","descriptor":"aura://ApexActionController/ACTION$execute","callingDescriptor":"UNKNOWN","params":{"namespace":"","classname":"HeaderCommunityController","method":"getFooterNavigationMenu","params":{"menuName":"Default_Navigation","language":"en_US"},"cacheable":false,"isContinuation":false}}]}',
            "aura.context" => $this->State['context'],
            "aura.pageURI" => "/myloyalty/s/",
            "aura.token"   => $this->token,
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://www.volare.ita-airways.com/myloyalty/s/sfsites/aura?r=4&aura.ApexAction.execute=5", $data);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog(null, 3, false, "PointsBalance");

        foreach ($response->actions as $action) {
            switch ($action->id) {
                case '129;a':
                    // refs $21766
                    foreach ($action->returnValue->returnValue->Loyalty_Member_Currency as $loyalty_Member_Currency) {
                        switch ($loyalty_Member_Currency->LoyaltyProgramCurrency->CurrencyType) {
                            case 'NonQualifying':
                                // Balance - Points Balance
                                $this->SetBalance($loyalty_Member_Currency->PointsBalance);

                                break;

                            case 'Qualifying':
                                // Qualifying Points Balance
                                $qualifyingPoints = $loyalty_Member_Currency->PointsBalance;
                                $this->SetProperty('QualifyingPoints', $qualifyingPoints);

                                break;
                        }
                    }
                    $tier = $action->returnValue->returnValue->Loyalty_Member_Tier[0];

                    if (is_numeric($tier->LoyaltyTier->To_Points__c ?? null)
                        && is_numeric($qualifyingPoints ?? null)
                    ) {
                        $this->SetProperty('QualifyingPointsToNextTier', $tier->LoyaltyTier->To_Points__c - $qualifyingPoints ?? null);
                    } elseif (isset($tier->Name)
                        && $tier->Name == 'Club Executive'
                    ) {
                        $this->SetProperty('QualifyingPointsToNextTier', 0);
                    }

                    // Number
                    $this->SetProperty('Number', $action->returnValue->returnValue->MembershipNumber);
                    // Status
                    $this->SetProperty('Status', $tier->Name);
                    // Status Expiration
                    $this->SetProperty('StatusExpiration', $tier->TierExpirationDate);
                    // Name
                    $contact = $action->returnValue->returnValue->Contact;
                    $this->SetProperty('Name', beautifulName($contact->FirstName . " " . $contact->LastName));
            }
        }// foreach ($response->actions as $action)
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);
        $this->http->RetryCount = 0;
        $this->http->GetURL(self::REWARDS_PAGE_URL, [], 20);
        $this->http->RetryCount = 2;

        $cookies = $this->http->GetCookies("www.volare.ita-airways.com", "/", true);
//        $this->logger->debug(var_export($cookies, true), ['pre' => true]);

        foreach ($cookies as $key => $cookie) {
//            $this->logger->debug(var_export($key, true), ['pre' => true]);
//            $this->logger->debug(var_export($cookie, true), ['pre' => true]);
            if (strstr($key, '__Host-ERIC_PROD')) {
                $this->token = urldecode($cookie);
            }
        }

        $bootstrap = $this->http->FindSingleNode("//script[contains(@src, 'bootstrap.js')]/@src");

        if (!isset($bootstrap) || !$this->token) {
            return false;
        }

        $this->http->NormalizeURL($bootstrap);
        $this->http->GetURL($bootstrap, [], 20);

        $this->userID = $this->http->FindPreg("/\"Email\":\"[^\"]+\",\"Id\":\"([^\"]+)\"\}\}\}/");
        $email = $this->http->FindPreg("/\"Email\":\"([^\"]+)\",\"Id\":\"[^\"]+\"\}\}\}/");
        $this->logger->debug("[Email]: {$email}");

        if (!$this->userID || strtolower($email) != strtolower($this->AccountFields['Login'])) {
            return false;
        }

        return true;
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        return false;
    }
}
