<?php
namespace AwardWallet\ExtensionWorker;

use AwardWallet\Common\Parsing\Exception\ErrorFormatter;
use AwardWallet\ExtensionWorker\Commands\SetCookieRequest;
use Psr\Log\LoggerInterface;

class Tab
{
    private const DEFAULT_TIMEOUT = 60;
    private const SHORT_TIMEOUT = 5;
    private const MEDIUM_TIMEOUT = 10;

    /**
     * use Message::MESSAGE_RECAPTCHA
     * @deprecated
     */
    public const MESSAGE_RECAPTCHA = Message::MESSAGE_RECAPTCHA;
    /**
     * use Message::MESSAGE_IDENTIFY_COMPUTER
     * @deprecated
     */
    public const MESSAGE_IDENTIFY_COMPUTER = Message::MESSAGE_IDENTIFY_COMPUTER;

    private $tabId;
    private Communicator $communicator;
    private int $frameId;
    private LoggerInterface $logger;
    private FileLogger $fileLogger;
    private ErrorFormatter $errorFormatter;

    public function __construct($tabId, $communicator, int $frameId, LoggerInterface $logger, FileLogger $fileLogger, ErrorFormatter $errorFormatter) {
        $this->tabId = $tabId;
        $this->communicator = $communicator;
        $this->frameId = $frameId;
        $this->logger = $logger;
        $this->fileLogger = $fileLogger;
        $this->errorFormatter = $errorFormatter;
    }

    public function querySelector($selector, ?QuerySelectorOptions $options = null) : Element {
        if ($options === null) {
            $options = new QuerySelectorOptions();
        }

        $elements = $this->querySelectorInternal($selector, false, "querySelector", $options->getShadowRoot(), $options->getVisible(), $options->getNotEmptyString(), false, $options->getTimeout() ?? self::DEFAULT_TIMEOUT);

        if (count($elements) === 0) {
            $this->logger->debug(__FUNCTION__ . "('$selector') - not found");
            throw new ElementNotFoundException("Could not locate element by selector {$selector}");
        }

        $this->logger->debug(__FUNCTION__ . "('$selector') - success, element {$elements[0]->id}");

        return $elements[0];
    }

    /**
     * @return Element[]
     */
    public function querySelectorAll($selector, ?QuerySelectorOptions $options = null) : array {
        if ($options === null) {
            $options = new QuerySelectorOptions();
        }

        $this->checkTimeoutOnAll($options->getTimeout());

        $result = $this->querySelectorInternal($selector, true, "querySelector", $options->getShadowRoot(), $options->getVisible(), $options->getNotEmptyString(), false, 0);

        $this->logger->debug(__FUNCTION__ . "('$selector') - found " . count($result) . " elements");

        return $result;
    }

    public function evaluate(string $xpath, ?EvaluateOptions $options = null) : ?Element
    {
        if ($options === null) {
            $options = new EvaluateOptions();
        }

        if ($options->getTimeout() === null && $options->getAllowNull()) {
            $options->timeout(0);
        }

        $elements = $this->querySelectorInternal($xpath, false, "evaluate", $options->getContextNode(), $options->getVisible(), $options->getNotEmptyString(), false, $options->getTimeout() ?? self::DEFAULT_TIMEOUT);

        if (count($elements) === 0 && $options->getAllowNull()) {
            $this->logger->info(__FUNCTION__ . ": Could not locate element by xpath {$xpath}, returning null");

            return null;
        }

        if (count($elements) === 0) {
            $this->logger->debug(__FUNCTION__ . ": Could not locate element by xpath {$xpath}, throwing exception");
            throw new ElementNotFoundException("Could not locate element by xpath {$xpath}");
        }

        $this->logger->debug(__FUNCTION__ . "('$xpath') - success, element {$elements[0]->id}");

        return $elements[0];
    }

    public function evaluateAll(string $xpath, ?EvaluateOptions $options = null) : array {
        if ($options === null) {
            $options = new EvaluateOptions();
        }

        $this->checkTimeoutOnAll($options->getTimeout());

        $elements = $this->querySelectorInternal($xpath, true, "evaluate", $options->getContextNode(), $options->getVisible(), $options->getNotEmptyString(), false, 0);
        $this->logger->debug(__FUNCTION__ . "('$xpath') - found " . count($elements) . " elements");

        return $elements;
    }

    public function findText(string $selector, ?FindTextOptions $options = null) : ?string
    {
        if ($options === null) {
            $options = new FindTextOptions();
        }

        if ($options->getTimeout() === null && $options->getAllowNull()) {
            $options->timeout(0);
        }

        $startTime = microtime(true);
        $endTime = $startTime + ($options->getTimeout() ?? self::DEFAULT_TIMEOUT);
        $timeout = fn() => $endTime - microtime(true);
        $lastError = null;
        $pass = 0;
        $sleep = function() use ($timeout) {
            if ($timeout() > 0.1) {
                sleep(1);
            }
        };

        $pregReplaceIfNeeded = function(?string $result) use ($options, $selector) {
            if ($options->getPregReplaceRegexp() === null) {
                return $result;
            }

            $result = preg_replace($options->getPregReplaceRegexp(), $options->getPregReplaceReplacement(), $result);
            $this->logger->debug(__FUNCTION__ . "('$selector', {pregReplaceRegExp:'{$options->getPregReplaceRegexp()}'} - $result");

            return $result;
        };

        while ($timeout() > 0.1 || $pass === 0) {
            $pass++;
            $elements = $this->querySelectorInternal($selector, false, $options->getMethod(), $options->getContextNode(), $options->getVisible(), $options->getNotEmptyString(), false, $timeout());
            if (count($elements) === 0) {
                $sleep();
                $lastError = "Selector '$selector' not found";
                continue;
            }

            $element = $elements[0];
            $text = $element->getInnerText();
            if ($options->getPreg() === null) {
                return $pregReplaceIfNeeded($text);
            }

            $result = preg($options->getPreg(), $text);
            if ($result !== null) {
                return $pregReplaceIfNeeded($result);
            }

            $lastError = "selector '$selector' found, but preg not found: '{$options->getPreg()}' within text '" . Text::cutString($text) . "'";
            $sleep();
        };

        if ($options->getAllowNull()) {
            $this->logger->debug(__FUNCTION__ . ": " . $lastError);

            return null;
        }

        throw new ElementNotFoundException($lastError);
    }

    public function findTextNullable(string $selector, ?FindTextOptions $options = null) : ?string
    {
        if ($options === null) {
            $options = new FindTextOptions();
        }

        if ($options->getTimeout() === null) {
            $options->timeout(0);
        }

        try {
            return $this->findText($selector, $options);
        } catch (ElementNotFoundException $e) {
            $this->logger->debug(__FUNCTION__ . "('$selector'): " . $e->getMessage());
            return null;
        }
    }

    /**
     * @return string[]
     */
    public function findTextAll(string $selector, ?FindTextOptions $options = null) : array
    {
        if ($options === null) {
            $options = new FindTextOptions();
        }

        $this->checkTimeoutOnAll($options->getTimeout());

        $elements = $this->querySelectorInternal($selector, true, $options->getMethod(), $options->getContextNode(), $options->getVisible(), $options->getNotEmptyString(), false, 0);
        $result = array_map(fn(Element $element) => $element->getInnerText(), $elements);

        if ($options->getPreg() === null) {
            $this->logger->debug(__FUNCTION__ . "('$selector') - found " . count($result) . " elements: " . implode(", ", array_map(fn(string $s) => Text::cutString($s), $result)));
            return $result;
        }

        $result = array_map(fn(string $text) => preg($options->getPreg(), $text), $result);
        $result = array_filter($result, fn(?string $s) => $s !== null);
        $this->logger->debug(__FUNCTION__ . "('$selector', {preg:'{$options->getPreg()}'}) - found " . count($result) . " elements: " . implode(", ", array_map(fn(string $s) => Text::cutString($s), $result)));

        return $result;
    }

    /**
     * find frame containing specified element. do not specify "frame" or "iframe" within selector
     */
    public function selectFrameContainingSelector(string $selector, ?SelectFrameOptions $options = null) : ?Tab
    {
        if ($options === null) {
            $options = new SelectFrameOptions();
        }

        if ($options->getTimeout() === null && $options->getAllowNull()) {
            $options->timeout(0);
        }

        $elements = $this->querySelectorInternal($selector, false, $options->getMethod(), null, $options->getVisible(), $options->getNotEmptyString(), true, $options->getTimeout() ?? self::DEFAULT_TIMEOUT);

        if (count($elements) === 0 && $options->getAllowNull()) {
            $this->logger->debug(__FUNCTION__ . "('$selector'): Could not any frame containing element by selector {$selector}, returning null");

            return null;
        }

        if (count($elements) === 0) {
            throw new ElementNotFoundException("Could not locate element by selector {$selector}");
        }

        if (count($elements) > 1) {
            throw new ElementNotFoundException("Too much frames matching selector {$selector}");
        }

        $element = $elements[0];
        $this->logger->debug(__FUNCTION__ . "('$selector'):  success");

        return new Tab($this->tabId, $this->communicator, $element->frameId, $this->logger, $this->fileLogger, $this->errorFormatter);
    }

    public function getHtml(int $timeout = self::DEFAULT_TIMEOUT) : string
    {
        return $this->communicator->sendMessageToExtension(
            new ExtensionRequest("getHtml", new NoParamsRequest($this->tabId, $this->frameId)),
            $timeout
        );
    }

    /**
     * @param string|null $name - name of file, without extension
     */
    public function saveHtml(string $name = null) : void
    {
        try {
            $html = $this->getHtml(self::MEDIUM_TIMEOUT);
        }
        catch (ExtensionError $error) {
            $this->logger->notice("failed to save html: " . $error->getMessage());
            return;
        }

        $this->fileLogger->logFile($html, "{$name}.html");
    }

    /**
     * will save screenshot and page source
     * @param string|null $name - name of file, without extension
     */
    public function logPageState(string $name = null) : void
    {
        $this->saveHtml($name ?? $this->fileLogger->getStepBaseName());
        $this->saveScreenshot($name);
    }

    public function getUrl() : string
    {
        $result = $this->communicator->sendMessageToExtension(
            new ExtensionRequest("getUrl", new NoParamsRequest($this->tabId, $this->frameId))
        );

        $this->logger->debug(__FUNCTION__ . ": $result");

        return $result;
    }

    public function gotoUrl(string $url) : void
    {
        $this->logger->debug(__FUNCTION__ . "('$url')");
        $urlBeforeNavigation = $this->getUrl();
        $startTime = microtime(true);
        $this->communicator->sendMessageToExtension(
            new ExtensionRequest("gotoUrl", new GotoUrlRequest($this->tabId, $this->frameId, $url))
        );

        while ((microtime(true) - $startTime) < 30 && $url !== $urlBeforeNavigation && $this->getUrl() === $urlBeforeNavigation) {
            sleep(2);
        }
    }

    /**
     * @link https://developer.mozilla.org/en-US/docs/Web/API/fetch
     * @param $options array{ body:string, cache:string, headers:array, method:string, redirect:string, referrer:string }
     */
    public function fetch(string $url, array $options = []) : FetchResponse
    {
        $this->logger->debug(__FUNCTION__ . "('$url', " . json_encode($options) . ")");
        $response = $this->communicator->sendMessageToExtension(
            new ExtensionRequest("fetch", new FetchRequest($this->tabId, $this->frameId, $url, $options)),
            60
        );

        $result = new FetchResponse();
        foreach ($response as $property => $value) {
            if (property_exists($result, $property)) {
                $result->$property = $response[$property];
            }
        }

        $extension = ".html";
        if (stripos($result->headers['content-type'] ?? 'text/html', 'application/json') !== false) {
            $extension = ".json";
        }

        $this->fileLogger->logFile($result->body, $extension);

        return $result;
    }

    /**
     * @param string|null $name - name of screenshot, without extension
     */
    public function saveScreenshot(string $name = null) : void
    {
        try {
            $content = base64_decode($this->communicator->sendMessageToExtension(
                new ExtensionRequest("screenshot", new NoParamsRequest($this->tabId, $this->frameId)),
                self::MEDIUM_TIMEOUT
            ));
        }
        catch (ExtensionError $error) {
            $this->logger->notice("failed to save screenshot: " . $error->getMessage());
            return;
        }

        $this->fileLogger->logFile($content, "{$name}.png");
    }

    /**
     * returns cookies for the current domain parsed into array ["cookie1" => "value1", "cookie2" => "value2" ...
     * it will return only cookies accessible to javascript (document.cookie)
     *
     * @return array - ["cookie1" => "value1", "cookie2" => "value2" ...
     */
    public function getCookies() : array
    {
        $cookieStr = $this->communicator->sendMessageToExtension(
            new ExtensionRequest("getCookies", new NoParamsRequest($this->tabId, $this->frameId))
        );

        $result = CookieParser::parseCookieString($cookieStr);
        $this->logger->debug(__FUNCTION__ . ": got cookies with names " . implode(", ", array_keys($result)));

        return $result;
    }

    /**
    /**
     * * @link https://developer.mozilla.org/en-US/docs/Web/API/Document/cookie#write_a_new_cookie
     * * @param $options array{ domain:string, maxAge:int, path:string, sameSite:string, secure:boolean }
     */
    public function setCookie(string $name, string $value, ?string $path = '/', array $options = []) : void
    {
        $this->logger->debug(__FUNCTION__ . "('$name', '$value', " . json_encode($options) . ")");
        $this->communicator->sendMessageToExtension(
            new ExtensionRequest("setCookie", new SetCookieRequest(
                $this->tabId,
                $this->frameId,
                $name,
                $value,
                $options['domain'] ?? null,
                $path,
                $options['sameSite'] ?? null,
                $options['secure'] ?? null,
                $options['maxAge'] ?? null
            ))
        );
    }

    public function getFromSessionStorage(string $itemName) : ?string
    {
        $result = $this->communicator->sendMessageToExtension(
            new ExtensionRequest("getFromSessionStorage", new ReadStorageRequest($this->tabId, $this->frameId, $itemName))
        );

        $this->logger->debug(__FUNCTION__ . "('$itemName'): " . ( $result === null ? 'null' : ("'" . Text::cutString($result)) . "'"));

        return $result;
    }

    public function getFromLocalStorage(string $itemName) : ?string
    {
        $result = $this->communicator->sendMessageToExtension(
            new ExtensionRequest("getFromLocalStorage", new ReadStorageRequest($this->tabId, $this->frameId, $itemName))
        );

        $this->logger->info(__FUNCTION__ . "('$itemName'): " . ( $result === null ? 'null' : ("'" . Text::cutString($result)) . "'"));

        return $result;
    }

    public function back() : void
    {
        $this->logger->debug("Tab::back()");
        $this->communicator->sendMessageToExtension(
            new ExtensionRequest("back", new NoParamsRequest($this->tabId, $this->frameId))
        );
    }

    /**
     * @internal
     */
    public function getId() : string
    {
        return $this->tabId;
    }

    /**
     * @internal
     * @return Element[]
     */
    public function querySelectorInternal(string $selector, bool $all, string $method, ?Element $contextNode, bool $visible, bool $notEmptyString, bool $allFrames, float $timeout = Communicator::DEFAULT_TIMEOUT) : array {
        if ($all && $timeout >= 0.1) {
            throw new \InvalidArgumentException("Could not use timeout greater than 0 when all is true");
        }

        $elements = [];
        $poll = true;
        $startTime = microtime(true);

        while ($poll) {
            $closure = function() use ($selector, $all, $method, $contextNode, $visible, $notEmptyString, $allFrames) {
                return $this->communicator->sendMessageToExtension(
                    new ExtensionRequest("querySelector", new QuerySelectorRequest($selector, $all, $this->tabId, $method, $contextNode ? $contextNode->id : null, $visible, $notEmptyString, $allFrames, $this->frameId))
                );
            };

            if ($contextNode) {
                $closure = function() use ($closure, $contextNode) {
                    return $contextNode->retryOnDetachedElement($closure);
                };
            }

            $elements = $closure();
            $poll = !$all && count($elements) === 0 && (microtime(true) - $startTime) < $timeout;

            if ($poll) {
                usleep(1000000); // Sleep for 1 second
            }
        }

        $position = 0;
        return array_map(function($element) use ($selector, $all, $method, $contextNode, $visible, $notEmptyString, $allFrames, $timeout, &$position) {
            return new Element(
                $element["elementId"],
                $element["nodeType"],
                $this->communicator,
                $this,
                $element["frameId"],
                new QuerySelectorParams($selector, $all, $method, $contextNode, $visible, $notEmptyString, $allFrames, $timeout, $position),
                $this->logger
            );

        }, $elements);
    }

    public function showMessage(string $message) : void
    {
        if ($message !== '') {
            $this->logger->info(__FUNCTION__ . ": $message");
            $this->communicator->sendMessageToExtension(
                new ExtensionRequest("switchToTab", new NoParamsRequest($this->tabId, $this->frameId))
            );
        }

        $message = $this->errorFormatter->format($message);

        $this->communicator->sendMessageToExtension(
            new ExtensionRequest("showMessage", new ShowMessageRequest($this->tabId, $this->frameId, $message))
        );
    }

    public function hideMessage() : void
    {
        $this->logger->info(__FUNCTION__);
        $this->communicator->sendMessageToExtension(
            new ExtensionRequest("hideMessage", new NoParamsRequest($this->tabId, $this->frameId)),
            self::SHORT_TIMEOUT
        );
    }

    public function close() : void
    {
        $this->logger->debug('Tab::close');
        $this->communicator->sendMessageToExtension(
            new ExtensionRequest("closeTab", new NoParamsRequest($this->tabId, $this->frameId)),
            self::SHORT_TIMEOUT
        );
    }

//    not working, chrome sends tab status updates too late
//    public function waitLoaded(int $idleMilliseconds = 1000, int $maxWaitMilliseconds = 20000) : void
//    {
//        $startTime = microtime(true);
//        do {
//            $status = $this->communicator->sendMessageToExtension(
//                new ExtensionRequest("getTabStatus", new NoParamsRequest($this->tabId, $this->frameId))
//            );
//            $waitTime = (microtime(true) - $startTime) / 1000;
//            $timedOut = $waitTime > $maxWaitMilliseconds;
//            $complete = $status['age'] >= $idleMilliseconds && $status['status'] === 'complete';
//            if (!$timedOut && !$complete) {
//                usleep(500000);
//            }
//        } while (!$complete && !$timedOut);
//    }
    private function checkTimeoutOnAll(?int $timeout) : void
    {
        if ($timeout !== null) {
            throw new \Exception("You could not use timeout with ..All methods");
        }
    }

    /**
     * use Message::identifyComputer
     * @deprecated
     */
    public static function identifyComputerMessage(string $buttonName = 'Verify') : string
    {
        return Message::identifyComputer($buttonName);
    }

    /**
     * @internal
     */
    public function getFrameId(): int
    {
        return $this->frameId;
    }

}