<?php

namespace AwardWallet\Engine\priceline\Email;

class ConnectionUpdate extends \TAccountChecker
{
    public $mailFiles = "priceline/it-32382797.eml, priceline/it-9808398.eml, priceline/it-9834281.eml, priceline/it-9903818.eml";

    private $reFrom = "trans@priceline.com";
    private $reProvider = "@priceline.com";
    private $reSubject = [
        "en"  => "Connection Update Notification for",
        "en2" => "Connecting flight gate change notification for",
    ];
    private $reBody = 'Priceline';
    private $reBody2 = [
        "en"  => "Flight Update",
        "en2" => "Flight is Now Departing from Gate",
    ];

    private $emailSubject;

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->reProvider) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['from'], $this->reFrom) !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (strpos($body, $this->reBody) === false) {
            return false;
        }

        foreach ($this->reBody2 as $re) {
            if (strpos($body, $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->emailSubject = $parser->getSubject();
        $it = $this->parseEmail();

        return [
            'emailType'  => 'ConnectionUpdate',
            'parsedData' => [
                'Itineraries' => [$it],
            ],
        ];
    }

    public static function getEmailLanguages()
    {
        return ['en'];
    }

    public static function getEmailTypesCount()
    {
        return 2;
    }

    public function IsEmailAggregator()
    {
        return false;
    }

    protected function normalizeDate($str)
    {
//        $this->logger->debug('$str = '.print_r( $str,true));
        $in = [
            // Monday, November 06 2017 at 3:30 PM
            // Monday, November 29, 2021 at 6:30 PM
            "#^\s*[^\d\s]+,\s+([^\d\s]+)\s+(\d+)[,]?\s+(\d{4})\s+at\s+(\d+:\d+\s*[AP]M)$#",
        ];
        $out = [
            "$2 $1 $3 $4",
        ];
        $str = preg_replace($in, $out, $str);
//        $this->logger->debug('$str = '.print_r( $str,true));
        //		if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)){
        //			if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
        //				$str = str_replace($m[1], $en, $str);
        //			}
        //		}
        return $str;
    }

    private function parseEmail()
    {
        $it = [];
        $it['Kind'] = 'T';

        $it['RecordLocator'] = CONFNO_UNKNOWN;
        $it['TripNumber'] = $this->http->FindSingleNode('//td[contains(normalize-space(.),"trip number is") and not(.//td)]', null, true, '/trip\s+number\s+is\s*([-\d]+)/i');

        $seg = [];
        $dep = $this->http->FindSingleNode("(//text()[contains(normalize-space(.), 'Departing:') or contains(normalize-space(.), 'Departure:') or normalize-space(.) = 'Departs:']/ancestor::td[1]/following-sibling::td[1])[last()]");

        if (preg_match("#^\s*(?:(?:\w+ )?(?:Departing|Departure|Departs)\s+)?(.+)\s*\(([A-Z]{3})\)\s*(.+)#", $dep, $m)) {
            $seg['DepName'] = trim($m[1]);
            $seg['DepCode'] = $m[2];
            $seg['DepDate'] = strtotime($this->normalizeDate($m[3]));
            $seg['ArrDate'] = MISSING_DATE;
        }

        $seg['DepartureTerminal'] = trim(preg_replace("#\s*Terminal\s*#i", '', $this->http->FindSingleNode("//text()[contains(normalize-space(.), 'Terminal:')]/ancestor::td[1]/following-sibling::td[1]")));
        if (empty($seg['DepartureTerminal'])) {
            $seg['DepartureTerminal'] = trim(preg_replace("#\s*Terminal\s*#i", '', $this->http->FindSingleNode("//text()[contains(normalize-space(.), 'Current Terminal:')]/ancestor::td[1]/following-sibling::td[1]")));
        }

        $flight = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Departing:') or normalize-space(.) = 'Departs:']/preceding::text()[contains(translate(normalize-space(.),'0123456789','dddddddddd'), ' Flight d')][1]");

        if (preg_match("#(.+)\s+Flight\s+(\d{1,5})#", $flight, $m)) {
            $seg['AirlineName'] = $m[1];
            $seg['FlightNumber'] = $m[2];
        }

        if (preg_match("#[Nn]otification for\s+(.+)\s+Flight\s+(\d{1,5})\s+departing\s+from\s+([A-Z]{3})\s+to\s+([A-Z]{3})#", $this->emailSubject, $m)) {
            $seg['AirlineName'] = $m[1];
            $seg['FlightNumber'] = $m[2];
            $seg['DepCode'] = $m[3];
            $seg['ArrCode'] = $m[4];
        }
        $it['TripSegments'][] = $seg;

        return $it;
    }
}
