<?php

namespace AwardWallet\ExtensionWorker;

use Psr\Log\LoggerInterface;

class Element {

    private const TEXT_NODE = 3;
    private const ATTRIBUTE_NODE = 2;

    /** @internal */
    public string $id;
    private Communicator $communicator;
    private Tab $tab;
    /** @internal */
    public int $frameId;
    /** @internal */
    public int $nodeType;
    private QuerySelectorParams $querySelectorParams;
    private LoggerInterface $logger;

    public function __construct(string $id, int $nodeType, Communicator $communicator, Tab $tab, int $frameId, QuerySelectorParams $querySelectorParams, LoggerInterface $logger) {
        $this->id = $id;
        $this->communicator = $communicator;
        $this->tab = $tab;
        $this->frameId = $frameId;
        $this->nodeType = $nodeType;
        $this->querySelectorParams = $querySelectorParams;
        $this->logger = $logger;
    }

    public function click(): void
    {
        $this->logger->info("clicking element {$this->id}");
        $this->onlyElementNode();
        $this->retryOnDetachedElement(function() {
            $this->communicator->sendMessageToExtension(
                new ExtensionRequest("element", new ElementRequest($this->id, $this->tab->getId(), $this->frameId, "method", ["method" => "click", "arguments" => []]))
            );
        });
    }

    public function focus(): void
    {
        $this->logger->info("focusing element {$this->id}");
        $this->onlyElementNode();
        $this->retryOnDetachedElement(function() {
            $this->communicator->sendMessageToExtension(
                new ExtensionRequest("element", new ElementRequest($this->id, $this->tab->getId(), $this->frameId, "method", ["method" => "focus",
                    "arguments" => []]))
            );
        });
    }

    public function setValue(string $text): void
    {
        $this->logger->info("set value on element {$this->id}");
        $this->onlyElementNode();
        $this->retryOnDetachedElement(function() {
            $this->communicator->sendMessageToExtension(
                new ExtensionRequest("element", new ElementRequest($this->id, $this->tab->getId(), $this->frameId, "method", ["method" => "focus",
                    "arguments" => []]))
            );
        });

        $maxLength = $this->getAttribute("maxLength");
        if ($maxLength !== null && (string) (int) $maxLength === $maxLength && strlen($text) > (int) $maxLength) {
            $this->logger->info("maxLength is set to $maxLength, truncating the value, value length: " . strlen($text));
            $text = substr($text, 0, $maxLength);
        }

        $this->communicator->sendMessageToExtension(
            new ExtensionRequest("element", new ElementRequest($this->id, $this->tab->getId(), $this->frameId, "setProperty", ["property" => "value", "value" => $text]))
        );
        $this->communicator->sendMessageToExtension(
            new ExtensionRequest("element", new ElementRequest($this->id, $this->tab->getId(), $this->frameId, "dispatchEvent", ["event" => "input"]))
        );
        $this->communicator->sendMessageToExtension(
            new ExtensionRequest("element", new ElementRequest($this->id, $this->tab->getId(), $this->frameId, "dispatchEvent", ["event" => "change"]))
        );
        $this->communicator->sendMessageToExtension(
            new ExtensionRequest("element", new ElementRequest($this->id, $this->tab->getId(), $this->frameId, "dispatchEvent", ["event" => "keydown"]))
        );
        $this->communicator->sendMessageToExtension(
            new ExtensionRequest("element", new ElementRequest($this->id, $this->tab->getId(), $this->frameId, "dispatchEvent", ["event" => "keyup"]))
        );
    }

    public function setProperty(string $propertyName, string $value): void
    {
        $this->logger->info("set property {$propertyName} on element {$this->id}");
        $this->onlyElementNode();
        $this->retryOnDetachedElement(function() use ($propertyName, $value) {
            $this->communicator->sendMessageToExtension(
                new ExtensionRequest("element", new ElementRequest($this->id, $this->tab->getId(), $this->frameId, "setProperty", ["property" => $propertyName, "value" => $value]))
            );
        });
    }

    public function getNodeName(): string
    {
        if ($this->nodeType === self::TEXT_NODE) {
            $this->logger->debug(__FUNCTION__ . ': TEXT');
            return "TEXT";
        }

        if ($this->nodeType === self::ATTRIBUTE_NODE) {
            $this->logger->debug(__FUNCTION__ . ': ATTRIBUTE');
            return "ATTRIBUTE";
        }

        $result = $this->retryOnDetachedElement(function() {
            return $this->communicator->sendMessageToExtension(
                new ExtensionRequest("element", new ElementRequest($this->id, $this->tab->getId(), $this->frameId, "getProperty", ["property" => "nodeName"]))
            );
        });
        
        $this->logger->debug(__FUNCTION__ . ": $result");
        
        return $result;
    }

    public function getInnerText(): string
    {
        $result = $this->retryOnDetachedElement(function() {
            $propertyName = $this->isTextNode() ? "textContent" : "innerText";
            return $this->communicator->sendMessageToExtension(
                new ExtensionRequest("element", new ElementRequest($this->id, $this->tab->getId(), $this->frameId, "getProperty", ["property" => $propertyName]))
            );
        });
        
        $this->logger->debug(__FUNCTION__ . ": " . Text::cutString($result));
        
        return $result;
    }

    public function getInnerHtml(): string
    {
        $this->onlyElementNode();
        $result = $this->retryOnDetachedElement(function() {
            return $this->communicator->sendMessageToExtension(
                new ExtensionRequest("element", new ElementRequest($this->id, $this->tab->getId(), $this->frameId, "getProperty", ["property" => "innerHTML"]))
            );
        });

        $this->logger->debug(__FUNCTION__ . ": " . Text::cutString($result));
        
        return $result;
    }

    public function checked(): bool
    {
        $this->onlyElementNode();
        $result = $this->retryOnDetachedElement(function() {
            return $this->communicator->sendMessageToExtension(
                new ExtensionRequest("element", new ElementRequest($this->id, $this->tab->getId(), $this->frameId, "getProperty", ["property" => "checked"]))
            );
        });

        $this->logger->debug(__FUNCTION__ . ": " . json_encode($result));

        return $result;
    }

    public function getValue(): string
    {
        $this->onlyElementNode();
        $result = $this->retryOnDetachedElement(function() {
            return $this->communicator->sendMessageToExtension(
                new ExtensionRequest("element", new ElementRequest($this->id, $this->tab->getId(), $this->frameId, "getProperty", ["property" => "value"]))
            );
        });

        $this->logger->debug(__FUNCTION__ . ": " . Text::cutString($result));

        return $result;
    }

    private function onlyElementNode(): void
    {
        if ($this->isTextNode()) {
            throw new \InvalidArgumentException("Only ELEMENT nodes can be used for this operation, this is TEXT node ($this->nodeType)");
        }
    }

    public function getAttribute(string $attributeName): ?string
    {
        $this->onlyElementNode();
        $result = $this->retryOnDetachedElement(function() use ($attributeName) {
            return $this->communicator->sendMessageToExtension(
                new ExtensionRequest("element", new ElementRequest($this->id, $this->tab->getId(), $this->frameId, "method", ["method" => "getAttribute",
                    "arguments" => [$attributeName]]))
            );
        });
        $this->logger->debug(__FUNCTION__ . "('$attributeName'): " . Text::cutString($result ?? ''));

        return $result;
    }
    
    public function shadowRoot() : ShadowRoot
    {
        return new ShadowRoot($this->tab, $this);
    }

    private function isTextNode() : bool
    {
        return in_array($this->nodeType, [self::TEXT_NODE, self::ATTRIBUTE_NODE]);
    }

    /**
     * @internal
     */
    public function retryOnDetachedElement(Callable $executor)
    {
        $startTime = microtime(true);
        $lastError = null;

        while ($lastError === null || (microtime(true) - $startTime) < 500) {
            try {
                return call_user_func($executor);
            } catch (CommunicationException $error) {
                $lastError = $error;

                if (
                    stripos($error->getMessage(), "REMOVED_FROM_DOM") !== false
                    || preg_match('#Context node \w+ not found in cache#ims', $error->getMessage())
                    || stripos($error->getMessage(), 'Element not found in cache') !== false
                ) {
                    $this->logger->debug("element {$this->id} was not found: {$error->getMessage()}. Trying to find it again by selector {$this->querySelectorParams->getSelector()}");
                    $elements = $this->tab->querySelectorInternal(
                        $this->querySelectorParams->getSelector(),
                        $this->querySelectorParams->isAll(),
                        $this->querySelectorParams->getMethod(),
                        $this->querySelectorParams->getContextNode(),
                        $this->querySelectorParams->isVisible(),
                        $this->querySelectorParams->isNotEmptyString(),
                        $this->querySelectorParams->isAllFrames(),
                        0
                    );

                    if (isset($elements[$this->querySelectorParams->getPosition()])) {
                        $this->logger->debug("replacing element {$this->id} with {$elements[$this->querySelectorParams->getPosition()]->id} found by selector {$this->querySelectorParams->getSelector()}");
                        $this->id = $elements[$this->querySelectorParams->getPosition()]->id;
                        continue;
                    }

                    sleep(1);
                }

                throw $error;
            }
        }

        throw $lastError;
    }

}