<?php

namespace AwardWallet\Engine\jetblue\Email;

class ItineraryForYourUpcomingTrip3Text extends \TAccountChecker
{
    public $mailFiles = "jetblue/it-4109335.eml";
    protected $result = [];

    private $date;

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->date = strtotime($parser->getDate());

        return [
            'parsedData' => ['Itineraries' => $this->parseEmail($parser->getHTMLBody())],
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], '@jetblue.com') !== false
                && isset($headers['subject']) && stripos($headers['subject'], 'Fwd: Itinerary for your upcoming trip') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return strpos($parser->getHTMLBody(), 'Please find below your Finnair flight and service information.') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@jetblue.com') !== false;
    }

    protected function parseEmail($htmlBody)
    {
        $this->result['Kind'] = 'T';

        $recordLocator = $this->findСutSection($htmlBody, 'Your confirmation number is ', PHP_EOL);

        if (preg_match('/[A-Z\d]{5,6}/', $recordLocator)) {
            $this->result['RecordLocator'] = $recordLocator;
        }

        $this->parsePassengers($this->findСutSection($htmlBody, 'Ticket number(s)', 'Please click here'));
        $this->parseSegments($this->findСutSection($htmlBody, 'Terminal', 'For a detailed receipt, select a customer'));

        return [$this->result];
    }

    protected function parsePassengers($htmlBody)
    {
        $array = preg_split('/\n{1,}/', strip_tags($htmlBody), -1, PREG_SPLIT_NO_EMPTY);

        foreach ($array as $value) {
            if (preg_match('/([a-z\s]+)\s+(\d+)/i', $value, $match)) {
                $this->result['Passengers'][] = trim($match[1]);
                $this->result['AccountNumbers'][] = $match[2];
            }
        }
    }

    protected function parseSegments($htmlBody)
    {
        $array = preg_split('/\n{2,}/', $htmlBody, -1, PREG_SPLIT_NO_EMPTY);

        foreach ($array as $value) {
            $this->result['TripSegments'][] = $this->iterSegments($value);
        }
    }

    /**
     * Example text:
     * <pre>
     * Sat,
     * May 02  7:08 a.m.
     * 9:13 a.m.       NEW YORK JFK, NY (JFK)  to
     * CHARLESTON SC, SC (CHS)         1273
     * [B6]    Gabriel Corydon Suprise         N/A     Select seat<http://myflights.jetblue.com/B6.myb/>       5
     * Ryan Thomas Bolger      N/A     Select seat<http://myflights.jetblue.com/B6.myb/>
     * </pre>.
     *
     * @param type $text
     *
     * @return type
     */
    protected function iterSegments($text)
    {
        $segment = [];

        if (preg_match('/[a-z]{3},\s*[a-z]{3}\s+\d{1,2}/i', $text, $match)) {
            $this->date = strtotime($match[0], $this->date);
        }

        if (preg_match_all('/\d+:\d+\s+[amp.]{4}/i', $text, $match)) {
            $match = end($match);
            $segment['DepDate'] = strtotime($match[0], $this->date);
            $segment['ArrDate'] = strtotime($match[1], $this->date);
        }

        if (preg_match('/([A-Z(),\s]+)\s+to\s+([A-Z(),\s]+)\s+(\d{3,4})/', $text, $match)) {
            $segment['FlightNumber'] = $match[3];
            $segment['AirlineName'] = AIRLINE_UNKNOWN;

            if (preg_match('/\(([A-Z]{3})\)/', $match[1], $m)) {
                $segment['DepName'] = trim($match[1]);
                $segment['DepCode'] = $m[1];
            }

            if (preg_match('/\(([A-Z]{3})\)/', $match[2], $m)) {
                $segment['ArrName'] = trim($match[2]);
                $segment['ArrCode'] = $m[1];
            }
        }

        return $segment;
    }

    /**
     * Quick change preg_match. 1000 iterations takes 0.0008 seconds,
     * while similar <i>preg_match('/FLIGHT\n(.*)Price details')</i> 0.300 sec.
     * <p>
     * Example:
     * <b>FLIGHT</b> <i>cut text</i> <b>Price details</b>.
     *
     * @param type $input
     * @param type $searchStart
     * @param type $searchFinish
     *
     * @return type
     */
    protected static function findСutSection($input, $searchStart, $searchFinish)
    {
        $input = mb_strstr(mb_strstr($input, $searchStart), $searchFinish, true);

        return mb_substr($input, mb_strlen($searchStart));
    }
}
