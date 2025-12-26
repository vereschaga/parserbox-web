<?php

// case #10322

namespace AwardWallet\Engine\ichotelsgroup\Transfer;

use AwardWallet\Engine\ProxyList;

class Purchase extends \TAccountChecker
{
    use \SeleniumCheckerHelper;
    use ProxyList;

    protected $loadTimeout = 20;
    protected $waitTimeout = 2;

    public function InitBrowser()
    {
        $this->UseSelenium();

        if (ConfigValue(CONFIG_SITE_STATE) === SITE_STATE_PRODUCTION) {
            //$this->http->SetProxy($this->proxyDOP());
            $this->setProxyBrightData();
        } else {
            $this->http->SetProxy($this->proxyPurchase());
        }

        $this->useFirefox(\SeleniumFinderRequest::FIREFOX_53);
    }

    public function LoadLoginForm()
    {
        return true;
    }

    public function Login()
    {
        return true;
    }

    public function purchaseMiles(array $fields, $numberOfMiles, $creditCard)
    {
        $numberOfMiles = intval($numberOfMiles);

        if ($numberOfMiles <= 0 || $numberOfMiles > 60000 || $numberOfMiles % 1000 !== 0) {
            throw new \UserInputError('Number of purchased points should be lesser then 60,000 and divisible by 1000.');
        }

        if (ConfigValue(CONFIG_SITE_STATE) === SITE_STATE_PRODUCTION) { // use affiliate link
            $this->http->GetURL('http://www.kqzyfj.com/click-8184014-11347127-1365617679000');
        }
        //		else {
        //			$this->http->GetURL('https://storefront.points.com/IHG-rewards-club/en-US/buy');
        //		}
        //if should without promoCode - check commented block below
        //		$this->http->GetURL('https://storefront.points.com/IHG-rewards-club/en-US/buy');
        $this->http->GetURL('https://storefront.points.com/IHG-rewards-club/en-US/buy?promoCode=cobrandBase');

        if (!$this->waitForElement(\WebDriverBy::xpath('//form[@name="loginForm"]'), $this->loadTimeout)) {
            return $this->fail('Login form not loaded.');
        }
        $inputFieldsMap = [
            'FirstName'     => 'firstName',
            'LastName'      => 'lastName',
            'AccountNumber' => 'memberId',
            'Password'      => 'password',
            'Email'         => 'email',
        ];

        foreach ($inputFieldsMap as $awKey => $inputId) {
            $this->logger->info('Setting ' . $awKey . '...');

            if ($elem = $this->waitForElement(\WebDriverBy::id($inputId), $this->waitTimeout)) {
                $elem->sendKeys($fields[$awKey]);
                $this->logger->info('Done.');
            } else {
                $this->http->Log('Could not find input field for ' . $awKey . '.', LOG_LEVEL_ERROR);

                return false;
            }
        }
        sleep(1);
        $this->checkLoginFormErrors();

        if ($elem = $this->waitForElement(\WebDriverBy::xpath('//form[@name="loginForm"]//button[@type="submit"]'), $this->waitTimeout)) {
            $elem->click();
            $this->logger->info('Login form submitted.');
        } else {
            return false;
        }

        if (!$this->waitForElement(\WebDriverBy::xpath('//form[@data-name="transactionForm"]//select[@id="bgt-offer-dropdown"]'), $this->loadTimeout)) {
            $this->checkLoginFormErrors();

            return $this->fail('Buy form not loaded');
        }

        if (!($elem = $this->waitForElement(\WebDriverBy::id('bgt-offer-dropdown'), $this->loadTimeout))) {
            return $this->fail('Select "bgt-offer-dropdown" not found.');
        }
        $optionXpath = sprintf('//select[@id = "bgt-offer-dropdown"]//option[@value = "%s"]', $numberOfMiles);

        if ($option = $this->waitForElement(\WebDriverBy::xpath($optionXpath), $this->waitTimeout)) {
            $option->click();
            $this->logger->info('Select ' . $numberOfMiles . ' points done.');
        } else {
            return $this->fail('Didn\'t find option for ' . $numberOfMiles . ' points.');
        }

        //not checked block if started with https://storefront.points.com/IHG-rewards-club/en-US/buy
//        if ($elem = $this->waitForElement(\WebDriverBy::xpath("(//text()[starts-with(normalize-space(),'If you are not paying with your IHG')])[1]/following::a[1][contains(.,'here')]"), $this->loadTimeout)){
//            $elem->click();
//            $this->logger->info('go to page with standard payment details');
//            if( !$this->waitForElement(\WebDriverBy::xpath('//form[@data-name="transactionForm"]//select[@id="bgt-offer-dropdown"]'), $this->loadTimeout) ) {
//                $this->checkLoginFormErrors();
//                return $this->fail('Buy form not loaded');
//            }
//            if( !($elem = $this->waitForElement(\WebDriverBy::id('bgt-offer-dropdown'), $this->loadTimeout)) ){
//                return $this->fail('Select "bgt-offer-dropdown" not found.');
//            }
//            $optionXpath = sprintf('//select[@id = "bgt-offer-dropdown"]//option[@value = "%s"]', $numberOfMiles);
//            if( $option = $this->waitForElement(\WebDriverBy::xpath($optionXpath), $this->waitTimeout) ){
//                $option->click();
//                $this->logger->info('Select ' . $numberOfMiles . ' points done.');
//            }else{
//                return $this->fail('Didn\'t find option for ' . $numberOfMiles . ' points.');
//            }
//        }

        if (!$this->waitForElement(\WebDriverBy::id('cardName'), $this->loadTimeout)) {
            $this->checkLoginFormErrors();

            return $this->fail('Credit Card form not loaded.');
        }
        $inputSelectFieldsMap = [
            'Type' => [
                'cardName',
                'string:transaction.payment.form.creditCard.' . $creditCard['Type'],
            ],
            'ExpirationMonth' => [
                'expirationMonth',
                'number:' . (int) $creditCard['ExpirationMonth'],
            ],
            'ExpirationYear' => [
                'expirationYear',
                'number:' . (strlen($creditCard['ExpirationYear']) === 2 ? '20' . $creditCard['ExpirationYear'] : $creditCard['ExpirationYear']),
            ],
            'Country' => [
                'country',
                'string:' . $creditCard['CountryCode'],
            ],
            'StateOrProvince' => [
                'state',
                'string:' . $creditCard['StateCode'],
            ],
        ];

        foreach ($inputSelectFieldsMap as $key => $selectField) {
            [$selectFieldId, $value] = $selectField;
            $this->logger->info("Selecting '$key' to '$value' ...");

            try {
                (new \WebDriverSelect($this->waitForElement(\WebDriverBy::id($selectFieldId), $this->waitTimeout)))->selectByValue($value);
            } catch (\NoSuchElementException $e) {
                return $this->fail($e->getMessage());
            }
            $this->logger->info('Select "' . $value . '" done.');
        }
        $inputTextFieldsMap = [
            'cardNumber'         => 'CardNumber',
            'securityCode'       => 'SecurityNumber',
            'creditCardFullName' => 'Name',
            'street1'            => 'AddressLine',
            'city'               => 'City',
            'zip'                => 'Zip',
            'phone'              => 'PhoneNumber',
        ];

        foreach ($inputTextFieldsMap as $inputId => $awKey) {
            $this->logger->info("Setting '$awKey' to input '{$creditCard[$awKey]}' ...");
            $this->waitForElement(\WebDriverBy::id($inputId), $this->waitTimeout)->sendKeys($creditCard[$awKey]);
            $this->logger->info('Done.');
        }
        $this->logger->info('Setting "Email" to input "billingEmail" ...');

        if ($elem = $this->waitForElement(\WebDriverBy::id('billingEmail'), $this->waitTimeout)) {
            $elem->clear();
            $elem->sendKeys($fields['Email']);
            $this->logger->info('Done.');
        } else {
            $this->logger->info('Could not find input field for Email.');

            return false;
        }
        $this->waitForElement(\WebDriverBy::id('termsAndConditions'), $this->waitTimeout)->click();

        $this->waitForElement(\WebDriverBy::xpath('//*[@class="bgt-order-information-form"]//*[@type="submit"]'), $this->waitTimeout)->click();

        if ($this->waitForElement(\WebDriverBy::xpath('(//div[@class="bgt-form-error" and contains(.,"Please complete all of the required fields")])[1]'), $this->loadTimeout)) {
            $this->logger->info('Error: Missing required fields.');

            return false;
        }
        $this->logger->info('Done.');

        // success
        $success = $this->waitForElement(\WebDriverBy::xpath('//*[text()[contains(normalize-space(.), "Thank you for your purchase") or contains(normalize-space(.),"We have received your purchase request")]]'), $this->waitTimeout);
        $this->saveResponse();

        if ($success) {
            $this->ErrorMessage = 'Thank you for your purchase!';

            return true;
        }

        return false;
    }

    public function getPurchaseMilesFields()
    {
        return [
            "Email" => [
                "Type"     => "string",
                "Required" => true,
            ],
            "FirstName" => [
                "Type"     => "string",
                "Required" => true,
            ],
            "LastName" => [
                "Type"     => "string",
                "Required" => true,
            ],
            "AccountNumber" => [
                "Type"     => "string",
                "Required" => true,
                "Caption"  => "IHG Rewards Club Number",
            ],
            "Password" => [
                "Type"     => "string",
                "Required" => true,
                "Caption"  => "PIN",
            ],
        ];
    }

    protected function fail($message = null)
    {
        if (isset($message)) {
            $this->http->Log($message, LOG_LEVEL_ERROR);
        }
        $this->http->SaveResponse();

        return false;
    }

    protected function checkLoginFormErrors()
    {
        if ($error = $this->waitForElement(\WebDriverBy::xpath('//div[@class="bgt-form-error" and normalize-space(.) != ""]'), $this->waitTimeout)) {
            throw new \UserInputError(CleanXMLValue($error->getText()));
        }
    }

    /*
     test creds:
    John Doe
    705265083
    3456
    diri@vps30.com
     */
}
