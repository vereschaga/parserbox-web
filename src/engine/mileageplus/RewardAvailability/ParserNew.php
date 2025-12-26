<?php

namespace AwardWallet\Engine\mileageplus\RewardAvailability;

use AwardWallet\Common\Selenium\BrowserCommunicatorException;
use AwardWallet\Engine\mileageplus\QuestionAnalyzer;
use AwardWallet\Engine\ProxyList;

class ParserNew extends \TAccountChecker
{
    use \SeleniumCheckerHelper;
    use ProxyList;

    public static function getRASearchLinks(): array
    {
        return ['https://www.united.com/en/us' => 'search page'];
    }

    public function InitBrowser()
    {
        parent::InitBrowser();

        $this->UseSelenium();
        $this->useChromeExtension();
//        $this->useGoogleChrome(\SeleniumFinderRequest::CHROME_95);
        $this->seleniumOptions->recordRequests = true;

        $this->seleniumOptions->addPuppeteerStealthExtension = false;
        $this->seleniumOptions->addHideSeleniumExtension = false;
        $this->seleniumOptions->userAgent = null;
        $this->disableImages();
        $this->http->saveScreenshots = true;

        if ($this->AccountFields['ParseMode'] === 'awardwallet') {
            $this->setProxyGoProxies(null, 'ca');
        } else {
            $this->setProxyNetNut(null, 'ca');
        }

        $resolutions = [
            [1152, 864],
            [1280, 720],
            [1280, 768],
            [1280, 800],
            [1360, 768],
            [1366, 768],
            [800, 600],
        ];

        $this->setScreenResolution($resolutions[array_rand($resolutions)]);
        $this->useCache();
        $this->http->setHttp2(true);

        $this->seleniumRequest->setHotSessionPool(self::class, $this->AccountFields['ProviderCode'], $this->AccountFields['AccountKey']);
    }

    public function LoadLoginForm()
    {
        return true;
    }

    public function Login()
    {
        return true;
    }

    public function getRewardAvailabilitySettings()
    {
        return [
            'supportedCurrencies'      => [],
            'supportedDateFlexibility' => 0, // 1
            'defaultCurrency'          => 'USD',
        ];
    }

    public function ParseRewardAvailability(array $fields)
    {
        $this->logger->info("Parse Reward Availability", ['Header' => 2]);
        $this->logger->debug("params: " . var_export($fields, true));
        $this->logger->debug("Parser New!!!");

        //$this->http->GetURL('https://www.united.com/en/us/');

        $auth = $this->getAuthFromRecorder();
        $this->logger->debug($auth);

        if ($this->tryLogin($auth)) {
            return $this->parseQuestion();
        }

        return ['routes' => []];
    }

    public function parseQuestion()
    {
        $this->logger->debug(__METHOD__);
        $question = $this->waitForElement(\WebDriverBy::xpath('//span[@class="app-components-ScreenReaderMessage-screenReaderMessage__screenReaderMessage--BrwiC"]'));
        $this->saveResponse();
        $question = $question->getText();

        $this->logger->debug($question);

        if (QuestionAnalyzer::isOtcQuestion($question)) {
            $this->logger->info("Two Factor Authentication Login", ['Header' => 3]);

            $this->holdSession();
            $this->question = $question;

            $this->AskQuestion($this->question, null, 'Question');

            return false;
        }

        return true;
    }

    public function ProcessStep($step)
    {
        $this->logger->debug(__METHOD__);

        $answer = $this->Answers[$this->Question];
        unset($this->Answers[$this->Question]);

        $answerInput = $this->waitForElement(\WebDriverBy::xpath('//input[@inputmode="numeric"]'), 5);
        $button = $this->waitForElement(\WebDriverBy::xpath('//button//span[text()="Continue"]'), 0);

        if (!$answerInput || !$button) {
            return false;
        }

        $answerInput->click();
        $answerInput->sendKeys($answer);
        $this->saveResponse();

        return true;
    }

    private function tryLogin($auth)
    {
        $this->http->GetURL('https://www.united.com/en/us/myunited');

        $login = $this->waitForElement(\WebDriverBy::xpath("//input[@id='MPIDEmailField']"), 5);
        $button = $this->waitForElement(\WebDriverBy::xpath('//button//span[text()="Continue"]'), 0);

        if (!$login || !$button) {
            return false;
        }

        $login->click();
        $login->sendKeys($this->AccountFields['Login']);
        $button->click();
        $this->saveResponse();

        $pass = $this->waitForElement(\WebDriverBy::xpath('//input[@id="password"]'), 5);
        $singIn = $this->waitForElement(\WebDriverBy::xpath('//button[@type="submit"]//span[text()="Sign in"]'));

        if (!$pass || !$singIn) {
            return false;
        }

        $pass->click();
        $pass->sendKeys($this->AccountFields['Pass']);

        $singIn->click();
        $this->saveResponse();

        if ($this->waitForElement(\WebDriverBy::xpath('//span[@class="app-components-ScreenReaderMessage-screenReaderMessage__screenReaderMessage--BrwiC"]'), 10, false)) {
            return $this->parseQuestion();
        }

        return false;
    }

    private function getAuthFromRecorder()
    {
        $this->logger->notice(__METHOD__);

        /** @var \SeleniumDriver $seleniumDriver */
        $seleniumDriver = $this->http->driver;

        try {
            $requests = $seleniumDriver->browserCommunicator->getRecordedRequests();
        } catch (BrowserCommunicatorException $e) {
            $this->logger->error(('BrowserCommunicatorException: ' . $e->getMessage()));
            $this->isPuppeteer = true;

            return null;
        } catch (\ErrorException $e) {
            $this->logger->error(('ErrorException: ' . $e->getMessage()));
            $this->isPuppeteer = true; // hard code, ff84 not work

            return null;
        } catch (\TypeError $e) {
            $this->logger->error(('TypeError: ' . $e->getMessage()));

            return null;
        }

        $auth = null;

        foreach ($requests as $n => $xhr) {
            $auth = $xhr->request->getHeaders()['X-Authorization-api'] ?? $xhr->request->getHeaders()['X-Authorization-Api'] ?? $auth;

            if (!empty($auth)) {
                break;
            }
        }

        return $auth;
    }
}
