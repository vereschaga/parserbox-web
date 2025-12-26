<?php

namespace AwardWallet\Engine\goldpassport\Transfer;

use AwardWallet\Engine\ProxyList;

class Purchase extends \TAccountChecker
{
    use \SeleniumCheckerHelper;
    use ProxyList;

    public $timeout = 20;
    protected $fields;
    protected $rewardsQuantity;
    protected $creditCard;
    protected $loadTimeout = 50;
    protected $payTimeout = 60;

    public function InitBrowser()
    {
        $this->UseSelenium();
        $this->useChromium();

        if (ConfigValue(CONFIG_SITE_STATE) === SITE_STATE_PRODUCTION) {
            $this->http->setProxy($this->proxyDOP());
        } else {
            $this->http->setProxy($this->proxyPurchase());
            //			$this->allowRealIPAndCreditCardForLocalUsage = true;
        }
        $this->keepSession(false);
        $this->http->setDefaultHeader('User-Agent', \HttpBrowser::PROXY_USER_AGENT);
        $this->KeepState = false;
    }

    public function LoadLoginForm()
    {
        return true;
    }

    public function Login()
    {
        return true;
    }

    public function getPurchaseMilesFields()
    {
        return [
            'AccountNumber' => [
                'Type'     => 'string',
                'Required' => true,
                'Caption'  => 'Hyatt Gold Passport Number',
            ],
            'FirstName' => [
                'Type'     => 'string',
                'Required' => true,
            ],
            'LastName' => [
                'Type'     => 'string',
                'Required' => true,
            ],
            'Email' => [
                'Type'     => 'string',
                'Required' => true,
            ],
        ];
    }

    public function purchaseMiles(array $fields, $numberOfMiles, $creditCard)
    {
        $this->http->saveScreenshots = true;

        $this->fields = $fields;
        $this->rewardsQuantity = $numberOfMiles;
        $this->creditCard = $creditCard;

        $this->logger->debug('Fileds: ' . var_export($fields, true));
        $this->logger->info('Login to get started.');

        if (ConfigValue(CONFIG_SITE_STATE) === SITE_STATE_PRODUCTION) { // use affiliate link
            $this->http->GetURL('http://www.jdoqocy.com/click-8184014-11347183-1365619870000');
        } else {
            $this->http->GetURL('https://buy.points.com/PointsPartnerFrames/partners/hyatt/container.html?language=EN&product=BUY');
        }

        if (!$this->waitForElement(\WebDriverBy::xpath('//input[@id="memberId"]'), $this->loadTimeout, true)) {
            $this->logger->info('Step 0: Before allover, need choose points.');
            $this->saveResponse();

            if ($elem = $this->waitForElement(\WebDriverBy::id('bgt-offer-dropdown'), $this->loadTimeout)) {
                $this->driver->executeScript('jQuery(arguments[0]).trigger("focus")', [$elem]);
                $this->driver->executeScript('jQuery(arguments[0]).val("' . $this->rewardsQuantity . '")', [$elem]);
                $this->driver->executeScript('jQuery(arguments[0]).trigger("change")', [$elem]);
                $this->logger->info('Select ' . $this->rewardsQuantity . ' miles done.');
            }
        }
        //https://storefront.points.com/hyatt-gold-passport/en-US/buy
        $inputFieldsMap = [
            'AccountNumber' => '//input[@id="memberId"]',
            'FirstName'     => '//input[@id="firstName"]',
            'LastName'      => '//input[@id="lastName"]',
            'Email'         => '//input[@id="email"]',
        ];

        foreach ($inputFieldsMap as $awKey => $inputXpath) {
            $this->logger->info('Setting ' . $awKey . '...');

            if ($elem = $this->waitForElement(\WebDriverBy::xpath($inputXpath), $this->timeout, true)) {
                $elem->sendKeys($this->fields[$awKey]);
                $this->logger->info('Done.');
            } else {
                $this->http->Log('Could not find input field for ' . $awKey . '.', LOG_LEVEL_ERROR);

                return false;
            }
        }

        if ($elem = $this->waitForElement(\WebDriverBy::xpath('//div[@class="bgt-login-submit"]/button[@type="submit"]'), $this->timeout, true)) {
            $elem->click();
            $this->logger->info('Login form submitted.');
        } else {
            return false;
        }
        /*
                if( $error = $this->waitForElement(\WebDriverBy::xpath('(//div[@class="bgt-form-error" and contains(.,"Please check your login information and try again")])[1]'), $this->loadTimeout, true) ){
                    $this->http->Log('Error: Please check your login information and try again.', LOG_LEVEL_ERROR);
                    return false;
                }
        */
        sleep(3);
        $this->saveResponse();

        if ($error = $this->waitForElement(\WebDriverBy::xpath('(//div[@class="bgt-form-error" and normalize-space()!=""])[1]'), $this->loadTimeout, true)) {
            throw new \UserInputError($error->getText());
        }

        if ($error = $this->waitForElement(\WebDriverBy::xpath("//div[contains(@class,'bgt-offer-empty') and contains(.,'limit')]"), $this->timeout)) {
            throw new \UserInputError(preg_replace(['/([^.,:!?-])\n/', '/\n/', '/\s+/'], ['$1. ', ' ', ' '], $error->getText()));
        }
        $this->saveResponse();
        $this->logger->info('Step 1: How many points would you like to purchase.');

        if (!($elem = $this->waitForElement(\WebDriverBy::id('bgt-offer-dropdown'), $this->loadTimeout))) {
            return $this->fail('Select "bgt-offer-dropdown" not found.');
        }
        $this->driver->executeScript('jQuery(arguments[0]).trigger("focus")', [$elem]);
        $this->driver->executeScript('jQuery(arguments[0]).val("' . $this->rewardsQuantity . '")', [$elem]);
        $this->driver->executeScript('jQuery(arguments[0]).trigger("change")', [$elem]);
        $this->logger->info('Select ' . $this->rewardsQuantity . ' miles done.');

        $this->logger->info('Step 2: Credit Card and Billing Information.');
        $inputSelectFieldsMap = [
            'Type' => [
                'cardName',
                'string:transaction.payment.form.creditCard.' . $this->creditCard['Type'],
            ],
            'ExpirationMonth' => [
                'expirationMonth',
                'number:' . (int) $this->creditCard['ExpirationMonth'],
            ],
            'ExpirationYear' => [
                'expirationYear',
                'number:' . (strlen($this->creditCard['ExpirationYear']) == 2 ? '20' . $this->creditCard['ExpirationYear'] : $this->creditCard['ExpirationYear']),
            ],
            'Country' => [
                'country',
                'string:' . $this->creditCard['CountryCode'],
            ],
            'StateOrProvince' => [
                'state',
                'string:' . $this->creditCard['StateCode'],
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
            $this->logger->info("Setting '$awKey' to input '{$this->creditCard[$awKey]}' ...");
            $this->driver->findElement(\WebDriverBy::id($inputId))->sendKeys($this->creditCard[$awKey]);
            $this->logger->info('Done.');
        }
        $this->logger->info('Setting "Email" to input "billingEmail" ...');

        if ($elem = $this->waitForElement(\WebDriverBy::id('billingEmail'), $this->timeout, true)) {
            $elem->clear();
            $elem->sendKeys($this->fields['Email']);
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

        if ($success = $this->http->FindSingleNode('//text()[contains(., "We have received your purchase request")]')) {
            $this->ErrorMessage = $success;

            return true;
        }
        // old, just in case
        if ($success1) {
            $this->ErrorMessage = $success1->getText();

            return true;
        } elseif ($success2 = $this->waitForElement(\WebDriverBy::xpath('//*[contains(text(),"Thank you for your purchase!")]'), $this->timeout)) {
            $this->ErrorMessage = $success2->getText();

            return true;
        } elseif ($success3 = $this->waitForElement(\WebDriverBy::xpath('//*[contains(text(),"We have received your purchase request.")]'), $this->timeout)) {
            $this->ErrorMessage = $success3->getText();

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

    protected function fail($message = null)
    {
        if (isset($message)) {
            $this->logger->info($message);
        }
        $this->saveResponse();

        return false;
    }
}
