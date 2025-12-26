<?php

namespace AwardWallet\Engine\piu\Email;

class TTicket extends \TAccountChecker
{
    public $mailFiles = "piu/it-3234444.eml";

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $its = $this->parseEmail();

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => 'TTicket',
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        return !empty($headers['from']) && (stripos($headers['from'], 'piu@mail.italotreno.it')) !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query("//text()[contains(normalize-space(.), 'grazie per aver scelto di viaggiare con Italo')]")->length > 0
        || $this->http->XPath->query("//text()[contains(normalize-space(.), 'thanks for choosing to travel with Italo')]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'piu@mail.italotreno.it') !== false || stripos($from, 'italo@mail.italotreno.it') !== false;
    }

    protected function parseEmail()
    {
        $it = ['Kind' => 'T', 'TripSegments'=>[], 'TripCategory' => TRIP_CATEGORY_TRAIN];
        $it['RecordLocator'] = $this->http->FindSingleNode("//strong[contains(normalize-space(.), 'Ticket code:')]/following-sibling::strong");
        $seg = [];
        $data = $this->http->FindSingleNode("//text()[contains(normalize-space(.), 'Departure date:')]/following-sibling::strong");

        if (preg_match("/(?<day>[\d]{2})\/(?<month>[\d]{2})\/(?<year>[\d]{4})/", $data, $m)) {
            $data = $m['month'] . '/' . $m['day'] . '/' . $m['year'];
        }
        $seg['DepName'] = $this->http->FindSingleNode("//text()[contains(normalize-space(.), 'Departure:')]/following::td[1]");
        $seg['DepCode'] = TRIP_CODE_UNKNOWN;
        $seg['ArrName'] = $this->http->FindSingleNode("//text()[contains(normalize-space(.), 'Arrival:')]/following::td[1]");
        $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
        $seg['FlightNumber'] = $this->http->FindSingleNode("//text()[contains(normalize-space(.), 'Train number:')]/following-sibling::strong");
        $timeDep = $this->http->FindSingleNode("//text()[contains(normalize-space(.), 'Departure time:')]/following-sibling::strong");
        $timeArr = $this->http->FindSingleNode("//text()[contains(normalize-space(.), 'Arrival time:')]/following-sibling::strong");

        if (!empty($data) && !empty($timeDep) && !empty($timeArr)) {
            $seg['DepDate'] = strtotime($data . ' ' . $timeDep);
            $seg['ArrDate'] = strtotime($data . ' ' . $timeArr);
        }
        $seg['Seats'] = $this->http->FindSingleNode("//strong[contains(normalize-space(.), 'Seat')]/following::strong[2]");
        $it['TripSegments'][] = $seg;

        return [$it];
    }
}
