<?php

namespace AwardWallet\Engine\ufly\Email;

class BookingRegistration extends \TAccountChecker
{
    use \DateTimeTools;

    public $mailFiles = "";

    public $reBody = [
        'en' => ['check in', 'Booking'],
    ];
    public $pdf;

    public $lang = '';
    public static $dict = [
        'en' => [
            'Subject'  => 'Pre Trip Notification',
            'FindYear' => 'Your flight to',
        ],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $body = $this->http->Response['body'];

        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if (stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false) {
                    $this->lang = $lang;

                    break;
                }
            }
        }

        $its = $this->parseEmail();

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => "BookingRegistration",
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query("//text()[contains(.,'Sun Country Airlines')]")->length > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        //!!!for garbage - don't delete
        //if (($headers['subject']))
        //	$this->subj = $headers['subject'];
        //else $this->subj = 'Not isset';
        return isset($headers['subject']) && stripos($headers['subject'], $this->t('Subject')) !== false
        && isset($headers['from']) && stripos($headers['from'], 'suncountry.com') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, "suncountry.com") !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    //!!!for garbage - don't delete
    //private $subj;

    private function parseEmail()
    {
        $it = ['Kind' => 'T', 'TripSegments' => []];
        //!!!for garbage - don't delete
        //if ((($this->subj != 'Not isset') && stripos($this->subj, $this->t('Subject')) !== false)
        //	 && ($this->http->XPath->query("//div[@id='itinerary-container']")->length <= 0))
        //{
        //   return $this->noItinerariesArr();
        //}
        $it['RecordLocator'] = $this->http->FindSingleNode("//span[@id='pnr-locator']");
        $it['Passengers'] = implode(",", array_unique($this->http->FindNodes("//div[@id='itinerary-container']/table//td[contains(@id,'pax') and contains(@id,'name')]")));

        $flights = $this->http->FindNodes("//div[@id='itinerary-container']/table//td[contains(@id,'itinerary') and contains(@id,'flight-number')]");
        $seats = $this->http->FindNodes("//div[@id='itinerary-container']/table//td[contains(@id,'pax') and contains(@id,'seat')]");
        $depDate = $this->http->FindNodes("//div[@id='itinerary-container']/table//td[contains(@id,'itinerary') and contains(@id,'departure-date')]");
        $depTime = $this->http->FindNodes("//div[@id='itinerary-container']/table//td[contains(@id,'itinerary') and contains(@id,'departure-time')]");
        $arrTime = $this->http->FindNodes("//div[@id='itinerary-container']/table//td[contains(@id,'itinerary') and contains(@id,'arrival-time')]");
        $route = $this->http->FindNodes("//div[@id='itinerary-container']/table//td[contains(@id,'itinerary') and contains(@id,'arrival-city')]");

        $depTerminal = $this->http->FindNodes("//div[@id='itinerary-container']/table//td[contains(@id,'itinerary') and contains(@id,'departure-terminal')]");
        $year = $this->http->FindSingleNode("//span[contains(text(),'" . $this->t('FindYear') . "')]", null, true, "#" . $this->t('FindYear') . ".+\s+\d+\/\d+\/(\d+).+#");

        if (!$year) {
            $year = $this->http->FindSingleNode("//span[starts-with(normalize-space(.), 'New departure')]/following-sibling::span[1]", null, true, '/\d+\/\d+\/(\d+)/');
        }

        foreach ($flights as $i => $flight) {
            $segs = [];

            if (isset($route[$i]) && preg_match("#([A-Z]{3})\s+to\s+([A-Z]{3})#i", $route[$i], $m)) {
                $segs['DepCode'] = trim($m[1]);
                $segs['ArrCode'] = trim($m[2]);
            }

            if (isset($year) && isset($depDate[$i]) && isset($depTime[$i]) && preg_match("#,\s+(.+)#", $depDate[$i], $m)) {
                $segs['DepDate'] = strtotime(trim($m[1]) . " " . $year . " " . trim($depTime[$i]));
            }

            if (isset($year) && isset($depDate[$i]) && isset($arrTime[$i]) && preg_match("#,\s+(.+)#", $depDate[$i], $m)) {
                $segs['ArrDate'] = strtotime(trim($m[1]) . " " . $year . " " . trim($arrTime[$i]));
            }

            if (preg_match("#([A-Z\d]{2})\s+(\d+)#", $flight, $m)) {
                $segs['AirlineName'] = trim($m[1]);
                $segs['FlightNumber'] = trim($m[2]);
            }

            if (isset($seats[$i])) {
                $segs['Seats'] = trim($seats[$i]);
            }

            $it['TripSegments'][] = $segs;
        }

        return [$it];
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }
}
