<?php

// case #10396

namespace AwardWallet\Engine\triprewards\Transfer;

class Purchase extends \TAccountCheckerTriprewards
{
    use \PointsDotComSeleniumHelper;

    protected $timeout = 5;

    protected $loadTimeout = 20;

    /**
     * @var Purchase
     */
    protected $seleniumChecker = null;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->http->setHttp2(true);
        $this->http->setDefaultHeader("User-Agent", "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_13_2) AppleWebKit/604.4.7 (KHTML, like Gecko) Version/11.0.2 Safari/604.4.7");
    }

    public function IsLoggedIn()
    {
        return false;
    }

    public function LoadLoginForm()
    {
        $numberOfMiles = intval($this->TransferFields['numberOfMiles']);

        if ($numberOfMiles <= 0 || $numberOfMiles > 80000 || $numberOfMiles % 1000 !== 0) {
            throw new \UserInputError("Number of purchased points should be less than 80,000 and divisible by 1000");
        }

        return parent::LoadLoginForm();
    }

    public function Login()
    {
        return parent::Login();
    }

    public function purchaseMilesOld(array $fields, $numberOfMiles, $creditCard)
    {
        $this->http->log('[INFO] ' . __METHOD__);

        $this->seleniumChecker = new PurchaseSelenium();
        $this->seleniumChecker->InitBrowser();
        $this->http->brotherBrowser($this->seleniumChecker->http);

        $allCookies = array_merge($this->http->GetCookies(".wyndhamrewards.com"), $this->http->GetCookies(".wyndhamrewards.com", "/", true));
        $this->http->log('[INFO] all cookies:');
        $this->http->log(print_r($allCookies, true));
        $this->seleniumChecker->setCookies($allCookies);

        $status = $this->seleniumChecker->purchaseMiles($fields, $numberOfMiles, $creditCard);
        $this->ErrorMessage = $this->seleniumChecker->ErrorMessage;

        return $status;
    }

    public function purchaseMiles(array $fields, $numberOfMiles, $creditCard)
    {
        $this->logger->notice(__METHOD__);

        $checker2 = clone $this;
        $this->http->brotherBrowser($checker2->http);

        try {
            $this->http->Log('Running Selenium...');
            $checker2->InitSeleniumBrowser();
            $checker2->useFirefox(\SeleniumFinderRequest::FIREFOX_59);
            $checker2->saveScreenshots = true;
            // $checker2->disableImages();
            $checker2->http->start();
            $checker2->Start();

            $this->seleniumChecker = $checker2;

            $this->http->GetURL('https://www.wyndhamhotels.com/WHGServices/loyalty/member/ssoPointsToken');
            $data = $this->http->JsonLog(null, true, true);
            $token = $data['links']['self']['href'] ?? null;

            if (!$token) {
                $this->logger->error('Buy token not found');

                return false;
            }
            $buyUrl = sprintf('https://storefront.points.com/wyndham-rewards/sso/mv-delegate/buy?token=%s', $token);
            $checker2->http->GetURL($buyUrl);

            //step 1
            if (!$checker2->waitForElement(\WebDriverBy::xpath('//form[@data-name="transactionForm"]//select[@id="bgt-offer-dropdown"]'), $this->loadTimeout)) {
                $this->checkLoginFormErrors();

                return $this->clear('Buy form not loaded');
            }

            if (!($elem = $checker2->waitForElement(\WebDriverBy::id('bgt-offer-dropdown'), $this->loadTimeout))) {
                return $this->clear('Select "bgt-offer-dropdown" not found.');
            }
            $optionXpath = sprintf('//select[@id = "bgt-offer-dropdown"]//option[@value = "%s"]', $numberOfMiles);

            if ($option = $checker2->waitForElement(\WebDriverBy::xpath($optionXpath), $this->timeout)) {
                $option->click();
                $this->logger->info('Select ' . $numberOfMiles . ' points done.');
            } else {
                return $this->clear('Didn\'t find option for ' . $numberOfMiles . ' points.');
            }

            if (!$checker2->waitForElement(\WebDriverBy::id('cardName'), $this->loadTimeout)) {
                $this->checkLoginFormErrors();

                return $this->clear('Credit Card form not loaded.');
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
                    (new \WebDriverSelect($checker2->waitForElement(\WebDriverBy::id($selectFieldId), $this->timeout)))->selectByValue($value);
                } catch (\NoSuchElementException $e) {
                    return $this->clear($e->getMessage());
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
                $checker2->waitForElement(\WebDriverBy::id($inputId), $this->timeout)->sendKeys($creditCard[$awKey]);
                $this->logger->info('Done.');
            }
            $this->logger->info('Setting "Email" to input "billingEmail" ...');

            if ($elem = $checker2->waitForElement(\WebDriverBy::id('billingEmail'), $this->timeout)) {
                $elem->clear();
                $elem->sendKeys($fields['Email']);
                $this->logger->info('Done.');
            } else {
                $this->logger->info('Could not find input field for Email.');

                return false;
            }
            $checker2->waitForElement(\WebDriverBy::id('termsAndConditions'), $this->timeout)->click();

            $checker2->waitForElement(\WebDriverBy::xpath('//*[@class="bgt-order-information-form"]//*[@type="submit"]'), $this->timeout)->click();

            if ($checker2->waitForElement(\WebDriverBy::xpath('(//div[@class="bgt-form-error" and contains(.,"Please complete all of the required fields")])[1]'), $this->loadTimeout)) {
                $this->logger->info('Error: Missing required fields.');

                return false;
            }
            $this->logger->info('Done.');

            // success
            $success = $checker2->waitForElement(\WebDriverBy::xpath('//*[text()[contains(normalize-space(.), "Thank you for your purchase") or contains(normalize-space(.),"We have received your purchase request")]]'), $this->loadTimeout);
            $checker2->saveResponse();
            $checker2->http->cleanup();

            if ($success) {
                $this->ErrorMessage = 'Thank you for your purchase!';

                return true;
            }

            return false;
        } catch (\InstanceThrottledException $e) {
            return $this->clear($e->getMessage());
        } catch (\WebDriverCurlException $e) {
            return $this->clear($e->getMessage());
        } catch (\UnknownServerException $e) {
            return $this->clear($e->getMessage());
        } catch (\Exception $e) {
            $this->http->Log($e->getMessage(), LOG_LEVEL_ERROR);

            if (isset($this->seleniumChecker)) {
                $this->seleniumChecker->http->cleanup();
            }

            return false;
        }
    }

    public function getPurchaseMilesFields()
    {
        return [
            "Login" => [
                "Type"     => "string",
                "Required" => true,
                "Caption"  => "Wyndham Username",
            ],
            "Password" => [
                "Type"     => "string",
                "Required" => true,
                "Caption"  => "Password",
            ],
            "Email" => [
                "Type"     => "string",
                "Required" => true,
                "Caption"  => "Email",
            ],
        ];
    }

    protected function clear($message = null)
    {
        if (isset($message)) {
            $this->http->Log($message, LOG_LEVEL_ERROR);
        }
        $this->seleniumChecker->saveResponse();

        if (isset($this->seleniumChecker)) {
            $this->seleniumChecker->http->cleanup();
        }

        return false;
    }

    protected function checkLoginFormErrors()
    {
        if ($error = $this->seleniumChecker->waitForElement(\WebDriverBy::xpath('//div[@class="bgt-form-error" and normalize-space(.) != ""]'), $this->timeout)) {
            throw new \UserInputError(CleanXMLValue($error->getText()));
        }
    }
}

class PurchaseSelenium extends \TAccountChecker
{
    use \PointsDotComSeleniumHelper;

    protected $timeout = 5;

    protected $LOGIN_URL = 'https://www.wyndhamrewards.com/trec/consumer/home.action?variant=';
    protected $PURCHASE_URL = 'https://www.wyndhamrewards.com/trec/consumer/pointsActivity.action?variant=';
    protected $ACCOUNT_URL = 'https://www.wyndhamrewards.com/trec/consumer/myaccount.action?ft=true&variant=';

    protected $ccTypes = [
        'amex' => 'American Express',
        'visa' => 'VISA Credit',
    ];

    protected static $fieldMapLogin = [
        'Login'    => 'username',
        'Password' => 'password',
    ];

    public function Login()
    {
        return true;
    }

    public function LoadLoginForm()
    {
        return true;
    }

    public function InitBrowser()
    {
        $this->UseSelenium();
        $this->useChromium();
        $this->http->saveScreenshots = true;

        if (ConfigValue(CONFIG_SITE_STATE) === SITE_STATE_DEBUG) {
            $this->keepSession(false); //no need true
            $this->http->setProxy('localhost:8000');
        } elseif (ConfigValue(CONFIG_SITE_STATE) === SITE_STATE_PRODUCTION) {
            $this->usePacFile();
            $this->keepSession(false);
        }
        $this->http->driver->start($this->http->GetProxy());
        $this->Start();
    }

    public function setCookies($cookies)
    {
        $this->http->log('[INFO] ' . __METHOD__);
        $this->http->getUrl($this->LOGIN_URL);

        foreach ($cookies as $key => $value) {
            $this->driver->manage()->addCookie(['name' => $key, 'value' => $value, 'domain' => ".wyndhamrewards.com"]);
        }
        $this->http->getUrl($this->ACCOUNT_URL);
    }

    public function purchaseMiles(array $fields, $numberOfMiles, $creditCard)
    {
        $this->http->log('[INFO] ' . __METHOD__);

        $numberOfMiles = intval($numberOfMiles);

        if ($numberOfMiles <= 0 || $numberOfMiles > 80000 || $numberOfMiles % 1000 !== 0) {
            throw new \UserInputError("Number of purchased points should be less than 80,000 and divisible by 1000");
        }

        $this->http->getUrl($this->PURCHASE_URL);
        $buyLink = $this->waitForElement(\WebDriverBy::xpath('//a[@title = "Buy"]'), $this->timeout, true);

        if (!$buyLink) {
            return $this->fail('buy link not found');
        }
        $buyLink->click();
        $this->driver->switchTo()->window(end($this->driver->getWindowHandles()));

        if (!($frame = $this->waitForElement(\WebDriverBy::xpath('//frame[@id="pageFrame"]'), $this->loadTimeout))) {
            return $this->fail('frame not found');
        }
        $this->driver->switchTo()->frame($frame);

        // account info
        if (!$this->waitForElement(\WebDriverBy::id('mv_first_name'), $this->loadTimeout)) {
            return $this->fail('no form for membership info');
        }

        foreach ([
            // 'FirstName' => 'mv_first_name',
            // 'LastName' => 'mv_last_name',
            // 'AccountNumber' => 'mv_member_id',
            'Email' => 'mv_email_address',
        ] as $field => $name) {
            $text = $this->waitForElement(\WebDriverBy::xpath(sprintf('//div[@id="%s"]/input[@type="text"]', $name)), $this->timeout);

            if (!$text) {
                return $this->fail('no input ' . $name);
            }
            $text->clear();
            $text->sendKeys($fields[$field]);
        }
        $arrow = $this->waitForElement(\WebDriverBy::xpath('//div[@id="mv_points"]/img'), $this->timeout);

        if (!$arrow) {
            return $this->fail();
        }
        $arrow->click();
        $item = $this->waitForElement(\WebDriverBy::xpath(sprintf('//div[@id="mv_points_list"]/div[text() = "%s"]', number_format($numberOfMiles))), $this->timeout);

        if (!$item) {
            return $this->fail('no item with ' . $numberOfMiles . ' miles');
        }
        $item->click();
        $this->saveResponse();

        if (!($button = $this->waitForElement(\WebDriverBy::id('mv_submit'), $this->timeout))) {
            return $this->fail();
        }
        $button->click();

        // cc info
        if (!$this->waitForElement(\WebDriverBy::id('pay_card_type'), $this->loadTimeout)) {
            // error check from account info
            $li = $this->waitForElement(\WebDriverBy::xpath('//li[@class="error"]'), 1);

            if ($li) {
                throw new \UserInputError($li->getText());
            } else {
                return $this->fail('no form for cc info');
            }
        }

        $creditCard['PhoneNumber'] = preg_replace('/\D/', '', $creditCard['PhoneNumber']);

        foreach ([
            'CardNumber'     => 'pay_card_number',
            'SecurityNumber' => 'pay_card_cvv',
            'Name'           => 'pay_card_name',
            'AddressLine'    => 'pay_billing_address1',
            'City'           => 'pay_billing_city',
            'Zip'            => 'pay_billing_code',
            'PhoneNumber'    => 'pay_billing_phone',
        ] as $field => $name) {
            $text = $this->waitForElement(\WebDriverBy::xpath(sprintf('//div[@id="%s"]/input[@type="text" or @type="password"]', $name)), $this->timeout);

            if (!$text) {
                return $this->fail('no input ' . $name);
            }
            $text->sendKeys($creditCard[$field]);
            $this->http->Log(sprintf('sent %d keys to input %s', strlen($creditCard[$field]), $name));
        }

        if (!isset($this->ccTypes[$creditCard['Type']])) {
            return $this->fail('unknown cc: ' . $creditCard['Type']);
        }
        $cc = ['CCType' => $this->ccTypes[$creditCard['Type']]];
        $cc['ExpMonth'] = sprintf('%02s', $creditCard['ExpirationMonth']);
        $cc['ExpYear'] = substr($creditCard['ExpirationYear'], -2);

        if ($creditCard['CountryCode'] !== 'US') {
            return $this->fail('unsure about country ' . $cc['CountryCode']);
        }
        $cc['CountryName'] = 'United States of America';
        $cc['StateName'] = $creditCard['State'];

        foreach ([
            'CCType' => 'pay_card_type',
            'ExpMonth' => 'pay_expiry_month',
            'ExpYear' => 'pay_expiry_year',
            'CountryName' => 'pay_billing_country',
            'StateName' => 'pay_billing_region',
        ] as $field => $name) {
            $arrow = $this->waitForElement(\WebDriverBy::xpath(sprintf('//div[@id="%s"]/img', $name)), $this->timeout);

            if (!$arrow) {
                return $this->fail('failed ' . $name);
            }
            $arrow->click();
            $item = $this->waitForElement(\WebDriverBy::xpath(sprintf('//div[@id="%s_list"]/div[text() = "%s"]', $name, $cc[$field])), $this->timeout);

            if (!$item) {
                return $this->fail('no item for ' . $name);
            }
            $item->click();
        }
        $this->saveResponse();

        if (!($button = $this->waitForElement(\WebDriverBy::id('pay_submit'), $this->timeout))) {
            return $this->fail();
        }
        $button->click();

        // review and confirm
        $check = $this->waitForElement(\WebDriverBy::xpath('//div[@id="review_accept"]/input[@type="checkbox"]'), $this->loadTimeout);

        if (!$check) {
            return $this->fail('no confirm form');
        }
        $check->click();
        $button = $this->waitForElement(\WebDriverBy::id('review_submit'), $this->timeout);

        if (!$button) {
            return $this->fail();
        }
        $this->saveResponse();
        $button->click();

        // success
        $this->waitForElement(\WebDriverBy::id('BuyMVProdPay_RECEIPT_PAGE'), $this->loadTimeout);
        $this->saveFrameContent();

        if ($this->http->findPreg('/We have received your purchase request/i')) {
            $this->ErrorMessage = 'We have received your purchase request. We will notify you by email when the transaction completes.';

            return true;
        }

        $error = $this->http->findPreg('/We are unable to process your credit card transaction|We are sorry but your credit card has been declined/i');

        if ($error) {
            throw new \UserInputError($error);
        }

        return false;
    }

    protected function fail($message = null)
    {
        if (isset($message)) {
            $this->logger->info($message);
        }
        $this->saveResponse();

        return false;
    }
}
