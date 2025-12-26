<?php

namespace AwardWallet\ExtensionWorker;

use Psr\Log\LoggerInterface;

class StateManager
{

    private State $state;
    private LoggerInterface $logger;

    public function __construct(State $state, LoggerInterface $logger)
    {
        $this->state = $state;
        $this->logger = $logger;
    }

    public function keepBrowserState(bool $keep): self
    {
        $this->logger->info('StateManager:' . __METHOD__ . '(' . json_encode($keep) . ')');
        $this->state->keepBrowserState = $keep;

        return $this;
    }

    public function keepBrowserSession(bool $keep): self
    {
        $this->logger->info('StateManager:' . __METHOD__ . '(' . json_encode($keep) . ')');
        $this->state->keepBrowserSession = $keep;

        return $this;
    }

    public function isBrowserSessionRestored() : bool
    {
        return $this->state->browserSessionRestored;
    }

    public function set(string $param, $value) : self
    {
        $encoded = json_encode($value);
        if (json_decode($encoded, true) !== $value) {
            throw new \Exception('StateManager:' . __METHOD__ . '(' . json_encode($param) . ', ' . json_encode($value) . ') : could not json_encode this value');
        }

        $this->logger->info('StateManager:' . __METHOD__ . '(' . json_encode($param) . ', ' . json_encode($value) . ')');
        $this->state->parserState[$param] = $value;

        return $this;
    }

    public function get(string $param)
    {
        $result = $this->state->parserState[$param] ?? null;
        $this->logger->info('StateManager:' . __METHOD__ . '(' . json_encode($param) . ') -> ' . json_encode($result));

        return $result;
    }

    public function has(string $param) : bool
    {
        $result = array_key_exists($param, $this->state->parserState);
        $this->logger->info('StateManager:' . __METHOD__ . '(' . json_encode($param) . ') -> ' . json_encode($result));

        return $result;
    }

    public function delete(string $param) : self
    {
        $this->logger->info('StateManager:' . __METHOD__ . '(' . json_encode($param) . ')');
        unset($this->state->parserState[$param]);

        return $this;
    }

    public function clear()
    {
        $this->state->parserState = [];
    }

}