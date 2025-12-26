<?php

namespace AwardWallet\Engine\goibibo\Email;

class ScheduleChange extends \TAccountChecker
{
    public $mailFiles = "goibibo/it-8912360.eml, goibibo/it-8912366.eml";
    public $reFrom = "goibibo.com";
    public $reSubject = [
        "Schedule change",
    ];
    public $reBody = 'goibibo.com';
    public $reBody2 = [
        'schedule change/flight number change',
    ];
    public $date;

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->date = \AwardWallet\Common\Parser\Util\EmailDateHelper::calculateOriginalDate($this, $parser);

        if (empty($this->date)) {
            return false;
        }

        $its = $this->parseEmailHTML();

        $result = [
            'emailType'  => 'ScheduleChange',
            'parsedData' => [
                'Itineraries' => $its,
            ],
        ];

        return $result;
    }

    public function detectEmailFromProvider($from)
    {
        return isset($this->reFrom) && strpos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (!isset($headers["from"]) || stripos($headers["from"], $this->reFrom) === false) {
            return false;
        }

        foreach ($this->reSubject as $re) {
            if (isset($headers["subject"]) && stripos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return true;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (stripos($body, $this->reBody) === false) {
            return false;
        }

        foreach ($this->reBody2 as $re) {
            if (stripos($body, $re) !== false) {
                return true;
            }
        }

        return false;
    }

    private function parseEmailHTML()
    {
        $its = [];
        $result = [];
        $xpath = "(//text()[contains(.,'Departure Date')]/ancestor::tr[contains(normalize-space(.), 'From Airport')])[1]/following-sibling::tr";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $itsegment = [];
            $timeDep = $this->http->FindSingleNode("./td[5]", $root);
            $timeArr = $this->http->FindSingleNode("./td[7]", $root);

            if (empty($timeDep) && empty($timeArr) && !empty($this->http->FindSingleNode("./td[9][contains(normalize-space(.), 'Flown')]", $root))) {
                continue;
            }

            $itsegment['AirlineName'] = $this->http->FindSingleNode("./td[1]", $root, true, "#\(([A-Z\d]{2})\)#");
            $itsegment['FlightNumber'] = $this->http->FindSingleNode("./td[2]", $root, true, "#(\d+)#");

            $node = $this->http->FindSingleNode("./td[4]", $root);

            if (preg_match("#(.+)\(([A-Z]{3})\)\s*$#", $node, $m)) {
                $itsegment['DepName'] = trim($m[1]);
                $itsegment['DepCode'] = $m[2];
            }

            $timeDep = preg_replace("#^(\d{2})(\d{2})$#", "$1:$2", $timeDep);
            $node = $this->http->FindSingleNode("./td[3]", $root);

            if (preg_match("#(\d+)([A-Z]+)#i", $node, $m)) {
                $itsegment['DepDate'] = \AwardWallet\Common\Parser\Util\EmailDateHelper::parseDateRelative($timeDep . ' ' . $m[1] . ' ' . $m[2], $this->date);
            }

            $node = $this->http->FindSingleNode("./td[6]", $root);

            if (preg_match("#(.+)\(([A-Z]{3})\)\s*$#", $node, $m)) {
                $itsegment['ArrName'] = trim($m[1]);
                $itsegment['ArrCode'] = $m[2];
            }

            $timeArr = preg_replace("#^(\d{2})(\d{2})$#", "$1:$2", $timeArr);
            $node = $this->http->FindSingleNode("./td[3]", $root);

            if (preg_match("#(\d+)([A-Z]+)#i", $node, $m)) {
                $itsegment['ArrDate'] = \AwardWallet\Common\Parser\Util\EmailDateHelper::parseDateRelative($timeArr . ' ' . $m[1] . ' ' . $m[2], $this->date);
            }

            $rl = $this->http->FindSingleNode("./td[8]", $root, true, "#/([A-Z\d]{5,7})#");
            $result[$rl][] = $itsegment;
        }
        $TripNumber = $this->http->FindSingleNode("//text()[contains(normalize-space(.),'the booking Id')][1]", null, true, "#the\s+booking\s+Id\s+([A-Z\d]+)#");

        foreach ($result as $key => $value) {
            $it = [];
            $it['Kind'] = 'T';
            $it['RecordLocator'] = $key;
            $it['TripNumber'] = $TripNumber;
            $it['TripSegments'] = $value;
            $its[] = $it;
        }

        return $its;
    }
}
