<?php

require_once __DIR__ . '/../algerie/functions.php';

class TAccountCheckerRwandair extends TAccountCheckerAlgerieAero
{
    public $code = 'dreammiles';

    public function getStatus($tier)
    {
        $this->logger->debug("Tier: {$tier}");

        switch ($tier) {
            case 'EMER':
                $status = 'Emerald';

                break;

            case 'SILV':
                $status = 'Silver';

                break;
//            case 'GOLD':
//                $status = 'GOLD';
//                break;
//            case 'EXEC':
//                $status = 'EXECUTIVE GOLD';
//                break;
//            case 'EXEC':
//                $status = 'Diamond';
//                break;
            default:
                $status = '';
                $this->sendNotification("{$this->AccountFields['ProviderCode']}, New status was found: {$tier}");
        }

        return $status;
    }
}
