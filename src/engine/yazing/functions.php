<?php

use AwardWallet\Common\Parser\Util\PriceHelper;

class TAccountCheckerYazing extends TAccountChecker
{
    private const REWARDS_PAGE_URL = 'https://yazing.com/user/profile';
//    private $prefix = 'www.';
    private $prefix = '';

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setDefaultHeader("Accept-Encoding", "gzip, deflate, br");
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL(self::REWARDS_PAGE_URL, [], 20);
        $this->http->RetryCount = 2;

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://{$this->prefix}yazing.com/user/login");

        if (!$this->http->ParseForm('loginform')) {
            return $this->checkErrors();
        }

        $this->http->FormURL = "https://{$this->prefix}yazing.com/user/process-login";
        $this->http->SetInputValue('LoginForm[username]', $this->AccountFields['Login']);
        $this->http->SetInputValue('LoginForm[password]', $this->AccountFields['Pass']);

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        return false;
    }

    public function Login()
    {
        if (!$this->http->PostForm(['X-Requested-With' => 'XMLHttpRequest'])) {
            return $this->checkErrors();
        }

        $response = $this->http->JsonLog();

        if (!empty($response) && isset($response->response)) {
            if ($this->http->FindPreg('/"response":"","code":1/')) {
                return true;
            }

            $message = $response->response ?? null;

            if (
                $message == 'Incorrect username or password.'
                || $message == 'Username does not exist.'
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if (
                $message == "This account has been flagged for suspicious activity and all transactions are currently on hold. <a href='/display/blocked'>Read more</a>"
            ) {
                throw new CheckException("This account has been flagged for suspicious activity and all transactions are currently on hold.", ACCOUNT_LOCKOUT);
            }
        }// if (!empty($response) && isset($response->response))

        if ($this->http->FindPreg("/^Failed to connect to MySQL: No such file or directory\{\"code\":0,\"response\":\"Username does not exist\.\"\}$/")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Parse()
    {
        if ($this->http->currentUrl() != self::REWARDS_PAGE_URL) {
            $this->http->GetURL(self::REWARDS_PAGE_URL);
        }
        // Username
        $this->SetProperty('Name', strtoupper($this->http->FindPreg('/class="dropdown-toggle" href="#" data-toggle="dropdown">([^\<]+)\s+<span class="caret"><\/span><\/a><ul id="w4/')));

        // First/Last Name
        $name = trim($this->http->FindSingleNode('//input[@id = "profileform-firstname"]/@value') . ' ' . $this->http->FindSingleNode('//input[@id = "profileform-lastname"]/@value'));

        if (!empty($name)) {
            $this->SetProperty('Name', beautifulName($name));
        }

        // refs #16899
        $this->http->GetURL("https://{$this->prefix}yazing.com/dashboard");
        $pending = $this->http->FindSingleNode('//p[contains(text(), "Pending")]/preceding-sibling::h3', null, true, self::BALANCE_REGEXP);
        $paid = $this->http->FindSingleNode('//p[contains(text(), "Paid")]/preceding-sibling::h3', null, true, self::BALANCE_REGEXP);

        if (isset($pending)) {
            // Pending
            $this->AddSubAccount([
                "Code"              => "yazingPending",
                "DisplayName"       => "Pending",
                "Balance"           => $pending,
                "BalanceInTotalSum" => true,
            ]);
            // Balance - Pending
            $this->SetBalance($this->getValue($pending));
        }// if (isset($pending)

        if (isset($paid)) {
            // Future Payments
            $this->AddSubAccount([
                "Code"        => "yazingPaid",
                "DisplayName" => "Paid",
                "Balance"     => $paid,
            ]);
        }
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode('//a[contains(@href, "logout")]/@href')) {
            return true;
        }

        return false;
    }

    private function getValue($amount)
    {
        $this->logger->notice(__METHOD__);
        $sign = 1;

        if ($val = $this->http->FindPreg("/^-(.+)/", false, $amount)) {
            $sign = -1;
            $amount = $val;
        }
        $amount = PriceHelper::cost($amount);
        $amount *= $sign;

        return $amount;
    }
}
