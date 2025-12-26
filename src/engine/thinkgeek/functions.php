<?php

// refs #2041, thinkgeek

class TAccountCheckerThinkgeek extends TAccountChecker
{
    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://www.thinkgeek.com/brain/account/login.cgi");

        if (!$this->http->ParseForm(null, 1, true, "(//form[@action = 'https://www.thinkgeek.com/brain/account/login.cgi'])[2]")) {
            return $this->checkErrors();
        }
        $this->http->SetInputValue("p", "");
        $this->http->SetInputValue("un", $this->AccountFields['Login']);
        $this->http->SetInputValue("pass", $this->AccountFields['Pass']);

        return true;
    }

    public function checkErrors()
    {
        // Site is down
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'our servers have been thrown forward in time')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Internal Server Error
        if ($this->http->FindSingleNode("//h2[contains(text(), 'Internal Server Error')]")
            || $this->http->FindSingleNode("//h1[contains(text(), 'Error 503 backend read error')]")
            || $this->http->FindSingleNode("//h1[contains(text(), 'Error 503 certificate has expired')]")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }
        // Maintenance
        if ($message = $this->http->FindSingleNode('//p[contains(text(), "We thought maybe you\'d sleep in and not notice that we\'re doing a little site maintenance this morning")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        sleep(2);

        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }

        // Access is allowed
        if ($this->http->FindSingleNode("(//a[contains(text(), 'Logout')])[1]")
            || $this->http->FindPreg("/Log Out/ims")) {
            return true;
        }

        if ($message = $this->http->FindSingleNode("//p[@class = 'error']")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        // retries
        if ($this->http->ParseForm(null, 1, true, "(//form[@action = 'https://www.thinkgeek.com/brain/account/login.cgi'])[2]")
        && $this->http->FindSingleNode("(//form[@action = 'https://www.thinkgeek.com/brain/account/login.cgi'])[2]", null, true, "/^\s*Email\s*Password\s*Forgot your password\?\s*$/")) {
            throw new CheckRetryNeededException(2, 7);
        }
        // hard code (AccountID: 772384)
        if ($this->AccountFields['Login'] == 'patrick@ifroggy.com' && $this->http->Response['code'] == 403) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $this->http->GetURL("https://www.thinkgeek.com/geekpoints/");
        //# Sign Up Now. Geek Points is Free to Join!
        if ($this->http->FindSingleNode("//input[contains(@value, 'I WANT FREE STUFF!')]/@value", null, true, null, 0) && $this->http->FindPreg("/Geek Points is Free to Join!/ims")) {
            throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        //# Balance - Your Current Geek Points Total
        $this->SetBalance($this->http->FindPreg("/\"geek_points_balance\":\"?([^\"\,]+)/"));

        // Expiration Date
        $this->http->GetURL("https://www.thinkgeek.com/brain/account/points.cgi?a=bal");
        $transactions = $this->http->XPath->query("//div[@id = 'generic-wrapper']/table//tr[@class]");
        $this->http->Log("Total transactions found: " . $transactions->length);
        $expiringBalance = 0;

        for ($i = 0; $i < $transactions->length; $i++) {
            $transaction = $transactions->item($i);
            $date = $this->http->FindSingleNode("td[2]", $transaction);

            if (empty($date)) {
                $this->http->Log("Date is empty");

                continue;
            }
            $date = $date . '/01';
            $dateUnixTime = strtotime($date);
            $this->http->Log("Date: {$date} / {$dateUnixTime}");

            if ($dateUnixTime < time()) {
                $this->http->Log("Skip past date");

                continue;
            }

            if (!isset($exp) || $exp == $dateUnixTime) {
                $exp = $dateUnixTime;
                $expiringBalance += str_replace(',', '', $this->http->FindSingleNode("td[4]", $transaction));
                $this->SetProperty("ExpiringBalance", number_format($expiringBalance));
                $this->SetExpirationDate($exp);
            } else {
                break;
            }
        }// for ($i = 0; $i < $transactions->length; $i++)

        $this->http->GetURL("https://www.thinkgeek.com/brain/account/index.cgi");
        $this->SetProperty("Name", $this->http->FindSingleNode("//p[contains(text(), 'Welcome to your account')]", null, true, '/err, we mean ([a-zA-Z0-9 ]+)/ims'));
    }

    public function GetExtensionFinalURL(array $fields)
    {
        return "https://www.thinkgeek.com/brain/account/index.cgi";
    }
}
