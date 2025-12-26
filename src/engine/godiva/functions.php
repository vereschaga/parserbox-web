<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerGodiva extends TAccountChecker
{
    use ProxyList;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setDefaultHeader("Accept-Encoding", "gzip, deflate, br");
        $this->http->setHttp2(true);
        $this->http->SetProxy($this->proxyReCaptcha());
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $success = $this->loginSuccessful();
        $this->http->RetryCount = 2;

        if ($success) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->RetryCount = 0;
        $this->http->GetURL('https://www.godiva.com/sign-in?source=signin');
        $this->http->RetryCount = 2;
        $this->challengeForm();

        if (!$this->http->ParseForm('login-form')) {
            return $this->checkErrors();
        }

        $this->http->SetInputValue("loginEmail", $this->AccountFields['Login']);
        $this->http->SetInputValue('loginPassword', $this->AccountFields['Pass']);
        $this->http->SetInputValue('loginRememberMe', "true");

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // maintenance
        if ($message = $this->http->FindSingleNode("//td[contains(text(), 'Thank you for visiting (www.godiva.com). Our site is currently') and contains(., 'undergoing scheduled system maintenance.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Our site is temporary offline for maintenance.
        if ($message = $this->http->FindSingleNode("
                //title[contains(text(), 'Our site is temporary offline for maintenance.')]
            ")
        ) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        $this->http->GetURL("http://godiva.com/");

        if ($this->http->FindSingleNode("//img[contains(@src, 'maintenance')]")) {
            throw new CheckException("We will be back soon. We're busy updating and improving Godiva.com for you. Thank you for your patience.", ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }

        $response = $this->http->JsonLog();
        // successful login
        if ($response->success ?? false) {
            if ($this->loginSuccessful()) {
                return true;
            }

            if (
                // AccountID: 3907046
                $this->http->Response['code'] == '500'
                && $this->http->FindSingleNode('//p[contains(text(), "Visit some of our other popular pages or use the search box below")]')
            ) {
                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }

            return false;
        }
        $message = $response->error ?? null;

        if (isset($message)) {
            if (is_array($message) && isset($message[0])) {
                $message = $message[0];
            }

            if (strstr($message, 'Invalid login or password.')) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            return false;
        }// if (isset($message))

        return $this->checkErrors();
    }

    public function Parse()
    {
        // Membership Number
        $this->SetProperty('MembershipNumber', $this->http->FindSingleNode('//dt[contains(text(), "Rewards Club No.")]/following-sibling::dd[1]'));

        // Rewards

        $rewards_count = $this->http->XPath->query("//ul[@class = 'rewards__list']/li");
        $this->logger->notice("Total {$rewards_count->length} rewards were found");

        for ($i = 0; $i < $rewards_count->length; $i++) {
            $node = $rewards_count->item($i);
            $displayName = $this->http->FindSingleNode('h3[@class = "rewards__item-header"]', $node);
            $expirationDate = strtotime($this->http->FindSingleNode('p', $node, true, '/ ([A-Z][a-z]{2} \d{2} \d{4})$/'));
            // Valid record
            if (!$expirationDate) {
                $this->sendNotification("rewards issue. User with rewards (unknown expiration date)");
            }

            if ($expirationDate < time()) {
                $this->logger->debug("Skip old reward: {$displayName}");

                continue;
            }

            $this->AddSubAccount([
                'Code'           => 'godivaRewards' . md5($displayName) . $expirationDate,
                'DisplayName'    => $displayName,
                'Balance'        => null,
                'ExpirationDate' => $expirationDate,
            ]);
        }// for ($i = 0; $i < $rewards_count->length; $i++)
        // subaccounts
        if (!empty($this->Properties['SubAccounts'])) {
            $this->SetBalanceNA();
            $this->SetProperty("CombineSubAccounts", false);
        } elseif (
            $this->http->FindSingleNode("//p[contains(text(), 'You are not currently signed up for GODIVA Chocolate Rewards Club.')]")
            || $this->AccountFields['Login'] == 'rio.paolo@gmail.com'
            || (
                $this->http->FindSingleNode('//p[contains(normalize-space(),", we noticed you are not a Rewards Club Member")]/a[contains(normalize-space(),"Join the Rewards Club")]')
                && !isset($this->Properties['MembershipNumber'])
            )
        ) {
            throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_WARNING);
        }

        // Profile page
        $this->http->GetURL('https://www.godiva.com/account-edit');
        // Name
        $this->SetProperty('Name', beautifulName($this->http->FindSingleNode('//input[@id="firstName"]/@value') . ' ' . $this->http->FindSingleNode('//input[@id="lastName"]/@value')));

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            if (isset($this->Properties['MembershipNumber']) && !empty($this->Properties['Name'])) {
                $this->SetBalanceNA();
            }
//            $this->http->GetURL('https://www.godiva.com/get-chocolate-rewards');
            if ($message = $this->http->FindSingleNode("//p[contains(text(), 'You are currently unsubscribed from GODIVA Chocolate Rewards Club')]")) {
                throw new CheckException($message, ACCOUNT_WARNING);
            }
            // Please sign me up for Godiva Rewards
            if ($message = $this->http->FindSingleNode("//label[contains(text(), 'Please sign me up for Godiva Rewards')]")) {
                throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_WARNING);
            }
            // We're sorry. Online access to our Loyalty Program is currently unavailable.
            if ($message = $this->http->FindSingleNode('//span[contains(text(), "We\'re sorry. Online access to our Loyalty Program is currently unavailable.")]')) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }
        }// if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR)
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);
        $this->http->GetURL("https://www.godiva.com/sign-in?source=signin");

        if ($this->http->FindNodes('//h1[contains(text(), "Dashboard")]')) {
            return true;
        }

        return false;
    }

    private function challengeForm()
    {
        $this->logger->notice(__METHOD__);
        $script = $this->http->FindPreg("/setTimeout\(function\(\)\{(.+?)'; 121'/s");

        if (!$script) {
            return false;
        }

        $host = parse_url($this->http->currentUrl(), PHP_URL_HOST);
        $script = str_replace('a.value = ', '', $script);
        $script = str_replace('+ t.length', "+ '{$host}'.length", $script);
        $script = preg_replace("/t = document.createElement\('div'\);.+?getElementById\('challenge-form'\);/s", '', $script);
        // not sure
        $script = "sendResponseToPhp($script)";
        $this->logger->debug($script);

        $jsExecutor = $this->services->get(\AwardWallet\Common\Parsing\JsExecutor::class);
        $encrypted = $jsExecutor->executeString($script);
        $this->logger->debug("encrypted: " . $encrypted);

        sleep(4);
        $params = [];
        $inputs = $this->http->XPath->query("//form[@id='challenge-form']//input");

        for ($n = 0; $n < $inputs->length; $n++) {
            $input = $inputs->item($n);
            $params[$input->getAttribute('name')] = $input->getAttribute('value');

            if ($input->getAttribute('name') == 'jschl_answer') {
                $params[$input->getAttribute('name')] = $encrypted;
            }
        }

        if (!empty($params) && $this->http->FindSingleNode("//form[@id = 'challenge-form' and @method = 'POST']")) {
            $action = $this->http->FindSingleNode("//form[@id='challenge-form']/@action");
            $this->http->NormalizeURL($action);
            $this->http->RetryCount = 0;
            $this->http->GetURL($action . '?' . http_build_query($params));
            $this->http->RetryCount = 2;
        } else {
            $this->http->SetInputValue("jschl_answer", $encrypted);
            $this->http->PostForm();
        }

        return true;
    }
}
