<?php

namespace AwardWallet\Engine\webjet\Email;

class BookingSuccess extends \TAccountChecker
{
    public $mailFiles = "webjet/it-10116057.eml";

    public $reFrom = "noreply@webjet.com";
    public $reBody = [
        'en'  => ['Use this link to manage your booking', 'DEPARTING'],
        'en2' => ['Thank you for booking with', 'webjet'],
    ];
    public $reSubject = [
        '#Webjet - Your booking [A-Z\d]+ was successfully created#',
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
        ],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->AssignLang();
        $its = $this->parseEmail();

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => $a[count($a = explode('\\', __CLASS__)) - 1] . ucfirst($this->lang),
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href,'www.webjet.com')]")->length > 0) {
            return $this->AssignLang();
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], $this->reFrom) !== false && isset($this->reSubject)) {
            foreach ($this->reSubject as $reSubject) {
                if (preg_match($reSubject, $headers["subject"])) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->reFrom) !== false;
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
        $it['RecordLocator'] = $this->http->FindSingleNode("//text()[contains(.,'{$this->t('Webjet Reference')}')]/following::text()[normalize-space(.)][1]");
        $it['Passengers'] = $this->http->FindNodes("//text()[contains(.,'Passenger names') or contains(., 'Passenger Names')]/following::ul[contains(.,'ADULT') or contains(.,'CHILD')]//text()[normalize-space(.) and not (contains(.,'ADULT') or contains(.,'CHILD'))]");
        $tot = $this->getTotalCurrency($this->http->FindSingleNode("//text()[contains(.,'Total cost')]/following::text()[normalize-space(.)][1]"));

        if (!empty($tot['Total'])) {
            $it['TotalCharge'] = $tot['Total'];
            $it['Currency'] = $tot['Currency'];
        }
        $ruleTime = "contains(translate(normalize-space(.),'0123456789','dddddddddd--'),'d:dd') and translate(translate(substring(normalize-space(.),string-length(normalize-space(.))-1),'APM','apm'),'apm','ddd')='dd'";
        $xpath = "//text()[{$ruleTime}]/ancestor::tr[1]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $seg = [];

            $node = $this->http->FindSingleNode("./preceding-sibling::tr[1]/td[2]", $root);

            if (preg_match("#(.+)\s+\(([A-Z]{3})\)#", $node, $m)) {
                $seg['DepName'] = $m[1];
                $seg['DepCode'] = $m[2];
            }

            $node = $this->http->FindSingleNode("./preceding-sibling::tr[1]/td[3]", $root);

            if (preg_match("#(.+)\s+\(([A-Z]{3})\)#", $node, $m)) {
                $seg['ArrName'] = $m[1];
                $seg['ArrCode'] = $m[2];
            }

            $seg['Cabin'] = $this->http->FindSingleNode("./preceding-sibling::tr[1]/td[4]", $root);

            $node = $this->http->FindSingleNode("./td[1]", $root);

            if (preg_match("#([A-Z\d]{2})\s*(\d+)#", $node, $m)) {
                $seg['AirlineName'] = $m[1];
                $seg['FlightNumber'] = $m[2];
            }
            $seg['DepDate'] = strtotime($this->http->FindSingleNode("./td[2]", $root));
            $seg['ArrDate'] = strtotime($this->http->FindSingleNode("./td[3]", $root));
            $seg['Operator'] = $this->http->FindSingleNode("./following-sibling::tr[1]/td[contains(.,'Operating by')]", $root, true, "#Operating by\s+(?:\*?OPERATED BY\s+)?(.+)#i");

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

    private function AssignLang()
    {
        if (isset($this->reBody)) {
            $body = $this->http->Response['body'];

            foreach ($this->reBody as $lang => $reBody) {
                if (stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false) {
                    $this->lang = substr($lang, 0, 2);

                    return true;
                }
            }
        }

        return false;
    }

    private function getTotalCurrency($node)
    {
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m) || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m) || preg_match("#(?<c>\-*?)(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)) {
            $m['t'] = str_replace(" ", "", $m['t']);
            $m['t'] = preg_replace("#,(\d{3})$#", '$1', $m['t']);

            if (strpos($m['t'], ',') !== false && strpos($m['t'], '.') !== false) {
                if (strpos($m['t'], ',') < strpos($m['t'], '.')) {
                    $m['t'] = str_replace(',', '', $m['t']);
                } else {
                    $m['t'] = str_replace('.', '', $m['t']);
                    $m['t'] = str_replace(',', '.', $m['t']);
                }
            }
            $tot = str_replace(",", ".", str_replace(' ', '', $m['t']));

            if (!$cur = $m['c'] && strpos($node, '$') !== false) {
                $cur = 'USD';
            }
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }
}
