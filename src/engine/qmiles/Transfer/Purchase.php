<?php

// case #12104

namespace AwardWallet\Engine\qmiles\Transfer;

class Purchase extends \TAccountChecker
{
    use \SeleniumCheckerHelper {
        waitForElement as traitWaitForElement;
    }

    protected $TIMEOUT = 10;
    protected $PURCHASE_URL = 'https://qmiles.qatarairways.com/ffponline/ffp-online/Bgt.jsf';
    protected $LOGIN_URL = 'https://www.qatarairways.com/en/Privilege-Club/loginpage.html';

    protected $fields;
    protected $numberOfMiles;
    protected $creditCard;

    protected static $fieldMapLogin = [
        'Login'    => 'f1003',
        'Password' => 'f1001',
    ];

    protected static $creditCardToLinkId = [
        'visa'       => 'VIpayment-type',
        'mastercard' => 'CApayment-type',
    ];

    protected static $fieldMapCC = [
        'CardNumber'      => 'cardNumber',
        'ExpirationMonth' => 'expMonth',
        'ExpirationYear'  => 'expYear',
        'Name'            => 'nameOnCard',
        'SecurityNumber'  => 'cvvCodeMasked',
    ];

    public function initBrowser()
    {
        $this->useSelenium();
        $this->useGoogleChrome();
        $this->http->saveScreenshots = true;

        if (ConfigValue(CONFIG_SITE_STATE) === SITE_STATE_DEBUG) {
            $this->keepSession(false); //no need true
            $this->http->setProxy('localhost:8000');
        } elseif (ConfigValue(CONFIG_SITE_STATE) === SITE_STATE_PRODUCTION) {
            $this->usePacFile();
            $this->keepSession(false);
        }
    }

    public function purchaseMiles(array $fields, $numberOfMiles, $creditCard)
    {
        $this->http->log('[INFO] ' . __METHOD__);

        $this->http->log('[DEBUG] fields:');
        $this->http->log(json_encode($fields, JSON_PRETTY_PRINT));

        $this->fields = $fields;
        $this->numberOfMiles = $numberOfMiles;
        $this->creditCard = $creditCard;

        $this->checkFields();
        $this->modifyFields();
        $this->loginInner();
        $this->http->getUrl($this->PURCHASE_URL);
        $this->stepBuyQmiles();
        $this->stepPayment();
        $this->stepPaymentInformation();

        return $this->checkResult();
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
            "Login" => [
                "Type"     => "string",
                "Required" => true,
                "Caption"  => "Qmiles Number",
            ],
            "Password" => [
                "Type"     => "string",
                "Required" => true,
                "Caption"  => "Password",
            ],
        ];
    }

    protected function checkResult()
    {
        $this->http->log('[INFO] ' . __METHOD__);

        $successSel = \WebDriverBy::id('bgtForm:qmBQSuccessfulMsgBuy');
        $success = $this->waitForElement($successSel, 60, true, false);
        $this->saveResponse();

        if ($success) {
            $msg = trim($success->getText());

            if ($msg) {
                $this->http->log('[INFO] ' . $msg);
                $this->ErrorMessage = $msg;

                return true;
            }
        }

        $errorSel = \WebDriverBy::xpath('//div[contains(@style, "color: red")]');
        $this->waitForElement($errorSel);
        $errors = $this->driver->findElements($errorSel);

        if ($errors) {
            $errors = array_map(function ($el) { return trim($el->getText()); }, $errors);
            $msg = implode(' ', $errors);

            throw new \UserInputError($msg);
        }

        return false;
    }

    protected function stepPayment()
    {
        $this->http->log('[INFO] ' . __METHOD__);
        $submitSel = \WebDriverBy::xpath('//*[@type = "submit" and @title = "Buy Qmiles Now"]');
        $submit = $this->waitForElement($submitSel);
        $this->saveResponse();

        if (!$submit) {
            $this->checkValidation();
        }
        $submit->click();
    }

    protected function stepPaymentInformation()
    {
        $this->http->log('[INFO] ' . __METHOD__);
        $cancelSel = \WebDriverBy::xpath('//a[normalize-space(text()) = "Cancel and Return"]');
        $cancel = $this->waitForElement($cancelSel);
        $this->saveResponse();

        if (!$cancel) {
            $this->checkValidation();
        }

        $this->chooseCreditCard();
        $this->waitForElement(\WebDriverBy::id('cardNumber'));
        $this->saveResponse();
        $this->populate($this->creditCard, self::$fieldMapCC);
        $this->setInputValue('agreeTerms', '');
        $submitSel = \WebDriverBy::xpath('//a[normalize-space(text()) = "Confirm Payment"]');
        $submit = $this->waitForElement($submitSel);
        $submit->click();
    }

    protected function chooseCreditCard()
    {
        $this->http->log('[INFO] ' . __METHOD__);
        $ccSel = \WebDriverBy::id(self::$creditCardToLinkId[$this->creditCard['Type']]);
        $cc = $this->waitForElement($ccSel);
        $cc->click();
    }

    protected function stepBuyQmiles()
    {
        $this->http->log('[INFO] ' . __METHOD__);
        $this->setInputValue('bgtForm:blocksBuy', $this->numberOfMiles);
        $this->saveResponse();
        $this->setInputValue('bgtForm:currencyBuy', 'USD'); // default
        $submitSel = \WebDriverBy::xpath('//*[@title = "Continue"]');
        $submit = $this->waitForElement($submitSel);
        $submit->click();
    }

    protected function modifyFields()
    {
        $this->http->log('[INFO] ' . __METHOD__);
        $this->numberOfMiles = floor($this->numberOfMiles / 1000);
        $this->creditCard['ExpirationMonth'] = sprintf('%02d', $this->creditCard['ExpirationMonth']);
    }

    protected function checkFields()
    {
        $this->http->log('[INFO] ' . __METHOD__);
    }

    protected function checkValidation()
    {
        $error = $this->waitForElement(\WebDriverBy::cssSelector('.validationalert'));

        if ($error) {
            $msg = $error->getText();
            $msg = preg_replace('/\r|\n/', ' ', $msg);

            throw new \UserInputError(trim($msg));
        }
    }

    protected function loginInner()
    {
        $this->http->log('[INFO] ' . __METHOD__);
        $this->http->getUrl($this->LOGIN_URL);

        $this->populate($this->fields, self::$fieldMapLogin);
        $this->saveResponse();
        // $this->driver->executeScript('loginQmilesNew();');
        $submit = $this->waitForElement(\WebDriverBy::id('loginButtonInvoke'), $this->TIMEOUT);

        if ($submit) {
            $submit->click();
        }

        $loggedSel = \WebDriverBy::xpath('//p[contains(@class, "login-tier-status")]');
        $logged = $this->waitForElement($loggedSel, $this->TIMEOUT * 3, true, false);
        $this->saveResponse();

        if (!$logged) {
            throw new \UserInputError('Invalid login / password');
        }
    }

    protected function populate($fields, $fieldMap)
    {
        foreach ($fieldMap as $awkey => $key) {
            if (!arrayVal($fields, $awkey)) {
                continue;
            }
            $value = $fields[$awkey];
            $this->http->log(sprintf(
                '[DEBUG] setting input value: awkey = "%s", key = "%s", value = "%s"',
            $awkey, $key, $value));
            $this->setInputValue($key, $value);
        }
    }

    protected function waitForElement($selector, $timeout = null, $visible = true, $raise = true)
    {
        if ($timeout === null) {
            $timeout = $this->TIMEOUT;
        }
        $elem = $this->traitWaitForElement($selector, $timeout, $visible);

        if (!$elem && $raise) {
            throw new \EngineError(sprintf('selector %s(%s) not found', $selector->getMechanism(), $selector->getValue()));
        }

        return $elem;
    }

    protected function setInputValue($key, $value)
    {
        $elemSel = sprintf('//*[@id = "%s" or @name = "%s"]', $key, $key);
        $elem = $this->waitForElement(\WebDriverBy::xpath($elemSel));
        $type = $elem->getAttribute('type');
        $tagName = $elem->getTagName();

        if ($tagName === 'input' && $type === 'radio') {
            $elem->click();
        } elseif ($tagName === 'input' && $type === 'checkbox') {
            $elem->click();
        } elseif ($tagName === 'input') {
            $elem->clear();
            $elem->sendKeys($value);
        } elseif ($tagName === 'select') {
            $select = new \WebDriverSelect($elem);
            $select->selectByValue($value);
        }
    }
}
