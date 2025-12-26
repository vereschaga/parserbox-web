<?php

require_once __DIR__ . '/../algerie/functions.php';

class TAccountCheckerTaag extends TAccountCheckerAlgerieAero
{
    public $code = "umbiumbiclub";

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
//        $this->setProxyMount();
    }

    public function getStatus($tier)
    {
        $this->logger->debug("Tier: {$tier}");

        switch ($tier) {
            case 'CLAS':
                $status = 'CLASSIC';

                break;

            case 'SLVR':
                $status = 'SILVER';

                break;

            case 'GOLD':
                $status = 'GOLD';

                break;

            default:
                $this->sendNotification("New status was found: {$tier}");
                $status = '';
        }

        return $status;
    }

    public function getName($response)
    {
        // Name
        return beautifulName(ArrayVal($response, 'name') . " " . ArrayVal($response, 'surname'));
    }
}
