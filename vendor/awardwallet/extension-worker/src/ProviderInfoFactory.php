<?php

namespace AwardWallet\ExtensionWorker;

use Doctrine\DBAL\Connection;

class ProviderInfoFactory
{

    private Connection $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function createProviderInfo(string $providerCode): ProviderInfo
    {
        $row = $this->connection->fetchAssociative("select DisplayName, ShortName from Provider where Code = ?", [$providerCode]);

        return new ProviderInfo($row["DisplayName"], $row["ShortName"]);
    }

}