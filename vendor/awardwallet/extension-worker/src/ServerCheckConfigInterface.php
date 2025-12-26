<?php

namespace AwardWallet\ExtensionWorker;

interface ServerCheckConfigInterface
{

    /**
     * @param AccountOptions|null $accountOptions - could be null if this is confirmation number check
     * @param \SeleniumFinderRequest $seleniumRequest - request to selenium finder, leave as is, only chrome-extension is supported for now
     * @return bool - should we use server check v3 for this account
     */
    public function configureServerCheck(?AccountOptions $accountOptions, \SeleniumFinderRequest $seleniumRequest, \SeleniumOptions $seleniumOptions): bool;

}