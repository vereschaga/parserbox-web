<?php

namespace AwardWallet\ExtensionWorker;

use AwardWallet\Schema\Parser\Component\Master;

interface RetrieveByConfNoInterface
{

    public function retrieveByConfNo(Tab $tab, Master $master, array $fields, ConfNoOptions $options) : void;

}