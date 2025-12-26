<?php

namespace AwardWallet\Engine\kulula\Email;

class Confirmation extends \TAccountChecker
{
    public $mailFiles = "kulula/it-37815287.eml";

    public $reBody = [
        'en' => ['Depart', 'First name'],
    ];
    public $reSubject = [
        'your kulula confirmation',
    ];
    public $lang = '';
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

        $its = $this->parseEmail();

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => "Confirmation",
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//img[@alt='kulula.com']")->length > 0) {
            $body = $parser->getHTMLBody();

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
        return stripos($from, "kulula.com") !== false;
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
        $it['RecordLocator'] = $this->http->FindSingleNode("//text()[contains(.,'" . $this->t('kulula booking code') . "')]", null, true, "#:\s+([A-Z\d]+)#");

        if (!$it['RecordLocator']) {
            $it['RecordLocator'] = $this->http->FindSingleNode("(//text()[contains(.,'" . $this->t('kulula booking code') . "')]/following::text()[string-length(normalize-space(.))>3])[1]");
        }

        $paxinfo = $this->http->XPath->query("//*[contains(text(),'" . $this->t('First name') . "')]/ancestor::tr[count(descendant::tr)=0 and contains(.,'" . $this->t('Last name') . "')]/following-sibling::tr[count(./td)=3]");

        foreach ($paxinfo as $root) {
            $it['Passengers'][] = $this->http->FindSingleNode("./td[1]", $root) . ' ' . $this->http->FindSingleNode("./td[2]", $root);
            $it['TicketNumbers'][] = $this->http->FindSingleNode("./td[3]", $root);
        }
        $xpath = "//*[contains(text(),'" . $this->t('Depart') . "')]/ancestor::tr[count(descendant::tr)=0 and contains(.,'" . $this->t('Arrive') . "')]/ancestor::table[1]/descendant::tr[count(./td)=7]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $seg = [];

            $seg['DepCode'] = TRIP_CODE_UNKNOWN;
            $seg['DepName'] = $this->http->FindSingleNode("./td[1]", $root);
            $seg['DepDate'] = strtotime($this->http->FindSingleNode("./td[2]", $root));

            $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
            $seg['ArrName'] = $this->http->FindSingleNode("./td[3]", $root);
            $seg['ArrDate'] = strtotime($this->http->FindSingleNode("./td[4]", $root));

            $seg['Stops'] = $this->http->FindSingleNode("./td[5]", $root, true, "#\d+#");
            $seg['Cabin'] = $this->http->FindSingleNode("./td[7]", $root);

            $node = $this->http->FindSingleNode("./td[6]", $root);

            if (preg_match("#^\s*([A-Z\d]{2})\s*(\d+)\s*$#", $node, $m)) {
                $seg['AirlineName'] = $m[1];
                $seg['FlightNumber'] = $m[2];
            }

            $it['TripSegments'][] = $seg;
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
