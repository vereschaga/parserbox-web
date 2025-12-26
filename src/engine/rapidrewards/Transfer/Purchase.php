<?php

namespace AwardWallet\Engine\rapidrewards\Transfer;

use AwardWallet\Engine\ProxyList;

class Purchase extends \TAccountCheckerRapidrewards
{
    use \SeleniumCheckerHelper;
    use ProxyList;

    protected $loadTimeout = 20;

    protected $waitTimeout = 2;

    protected $frame;

    public function InitBrowser()
    {
        $this->UseSelenium();

        if (ConfigValue(CONFIG_SITE_STATE) === SITE_STATE_PRODUCTION) {
            $this->http->SetProxy($this->proxyDOP());
        } else {
            //			$this->http->SetProxy($this->proxyPurchase());
            $this->http->SetProxy('localhost:8000');
        }
        $this->useChromium();
        $this->disableImages();
        $this->AccountFields['BrowserState'] = '';
    }

    public function LoadLoginForm()
    {
        $number = intval($this->TransferFields['numberOfMiles']);

        if ($number < 2000 || $number > 60000 || $number % 500 !== 0) {
            throw new \UserInputError('Number of miles should be between 2,000 and 60,000 and divisible by 500');
        }

        if (ConfigValue(CONFIG_SITE_STATE) === SITE_STATE_PRODUCTION) { // use affiliate link
            $this->http->GetURL('http://www.tkqlhce.com/click-8184014-11429225-1373902798000');
        }
        $this->http->GetURL('https://www.southwest.com/flight/login?returnUrl=%2Faccount%2Frapidrewards%2Fpoints%2Fbuy-points');
        $this->waitForElement(\WebDriverBy::id('accountCredential'), $this->loadTimeout)->sendKeys($this->AccountFields['Login']);
        $this->waitForElement(\WebDriverBy::id('accountPassword'), $this->waitTimeout)->sendKeys($this->AccountFields['Pass']);

        return true;
    }

    public function Login()
    {
        $this->waitForElement(\WebDriverBy::id('submit'), $this->waitTimeout)->click();
        $this->frame = $this->waitForElement(\WebDriverBy::id('points-frame'), $this->loadTimeout);

        if (!$this->frame) {
            $error = $this->waitForElement(\WebDriverBy::xpath('//ul[@id=\'errors\']/li[1]'), $this->waitTimeout);

            if ($error) {
                $text = preg_replace('/\s*\(SW.+/ims', '', CleanXMLValue($error->getText()));

                throw new \UserInputError($text);
            }

            return false;
        }

        return true;
    }

    public function purchaseMiles(array $fields, $numberOfMiles, $creditCard)
    {
        $this->logger->notice(__METHOD__);

        $this->http->GetURL('https://www.southwest.com/account/rapidrewards/points/buy-points');
        $this->frame = $this->waitForElement(\WebDriverBy::id('points-frame'), $this->loadTimeout);
        $this->driver->switchTo()->frame($this->frame);
        $select = $this->waitForElement(\WebDriverBy::id('bgt-offer-dropdown'), $this->loadTimeout);

        if (!$select) {
            $this->logger->info("no id('bgt-offer-dropdown')");

            return $this->fail();
        }

        try {
            $this->logger->info("try select miles");
            (new \WebDriverSelect($select))->selectByValue(intval($numberOfMiles));
        } catch (\NoSuchElementException $e) {
            return $this->fail($e->getMessage());
        }

        if (!$this->waitForElement(\WebDriverBy::id('cardName'), $this->waitTimeout)) {
            return $this->fail('cc form not loaded?');
        }
        $cc = $creditCard;
        $cc['Type'] = ['amex' => 'string:transaction.payment.form.creditCard.amex', 'visa' => 'string:transaction.payment.form.creditCard.visa'][$cc['Type']];
        $cc['ExpirationMonth'] = sprintf('number:%d', intval($cc['ExpirationMonth']));

        if (strlen($cc['ExpirationYear']) < 4) {
            $cc['ExpirationYear'] = '20' . $cc['ExpirationYear'];
        }
        $cc['ExpirationYear'] = sprintf('number:%d', intval($cc['ExpirationYear']));
        [$cc['FirstName'], $cc['LastName']] = explode(' ', $cc['Name']);
        $cc['CountryCode'] = sprintf('string:%s', strtoupper($cc['CountryCode']));
        $cc['StateCode'] = sprintf('string:%s', strtoupper($cc['StateCode']));
        $cc['Email'] = $fields['Email'];

        foreach ([
            'cardName' => 'Type',
            'cardNumber' => 'CardNumber',
            'securityCode' => 'SecurityNumber',
            'expirationMonth' => 'ExpirationMonth',
            'expirationYear' => 'ExpirationYear',
            //					'creditCardFirstName' => 'FirstName',
            //					'creditCardLastName' => 'LastName',
            'creditCardFullName' => 'Name',
            'street1' => 'AddressLine',
            'city' => 'City',
            'country' => 'CountryCode',
            'state' => 'StateCode',
            'zip' => 'Zip',
            'phone' => 'PhoneNumber',
            'billingEmail' => 'Email',
        ] as $id => $key) {
            try {
                $this->http->Log('filling ' . $id);
                $input = $this->waitForElement(\WebDriverBy::id($id), $this->waitTimeout);

                switch (strtolower($input->getTagName())) {
                    case 'input':
                        $input->clear()->sendKeys($cc[$key]);

                        break;

                    case 'select':
                        (new \WebDriverSelect($input))->selectByValue($cc[$key]);

                        break;
                }
            } catch (\NoSuchElementException $e) {
                return $this->fail($e->getMessage());
            }
        }
        $this->waitForElement(\WebDriverBy::id('termsAndConditions'), $this->waitTimeout)->click();
        $button = $this->waitForElement(\WebDriverBy::xpath('//button[contains(@class, "bgt-order-submit") and not(contains(@class, "disabled"))]'), $this->waitTimeout);

        if (!$button) {
            return $this->fail('button fail');
        }
        $this->saveResponse();
        $this->http->Log('clicking pay submit');

        $button->click();
        $success = $this->waitForElement(\WebDriverBy::xpath('//p[contains(@class, "bgt-receipt-header-completed") or contains(@class, "bgt-receipt-header-pending")]'), $this->loadTimeout);

        if ($success) {
            $success = CleanXMLValue($success->getText());
        } else {
            unset($success);
        }
        $this->saveResponse();

        if (isset($success)) {
            $this->http->Log('success');
            $this->ErrorMessage = $success;

            return true;
        }
        $this->http->Log('no success message', LOG_LEVEL_ERROR);

        return false;
    }

    public function getPurchaseMilesFields()
    {
        return [
            'Login' => [
                'Type'     => 'string',
                'Caption'  => 'Account Number or Username',
                'Required' => true,
            ],
            'Password' => [
                'Type'     => 'string',
                'Caption'  => 'Password',
                'Required' => true,
            ],
            'Email' => [
                'Type'     => 'string',
                'Caption'  => 'Email',
                'Required' => true,
            ],
        ];
    }

    protected function fail($s = null)
    {
        if (isset($s)) {
            $this->http->Log($s, LOG_LEVEL_ERROR);
        }

        return false;
    }
}
