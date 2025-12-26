<?php
/**
 * Created by PhpStorm.
 * User: roman.
 */

namespace AwardWallet\Engine\panorama\Email;

use PlancakeEmailParser;

class PlainText extends \TAccountChecker
{
    public $mailFiles = "panorama/it-10372089.eml";

    private $detects = [
        'is delayed till',
    ];

    private $lang = 'en';

    private $from = '/[.@]flyuia\.com/';

    private $year = 0;

    public function ParsePlanEmail(PlancakeEmailParser $parser)
    {
        $class = explode('\\', __CLASS__);
        $this->year = date('Y', strtotime($parser->getDate()));

        return [
            'emailType'  => end($class) . ucfirst($this->lang),
            'parsedData' => [
                'Itineraries' => $this->parseEmail($parser->getPlainBody()),
            ],
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && preg_match($this->from, $headers['from']);
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $parser->getPlainBody();

        if (!$this->detectEmailByHeaders($parser->getHeaders()) || !$this->detectEmailFromProvider($parser->getHeader('from'))) {
            return false;
        }

        foreach ($this->detects as $detect) {
            if (false !== stripos($body, $detect)) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match($this->from, $from);
    }

    private function parseEmail($text): array
    {
        /** @var \AwardWallet\ItineraryArrays\AirTrip $it */
        $it = ['Kind' => 'T'];

        $it['RecordLocator'] = CONFNO_UNKNOWN;

        $re = '/([A-Z\d]{2})\s*(\d+)\s*\-\s*.+zatrymuietsia\/is delayed till\s+(\d{1,2}\s*[a-zA-Z]+\s*\d{0,4})\s+([A-Z]{3})\-([A-Z]{3})\s+(\d+:\d+)/u';
        /** @var \AwardWallet\ItineraryArrays\AirTripSegment $seg */
        $seg = [];

        if (preg_match($re, $text, $m)) {
            $seg['AirlineName'] = $m[1];
            $seg['FlightNumber'] = $m[2];
            $seg['DepCode'] = $m[4];
            $seg['ArrCode'] = $m[5];
            $seg['ArrDate'] = MISSING_DATE;
            $seg['DepDate'] = strtotime($this->normalizeDate($m[3]) . ', ' . $m[6]);
        }

        $it['TripSegments'][] = $seg;

        return [$it];
    }

    private function normalizeDate(string $str)
    {
        $date = '';

        if (preg_match('/(\d{1,2})\s*([A-Z]+)\s*(\d{0,4})/', $str, $m)) {
            $date = $m[1] . ' ' . $m[2] . ' ' . $m[3];
        } elseif (!empty($this->year) && preg_match('/(\d{1,2})\s*([A-Z]+)/', $str, $m)) {
            $date = $m[1] . ' ' . $m[2] . ' ' . $this->year;
        } else {
            $date = $str;
        }

        return $date;
    }
}
