<?php
/**
 * Created by PhpStorm.
 * User: Роман
 * Date: 16.03.2016
 * Time: 16:51.
 */

namespace AwardWallet\Engine\piu\Email;

class TrainTicket extends \TAccountChecker
//2669005
{
    public $mailFiles = "piu/it-2669005.eml, piu/it-2669007.eml";

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $its = $this->parseEmail();

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => 'TrainTicket',
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        return !empty($headers['from']) && (stripos($headers['from'], 'piu@mail.italotreno.it')) !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query("//text()[contains(normalize-space(.), 'grazie per aver scelto di viaggiare con Italo')]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'piu@mail.italotreno.it') !== false;
    }

    public static function getEmailLanguages()
    {
        return ['it'];
    }

    protected function parseEmail()
    {
        $it = ['Kind' => 'T', 'TripSegments'=>[], 'TripCategory' => TRIP_CATEGORY_TRAIN];
        $it['RecordLocator'] = $this->http->FindSingleNode("//strong[contains(normalize-space(.), 'Codice Biglietto:')]/following-sibling::strong");
        $seg = [];
        $data = $this->http->FindSingleNode("//text()[contains(normalize-space(.), 'Data partenza:')]/following-sibling::strong");

        if (preg_match("/(?<day>[\d]{2})\/(?<month>[\d]{2})\/(?<year>[\d]{4})/", $data, $m)) {
            $data = $m['month'] . '/' . $m['day'] . '/' . $m['year'];
        }
        $seg['DepName'] = $this->http->FindSingleNode("//text()[contains(normalize-space(.), 'Partenza:')]/following::td[1]");
        $seg['DepCode'] = TRIP_CODE_UNKNOWN;
        $seg['ArrName'] = $this->http->FindSingleNode("//text()[contains(normalize-space(.), 'Arrivo:')]/following::td[1]");
        $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
        $seg['FlightNumber'] = $this->http->FindSingleNode("//text()[contains(normalize-space(.), 'Numero treno:')]/following-sibling::strong");
        $timeDep = $this->http->FindSingleNode("//text()[contains(normalize-space(.), 'Orario partenza:')]/following-sibling::strong");
        $timeArr = $this->http->FindSingleNode("//text()[contains(normalize-space(.), 'Orario arrivo:')]/following-sibling::strong");

        if (!empty($data) && !empty($timeDep) && !empty($timeArr)) {
            $seg['DepDate'] = strtotime($data . ' ' . $timeDep);
            $seg['ArrDate'] = strtotime($data . ' ' . $timeArr);
        }
        $seg['Seats'] = $this->http->FindSingleNode("//strong[contains(normalize-space(.), 'Posto')]/following::strong[2]");
        $it['TripSegments'][] = $seg;

        return [$it];
    }
}
