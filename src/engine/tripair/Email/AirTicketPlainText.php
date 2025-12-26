<?php
/**
 * Created by PhpStorm.
 * User: roman.
 */

namespace AwardWallet\Engine\tripair\Email;

class AirTicketPlainText extends \TAccountChecker
{
    public $mailFiles = "tripair/it-4855653.eml, tripair/it-6212803.eml";

    private $detectBody = [
        'Thank you for having selected tripair for your e-ticket',
    ];

    private $text;

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        return [
            'parsedData' => ['Itineraries' => $this->parseEmail($parser->getPlainBody())],
            'emailType'  => 'AirTicketPlainTextEn',
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['from'], 'tripair') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'tripair') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getPlainBody();

        foreach ($this->detectBody as $dt) {
            if (stripos($body, $dt) !== false) {
                return true;
            }
        }

        return false;
    }

    private function parseEmail($text)
    {
        $its = [];
        $recLocs = $this->cutText('Airline Record Locator', 'Departure', $text);

        $dep = $this->cutText('Departure', 'Return', $text);
        $arr = $this->cutText('Return', 'Contact information', $text);
        $depArr = [$dep, $arr];

        if (preg_match('/(\w+)\s*\/\s*(\w+)[\.](?:ms|miss|mrs)/iu', $arr, $m)) {
            $psng = $m[1] . ' ' . $m[2];
        }

        if (preg_match('/Locator:\s+([A-Z\d]{5,7})\s+.*Locator return:\s+([A-Z\d]{5,7})/i', $recLocs, $m)) {
            foreach ([$m[1], $m[2]] as $i => $r) {
                /** @var \AwardWallet\ItineraryArrays\AirTrip $it */
                $it = ['Kind' => 'T'];

                $it['RecordLocator'] = $r;

                if (!empty($psng)) {
                    $it['Passengers'][] = $psng;
                }

                $re = '/\d{1,2}\/\d{2}\/(?<Year>\d{4})\s+\D+\s+\b(?<DCode>[A-Z]{3}),\s+(?<DName>.+)\s+\D*\s+';
                $re .= '(?<DDay>\d{1,2})\/(?<DMonth>\d{2})\s*(?<DTime>\d{1,2}:\d{2})\s+(?<ACode>[A-Z]{3}),\s+(?<AName>.+)';
                $re .= '\s+\D*\s+(?<ADay>\d{1,2})\/(?<AMonth>\d{2})\s*(?<ATime>\d{1,2}:\d{2})\s+(?<AirName>[A-Z\d]{1,2})\s+(?<FNum>\d+)/iu';

                if (preg_match($re, $depArr[$i], $m)) {
                    /** @var \AwardWallet\ItineraryArrays\AirTripSegment $seg */
                    $seg = [];

                    $seg['DepName'] = $m['DName'];
                    $seg['ArrName'] = $m['AName'];
                    $seg['FlightNumber'] = $m['FNum'];
                    $seg['AirlineName'] = $m['AirName'];
                    $seg['DepCode'] = $m['DCode'];
                    $seg['ArrCode'] = $m['ACode'];
                    $year = $m['Year'];
                    $seg['DepDate'] = strtotime($m['DMonth'] . '/' . $m['DDay'] . '/' . $year . ', ' . $m['DTime']);
                    $seg['ArrDate'] = strtotime($m['AMonth'] . '/' . $m['ADay'] . '/' . $year . ', ' . $m['ATime']);

                    $it['TripSegments'][] = $seg;
                }
                $its[] = $it;
            }
        }

        return $its;
    }

    private function cutText($start, $end, $text)
    {
        if (empty($start) || empty($end) || empty($text)) {
            return null;
        }

        return strstr(strstr($text, $start), $end, true);
    }
}
