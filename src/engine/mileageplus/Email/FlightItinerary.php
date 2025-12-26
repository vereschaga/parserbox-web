<?php

namespace AwardWallet\Engine\mileageplus\Email;

use AwardWallet\Engine\MonthTranslate;

// format similar like TravelItinerary.php

class FlightItinerary extends \TAccountChecker
{
    public $mailFiles = "mileageplus/it-6314430.eml, mileageplus/it-7006410.eml, mileageplus/it-7006414.eml";

    public $reSubject = [
        'Itinerário de viagem enviado pela United Airlines, Inc', // pt
        'Travel Itinerary sent from United Airlines, Inc', // en
    ];

    public $reBody = [
        'pt' => ['Número de confirmação', 'Partida'],
        'en' => ['Confirmation Number', 'Depart'],
    ];

    protected $lang = '';

    protected static $dict = [
        'pt' => [
            'Confirmation Number'  => 'Número de confirmação',
            'Depart:'              => 'Partida:',
            'Travel Time:'         => 'Tempo de voo:',
            'Flight:'              => 'Voo:',
            'Fare Class:'          => 'Classe de tarifa:',
            'Meal:'                => 'Refeição:',
            'Aircraft:'            => 'Aeronave:',
            'Traveler Information' => 'Informações do passageiro',
            'Seat Assignments'     => 'Designação de assentos',
        ],
        'en' => [],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->assignLang();
        $its = $this->ParseEmail();

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => 'FlightItinerary_' . $this->lang,
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers['from'], 'unitedairlines@united.com') !== false) {
            return true;
        }

        foreach ($this->reSubject as $reSubject) {
            if (stripos($headers['subject'], $reSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $condition1 = $this->http->XPath->query('//node()[contains(normalize-space(.),"@united.com") or contains(normalize-space(.),"United Airlines, Inc")]')->length === 0;
        $condition2 = $this->http->XPath->query('//a[contains(@href,"//www.united.com")]')->length === 0;

        if ($condition1 && $condition2) {
            return false;
        }

        return $this->assignLang();
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[.@]united\.com/', $from);
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    protected function ParseEmail()
    {
        $it = ["Kind" => 'T', "TripSegments" => []];
        $it["RecordLocator"] = $this->http->FindSingleNode("//td[contains(.,'" . $this->t('Confirmation Number') . "') and not(.//td)]/following-sibling::td[2]");

        if (!isset($it["RecordLocator"])) {
            $it["RecordLocator"] = $this->http->FindSingleNode("//td[contains(normalize-space(.),'United Confirmation Number') and not(.//td)]", null, true, "/" . $this->t('Confirmation Number') . " ([A-Z\d]{6})$/");
        }
        $nodes = $this->http->XPath->query("//*[contains(text(),'" . $this->t('Depart:') . "')]");
        $flightNodes = [];

        for ($i = 0; $i < $nodes->length; $i++) {
            $node = $this->findParentNode($nodes->item($i), "tr");

            if ($node) {
                $flightNodes[] = $node;
            }
        }
        $segments = [];

        foreach ($flightNodes as $node) {
            $segment = [];
            $cells = $this->http->XPath->query("td", $node);
            // Departure
            $cell = ($cells->length > 0) ? $cells->item(0) : null;
            $timeDep = $this->http->FindSingleNode("div[2]", $cell, true, '/\d+:\d+(?: [ap]\.m\.)?/');

            if (empty($timeDep)) {
                $timeDep = $this->http->FindSingleNode("span[1]/following-sibling::b[1]", $cell, true, '/\d+:\d+(?: [ap]\.m\.)?/');
            }
            $dateDep = $this->http->FindSingleNode("div[3]", $cell);

            if (empty($dateDep)) {
                $dateDep = $this->http->FindSingleNode("span[1]/following-sibling::span[1]/br[1]/preceding-sibling::text()[1]", $cell);
            }
            $place = $this->http->FindSingleNode("div[4]", $cell);

            if (empty($place)) {
                $place = $this->http->FindSingleNode("span[1]/following-sibling::span[1]/br[1]/following-sibling::text()[1]", $cell);
            }

            if (!empty($dateDep) && !empty($timeDep)) {
                $segment["DepDate"] = strtotime($this->normalizeDate($dateDep) . ', ' . $timeDep);
            }

            if (preg_match("/([^\(]+)\(([A-Z]{3})[^\)]*\)/", $place, $matches)) {
                $segment["DepCode"] = $matches[2];
                $segment["DepName"] = $matches[1];
            }
            //Arrival
            $newCell = ($cells->length > 1) ? $cells->item(1) : null;

            if (!isset($newCell)) {
                $cell = $this->http->XPath->query('span[2]', $cell)->item(0);
            } else {
                $cell = $newCell;
            }
            $timeArr = $this->http->FindSingleNode("div[2]", $cell, true, '/\d+:\d+(?: [ap]\.m\.)?/');

            if (empty($timeArr)) {
                $timeArr = $this->http->FindSingleNode("following-sibling::b[1]", $cell, true, '/\d+:\d+(?: [ap]\.m\.)?/');
            }
            $dateArr = $this->http->FindSingleNode("div[3]", $cell);

            if (empty($dateArr)) {
                $dateArr = $this->http->FindSingleNode("following-sibling::span[1]/br[1]/preceding-sibling::text()[1]", $cell);
            }
            $place = $this->http->FindSingleNode("div[4]", $cell);

            if (empty($place)) {
                $place = $this->http->FindSingleNode("following-sibling::span[1]/br[1]/following-sibling::text()[1]", $cell);
            }

            if (!empty($dateArr) && !empty($timeArr)) {
                $segment["ArrDate"] = strtotime($this->normalizeDate($dateArr) . ', ' . $timeArr);
            }

            if (preg_match("/([^\(]+)\(([A-Z]{3})[^\)]*\)/", $place, $matches)) {
                $segment["ArrCode"] = $matches[2];
                $segment["ArrName"] = $matches[1];
            } elseif (isset($segment["DepDate"])) {
                $segment["ArrCode"] = TRIP_CODE_UNKNOWN;
            }
            //Duration
            $segment["Duration"] = $this->http->FindSingleNode("td[3]/descendant::text()[string-length(normalize-space(.))>2][2]", $node, true, '/\d{1,3}\s*hr\s*\d{1,2}\s*mn/i');

            if (empty($segment["Duration"])) {
                $this->http->FindSingleNode('following-sibling::span[1]/text()[contains(.,"' . $this->t('Travel Time:') . '")]/following::text()[string-length(normalize-space(.))>2][1]', $node, true, '/\d{1,3}\s*hr\s*\d{1,2}\s*mn/i');
            }
            //Other info
            $cell = $this->http->XPath->query("td[contains(.,'" . $this->t('Flight:') . "')]", $node);
            $cell = $cell->length > 0 ? $cell->item(0) : null;
            $flight = trim($this->http->FindSingleNode("descendant::*[contains(text(),'" . $this->t('Flight:') . "')]", $cell, true, "/" . $this->t('Flight:') . "(.+)/"));

            if (preg_match("/^([A-Z\d]{2})(\d+)$/", $flight, $m)) {
                $segment["AirlineName"] = $m[1];
                $segment["FlightNumber"] = $m[2];
            }
            $class = $this->http->FindSingleNode("descendant::*[contains(text(),'" . $this->t('Fare Class:') . "')]", $cell, true, "/" . $this->t('Fare Class:') . "(.+)/");

            if (preg_match("/([^\(]+)\((\w+)\)/", $class, $matches)) {
                $segment["Cabin"] = trim($matches[1]);
                $segment["BookingClass"] = $matches[2];
            } else {
                $segment["Cabin"] = $class;
            }
            $meal = $this->http->FindSingleNode("descendant::*[contains(text(),'" . $this->t('Meal:') . "')]", $cell, true, "/" . $this->t('Meal:') . "(.+)/");
            $segment["Meal"] = trim($meal, ' .');
            $aircraft = $this->http->FindSingleNode("descendant::*[contains(text(),'" . $this->t('Aircraft:') . "')]", $cell, true, "/" . $this->t('Aircraft:') . "(.+)/");
            $segment["Aircraft"] = trim($aircraft, ' .');
            // messed up html
            $text = CleanXMLValue($node->nodeValue);

            if (empty($segment["AirlineName"]) && preg_match("/([A-Z\d]{2})(\d+) \/ " . $this->t('Fare Class:') . "/", $text, $m)) {
                $segment["AirlineName"] = $m[1];
                $segment["FlightNumber"] = $m[2];
            }

            if (empty($segment["Cabin"]) && preg_match("/" . $this->t('Fare Class:') . " ([^\(]+) \(([A-Z]{1,2})\)/", $text, $m)) {
                $segment["Cabin"] = $m[1];
                $segment["BookingClass"] = $m[2];
            }
            $segments[] = $segment;
        }
        $seatBlocks = $this->http->XPath->query('//*[contains(text(),"' . $this->t('Traveler Information') . '")]/following-sibling::*/table[contains(.,"' . $this->t('Seat Assignments') . '")]');
        $passengers = [];
        $seats = [];

        foreach ($seatBlocks as $table) {
            $name = $this->http->FindSingleNode('.//tr[1]', $table);

            if (isset($name)) {
                $passengers[] = preg_replace('/^(mr|ms|mrs|MSTRS)\.?\s+/i', '', $name);
            }
            $seatNodes = array_values(array_filter($this->http->FindNodes('.//tr[contains(.,"' . $this->t('Seat Assignments') . '")]/td[2]//text()', $table)));

            if (count($seatNodes) !== count($segments)) {
                continue;
            }

            foreach ($seatNodes as $i => $text) {
                if (!isset($seats[$i])) {
                    $seats[$i] = [
                        'seats' => [],
                        'dep'   => null,
                        'arr'   => null,
                    ];
                }

                if (preg_match('/^([A-Z]{3}) \- ([A-Z]{3}): (\d+[A-Z])/', $text, $m)) {
                    if ((!isset($seats[$i]['dep']) || $seats[$i]['dep'] === $m[1]) && (!isset($seats[$i]['arr']) || $seats[$i]['arr'] === $m[2])) {
                        $seats[$i]['dep'] = $m[1];
                        $seats[$i]['arr'] = $m[2];
                        $seats[$i]['seats'][] = $m[3];
                    }
                }
            }
        }

        foreach ($segments as $i => $arr) {
            if (isset($seats[$i])) {
                $segments[$i]['Seats'] = implode(', ', $seats[$i]['seats']);
            }
        }

        /*
        $seatBlocks = $this->http->XPath->query("//div[h4 and descendant::*[contains(text(), 'Seat Assignments')]]");
        $passengers = [];
        for ($i = 0; $i < $seatBlocks->length; $i++) {
            $passengers[] = beautifulName($this->http->FindSingleNode("h4", $seatBlocks->item($i)));
            $seatLines = $this->http->FindNodes("descendant::th[contains(text(), 'Seat Assignments')]/following-sibling::td[1]/text()", $seatBlocks->item($i));
            if (count($seatLines) === count($segments))
                foreach ($seatLines as $j => $line) {
                    $seat = preg_replace("/^.*\:\s?/", "", $line);
                    if ($seat !== "---")
                        $segments[$j]["Seats"][] = preg_replace("/^.*\:\s?/", "", $line);
                    if (preg_match("/^([A-Z]{3}) - ([A-Z]{3})/", $line, $codes)) {
                        if ($segments[$j]["DepCode"] === TRIP_CODE_UNKNOWN)
                            $segments[$j]["DepCode"] = $codes[1];
                        if ($segments[$j]["ArrCode"] === TRIP_CODE_UNKNOWN)
                            $segments[$j]["ArrCode"] = $codes[2];
                    }
                }
        }
        foreach ($segments as $i => $segment) {
            if (count($segment["Seats"]) > 0)
                $segments[$i]["Seats"] = implode(", ", $segment["Seats"]);
            else
                unset($segments[$i]["Seats"]);
        }
        */
        $it["Passengers"] = $passengers;
        $it["TripSegments"] = $segments;

        return [$it];
    }

    protected function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    protected function findParentNode($start, $tag = 'td', $limit = 5)
    {
        if (!$start) {
            return null;
        }

        if (is_string($start)) {
            $str = explode(' ', $start);
            $and = [];

            foreach ($str as $s) {
                $and[] = "contains(text(),'$s')";
            }

            if (!($node = $this->findFirstNode("//*[" . implode(' and ', $and) . "]"))) {
                return null;
            }
        } elseif ($start instanceof \DOMNode) {
            $node = $start;
        } else {
            return null;
        }

        if (strtolower($node->nodeName) === strtolower($tag)) {
            return $node;
        }

        while ($limit > 0) {
            if (!($node = $this->findFirstNode("parent::*", $node))) {
                return null;
            }

            if (strtolower($node->nodeName) === strtolower($tag)) {
                return $node;
            }
            $limit--;
        }

        return null;
    }

    protected function findFirstNode($xpath, $parent = null)
    {
        $result = $this->http->XPath->query($xpath, $parent);

        if ($result->length === 0) {
            return null;
        } else {
            return $result->item(0);
        }
    }

    protected function assignLang()
    {
        foreach ($this->reBody as $lang => $phrases) {
            if ($this->http->XPath->query('//node()[contains(normalize-space(.),"' . $phrases[0] . '") and contains(normalize-space(.),"' . $phrases[1] . '")]')->length > 0) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function normalizeDate($string)
    {
        if (preg_match('/([^\d\s,.]{3,})[.\s]+(\d{1,2})[,\s]+(\d{4})$/', $string, $matches)) { // Sat., Apr. 28, 2012
            $month = $matches[1];
            $day = $matches[2];
            $year = $matches[3];
        } elseif (preg_match('/(\d{1,2})\s*([^\d\s,.]{3,})[,.\s]+(\d{4})$/', $string, $matches)) { // Mon., 1 May., 2017
            $day = $matches[1];
            $month = $matches[2];
            $year = $matches[3];
        }

        if ($day && $month && $year) {
            if (preg_match('/^\s*(\d{1,2})\s*$/', $month, $m)) {
                return $day . '.' . $m[1] . '.' . $year;
            }

            if ($this->lang !== 'th') {
                $month = str_replace('.', '', $month);
            }

            if (($monthNew = MonthTranslate::translate($month, $this->lang)) !== false) {
                $month = $monthNew;
            }

            return $day . ' ' . $month . ' ' . $year;
        }

        return false;
    }
}
