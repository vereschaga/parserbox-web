<?php

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Common\Selenium\FingerprintFactory;
use AwardWallet\Common\Selenium\FingerprintRequest;
use AwardWallet\Engine\ProxyList;
require_once __DIR__ . '/../expedia/TAccountCheckerExpediaSelenium.php';

class TAccountCheckerHomeawaySelenium extends TAccountCheckerExpediaSelenium
{
    use SeleniumCheckerHelper;
    use PriceTools;
    use ProxyList;

    protected TAccountCheckerHomeaway $curlChecker;
    public string $provider = 'homeaway';

    public function InitBrowser()
    {
        TAccountChecker::InitBrowser();
        $this->KeepState = true;
        $this->UseSelenium();
        $this->http->saveScreenshots = true;

        if ($this->attempt == 0) {
            $this->http->SetProxy($this->proxyReCaptcha());
        } elseif ($this->attempt == 1) {
            $this->setProxyDOP();
        } elseif ($this->attempt == 2) {
            $this->setProxyMount();
        }

//        $this->useChromePuppeteer();
        $this->useFirefoxPlaywright();
        $this->seleniumOptions->addHideSeleniumExtension = false;

        // refs #23984
        $this->setHost($this->AccountFields['Login2']);

        $this->http->setHttp2(true);
    }

    protected function getCheckerHomeaway()
    {
        $this->logger->notice(__METHOD__);
        if (!isset($this->curlChecker)) {
            $this->curlChecker = new TAccountCheckerHomeaway();
            $this->curlChecker->http = new HttpBrowser("none", new CurlDriver());
            $this->curlChecker->http->setProxyParams($this->http->getProxyParams());

            $this->http->brotherBrowser($this->curlChecker->http);
            $this->curlChecker->AccountFields = $this->AccountFields;
            $this->curlChecker->itinerariesMaster = $this->itinerariesMaster;
            $this->curlChecker->HistoryStartDate = $this->HistoryStartDate;
            $this->curlChecker->historyStartDates = $this->historyStartDates;
            $this->curlChecker->http->LogHeaders = $this->http->LogHeaders;
            $this->curlChecker->ParseIts = $this->ParseIts;
            $this->curlChecker->ParsePastIts = $this->ParsePastIts;
            $this->curlChecker->WantHistory = $this->WantHistory;
            $this->curlChecker->WantFiles = $this->WantFiles;
            $this->curlChecker->strictHistoryStartDate = $this->strictHistoryStartDate;

            $this->curlChecker->globalLogger = $this->globalLogger;
            $this->curlChecker->logger = $this->logger;
            $this->curlChecker->onTimeLimitIncreased = $this->onTimeLimitIncreased;
            $this->curlChecker->host = $this->host;
        }

        $cookies = $this->driver->manage()->getCookies();
        $this->logger->debug("set cookies");

        foreach ($cookies as $cookie) {
            $this->curlChecker->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
        }

        $this->curlChecker->http->GetURL($this->http->currentUrl());

        return $this->curlChecker;
    }

    public function Parse()
    {
        $this->saveResponse();

        $homeaway = $this->getCheckerHomeaway();
        $homeaway->Parse();
        $this->SetBalance($homeaway->Balance);
        $this->Properties = $homeaway->Properties;
        $this->ErrorCode = $homeaway->ErrorCode;

        if ($this->ErrorCode != ACCOUNT_CHECKED) {
            $this->ErrorMessage = $homeaway->ErrorMessage;
            $this->DebugInfo = $homeaway->DebugInfo;
        }
    }

    public function ParseItineraries($providerHost = 'www.vrbo.com', $ParsePastIts = false)
    {
        parent::ParseItineraries($providerHost, $ParsePastIts);

        return [];
    }
}
