<?php
/**
 * Created by PhpStorm.
 * User: APuzakov
 * Date: 27.12.15
 * Time: 18:09.
 */

namespace AwardWallet\Engine\virginamerica\Transfer;

use AwardWallet\Engine\ProxyList;

class Purchase extends \TAccountCheckerVirginamerica
{
    use \PointsDotComSeleniumHelper;
    use ProxyList;

    protected $ccTypes = [
        'amex' => '5',
        'visa' => '0',
    ];

    /**
     * @var Purchase
     */
    protected $seleniumChecker = null;

    public function InitBrowser()
    {
        $this->UseSelenium();
        $this->usePacFile();
        $this->useChromium();

        if (ConfigValue(CONFIG_SITE_STATE) === SITE_STATE_DEBUG) {
            $this->http->SetProxy('localhost:8000');
        } else {
            $this->http->SetProxy($this->proxyDOP());
        }
    }

    public function LoadLoginForm()
    {
        $numberOfMiles = intval($this->TransferFields['numberOfMiles']);

        if ($numberOfMiles <= 0 || $numberOfMiles > 20000 || $numberOfMiles % 500 !== 0) {
            throw new \UserInputError("Number of purchased points should be lesser then 20,000 and divisible by 500");
        }

        $this->http->GetURL('https://www.virginamerica.com/elevate-frequent-flyer/landing');
        $elem = $this->waitForElement(\WebDriverBy::xpath("//input[@name='email']"), $this->loadTimeout);
        $elem->sendKeys($this->AccountFields['Login']);
        $elem = $this->waitForElement(\WebDriverBy::xpath("//input[@name='password']"), $this->loadTimeout);
        $elem->sendKeys($this->AccountFields['Pass']);
        $this->waitForElement(\WebDriverBy::xpath("//button[contains(text(),'Sign in')]"), $this->loadTimeout)->click();

        if ($success = $this->waitForElement(\WebDriverBy::xpath("//a[contains(text(), 'SIGN OUT')]"), $this->loadTimeout, false)) {
            return true;
        }

        if ($error = $this->waitForElement(\WebDriverBy::xpath("//div[contains(@class,'alert-bar')]//span[@class='message']"), $this->loadTimeout)) {
            throw new \UserInputError($error->getText());
        }

        return false;
    }

    public function Login()
    {
        return true;
    }

    public function purchaseMiles(array $fields, $numberOfMiles, $creditCard)
    {
        $this->http->Log('PointsDotComSeleniumHelper');

        if (ConfigValue(CONFIG_SITE_STATE) === SITE_STATE_PRODUCTION) { // use affiliate link
            $this->http->GetURL('http://www.kqzyfj.com/click-8184014-11320006-1365608414000');
        } else {
            $this->http->GetURL('https://www.virginamerica.com/elevate-frequent-flyer/buy-gift-points');
        }

        if (!$sbm = $this->waitForElement(\WebDriverBy::xpath("//form[@id='buy']/input[@type='submit']"), $this->loadTimeout)) {
            throw new \ProviderError('purchase form not found');
        }
//        $this->driver->executeScript("$('#buy').submit();");
        sleep(1);
        $sbm->click();

        if (!$this->waitForElement(\WebDriverBy::id('quantity4'), $this->loadTimeout, false)) {
            throw new \ProviderError('No points selector');
        }

        $this->saveResponse();

        if (!isset($this->ccTypes[$creditCard['Type']])) {
            return $this->clear('unknown cc: ' . $creditCard['Type']);
        }
        $milesVal = $numberOfMiles / 500 - 1;
        $expYear = $this->http->FindSingleNode("//select[@id='expiryYear12']//option[@label='{$creditCard['ExpirationYear']}']/@value");
        $state = $this->http->FindSingleNode("//select[@id='state18']//option[contains(@label,'{$creditCard['State']}')]/@value");
        $data = [
            //select
            'quantity4'       => $milesVal,
            'creditCardType8' => $this->ccTypes[$creditCard['Type']],
            'expiryMonth11'   => $creditCard['ExpirationMonth'] - 1,
            'expiryYear12'    => $expYear,
            'country19'       => $creditCard['CountryCode'],
            'state18'         => $state,
            //text
            'creditCardNumber9' => $creditCard['CardNumber'],
            'creditCardCvv10'   => $creditCard['SecurityNumber'],
            'firstName13'       => $creditCard['Name'],
            'lastName14'        => $creditCard['Name'],
            'address115'        => $creditCard['AddressLine'],
            'city17'            => $creditCard['City'],
            'zipCode20'         => $creditCard['Zip'],
            'adr-phone-number'  => $creditCard['PhoneNumber'],
        ];
        $this->fillInputs($data);

        if (!$elem = $this->waitForElement(\WebDriverBy::xpath("//input[@id='termsAndConditions6']"), $this->loadTimeout)) {
            throw new \ProviderError('No termsAndConditions checkbox');
        }
        $elem->click();

        if (!$sbmBtn = $this->waitForElement(\WebDriverBy::xpath("//form[@name='orderForm']//button[@type='submit']"), $this->loadTimeout)) {
            throw new \ProviderError('No PayNow button');
        }
        $sbmBtn->click();

        if ($success = $this->waitForElement(\WebDriverBy::xpath("//p[contains(text(),'Thank you for your purchase')]"), $this->loadTimeout)) {
            $this->ErrorMessage = $success->getText();

            return true;
        }

        if ($error = $this->waitForElement(\WebDriverBy::xpath("//li[@rel='payment-error']"), $this->loadTimeout)) {
            throw new \UserInputError($error->getText());
        }

        return false;
    }

    public function getPurchaseMilesFields()
    {
        return [
            "Login" => [
                "Type"     => "string",
                "Required" => true,
                "Caption"  => "Email or elevate #",
            ],
            "Password" => [
                "Type"     => "string",
                "Required" => true,
                "Caption"  => "Password",
            ],
        ];
    }

    protected function fillInputs($data)
    {
        foreach ($data as $id => $val) {
            $this->driver->executeScript("
                $('#{$id}').val('{$val}');
                $('#{$id}').change();
            ");
        }
    }
}
