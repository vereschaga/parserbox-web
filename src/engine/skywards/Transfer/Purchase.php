<?php

namespace AwardWallet\Engine\skywards\Transfer;

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
        $this->useChromium();

        if (ConfigValue(CONFIG_SITE_STATE) === SITE_STATE_DEBUG) {
            $this->http->SetProxy($this->proxyPurchase());
        } else {
            $this->http->setProxy($this->proxyDOP());
        }

        $this->http->saveScreenshots = true;
        $this->ArchiveLogs = true;
    }

    public function LoadLoginForm()
    {
        $numberOfMiles = intval($this->TransferFields['numberOfMiles']);

        if ($numberOfMiles < 2000 || $numberOfMiles > 100000 || $numberOfMiles % 1000 !== 0) {
            throw new \UserInputError('Unavailable number of points');
        }

        $this->http->GetURL('https://www.emirates.com/account/english/manage-account/manage-account.aspx');

        if ($a = $this->waitForElement(\WebDriverBy::xpath("//a[@id='login-nav-link']", $this->loadTimeout))) {
            $a->click();
        } else {
            $this->fail('no load main page');
        }

        if (!$this->waitForElement(\WebDriverBy::xpath('//input[@id="sso-email"]'), $this->loadTimeout)) {
            $this->fail('not found login form');
        }
        //		$this->http->GetURL('https://www.emirates.com/account/english/manage-account/manage-account.aspx');

        return true;
    }

    public function Login()
    {
        if (!$this->fillTextInputs([
            'sso-email' => $this->AccountFields['Login'],
            'sso-password' => $this->AccountFields['Pass'],
        ])) {
            return $this->fail();
        }

        $this->saveResponse();
        $this->waitForElement(\WebDriverBy::id('login-button'), 0)->click();

        if ($elem = $this->waitForElement(\WebDriverBy::xpath("//div[starts-with(@class,'login-error') and normalize-space()!='']"), 5)) {
            throw new \UserInputError($elem->getText());
        }

        return true;
    }

    public function purchaseMiles(array $fields, $numberOfMiles, $creditCard)
    {
        //		$this->http->GetURL("https://www.emirates.com/account/english/manage-account/manage-account.aspx");
        $this->http->GetURL("https://www.emirates.com/account/system/aspx/ExternalTransfer.aspx?target=Points.com&returnURL=PointsPOM");

        $message = "We're sorry. You are not eligible to make this purchase. Please refer to the";

        if ($error = $this->waitForElement(\WebDriverBy::xpath('//h2[contains(normalize-space(),"' . $message . '")]'),
            $this->loadTimeout)
        ) {
            throw new \UserInputError($error->getText());
        }

        if (!$this->http->FindPreg("#storefront.points.com#", false, $this->http->currentUrl())) {
            $this->fail('maybe other logic of buy');
        }

        if (!($elem = $this->waitForElement(\WebDriverBy::id('bgt-offer-dropdown'), $this->loadTimeout))) {
            return $this->fail('Select "bgt-offer-dropdown" not found.');
        }

        try {
            (new \WebDriverSelect($this->waitForElement(\WebDriverBy::xpath('//select[@id = "bgt-offer-dropdown"]'), $this->waitTimeout)))->selectByValue($numberOfMiles);
        } catch (\NoSuchElementException $e) {
            return $this->fail($e->getMessage());
        }
        $this->logger->info('Select ' . $numberOfMiles . ' points done.');

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

        if ($input = $this->waitForElement(\WebDriverBy::xpath('//input[@id="termsAndConditions"]'), $this->waitTimeout, false)) {
            $input->click();
        }

        $this->saveResponse();

        if ($elem = $this->waitForElement(\WebDriverBy::xpath('//*[@class="bgt-order-information-form"]//*[@type="submit"]'),
            $this->waitTimeout)
        ) {
            $this->logger->info('click submit');
            $elem->click();
        }
        $this->saveResponse();

        if ($this->waitForElement(\WebDriverBy::xpath('(//div[@class="bgt-form-error" and contains(.,"Please complete all of the required fields")])[1]'),
            $this->loadTimeout)
        ) {
            $this->logger->info('Error: Missing required fields.');

            return false;
        }

        if ($this->waitForElement(\WebDriverBy::xpath('(//div[@class="bgt-form-error" and contains(.,"To continue with your purchase")])[1]'),
            $this->loadTimeout)
        ) {
            $this->logger->info('Error: termsAndConditions not checked.');

            return false;
        }

        $this->logger->info('Done.');

        // success
        $success = $this->waitForElement(\WebDriverBy::xpath('//*[text()[contains(., "Thank you for your purchase")]]'), $this->waitTimeout);

        if (!$success) {
            $success = $this->waitForElement(\WebDriverBy::xpath('//*[text()[contains(., "We have received your purchase request")]]'), $this->waitTimeout);
        }

        if ($success) {
            $this->ErrorMessage = 'Thank you. We have received your purchase request.';

            return true;
        }

        return false;
    }

    public function getPurchaseMilesFields()
    {
        return [
            'Login' => [
                'Type'     => 'string',
                'Required' => true,
            ],
            'Password' => [
                'Type'     => 'string',
                'Required' => true,
            ],
            'Email' => [
                'Type'     => 'string',
                'Required' => true,
            ],
        ];
    }

    protected function fail($message = null)
    {
        if (isset($message)) {
            $this->logger->info($message);
        }
        $this->saveResponse();

        return false;
    }

    protected function checkLoginFormErrors()
    {
        if ($error = $this->waitForElement(\WebDriverBy::xpath('//div[@class="bgt-form-error" and normalize-space(.) != ""]'), $this->waitTimeout)) {
            throw new \UserInputError(CleanXMLValue($error->getText()));
        }
    }

    protected function checkPurchaseParameters($creditCard)
    {
    }

    private function fillTextInputs($data)
    {
        foreach ($data as $name => $value) {
            $input = $this->waitForElement(\WebDriverBy::xpath(sprintf('//input[starts-with(@id,"%s") and (@type="text" or @type="password")]', $name)), 0);

            if (!$input) {
                return $this->fail(sprintf('input %s not found', $name));
            }
            $this->logger->info('click select');
            $mover = new \MouseMover($this->driver);
            $mover->logger = $this->logger;
            $mover->moveToElement($input);
            $mover->click();
            $ok = false;

            for ($i = 0; $i < 3; $i++) {
                $input->clear();
                $input->click()->sendKeys($value);

                $filled = $this->driver->executeScript(sprintf('return document.querySelector("input[id^=\'%s\']").value', $name));

                if (strcmp(trim($value), trim($filled)) === 0) {
                    $ok = true;

                    break;
                }
                $this->http->Log(sprintf('retrying filling input %s', $name));
                sleep(1);
            }

            if (!$ok) {
                return $this->fail(sprintf('input %s didn\'t get filled properly'));
            }
        }

        return true;
    }
}
