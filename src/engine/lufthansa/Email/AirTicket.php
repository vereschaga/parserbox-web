<?php
/**
 * Created by PhpStorm.
 * User: Roman.
 */

namespace AwardWallet\Engine\lufthansa\Email;

class AirTicket extends \TAccountChecker
{
    public $mailFiles = "lufthansa/it-4320599.eml";

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $its = $this->parseEmail();

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => 'AirTicketEs',
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query("//a[contains(@href, 'lufthansa.com')]")->length > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], '@fly-lh.lufthansa.com') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@fly-lh.lufthansa.com') !== false;
    }

    public static function getEmailLanguages()
    {
        return ['es'];
    }

    private function parseEmail()
    {
        $it = ['Kind' => 'T', 'TripSegments' => []];
        $it['RecordLocator'] = CONFNO_UNKNOWN;
        $it['Passengers'][] = $this->http->FindSingleNode("//*[contains(text(), 'Pasajero')]", null, true, '#.*:\s+([\w\s,]+)#');
        $xpath = "//*[contains(text(), 'Número de vuelo')]/ancestor::tr[2]/following-sibling::tr[normalize-space(.)!='' and 1]";
        $roots = $this->http->XPath->query($xpath);

        if ($roots->length === 0) {
            $this->logger->info('Segments not found: ' . $xpath);
        }

        foreach ($roots as $root) {
            $seg = [];

            if (preg_match('#(\D{2})\s*(\d+)#', $this->http->FindSingleNode('td[1]', $root), $m)) {
                $seg['AirlineName'] = $m[1];
                $seg['FlightNumber'] = $m[2];
            }
            $seg['DepName'] = $this->http->FindSingleNode('td[2]', $root);
            $seg['ArrName'] = $this->http->FindSingleNode('td[3]', $root);
            $date = $this->normalizeDate($this->http->FindSingleNode('td[4]', $root));
            $seg['DepDate'] = strtotime(($date) . ' ' . $this->http->FindSingleNode('td[5]', $root));
            $seg['ArrDate'] = strtotime($date);

            if (!empty($seg['DepDate']) && !empty($seg['ArrDate']) && !empty($seg['FlightNumber'])) {
                $seg['DepCode'] = $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
            }
            $it['TripSegments'][] = $seg;
        }

        return [$it];
    }

    private function normalizeDate($str)
    {
        $in = [
            '#(\d{2})\.(\d{2})\.(\d{4})#',
        ];
        $out = [
            '$2/$1/$3',
        ];

        return preg_replace($in, $out, $str);
    }
}
