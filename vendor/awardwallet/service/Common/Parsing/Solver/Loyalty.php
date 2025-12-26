<?php


namespace AwardWallet\Common\Parsing\Solver;


use AwardWallet\Common\Parsing\Solver\Extra\Extra;
use AwardWallet\Schema\Parser\Common\Statement;
use Doctrine\DBAL\Connection;

class Loyalty
{

    /**
     * @var Connection $connection
     */
    protected $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function solve(Statement $st, Extra $extra) {
        /** @var \TAccountChecker $checker */
        $extra->provider->historyFields = self::getHistoryFields($extra->provider->code);
        $properties = $this->connection->executeQuery(
            'select Code, Kind, Type, Name from ProviderProperty where ProviderID in (select ProviderID from Provider where Code = ?) or ProviderID is null',
                [$extra->provider->code])->fetchAllAssociative();
        $extra->provider->properties = [];
        foreach($properties as $row) {
            $extra->provider->properties[$row['Code']] = $row;
        }
        $st->loadProviderProperties($extra->provider->code, $properties);
        $st->validateProperties();
    }
    
    public static function getHistoryFields(string $providerCode) {
        $class = sprintf('\TAccountChecker%s', ucfirst($providerCode));
        if (class_exists($class)) {
            $checker = new $class();
            return $checker->GetHistoryColumns() ?? [];
        }
        
        return [];
    }

    /**
     * @return array[] - ['HistoryColumns' => [..], 'HiddenColumns' => [..]]
     */
    public static function getCheckerHistoryParameters(string $providerCode) : array 
    {
        $class = sprintf('\TAccountChecker%s', ucfirst($providerCode));
        if (class_exists($class)) {
            /** @var \TAccountChecker $checker */
            $checker = new $class();
            return [
                'HistoryColumns' => $checker->GetHistoryColumns(),
                'HiddenColumns' => $checker->GetHiddenHistoryColumns(),
            ];
        }
        
        return [
            'HistoryColumns' => [],
            'HiddenColumns' => [],
        ];
    }

}