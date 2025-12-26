<?php

namespace AwardWallet\Engine\gulfair\Email;

class TravelItinerary extends \TAccountChecker
{
    use \DateTimeTools;
    public $mailFiles = "gulfair/it-4969085.eml, gulfair/it-4969098.eml";

    public $reBody = [
        'en' => ['Thank You for choosing Gulfair', 'Your Reservation Number is'],
    ];
    public $reSubject = [
        'Travel Itinerary',
    ];
    public $lang = 'en';
    public static $dict = [
        'en' => [
            'Total' => ['TOTAL', 'Total'],
        ],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $body = $this->http->Response['body'];
        $this->AssignLang($body);

        $type = '';
        $xpath = "//text()[contains(.,'" . $this->t('Flight Details') . "')]/ancestor::tr[1]/following-sibling::tr[contains(.,'" . $this->t('Flight') . "')]";
        $its = $this->parseEmail();

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => "TravelItinerary" . $type . ucfirst($this->lang),
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();
        $this->AssignLang($body);

        return stripos($body, $this->reBody[$this->lang][0]) !== false && stripos($body, $this->reBody[$this->lang][1]) !== false;
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
        return stripos($from, "gulfair.com") !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        $types = 2;
        $cnt = count(self::$dict) * $types;

        return $cnt;
    }

    private function parseEmail()//gulfair/it-4969098.eml
    {
        $it = ['Kind' => 'T', 'TripSegments' => []];
        $it['RecordLocator'] = $this->http->FindSingleNode("//*[contains(text(),'" . $this->t('Your Reservation Number is') . "')]/ancestor::tr[1]/td[2]");

        if ($this->http->XPath->query("//*[contains(text(),'" . $this->t('You Successfully Booked Your Flights') . "')]")->length > 0) {
            $it['Status'] = 'Confirmed';
        }

        $it['Passengers'] = $this->http->FindNodes("//text()[contains(.,'" . $this->t('Passengers Information') . "')]/ancestor::tr[1]/following-sibling::tr[td[position()=1 and contains(.,'" . $this->t('Passenger') . "')]]/td/table//td[2]//tr[1]/td[position()=1 and not(contains(.,'" . $this->t('number') . "'))]");
        $it['TicketNumbers'] = $this->http->FindNodes("//text()[contains(.,'" . $this->t('Passengers Information') . "')]/ancestor::tr[1]/following-sibling::tr[td[position()=1 and contains(.,'" . $this->t('Passenger') . "')]]/td/table//td[2]//tr[1]/td[2]");

        $node = $this->http->FindSingleNode("//text()[contains(.,'" . $this->t('Price and Payment') . "')]/ancestor::tr[1]/following-sibling::tr[contains(.,'" . $this->t('Base Price') . "')]//table[1]//tr[2]/td[1]");

        if ($node !== null) {
            $it['BaseFare'] = cost($node);
        }
        $node = $this->http->FindSingleNode("//text()[contains(.,'" . $this->t('Price and Payment') . "')]/ancestor::tr[1]/following-sibling::tr[contains(.,'" . $this->t('Base Price') . "')]//table[1]//tr[2]/td[2]");

        if ($node !== null) {
            $it['Tax'] = cost($node);
        }

        $tot = $this->t('Total');

        if (!is_array($tot)) {
            $tot = [$tot];
        }
        $total = implode(" or ", array_map(function ($s) {
            return "contains(.,'{$s}')";
        }, $tot));

        $node = $this->http->FindSingleNode("//text()[contains(.,'" . $this->t('Price and Payment') . "')]/ancestor::tr[1]/following-sibling::tr[contains(.,'" . $this->t('Base Price') . "')]//table[1]//tr[position()>1 and ({$total})]/td[2]");

        if ($node !== null) {
            $it['TotalCharge'] = cost($node);
            $it['Currency'] = currency($node);
        }

        $xpath = "//text()[contains(.,'" . $this->t('Flight Details') . "')]/ancestor::tr[1]/following-sibling::tr[contains(.,'" . $this->t('Flight') . "')]";
        $roots = $this->http->XPath->query($xpath);

        if ($roots->length == 0) {
            $xpath = "//text()[contains(.,'" . $this->t('Flight Details') . "')]/ancestor::table[1]/following-sibling::table[contains(.,'" . $this->t('Flight') . "')]";
            $roots = $this->http->XPath->query($xpath);

            if ($roots->length == 0) {
                return null;
            }
        }

        foreach ($roots as $i => $root) {
            $dateFly = strtotime($this->http->FindSingleNode("./descendant::tr[1]//p[2]", $root));

            if ($dateFly === false) {
                return null;
            }
            $num = $i + 1;
            $x_xpath = "({$xpath})[{$num}]/descendant::tr[2]//table[1]//tr[position()>1]";
            $x_roots = $this->http->XPath->query($x_xpath);

            foreach ($x_roots as $x_root) {
                $seg = [];
                $node = $this->http->FindSingleNode("./td[1]", $x_root);

                if (($node !== null) && preg_match("#(\d+:\d+)\s*(.+)\s*\(([A-Z]{3})\)#", $node, $m)) {
                    $seg['DepDate'] = strtotime($m[1], $dateFly);
                    $seg['DepName'] = $m[2];
                    $seg['DepCode'] = $m[3];
                }
                $node = $this->http->FindSingleNode("./td[2]", $x_root);

                if (($node !== null) && preg_match("#(\d+:\d+)\s*(.+)\s*\(([A-Z]{3})\)#", $node, $m)) {
                    $seg['ArrDate'] = strtotime($m[1], $dateFly);
                    $seg['ArrName'] = $m[2];
                    $seg['ArrCode'] = $m[3];
                }
                $node = $this->http->FindSingleNode("./td[3]", $x_root);

                if (($node !== null) && preg_match("#([A-Z\d]{2})\s*(\d+)#", $node, $m)) {
                    $seg['AirlineName'] = $m[1];
                    $seg['FlightNumber'] = $m[2];
                }
                $node = $this->http->FindSingleNode("./td[4]", $x_root);

                if (($node !== null) && preg_match("#(\w+)#", $node, $m)) {
                    $seg['Cabin'] = $m[1];
                }
                $node = $this->http->FindSingleNode("./td[5]", $x_root);

                if ($node !== null) {
                    $seg['Aircraft'] = $node;
                }
                $it['TripSegments'][] = $seg;
            }
        }

        $it = array_filter($it);

        return [$it];
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function AssignLang($body)
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if (stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false) {
                    $this->lang = $lang;

                    break;
                }
            }
        }

        return true;
    }
}
