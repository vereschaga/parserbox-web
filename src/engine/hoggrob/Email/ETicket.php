<?php

namespace AwardWallet\Engine\hoggrob\Email;

class ETicket extends \TAccountChecker
{
    public $mailFiles = "hoggrob/it-5662483.eml";

    public $reBody = [
        'en' => ['ELECTRONIC TICKET', 'Consultant\'s Name'],
    ];
    public $reSubject = [
        'ELECTRONIC TICKET for',
    ];
    public $lang = '';
    public $pdf;
    public static $dict = [
        'en' => [
        ],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $body = $this->http->Response['body'];

        foreach ($this->reBody as $lang => $reBody) {
            if (stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false) {
                $this->lang = $lang;

                break;
            }
        }
//        echo $this->lang;
        $its = $this->parseEmail();

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => "ETicket",
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//img[contains(@src,'www.travelport') and @alt='Logo of the Agency']")->length > 0) {
            $body = $this->http->Response['body'];

            foreach ($this->reBody as $lang => $reBody) {
                if (stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($this->reSubject)) {
            foreach ($this->reSubject as $reSubject) {
                if (stripos($headers["subject"], $reSubject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, "hrgworldwide.com") !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function parseEmail()
    {
        $it = ['Kind' => 'T', 'TripSegments' => []];
        $it['RecordLocator'] = $this->http->FindSingleNode("//text()[contains(normalize-space(.),'" . $this->t('Reference:') . "')]/ancestor::*[1]/following::*[1]");
        $it['Passengers'] = $this->http->FindNodes("//text()[contains(normalize-space(.),'" . $this->t('Passengers:') . "')]/ancestor::tr[1]/following-sibling::tr/td[1]");
        $it['TicketNumbers'] = array_filter(array_map("trim", explode("/", implode("/", $this->http->FindNodes("//text()[contains(normalize-space(.),'" . $this->t('Passengers:') . "')]/ancestor::tr[1]/following-sibling::tr/td[3]")))));

        $xpath = "//text()[starts-with(normalize-space(.),'" . $this->t('Flight:') . "') and contains(.,'" . $this->t('to') . "')]/ancestor::div[1]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $seg = [];
            $seg['FlightNumber'] = $this->re("#^\s*[A-Z\d]{2}\s*(\d+)\s*$#", $this->getField($this->t('Flight:'), $root));
            $seg['AirlineName'] = $this->re("#^\s*([A-Z\d]{2})\s*\d+\s*$#", $this->getField('Flight:', $root));
            $date = strtotime($this->re("#^\s*(\d+\.\d+\.\d+)\s*\(#", $this->getField('Date:', $root)));

            $seg['DepDate'] = strtotime($this->re("#^\s*(\d+:\d+)\s*$#", $this->getField('Departs:', $root)), $date);

            if ($this->re("#\(\s*(\d+\.\d+\.\d+)\s*\)#", $this->getField('Arrives:', $root))) {
                $date = strtotime($this->re("#\(\s*(\d+\.\d+\.\d+)\s*\)#", $this->getField('Arrives:', $root)));
            }
            $seg['ArrDate'] = strtotime($this->re("#^\s*(\d+:\d+)\s*#", $this->getField('Arrives:', $root)), $date);

            $seg['DepCode'] = TRIP_CODE_UNKNOWN;
            $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
            $seg['DepName'] = $this->getField('From:', $root) . '-' . $this->re("#(.+?)\s*\(#", $this->getField('Airport:', $root));
            $seg['ArrName'] = $this->getField('To:', $root) . '-' . $this->re("#(.+?)\s*\(#", $this->getField('Airport:', $root, 2));
            $seg['DepartureTerminal'] = $this->re("#\s*\(\s*Terminal\s*(.*?)\s*\)#", $this->getField('Airport:', $root));
            $seg['ArrivalTerminal'] = $this->re("#\s*\(\s*Terminal\s*(.*?)\s*\)#", $this->getField('Airport:', $root, 2));

            $seg['Operator'] = $this->getField('Operated by:', $root);
            $seg['Duration'] = $this->getField('Duration:', $root);
            $seg['Aircraft'] = $this->getField('Aircraft:', $root);
            $seg['Cabin'] = $this->getField('Travel Class:', $root);
            $seg['BookingClass'] = $this->getField('Booking Class:', $root);
            $seg = array_filter($seg);

            $seg['Stops'] = $this->getField('Stops:', $root);
            $it['TripSegments'][] = $seg;
        }

        return [$it];
    }

    private function getField($field, $root = null, $n = 1)
    {
        return $this->http->FindSingleNode("./descendant::td[contains(.,'{$field}')][{$n}]/following-sibling::td[1]", $root);
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }
}
