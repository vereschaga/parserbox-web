<?php

namespace AwardWallet\ExtensionWorker;

interface LoginWithConfNoInterface
{

    /**
     * @param string[] $confNoFields - provider-specific fields, like: ["RecordLocator" => "ABC123", "LastName" => "Doe"]
     */
    public function getLoginWithConfNoStartingUrl(array $confNoFields, ConfNoOptions $options) : string;

    /**
     * @return string - errorMessage or null if login was successful
     */
    public function loginWithConfNo(Tab $tab, array $confNoFields, ConfNoOptions $options) : LoginWithConfNoResult;

}