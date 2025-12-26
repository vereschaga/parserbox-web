<?php

// case #10180

namespace AwardWallet\Engine\hhonors\Transfer;

use AwardWallet\Engine\ProxyList;

class Purchase extends \TAccountChecker
{
    use \PointsDotComSeleniumHelper;
    use ProxyList;
    public $payTimeout = 60;

    protected $timeout = 5;

    protected $ccTypes = [
        'amex' => 'American Express',
        'visa' => 'VISA Credit',
    ];

    public function InitBrowser()
    {
        $this->useSelenium();

        $resolutions = [
            [1152, 864],
            [1280, 720],
            [1280, 768],
            [1280, 800],
            [1360, 768],
            //            [1366, 768],
            [1920, 1080],
        ];
        $resolution = $resolutions[array_rand($resolutions)];
        $this->DebugInfo = "Resolution: " . implode("x", $resolution);
        $this->setScreenResolution($resolution);
        $this->disableImages();

        $this->useChromium();
        $this->http->saveScreenshots = true;

        if (ConfigValue(CONFIG_SITE_STATE) === SITE_STATE_DEBUG) {
            $this->http->SetProxy($this->proxyPurchase());
        } elseif (ConfigValue(CONFIG_SITE_STATE) === SITE_STATE_PRODUCTION) {
            // $this->usePacFile();
            $this->setProxyBrightData();
            $this->useCache();
        }
        $this->keepSession(false);
    }

    public function LoadLoginForm()
    {
        $this->logger->notice(__METHOD__);
        //$this->logger->info(var_export($this->AccountFields,true));

        $rewardsQuantity = intval($this->TransferFields['numberOfMiles']);

        if ($rewardsQuantity > 80000 or $rewardsQuantity < 1000 or $rewardsQuantity % 1000 !== 0) {
            throw new \UserInputError('RewardsQuantity invalid value');
        }

        //-->only test. check IP
//        $this->logger->info('Getting IP address');
//        $this->http->GetURL('https://ipinfo.io/ip');
//        $ip = $this->http->Response['body'];
//        if (!$ip)
//            throw new \EngineError('Could not get IP address');
//        if (!preg_match('#^\s*(<pre[^>]*>)?\s*(?<ip>\d+\.\d+\.\d+\.\d+)\s*(<\/pre>)?\s*$#i', $ip, $m)) // selenium returns body '<pre>1.1.1.1</pre>
//            throw new \EngineError('Invalid IP address in response "'.$this->http->Response['body'].'"');
//        $ip = $m['ip'];
//        $this->logger->info("IP address: $ip");
        //<---

        //		$this->http->GetURL('https://secure3.hilton.com/en/hi/customer/login/index.htm');
        $this->http->GetURL("https://secure3.hilton.com/en/hh/customer/login/index.htm");

        $mover = new \MouseMover($this->driver);
        $mover->logger = $this->logger;

        $loginInput = $this->waitForElement(\WebDriverBy::id('username'), 3);
        $passwordInput = $this->waitForElement(\WebDriverBy::id('password'), 0);
        $btn = $this->waitForElement(\WebDriverBy::xpath('//a[@class = "linkBtn"]'), 0);

        // captcha
        $iframe = $this->waitForElement(\WebDriverBy::xpath("//div[@id = 'divcaptcha']//iframe"), 10, false);

        if ($iframe) {
            if ($this->http->FindPreg("/recaptcha/i")) {
                $this->logger->notice(">>> recognize captcha");
                $key = $this->http->FindSingleNode("//div[@id = 'divcaptcha' and @data-sitekey]/@data-sitekey");
                $http2 = clone $this->http;
                $captcha = $this->parseCaptchaNew($http2, $key);

                if ($captcha === false) {
                    return false;
                } elseif (empty($captcha)) {
                    $this->logger->error("LoadLoginForm");
                    sleep(2);

                    throw new \CheckRetryNeededException(3, 1, self::CAPTCHA_ERROR_MSG);
                }
                $this->driver->executeScript("document.getElementById('g-recaptcha-response').removeAttribute('style');");
                $this->driver->executeScript("document.getElementById('g-recaptcha-response').value='" . $captcha . "';");
            } else {
                $this->logger->notice(">>> captcha not found !!!");
            }
        }

        // save page to logs
        $this->http->SetBody($this->driver->executeScript('return document.documentElement.innerHTML'));
        $this->http->SaveResponse();

        if (!$loginInput || !$passwordInput || !$btn) {
            $this->logger->error("something went wrong");

            return false;
        }

        $mover->duration = rand(90000, 120000);
        $mover->steps = rand(50, 70);
        $mover->moveToElement($loginInput);
        $mover->click();

        $loginInput->clear();
        $loginInput->sendKeys($this->AccountFields['Login']);

        $mover->moveToElement($passwordInput);
        $mover->click();

        $passwordInput->clear();
        $passwordInput->sendKeys($this->AccountFields['Pass']);
        $this->driver->executeScript('setTimeout(function(){
                delete document.$cdc_asdjflasutopfhvcZLmcfl_;
                delete document.$cdc_asdjflasutopfhvcZLawlt_;
            }, 500)');

        $this->saveResponse();

        $btn->click();

        //		//login
        //		if ($elem = $this->waitForElement(\WebDriverBy::id('username'), $this->timeout, false)) {
        //			$elem->sendKeys($this->AccountFields['Login']);
        //		} else {
        //			$this->logger->error('Could not find login form fields');
        //			return false;
        //		}
        //		//pass
        //		$this->driver->findElement(\WebDriverBy::id('password'))->sendKeys($this->AccountFields['Pass']);
//

        sleep(5);

        return true;
    }

    public function Login()
    {
        $this->logger->notice(__METHOD__);

        //		$this->waitForElement(\WebDriverBy::xpath('//a[contains(@onclick,\'submitForm\')]'), $this->timeout)->click();

        $this->saveResponse();

        if ($elem = $this->waitForElement(\WebDriverBy::id('invalidCredentials'), $this->timeout, false)) {
            throw new \UserInputError($elem->getText());
        }

        if ($elem = $this->waitForElement(\WebDriverBy::xpath('//p[contains(text(),\'Please use the reCAPTCHA\')]'), $this->timeout, true)) {
            $this->ErrorMessage = $elem->getText();

            return false;
        }

        if ($elem = $this->waitForElement(\WebDriverBy::xpath('//p[contains(text(),\'We now require you to sign\')]'), $this->timeout, true)) {
            throw new \UserInputError($elem->getText());
        }

        $this->logger->info('Done.');

        return true;
    }

    public function getPurchaseMilesFields()
    {
        return [
            //"Email" => [
            //	"Type" => "string",
            //	"Required" => true,
            //	"Caption" => "Email",
            //],
            'Login' => [
                'Type'     => 'string',
                'Required' => true,
            ],
            'Password' => [
                'Type'     => 'string',
                'Required' => true,
            ],
        ];
    }

    public function oldpurchaseMiles(array $fields, $numberOfMiles, $creditCard)
    {
        $numberOfMiles = intval($numberOfMiles);

        if ($numberOfMiles <= 0 || $numberOfMiles > 80000 || $numberOfMiles % 1000 !== 0) {
            throw new \UserInputError("Number of purchased points should be less than 80,000 and divisible by 1000");
        }

        /*        $this->http->log('[DEBUG] fields:');
                $this->http->log(json_encode([
                	'fields' => $fields,
                	'numberOfMiles' => $numberOfMiles,
                ], JSON_PRETTY_PRINT));
        */
        $this->http->GetURL("https://buy.points.com/PointsPartnerFrames/partners/hilton/container.html?language=en&product=BUY");

        if (!($frame = $this->waitForElement(\WebDriverBy::xpath('//frame[@id="pageFrame"]'), $this->loadTimeout))) {
            return $this->fail('frame not found');
        }
        $this->driver->switchTo()->frame($frame);

        // account info
        if (!$this->waitForElement(\WebDriverBy::id('mv_first_name'), $this->loadTimeout)) {
            return $this->fail('no form for membership info');
        }

        foreach ([
            'FirstName' => 'mv_first_name',
            'LastName' => 'mv_last_name',
            'AccountNumber' => 'mv_member_id',
            'Email' => 'mv_email_address',
        ] as $field => $name) {
            $text = $this->waitForElement(\WebDriverBy::xpath(sprintf('//div[@id="%s"]/input[@type="text"]', $name)), $this->timeout);

            if (!$text) {
                return $this->fail('no input ' . $name);
            }
            $text->sendKeys($fields[$field]);
        }
        $arrow = $this->waitForElement(\WebDriverBy::xpath('//div[@id="mv_points"]/img'), $this->timeout);

        if (!$arrow) {
            return $this->fail();
        }
        $arrow->click();
        $milesDot = number_format($numberOfMiles);
        $item = $this->findPointsItem($milesDot);
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
        $cc['ExpMonth'] = \DateTime::createFromFormat('!m', intval($creditCard['ExpirationMonth']))->format('F');
        $cc['ExpYear'] = $creditCard['ExpirationYear'];

        if (strlen($cc['ExpYear']) === 2) {
            $cc['ExpYear'] = '20' . $cc['ExpYear'];
        }

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
        $success = $this->waitForElement(\WebDriverBy::xpath('//*[contains(text(), "We have received your purchase request")]'), $this->loadTimeout);
        $this->saveFrameContent();

        if ($success) {
            $this->ErrorMessage = 'We have received your purchase request. We will notify you by email when the transaction completes.';

            return true;
        }

        $ccError = $this->http->findPreg('/We are unable to process your credit card transaction|We are sorry but your credit card has been declined|Sorry, your credit card information could not be confirmed/i');

        if ($ccError) {
            $this->http->Log('cc error');

            return false;
            //throw new \UserInputError($ccError);
        }

        $siteError = $this->http->findPreg('/We were unable to process your payment due to an unexpected error. Please try again/i');

        if ($siteError) {
            throw new \ProviderError($siteError);
        }

        return false;
    }

    public function purchaseMiles(array $fields, $numberOfMiles, $creditCard)
    {
        $this->http->GetURL('https://secure3.hilton.com/en/hh/customer/account/purchase.htm');

        if (!$iframe = $this->waitForElement(\WebDriverBy::xpath("//iframe[@id='PointsDotComFrame']"), $this->timeout, false)) {
            $this->ErrorMessage = 'Purchase form loading failed';

            return false;
        }
        $this->driver->switchTo()->frame($iframe);
        $rewardsQuantity = intval($numberOfMiles);

        $this->logger->info('STEP 1 HOW MANY POINTS DO YOU WANT TO BUY?');

        if (!($elem = $this->waitForElement(\WebDriverBy::id('bgt-offer-dropdown'), $this->timeout))) {
            if ($elem = $this->waitForElement(\WebDriverBy::xpath("//*[contains(normalize-space(text()),\"Sorry, you\") and contains(normalize-space(text()),\"ve reached your purchase limit\")]"), $this->timeout, false)) {
                throw new \UserInputError("Sorry, you've reached your purchase limit");
            } else {
                return $this->fail('Select "bgt-offer-dropdown" not found.');
            }
        }

        $this->driver->executeScript('jQuery(arguments[0]).trigger("focus")', [$elem]);
        $this->driver->executeScript('jQuery(arguments[0]).val("' . $rewardsQuantity . '")', [$elem]);
        $this->driver->executeScript('jQuery(arguments[0]).trigger("change")', [$elem]);
        $this->logger->info('Select ' . $rewardsQuantity . ' points done.');

        $this->logger->info('STEP 2 PAYMENT AND CONFIRMATION');
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
                'number:' . (strlen($creditCard['ExpirationYear']) == 2 ? '20' . $creditCard['ExpirationYear'] : $creditCard['ExpirationYear']),
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
                (new \WebDriverSelect($this->waitForElement(\WebDriverBy::id($selectFieldId), $this->timeout)))->selectByValue($value);
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
            $this->driver->findElement(\WebDriverBy::id($inputId))->sendKeys($creditCard[$awKey]);
            $this->logger->info('Done.');
        }

        /*
                $this->logger->info('Setting "Email" to input "billingEmail" ...');
                if( $elem = $this->waitForElement(\WebDriverBy::id('billingEmail'), $this->timeout, true) ){
                    $this->driver->executeScript("document.querySelector(\"#billingEmail\").value = \"".$fields['Email']."\"");
                    //$elem->clear();
                    //$elem->sendKeys($fields['Email']);
                    $this->logger->info('Done.');
                }else{
                    $this->logger->info('Could not find input field for Email.');
                    return false;
                }
        */

        $this->waitForElement(\WebDriverBy::id('termsAndConditions'), $this->timeout)->click();

        $this->logger->info('Sending My Order...');

        $this->waitForElement(\WebDriverBy::xpath('//*[@class="bgt-order-information-form"]//*[@type="submit"]'), $this->timeout)->click();

        if ($error = $this->waitForElement(\WebDriverBy::xpath('(//div[@class="bgt-form-error" and contains(.,"Please complete all of the required fields")])[1]'), $this->timeout, true)) {
            $this->logger->info('Error: Missing required fields.');

            return false;
        }

        $this->logger->info('Done.');

        // success
        $success1 = $this->waitForElement(\WebDriverBy::xpath('//*[contains(text(),"Thank you") and contains(text(),"We have received your purchase request")]'), $this->payTimeout);
        $this->saveResponse();

        if ($success1) {
            $this->ErrorMessage = $success1->getText();

            return true;
        } elseif ($success2 = $this->waitForElement(\WebDriverBy::xpath('//*[contains(text(),"Thank you for your purchase!")]'), $this->timeout)) {
            $this->ErrorMessage = $success2->getText();

            return true;
        } elseif ($success2 = $this->waitForElement(\WebDriverBy::xpath('//*[contains(text(),"Thank you for your purchase!")]'), $this->timeout, false)) {
            $this->ErrorMessage = $success2->getText();

            return true;
        }

        // fail
        if ($error = $this->waitForElement(\WebDriverBy::xpath("//div[contains(@class,'bgt-payment-errors')]/div[contains(@class,'bgt-form-error')]"), $this->timeout)) {
            throw new \UserInputError($error->getText());
        }

        if ($error = $this->waitForElement(\WebDriverBy::xpath("//div[contains(@class,'bgt-form-errors') and @aria-hidden='false']//div[contains(@class,'bgt-form-error')][1]"), $this->timeout)) {
            throw new \UserInputError($error->getText());
        }

        return false;
    }

    protected function parseCaptchaNew($http2, $key = null)
    {
        $this->logger->notice(__METHOD__);

        if (!$key) {
            return false;
        }
        $recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $recognizer->RecognizeTimeout = 240;

        $parameters = [
            "pageurl" => $http2->currentUrl(),
            "proxy"   => $http2->GetProxy(),
        ];
        $captcha = $this->recognizeByRuCaptcha($recognizer, $key, $parameters);

        return $captcha;
    }

    protected function fail($message = null)
    {
        if (isset($message)) {
            $this->logger->info($message);
        }
        $this->saveResponse();

        return false;
    }

    protected function findPointsItem($text)
    {
        $items = $this->driver->findElements(\WebDriverBy::xpath('//div[@id="mv_points_list"]/div'));
        $itemsText = array_map(function ($x) { return $x->getText(); }, $items);

        foreach ($items as $item) {
            $re = sprintf('/^\s*%s\s*$|^\s*%s\s*[+]/i', $text, $text);

            if (preg_match($re, $item->getText())) {
                return $item;
            }
        }

        throw new \EngineError('Could not find proper points item');
    }
}
