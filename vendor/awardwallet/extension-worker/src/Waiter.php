<?php

namespace AwardWallet\ExtensionWorker;

use Psr\Log\LoggerInterface;

class Waiter
{

    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    function waitFor(callable $whileCallback, int $timeoutSeconds = 15) : bool
    {
        $start = microtime(true);

        do {
            try {
                if (call_user_func($whileCallback)) {
                    $this->logger->info("waitFor successful");

                    return true;
                }
            } catch (\Exception $e) {
                $this->logger->error($e->getMessage());
            }
            sleep(1);
        } while ((time() - $start) < $timeoutSeconds);

        $this->logger->info("waitFor failed");

        return false;
    }

}