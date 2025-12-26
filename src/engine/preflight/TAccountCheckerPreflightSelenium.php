<?php
 class TAccountCheckerPreflightSelenium extends TAccountChecker
 {
     use SeleniumCheckerHelper;
     /**
      * @var HttpBrowser
      */
     public $browser;

     public function InitBrowser()
     {
         parent::InitBrowser();
         $this->UseSelenium();
         $this->disableImages();
         $this->useChromium();
     }

     public function LoadLoginForm()
     {
         $this->http->removeCookies();
         $this->http->LogHeaders = true;
         $this->http->GetURL("https://www.preflightairportparking.com/Login.aspx?ReturnUrl=%2fmembers%2fAccountInfo.aspx");

         $loginInput = $this->waitForElement(WebDriverBy::id('ctl00_ContentPlaceHolder1_tbUserAccount'), 20);

         if (empty($loginInput)) {
             return $this->checkErrors();
         }
         $loginInput->sendKeys($this->AccountFields['Login']);

         $passInput = $this->waitForElement(WebDriverBy::id('ctl00_ContentPlaceHolder1_tbPassword'), 0);

         if (empty($passInput)) {
             return $this->checkErrors();
         }
         $passInput->sendKeys($this->AccountFields['Pass']);
         $continueButton = $this->waitForElement(WebDriverBy::id('ctl00_ContentPlaceHolder1_btLogin'), 0);

         if (!$continueButton) {
             return $this->checkErrors();
         }
//         $continueButton->click();
         $this->driver->executeScript('setTimeout(function(){ delete document.$cdc_asdjflasutopfhvcZLmcfl_; document.getElementById("ctl00_ContentPlaceHolder1_btLogin").click(); }, 500)');

         // use curl
//         $this->parseWithCurl();
//         if (!$this->browser->ParseForm("aspnetForm"))
//             return $this->checkErrors();
//         $this->browser->SetInputValue('ctl00$ContentPlaceHolder1$tbUserAccount', $this->AccountFields['Login']);
//         $this->browser->SetInputValue('ctl00$ContentPlaceHolder1$tbPassword', $this->AccountFields['Pass']);
//         $this->browser->SetInputValue('ctl00$ContentPlaceHolder1$btLogin', "Login to Account");

         return true;
     }

     public function parseWithCurl()
     {
         $this->http->Log(__METHOD__, LOG_LEVEL_ERROR);
         // parse with curl
         $this->browser = new HttpBrowser("none", new CurlDriver());
         $this->http->brotherBrowser($this->browser);
         $cookies = $this->driver->manage()->getCookies();

         foreach ($cookies as $cookie) {
             $this->browser->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
         }

         $this->browser->LogHeaders = true;
         $this->browser->setProxyParams($this->http->getProxyParams());
//         $this->browser->GetURL($this->http->currentUrl());
         $this->browser->GetURL("https://www.preflightairportparking.com/members/AccountInfo.aspx");
     }

     public function checkErrors()
     {
         // The System is down for maintenance. Please try again later.
         $this->http->GetURL("https://www.preflightairportparking.com");

         if ($message = $this->http->FindSingleNode("//font[contains(text(), 'The System is down for maintenance')]")) {
             throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
         }
         // Server Error in '/' Application
         if ($this->http->FindSingleNode("//h1[contains(text(), 'Server Error')]")
             // Server Error in '/' Application
             || $this->http->FindPreg("/(Server Error in \'\/\' Application)/ims")) {
             throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
         }

         return false;
     }

     public function Login()
     {
//         if (!$this->browser->PostForm())
//             return $this->checkErrors();

         $logout = $this->waitForElement(WebDriverBy::xpath("//a[contains(text(), 'Logout')] | //span[contains(@id, 'lbNewAwardPts')]"), 10);
         $this->saveResponse();

         if ($logout) {
             // use curl
             $this->parseWithCurl();

             return true;
         }

         // Invalid login or password
         if ($message = $this->http->FindSingleNode('//span[@class="clsErrorMsg"]')) {
             throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
         }
         // Invalid login or password
         if ($message = $this->http->FindPreg("/Please login using your Card Number and Password/ims")) {
             throw new CheckException("Your passcode could not be verified", ACCOUNT_INVALID_PASSWORD);
         }
         // Username or Password is incorrect
         if ($message = $this->http->FindPreg("/(Username or Password is incorrect\.\s*Please try again\.)/ims")) {
             throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
         }
         // Update Account Security
         if ($this->http->FindSingleNode("//strong[contains(text(), 'Update Account Security')]")
             || $this->http->FindSingleNode("//h2[contains(text(), 'UPDATE ACCOUNT SECURITY')]")
             || $this->http->currentUrl() == 'https://www.preflightairportparking.com/ChangePassword.aspx') {
             $this->throwProfileUpdateMessageException();
         }

         // provider bug, any errors not showing on the website
         $loginInput = $this->waitForElement(WebDriverBy::id('ctl00_ContentPlaceHolder1_tbUserAccount'), 0);
         $passInput = $this->waitForElement(WebDriverBy::id('ctl00_ContentPlaceHolder1_tbPassword'), 0);

         if ($loginInput && $passInput) {
             $this->logger->debug("Login: {$loginInput->getAttribute('value')}");
             $this->logger->debug("Password: {$passInput->getAttribute('value')}");
             // hard code
             if ($loginInput->getAttribute('value') == '' && $passInput->getAttribute('value') == '') {
                 throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
             }
         }// if ($loginInput && $passInput)

         return $this->checkErrors();
     }

     public function Parse()
     {
         // Name
         $this->SetProperty("Name", beautifulName($this->browser->FindSingleNode("//input[contains(@id, 'tbAccountName')]/@value")));
         // Account Number
         $this->SetProperty("Number", $this->browser->FindSingleNode("//input[contains(@id, 'tbffAcctNum')]/@value"));
         // Points Earned Life Time
         $this->SetProperty("PointsEarned", $this->browser->FindSingleNode("//span[contains(@id, 'lbLifetimePts')]"));
         // Balance - Points Available for New Awards
         $points = $this->browser->FindSingleNode("//span[contains(@id, 'lbNewAwardPts')]");

         if (isset($points)) {
             $points = preg_replace('/([^\d\.])/ims', '${2}', $points);
         }
         $this->SetBalance($points);
         // Points Expiring in Next 60 Days
         $this->SetProperty("PointsExpiring", $this->browser->FindSingleNode("//span[contains(@id, 'lbPtsExpiring')]"));

         // Expiration Date  // refs #4936
         if (isset($points) && $points > 0) {
             $this->browser->GetURL("https://www.preflightairportparking.com/members/RPT_Frequent-Parker-Points-Transactions.aspx");
             $nodes = $this->browser->XPath->query("//tr[td[div[contains(text(), 'Points')]]]/following-sibling::tr[td[5]/div]");
             $this->browser->Log("Total nodes found " . $nodes->length);

             for ($i = 0; $i < $nodes->length; $i++) {
                 $historyPoints = $this->browser->FindSingleNode("td[5]/div", $nodes->item($i));
                 $this->browser->Log("Node # $i - Balance: $points, HistoryPoints: $historyPoints");

                 if ($historyPoints > 0) {
                     $points -= $historyPoints;
                 }
                 $this->browser->Log("Node # $i - Balance: $points / round: " . round($points, 2));

                 if (round($points, 2) <= 0) {
                     $date = $this->browser->FindSingleNode("td[1]/div", $nodes->item($i));

                     if (isset($date)) {
                         $this->SetProperty("EarningDate", $date);
                         $this->SetExpirationDate(strtotime("+2 year", strtotime($date)));
                     }
                     //# Points Expiring
                     $this->SetProperty("PointsToExpire", round(($points + $this->browser->FindSingleNode("td[5]/div", $nodes->item($i))), 2));

                     break;
                 }// if ($points <= 0)
             }// for ($i = 0; $i < $historyPoints->length; $i++)
         }// if (isset($points))
     }
 }
