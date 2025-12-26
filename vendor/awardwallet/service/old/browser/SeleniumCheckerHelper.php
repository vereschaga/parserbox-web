<?php

use AwardWallet\Common\OneTimeCode\OtcHelper;
use AwardWallet\Common\Selenium\DownloadedFile;
use AwardWallet\Common\Selenium\Puppeteer\Executor;
use AwardWallet\Common\Selenium\SeleniumDriverFactory;
use AwardWallet\Engine\CaptchaHelper;
use AwardWallet\Engine\Settings;
use Monolog\Handler\PsrHandler;
use Monolog\Logger;
use Symfony\Component\DependencyInjection\ServiceLocator;


/**
 * Class SeleniumCheckerHelper
 * @property HttpBrowser $http
 * @property ServiceLocator $services
 */
trait SeleniumCheckerHelper
{

    use CaptchaHelper;
    use OtcHelper;

	/**
	 * @var RemoteWebDriver
	 */
	protected $driver;

    protected $googleIMG = "//img[contains(@class, 'rc-image-tile')]";

    protected $noMatchingImages = "No_matching_images";

    // images change when you click on them
    protected $newCaptchaType = false;

    /**
     * @var SeleniumFinderRequest
     */
    protected $seleniumRequest;
    /**
     * @var SeleniumOptions
     */
    protected $seleniumOptions;
    /**
     * @var bool
     */
    private $useCache = false;
    private $usePacFile = true;
    private $keepProfile = false;
    private $filterAds = true;
    private $directImages = true;

    /** @var AccountCheckerLogger */
    public $logger;

    protected function construct_SeleniumCheckerHelper()
    {
        $this->seleniumRequest = new SeleniumFinderRequest();
        $this->seleniumOptions = new SeleniumOptions();
        $this->logger = new AccountCheckerLogger($this);
    }

    protected function UseSelenium() {
        $logger = new Logger('main');
        $logger->pushHandler(new PsrHandler($this->logger));
        if ($this->globalLogger !== null) {
            $logger->pushHandler(new PsrHandler($this->globalLogger));
        }
        $this->KeepState = true;
        $this->useLastHostAsProxy = false;
        $this->seleniumOptions->startupText = ( $this->AccountFields["ProviderCode"] ?? "" ) . " | " . ($this->AccountFields["Login"] ?? "") . " | " . ($this->AccountFields["Partner"] ?? "") . " | " . ($this->AccountFields["RequestAccountID"] ?? ($this->AccountFields["AccountID"]?? "")) . " | " . date("Y-m-d H:i:s");
        $this->seleniumOptions->loggingContext = [
            "provider" => $this->AccountFields["ProviderCode"] ?? "",
            "accountId" => $this->AccountFields["RequestAccountID"] ?? $this->AccountFields["AccountID"] ?? "",
            "partner" => $this->AccountFields["Partner"] ?? "",
            "requestId" => $this->AccountFields["RequestID"] ?? "",
        ];
        $driver = $this->services->get(SeleniumDriverFactory::class)->getDriver(
            $this->seleniumRequest,
            $this->seleniumOptions,
            $logger
        );
        if (isset($this->AccountFields["Priority"], $this->AccountFields["ThrottleBelowPriority"])
            && $this->AccountFields["Priority"] < $this->AccountFields["ThrottleBelowPriority"]
        ) {
            $this->seleniumRequest->setIsBackround();
        }
        $driver->setKeepProfile($this->keepProfile);

        $driver->onStart = function(SeleniumOptions $seleniumOptions)
        {
            if ($this->usePacFile) {
                $params = [];
                if ($this->useCache) {
                    $params["cache"] = "cache.awardwallet.com:3128";
                    if (defined('CACHE_HOST')) {
                        $params["cache"] = CACHE_HOST . ":3128";
                    }
                }
                if ($this->http->GetProxy() !== null) {
                    $params["proxy"] = $this->http->GetProxy();
                }
                if ($this->filterAds) {
                    $params["filterAds"] = "1";
                }
                if ($this->directImages) {
                    $params["directImages"] = "1";
                }

                $seleniumOptions->pacFile = Settings::getPacFile();
                if(!empty($params))
                    $seleniumOptions->pacFile .= "?" . http_build_query($params);
                $this->http->Log('set selenium pac File: ' . $seleniumOptions->pacFile);
            }
            else{
                $seleniumOptions->pacFile = null;
            }
            if ($this->http->GetProxy() !== null) {
                $params = $this->http->getProxyParams();
                $seleniumOptions->proxyHost = $params['proxyHost'];
                $seleniumOptions->proxyPort = $params['proxyPort'];
                $seleniumOptions->proxyUser = $params['proxyLogin'];
                $seleniumOptions->proxyPassword = $params['proxyPassword'];
                $this->http->Log("set selenium proxy: {$seleniumOptions->proxyUser}@{$seleniumOptions->proxyHost}:{$seleniumOptions->proxyPort}");
            }
            else{
                $seleniumOptions->proxyHost = null;
                $seleniumOptions->proxyPort = null;
                $seleniumOptions->proxyUser = null;
                $seleniumOptions->proxyPassword = null;
            }
        };

        if ($this->http !== null) {
            $oldOnLog = $this->http->OnLog;
            $oldProxyParams = $this->http->getProxyParams();
            $oldResponseNumber = $this->http->ResponseNumber;
            $oldLogBrother = $this->http->LogBrother;
            $oldUserAgent = $this->http->userAgent;
        }
        $this->http = new HttpBrowser($this->LogMode, $driver, $this->httpLogDir);
        if (isset($oldUserAgent)) {
            $this->http->setUserAgent($oldUserAgent);
        }
        if (isset($oldProxyParams)) {
            $this->http->setProxyParams($oldProxyParams);
        }
        $this->initBrowserSettings();
        if (!empty($oldOnLog)) {
            $this->http->OnLog = $oldOnLog;
        }
        if (!empty($oldResponseNumber)) {
            $this->http->ResponseNumber = $oldResponseNumber;
        }
        if (!empty($oldLogBrother)) {
            $this->http->LogBrother = $oldLogBrother;
        }
   	}

   	/**
	 * @return RemoteWebDriver
	 */
	protected function getWebDriver()
	{
		return $this->http->driver->webDriver;
	}

	/**
	 * @param Callable $whileCallback
	 * @param int $timeoutSeconds
	 * @return bool
	 */
	public function waitFor($whileCallback, $timeoutSeconds = 60) {
		$start = time();
		do {
			try {
				if (call_user_func($whileCallback)){
					return true;
				}
			} catch (Exception $e) {
                $this->reconnectFirefox($e);
            }
			sleep(1);
		} while((time() - $start) < $timeoutSeconds);
		return false;
	}

	private function reconnectFirefox(Throwable $e) : bool
    {
        if (stripos($e->getMessage(), "can't access dead object") !== false) {
            // https://stackoverflow.com/questions/44005034/cant-access-dead-object-in-geckodriver
            $this->logger->debug("firefox bug, reconnecting");
            $this->driver->switchTo()->defaultContent();
            return true;
        }

        return false;
    }

	/**
	 * @param WebDriverBy $by
	 * @param int $timeout
	 * @param bool $visible
	 * @return RemoteWebElement|null
	 */
	protected function waitForElement(WebDriverBy $by, $timeout = 60, $visible = true){
		/** @var RemoteWebElement $element */
		$element = null;
        $start = time();
		$this->waitFor(
            function () use ($by, &$element, $visible) {
                try {
				    $elements = $this->driver->findElements($by);
                } catch (Facebook\WebDriver\Exception\StaleElementReferenceException | StaleElementReferenceException $e) {
                    //$this->logger->error("[waitForElement exception on findElements]: " . $e->getMessage(), ['HtmlEncode' => true]);
                    sleep(1);
                    $elements = $this->driver->findElements($by);
                }

                foreach ($elements as $element) {
                    try {
                        if ($visible && !$element->isDisplayed()) {
                            $element = null;

                            continue;
                        }
                    } catch (Facebook\WebDriver\Exception\StaleElementReferenceException | StaleElementReferenceException $e) {
                        //$this->logger->error("[waitForElement StaleElementReferenceException on isDisplayed]: " . $e->getMessage(), ['HtmlEncode' => true]);
                        // isDisplayed throws this if element already disappeared from page
                        $element = null;

                        continue;
                    }

                    return true;
                }

				return false;
			},
			$timeout
		);

        $timeSpent = time() - $start;
        if (!empty($element))
            try {
                $this->http->Log("found element {$by->getValue()}, displayed: {$element->isDisplayed()}, text: '".trim($element->getText())."', spent time: $timeSpent", LOG_LEVEL_NOTICE);
            } catch (Facebook\WebDriver\Exception\StaleElementReferenceException | StaleElementReferenceException $e) {
                // final fallback for element disappearance, getText throws this too
			    $this->http->Log("element {$by->getValue()} found and disappeared, spent time: $timeSpent");
                $element = null;
                $timeLeft = $timeout - $timeSpent;

                if ($timeLeft > 0) {
                    $this->http->Log("restarting search, time left: $timeLeft");
                    return $this->waitForElement($by, $timeLeft, $visible);
                }
            }
		else
			$this->http->Log("element {$by->getValue()} not found, spent time: $timeSpent");

		return $element;
	}

	/**
     * Return true if we're trying to use window second time else false
     *
     * @return bool
	 */
	protected function isNewSession(){
		return $this->http->driver->isNewSession();
	}

	/**
	 * @param bool $keep
	 */
    protected function keepSession($keep)
    {
        if (property_exists($this,'isRewardAvailability') && $this->isRewardAvailability && !$this->http->driver->IsWithHotPool()) {
            $this->logger->notice("we don't set keepSession for reward availability if there is no HotSessionPool usage");
            return;
        }
        $this->http->driver->keepSession = $keep;
    }

	/*
	 * Smart keepSession
	 */
    protected function holdSession() {
        $this->logger->notice(__METHOD__);
        if (!$this->isBackgroundCheck() || method_exists($this, 'getWaitForOtc') && $this->getWaitForOtc())
            $this->keepSession(true);
    }

	/**
	 * @return DownloadedFile|null - last downloaded filename
	 */
	protected function getLastDownloadedFile($timeout = 20, $completeTimeout = 3)
    {
        $file = null;
        $this->waitFor(function() use (&$file) {
            $file = $this->http->driver->getLastDownloadedFile();
            return $file !== null;
        }, $timeout);
        return $file;
	}

	protected function clearDownloads(){
		$this->http->driver->clearDownloads();
	}

	protected function InitSeleniumBrowser($proxy = null){
		$this->AccountFields['ProviderEngine'] = PROVIDER_ENGINE_SELENIUM;
		$this->UseSelenium();
	}

	protected function startNewSession(){
		$this->http->driver->stop();
		$this->http->driver->setState([]);
		$this->http->driver->start();
	}

    public function saveResponse(): ?string
    {
        if ($this->driver !== null) {
            try {
                $this->http->SetBody($this->driver->executeScript('return document.documentElement.innerHTML;'));
                $this->http->SaveResponse();
            } catch (Exception $e) {
                if ($this->reconnectFirefox($e)) {
                    $this->http->SetBody($this->driver->executeScript('return document.documentElement.innerHTML;'));
                    $this->http->SaveResponse();
                }
                else {
                    $this->logger->warning("failed to save response: " . $e->getMessage());
                    return $e->getMessage();
                }
            }
        }
        return null;
    }

	/**
	 * @param bool $keep
	 */
	protected function keepCookies($keep){
		$this->http->driver->keepCookies = $keep;
	}

    protected function useChromium($version = null)
    {
        if(empty($version))
            $version = SeleniumFinderRequest::CHROMIUM_DEFAULT;
        $this->logger->debug("Selenium browser: Chromium v. {$version}");
        $this->seleniumRequest->request(SeleniumFinderRequest::BROWSER_CHROMIUM, $version);
	}

    protected function useGoogleChrome($version = null)
    {
        if(empty($version))
            $version = SeleniumFinderRequest::CHROME_DEFAULT;
        $this->logger->debug("Selenium browser: Google Chrome v. {$version}");
        $this->seleniumRequest->request(SeleniumFinderRequest::BROWSER_CHROME, $version);
	}

    protected function useChromePuppeteer($version = null)
    {
        if(empty($version))
            $version = SeleniumFinderRequest::CHROME_PUPPETEER_DEFAULT;
        $this->logger->debug("Selenium browser: Chrome Puppeteer v. {$version}");
        $this->seleniumRequest->request(SeleniumFinderRequest::BROWSER_CHROME_PUPPETEER, $version);
	}

    protected function useChromeExtension($version = null)
    {
        if(empty($version))
            $version = SeleniumFinderRequest::CHROME_EXTENSION_DEFAULT;
        $this->logger->debug("Selenium browser: Chrome Extension v. {$version}");
        $this->seleniumRequest->request(SeleniumFinderRequest::BROWSER_CHROME_EXTENSION, $version);
	}

    protected function useFirefoxPlaywright($version = null)
    {
        if(empty($version))
            $version = SeleniumFinderRequest::FIREFOX_PLAYWRIGHT_DEFAULT;
        $this->logger->debug("Selenium browser: Firefox Playwright v. {$version}");
        $this->seleniumRequest->request(SeleniumFinderRequest::BROWSER_FIREFOX_PLAYWRIGHT, $version);
	}

    protected function useChromePlaywright($version = null)
    {
        if(empty($version))
            $version = SeleniumFinderRequest::CHROME_PLAYWRIGHT_DEFAULT;
        $this->logger->debug("Selenium browser: Chrome Playwright v. {$version}");
        $this->seleniumRequest->request(SeleniumFinderRequest::BROWSER_CHROME_PLAYWRIGHT, $version);
	}

    protected function useBravePlaywright($version = null)
    {
        if(empty($version))
            $version = SeleniumFinderRequest::BRAVE_PLAYWRIGHT_DEFAULT;
        $this->logger->debug("Selenium browser: Brave Playwright v. {$version}");
        $this->seleniumRequest->request(SeleniumFinderRequest::BROWSER_BRAVE_PLAYWRIGHT, $version);
	}

    protected function useFirefox($version = null)
    {
        if(empty($version))
            $version = SeleniumFinderRequest::FIREFOX_DEFAULT;
        $this->logger->debug("Selenium browser: Firefox v. {$version}");
        $this->seleniumRequest->request(SeleniumFinderRequest::BROWSER_FIREFOX, $version);
	}

    /**
     * This method disables the hovering scripts
     *
     * @param bool $use
     */
    protected function usePacFile(bool $use = true) {
        $this->logger->debug("usePacFile: ". json_encode($use));
        $this->usePacFile = $use;
	}

    /**
     * emulated screen resolution, expected format [800, 600]
     * @var array
     */
    protected function setScreenResolution($resolution)
    {
        $this->logger->debug("set screen resolution: ".implode('x', $resolution));
        $this->seleniumOptions->resolution = $resolution;
    }

	protected function useCache(){
        $this->logger->debug('using cache');
        $this->useCache = true;
	}

	protected function waitAjax(){
		sleep(1);
		$this->waitFor(function(){ return $this->driver->executeScript('return jQuery.active') == 0; });
	}

	public function Start(){
		if($this->http->driver instanceof SeleniumDriver) {

		    if(!$this->http->driver->isStarted())
		        $this->http->start();
            $this->driver = $this->http->driver->webDriver;
            if ($this instanceof TAccountChecker) {
                /** @var SeleniumDriver $seleniumDriver */
                $seleniumDriver = $this->http->driver;
                
                $this->http->setSeleniumServer($seleniumDriver->getServerAddress());
                
                $browserInfo = $seleniumDriver->getBrowserInfo();
                $this->http->setSeleniumBrowserFamily($browserInfo[SeleniumStarter::CONTEXT_BROWSER_FAMILY]);
                $this->http->setSeleniumBrowserVersion($browserInfo[SeleniumStarter::CONTEXT_BROWSER_VERSION]);
            }
        }
	}

	protected function disableImages(){
        $this->logger->debug('images have been disabled');
        $this->seleniumOptions->showImages = false;
	}

	/**
	 * Take screenshot of selected element and return path to it on success or false otherwise
	 *
	 * @param RemoteWebElement | Facebook\WebDriver\Remote\RemoteWebElement $elem Element which should be screenshoted
	 * @return string|false
	 */
    protected function takeScreenshotOfElement($elem, $selenium = null)
    {
        $this->logger->notice(__METHOD__);
        if (!$elem)
            return false;
        if (!$selenium)
            $selenium = $this;
        $time = getmypid()."-".microtime(true);
        $path = '/tmp/seleniumPageScreenshot-'.$time.'.png';
        $selenium->driver->takeScreenshot($path);
        $img = imagecreatefrompng($path);
        unlink($path);
        if (!$img)
            return false;
        $rect = [
            'x' => $elem->getLocation()->getX(),
            'y' => $elem->getLocation()->getY(),
            'width' => $elem->getSize()->getWidth(),
            'height' => $elem->getSize()->getHeight(),
        ];
        $cropped = imagecrop($img, $rect);
        if (!$cropped)
            return false;
        $path = '/tmp/seleniumElemScreenshot-'.$time.'.png';
        $status = imagejpeg($cropped, $path);
        if (!$status)
            return false;
        $this->logger->info('screenshot taken');
        return $path;
    }

    protected function savePageToLogs($selenium)
    {
        $this->logger->notice(__METHOD__);

        try {
            $selenium->http->SaveResponse();
        } catch (
            ErrorException
            | NoSuchDriverException
            | WebDriverCurlException
            $e
        ) {
            $this->logger->error("Exception on SaveResponse: " . $e->getMessage(), ['HtmlEncode' => true]);
        }

        try {
            $this->http->SetBody($selenium->driver->executeScript('return document.documentElement.innerHTML'));
        } catch (
            UnexpectedJavascriptException
            | Facebook\WebDriver\Exception\JavascriptErrorException
            | TimeOutException
            $e
        ) {
            $this->logger->error("Exception: " . $e->getMessage(), ['HtmlEncode' => true]);
        } catch (UnknownServerException $e) {
            $this->logger->error("Exception on SetBody: " . $e->getMessage(), ['HtmlEncode' => true]);
            $this->http->SetBody($selenium->driver->getPageSource());
        }

        $this->http->SaveResponse();
    }

    protected function setKeepProfile(bool $keep)
    {
        $this->keepProfile = $keep;

        if ($this->seleniumRequest->getBrowser() !== SeleniumFinderRequest::BROWSER_FIREFOX && $keep) {
            $this->logger->error("{$this->seleniumRequest->getBrowser()} do not support KeepProfile method");
            return;
        }

        if ($this->http->driver !== null && $this->http->driver instanceof SeleniumDriver) {
            $this->http->driver->setKeepProfile($keep);
        }
    }

    protected function setFilterAds(bool $filterAds)
    {
        $this->filterAds = $filterAds;
        return $this;
    }

    protected function setDirectImages(bool $directImages)
    {
        $this->directImages = $directImages;
        return $this;
    }

    protected function getAllCookies() : array
    {
        if ($this->http->driver->browserCommunicator === null) {
            throw new Exception("unsupported browser for getting cookies");
        }
        return $this->http->driver->browserCommunicator->getCookies();
    }

    // closes browser window, to save resources
    // use it if you already finished selenium parsing (grabbed cookies from it)
    protected function stopSeleniumBrowser() : void
    {
        $this->logger->info(__METHOD__);
        $this->http->driver->stop();
    }
    
    public function getPuppeteerExecutor() : Executor
    {
        return new Executor($this->logger, 'ws://' . $this->http->driver->getServerAddress() . '/devtools/' . $this->getWebDriver()->getSessionID());
    }

    /**
     * @param string $shadowDomRootSelector - css selector of shadow root
     * @param string $shadowDomElementSelector - css selector of element within shadow root
     * @param string $jsCode - will be executed within shadow root, element found by $shadowRootElementSelector will be available as 'element' variable
     * @return mixed - result of js code execution. You should include 'return' in $jsCode
     */
    public function executeInShadowDom(string $shadowDomRootSelector, string $shadowDomElementSelector, string $jsCode)
    {
        return $this->driver->executeScript('
        shadowRootHost = document.querySelector(' . json_encode($shadowDomRootSelector) . ');
        shadowRoot = shadowRootHost.shadowRoot;
        element = shadowRoot.querySelector(' . json_encode($shadowDomElementSelector) . ');
        ' . $jsCode . '
        ');
    }

}
