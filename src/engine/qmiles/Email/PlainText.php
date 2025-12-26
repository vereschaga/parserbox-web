<?php
/**
 * Created by PhpStorm.
 * User: roman.
 */

namespace AwardWallet\Engine\qmiles\Email;

use AwardWallet\Engine\MonthTranslate;

class PlainText extends \TAccountChecker
{
    public $mailFiles = "qmiles/it-6884191.eml";

    private $detects = [
        'la informacio dels dies i les hores dels vols',
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $text = $parser->getPlainBody();
        $text = str_replace('>', '', $text);

        return [
            'emailType'  => 'AirPlainText',
            'parsedData' => [
                'Itineraries' => $this->parseEmail($text),
            ],
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['from'], 'qatarairways.com.qa') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'qatarairways.com.qa') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        foreach ($this->detects as $detect) {
            if (stripos($body, $detect) !== false) {
                return true;
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return ['es'];
    }

    private function parseEmail($text)
    {
        /** @var \AwardWallet\ItineraryArrays\AirTrip $it */
        $it = ['Kind' => 'T'];
        $segText = $this->cutText('Confirmación de reserva', 'Vuelo de ida', $text);

        if (preg_match('/reserva\s+-\s+([A-Z\d]{5,7})\s+.+\s+-\s+(\w+)\s+dear\s+(.+)\,/iu', $segText, $m)) {
            $it['RecordLocator'] = $m[1];
            $it['Status'] = $m[2];
            $it['Passengers'][] = str_replace('.', '', $m[3]);
        }

        $segments = preg_split('/\s+\b([A-Z\d]{2}\s+\d+)\b\s+/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        array_pop($segments);
        $flights = [];
        $segs = [];
        $date = '';

        foreach ($segments as $i => $segment) {
            if ($i % 2 !== 0) {
                $flights[] = $segment;
            } else {
                $segs[] = $segment;
            }
        }

        foreach ($segs as $i => $seg) {
            /** @var \AwardWallet\ItineraryArrays\AirTripSegment $segm */
            $segm = [];

            if (preg_match('/([A-Z\d]{2})\s*(\d+)/', $flights[$i], $m)) {
                $segm['AirlineName'] = $m[1];
                $segm['FlightNumber'] = $m[2];
            }

            if (preg_match('/(\d{1,2}\s+\w+\s+\d{4})/', $seg, $m)) {
                $date = $this->normalizeDate($m[1]);
            }

            $re = '/(\d{1,2}:\d{2})\s+([A-Z]{3})\s+(?:(\+\d)\s*\w+\s+)?(.+)\s+[\S\s]+(\d+\s*\w\s+\d+\s*\w)\s+(\d{1,2}:\d{2})\s+([A-Z]{3})\s+(?:(\+\d)\s*\w+\s+)?(.+)\s+/u';

            if (preg_match($re, $seg, $m)) {
                $segm['DepCode'] = $m[2];
                $segm['DepName'] = $m[4];
                $segm['Duration'] = $m[5];
                $segm['ArrCode'] = $m[7];
                $segm['ArrName'] = $m[9];
                $segm['DepDate'] = strtotime($date . ', ' . $m[1]);
                $segm['ArrDate'] = strtotime($date . ', ' . $m[6]);
            }

            $it['TripSegments'][] = $segm;
        }

        return [$it];
    }

    private function normalizeDate($str)
    {
        $re = [
            '/(\d{1,2})\s+(\w+)\s+(\d{4})/',
        ];

        foreach ($re as $regExp) {
            if (preg_match($regExp, $str, $m)) {
                return $m[1] . ' ' . MonthTranslate::translate($m[2], 'es') . ' ' . $m[3];
            }
        }

        return $str;
    }

    private function cutText($start, $end, $text)
    {
        if (!empty($start) && !empty($end) && !empty($text)) {
            $txt = stristr(stristr($text, $start), $end, true);

            return substr($txt, strlen($start));
        }

        return false;
    }
}
