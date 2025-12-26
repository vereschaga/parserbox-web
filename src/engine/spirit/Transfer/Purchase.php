<?php

namespace AwardWallet\Engine\spirit\Transfer;

use AwardWallet\Engine\ProxyList;

class Purchase extends \TAccountChecker
{
    use \SeleniumCheckerHelper;
    use ProxyList;

    protected $timeout = 5;

    protected $loadTimeout = 20;

    protected $ccTypes = [
        'amex' => 'string:transaction.payment.form.creditCard.amex',
        'visa' => 'string:transaction.payment.form.creditCard.visa',
    ];

    public function InitBrowser()
    {
        $this->UseSelenium();

        if (ConfigValue(CONFIG_SITE_STATE) === SITE_STATE_DEBUG) {
            $this->http->SetProxy($this->proxyPurchase());
        } else {
            $this->http->SetProxy($this->proxyDOP());
        }

        $this->http->saveScreenshots = true;
        $this->ArchiveLogs = true;
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
            "Email" => [
                "Type"     => "string",
                "Required" => true,
                "Caption"  => "Email (login email)",
            ],
            "FirstName" => [
                "Type"     => "string",
                "Required" => true,
                "Caption"  => "First Name",
            ],
            "LastName" => [
                "Type"     => "string",
                "Required" => true,
                "Caption"  => "Last Name",
            ],
            "AccountNumber" => [
                "Type"     => "string",
                "Required" => true,
                "Caption"  => "FREE SPIRIT NUMBER",
            ],
        ];
    }

    public function purchaseMiles(array $fields, $numberOfMiles, $creditCard)
    {
        $numberOfMiles = intval($numberOfMiles);

        if ($numberOfMiles < 1000 || $numberOfMiles > 60000 || $numberOfMiles % 1000 !== 0) {
            throw new \UserInputError("Number of purchased points should be lesser then 60,000 and divisible by 1000");
        }

        if (!isset($this->ccTypes[$creditCard['Type']])) {
            return $this->fail('unknown cc: ' . $creditCard['Type']);
        }

        $this->http->GetURL("https://storefront.points.com/free-spirit/en-US/buy");

        if (!($elem = $this->waitForElement(\WebDriverBy::id("bgt-offer-dropdown"), $this->loadTimeout))) {
            $this->logger->info('Login data error. Input number of miles not found');

            throw new \UserInputError('Login data error');
        }

        $this->fillSelectInputs([
            'bgt-offer-dropdown' => $numberOfMiles . '',
        ]);

        if (!($sbmBtn = $this->waitForElement(\WebDriverBy::xpath("//form[@name='loginForm']//button[@type='submit']"), $this->loadTimeout))) {
            return $this->fail('Account info submit not found');
        }

        if (!$this->fillTextInputs([
            'firstName' => $fields['FirstName'],
            'lastName' => $fields['LastName'],
            'memberId' => $fields['AccountNumber'],
            'email' => $fields['Email'],
        ])) {
            return $this->fail();
        }

        $this->saveResponse();

        $sbmBtn->click();

        // step 2
        if (!($elem = $this->waitForElement(\WebDriverBy::id("cardName"), $this->loadTimeout))) {
            $this->checkLoginFormErrors();

            return $this->fail('Payment info form not found');
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
            $this->waitForElement(\WebDriverBy::id($inputId), $this->timeout)->sendKeys($creditCard[$awKey]);
            $this->logger->info('Done.');
        }

        $this->waitForElement(\WebDriverBy::id('termsAndConditions'), $this->timeout)->click();

        $this->waitForElement(\WebDriverBy::xpath('//*[@class="bgt-order-information-form"]//*[@type="submit"]'), $this->timeout)->click();

        if ($this->waitForElement(\WebDriverBy::xpath('(//div[@class="bgt-form-error" and contains(.,"Please complete all of the required fields")])[1]'), $this->loadTimeout)) {
            $this->logger->info('Error: Missing required fields.');

            return false;
        }
        $this->logger->info('Done.');

        // success
        $success = $this->waitForElement(\WebDriverBy::xpath('//*[text()[contains(normalize-space(.), "Thank you for your purchase") or contains(normalize-space(.),"We have received your purchase request")]]'), $this->timeout);
        $this->saveResponse();

        if ($success) {
            $this->ErrorMessage = 'Thank you for your purchase!';

            return true;
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

    protected function fillTextInputs($data)
    {
        foreach ($data as $name => $value) {
            $input = $this->waitForElement(\WebDriverBy::id($name), $this->timeout);

            if (!$input) {
                return $this->fail(sprintf('input %s not found', $name));
            }
            $ok = false;

            for ($i = 0; $i < 3; $i++) {
                $input->clear();
                $input->sendKeys($value);

                $filled = $this->driver->executeScript(sprintf('return $("#%s").val()', $name));

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

    protected function checkLoginFormErrors()
    {
        if ($error = $this->waitForElement(\WebDriverBy::xpath('//div[@class="bgt-form-error" and normalize-space(.) != ""]'), $this->timeout)) {
            throw new \UserInputError(CleanXMLValue($error->getText()));
        }
    }

    protected function fillSelectInputs($data)
    {
        foreach ($data as $name => $value) {
            if (!($elem = $this->waitForElement(\WebDriverBy::id($name), $this->timeout))) {
                return $this->fail(sprintf('select %s not found', $name));
            }
            $this->driver->executeScript('$(arguments[0]).trigger("focus")', [$elem]);
            $this->driver->executeScript("$(arguments[0]).val('{$value}')", [$elem]);
            $this->driver->executeScript('$(arguments[0]).trigger("change")', [$elem]);
        }

        return true;
    }
}
