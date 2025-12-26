<?php
/**
 * Created by PhpStorm.
 * User: Roman.
 */

namespace AwardWallet\Engine\lufthansa\Email;

class AirTravel extends \TAccountChecker
{
    public $mailFiles = "lufthansa/it-4493729.eml";

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $its = $this->parseEmail();

        return [
            'parsedData' => ['Itineraries' => $its],
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query("//a[contains(@href, 'lufthansa-group.com')]")->length > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], 'lufthansa.com') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'lufthansa.com') !== false;
    }

    public static function getEmailLanguages()
    {
        return ['en'];
    }

    public static function getEmailTypesCount()
    {
        return 1;
    }

    private function parseEmail()
    {
        $it = ['Kind' => 'T', 'TripSegments' => []];
        $it['RecordLocator'] = $this->http->FindSingleNode("//tr[count(td)=2]/td[contains(normalize-space(.), 'Booking code')]/following-sibling::td[normalize-space(.)!=''][1]");
        $airNameAnFlightNum = $this->http->FindSingleNode("//tr[contains(normalize-space(.), 'Your flight booking')]/following-sibling::tr[5]/td[1]");
        $seg = [];

        if (preg_match('#(\w{2})\s*(\d+)#', $airNameAnFlightNum, $m)) {
            $seg['AirlineName'] = $m[1];
            $seg['FlightNumber'] = $m[2];
        }
        $date = $this->http->FindSingleNode("//tr[count(td)=2]/td[contains(normalize-space(.), 'Booking code')]/ancestor::tr[2]/following-sibling::tr[2]", null, true, '#.+(\d{2}\.\d+\.\d+)#');

        if (preg_match('#(\d{2})\.(\d{2})\.(\d{2,4})#', $date, $math)) {
            $dateWithoutTime = strtotime($math['2'] . '/' . $math[1] . '/' . $math[3]);
            $seg['DepDate'] = $dateWithoutTime;
            $seg['ArrDate'] = $dateWithoutTime;
        }
        $depArrName = $this->http->FindSingleNode("//tr[count(td)=2]/td[contains(normalize-space(.), 'Booking code')]/ancestor::tr[2]/following-sibling::tr[3]/descendant::tr[count(td)=2]/td[1][normalize-space(.)!='']");

        if (preg_match('#((?:\w+|[\w\s]*))\s+\S+\s+((?:\w+|[\w\s]*))#', $depArrName, $mathec)) {
            $seg['DepName'] = $mathec[1];
            $seg['ArrName'] = $mathec[2];
        }
        $seg['DepCode'] = TRIP_CODE_UNKNOWN;
        $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
        $it['TripSegments'][] = $seg;

        return [$it];
    }
}
