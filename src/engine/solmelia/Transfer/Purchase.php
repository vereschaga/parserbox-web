<?php

namespace AwardWallet\Engine\solmelia\Transfer;

use AwardWallet\Engine\ProxyList;

class Purchase extends \TAccountChecker
{
    use \SeleniumCheckerHelper;
    use ProxyList;

    public $timeout = 10;
    public $payTimeout = 60;

    public function InitBrowser()
    {
        $this->UseSelenium();
        $this->keepCookies(false);
        $this->keepSession(false);
        $this->AccountFields['BrowserState'] = null;

        if (ConfigValue(CONFIG_SITE_STATE) == SITE_STATE_PRODUCTION) {
            $this->http->SetProxy($this->proxyDOP());
        } else {
            $this->http->SetProxy($this->proxyPurchase());
        }
    }

    public function LoadLoginForm()
    {
        $this->logger->info(var_export($this->AccountFields, true));

        $rewardsQuantity = intval($this->TransferFields['numberOfMiles']);

        if ($rewardsQuantity > 125000 or $rewardsQuantity < 1000 or $rewardsQuantity % 1000 !== 0) {
            throw new \UserInputError('RewardsQuantity invalid value');
        }

        $this->logger->info('Logging in');
        $this->http->GetURL('https://www.melia.com/en/meliarewards/elprograma/home.htm');

        $this->waitForElement(\WebDriverBy::id('navlogin'), $this->timeout)->click();

        if ($elem = $this->waitForElement(\WebDriverBy::id('login-email'), $this->timeout, false)) {
            $elem->sendKeys($this->AccountFields['Login']);
        } else {
            $this->logger->error('Could not find login form fields');

            return false;
        }
        //pass
        $this->driver->findElement(\WebDriverBy::id('login-password'))->sendKeys($this->AccountFields['Pass']);
        // captcha
        $iframe = $this->waitForElement(\WebDriverBy::xpath("//div[@id = 'g-recaptchaLogin']//iframe"), 10, false);

        if ($iframe) {
            $captcha = $this->parseCaptchaNew($this);

            if ($captcha === false) {
                return false;
            }
            $this->logger->notice("Remove iframe");
            $this->driver->executeScript("$('div#g-recaptchaLogin iframe').remove();");
            $this->driver->executeScript("$('#g-recaptcha-response').val(\"" . $captcha . "\");");
            $this->driver->executeScript("$('#hiddenRecaptchaLogin').val(\"" . $captcha . "\");");
        }
        sleep(5);

        return true;
    }

    public function Login()
    {
        $this->logger->info('Login...');

        $this->waitForElement(\WebDriverBy::xpath('//*[@id="login-btn" and @type="submit"]'), $this->timeout)->click();

        if ($elem = $this->waitForElement(\WebDriverBy::id('caja_err'), $this->timeout, false)) {
            throw new \UserInputError($elem->getText());
        }

        if ($elem = $this->waitForElement(\WebDriverBy::xpath('//div[@class=\'popover-content\']'), $this->timeout, true)) {
            throw new \UserInputError($elem->getText());
        }

        $this->logger->info('Done.');

        return true;
    }

    public function purchaseMiles(array $fields, $numberOfMiles, $creditCard)
    {
        $this->http->GetURL('https://www.melia.com/en/mymeliarewards/mispuntos/compraregala/home.htm');

        if (!$iframe = $this->waitForElement(\WebDriverBy::xpath("//div[@id='tab1']//child::iframe"), $this->timeout, false)) {
            //throw new \Exception('Purchase form loading failed');
            return false;
        }

        $this->driver->switchTo()->frame($iframe);
        $rewardsQuantity = intval($numberOfMiles);

        $this->logger->info('STEP 1 HOW MANY POINTS DO YOU WANT TO BUY?');

        if (!($elem = $this->waitForElement(\WebDriverBy::id('bgt-offer-dropdown'), $this->timeout))) {
            return $this->fail('Select "bgt-offer-dropdown" not found.');
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
        $this->logger->info('Setting "Email" to input "billingEmail" ...');

        if ($elem = $this->waitForElement(\WebDriverBy::id('billingEmail'), $this->timeout, true)) {
            $elem->clear();
            $elem->sendKeys($fields['Email']);
            $this->logger->info('Done.');
        } else {
            $this->logger->info('Could not find input field for Email.');

            return false;
        }
        $this->waitForElement(\WebDriverBy::id('termsAndConditions'), $this->timeout)->click();

        $this->logger->info('Sending My Order...');

        $this->waitForElement(\WebDriverBy::xpath('//*[@class="bgt-order-information-form"]//*[@type="submit"]'), $this->timeout)->click();

        if ($error = $this->waitForElement(\WebDriverBy::xpath('(//div[@class="bgt-form-error" and contains(.,"Please complete all of the required fields")])[1]'), $this->timeout, true)) {
            $this->logger->info('Error: Missing required fields.');

            return false;
        }

        $this->logger->info('Done.');

        // success
        $success1 = $this->waitForElement(\WebDriverBy::xpath('//*[contains(text(),"Thank you for your purchase") and contains(text(),"Your order has been received")]'), $this->payTimeout);
        $this->saveResponse();

        if ($success1) {
            $this->ErrorMessage = $success1->getText();

            return true;
        } elseif ($success2 = $this->waitForElement(\WebDriverBy::xpath('//*[contains(text(),"Thank you for your purchase!")]'), $this->timeout)) {
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

    public function getPurchaseMilesFields()
    {
        return [
            'Email' => [
                'Type'     => 'string',
                'Required' => true,
            ],
            'FirstName' => [
                'Type'     => 'string',
                'Required' => true,
            ],

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

    protected function parseCaptchaNew($http2)
    {
        $http2->http->Log(__METHOD__);
        $key = $http2->http->FindPreg('/var key = "(.+?)";/');

        if (!$key) {
            return false;
        }
        $recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $recognizer->RecognizeTimeout = 120;

        $parameters = [
            "pageurl" => $http2->http->currentUrl(),
            "proxy"   => $http2->http->GetProxy(),
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
}
