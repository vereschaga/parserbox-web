<?php

namespace AwardWallet\Engine\lanpass\Email;

class Itinerary1 extends \TAccountChecker
{
    public $mailFiles = "";

    public function ParsePlanEmail1(\PlancakeEmailParser $parser)
    {
        $itineraries = [];

        $itineraries['Kind'] = 'T';
        $itineraries['RecordLocator'] = strip_tags($this->http->FindSingleNode(".//*[contains(text(),'Your reservation code is:') or contains(text(),'de reserva es')]/../../span[1]/*"));
        $total = strip_tags($this->http->FindSingleNode(".//*[contains(text(),'Total Ticket Price') or contains(text(),'Total pagado')]/../../*[contains(.,'$')]/*"));
        $currency = preg_replace("/[?:\\w, ]./", "", $total);

        if (strpos($currency, '$') !== false) {
            $itineraries['Currency'] = 'USD';
        } else {
            $itineraries['Currency'] = '';
        }

        $itineraries['TotalCharge'] = (float) preg_replace("/([^0-9\\.])/i", "", $total);
        $pass = $this->http->FindNodes(".//*[contains(text(),'Pasajeros') or contains(text(),'Passengers')]/../../*/text()");

        foreach ($pass as $value) {
            if (strpos($value, '(') === false && strpos($value, ')') == false && strpos($value, 'USD') === false && !empty($value)) {
                $itineraries['Passengers'][] = $value;
            }
        }
        $this->parseSegments($itineraries);

        return $itineraries;
    }

    public function ParsePlanEmail2(\PlancakeEmailParser $parser)
    {
        $it = ['Kind' => 'T'];

        $it['RecordLocator'] = $this->http->FindSingleNode("//*[contains(text(), 'Reservation code')]", null, true, "#Reservation code\s*:\s*(.*?)$#");

        $nodes = $this->http->XPath->Query("//*[contains(text(), 'Passengers')]/ancestor::tr[1]/following-sibling::tr");
        $names = [];
        $numbers = [];

        for ($i = 1; $i < $nodes->length; $i++) {
            $tr = $nodes->item($i);

            $names[trim($this->http->FindSingleNode("td[2]", $tr) . ' ' . $this->http->FindSingleNode("td[3]", $tr))] = 1;
            $numbers[] = $this->http->FindSingleNode("td[5]", $tr);
        }

        $it['AccountNumbers'] = implode(', ', $numbers);

        if ($names) {
            $it['Passengers'] = implode(', ', array_keys($names));
        }

        $nodes = $this->http->XPath->Query("//*[contains(text(), 'Itinerary')]/ancestor::tr[1]/following-sibling::tr");

        for ($i = 1; $i < $nodes->length; $i += 2) {
            $tr = $nodes->item($i);
            $seg = [];

            $seg['FlightNumber'] = $this->http->FindSingleNode("td[6]", $tr);

            $seg['DepName'] = $this->http->FindSingleNode("td[3]", $tr, true, "#^([^\(]+)\s+#");
            $seg['ArrName'] = $this->http->FindSingleNode("td[5]", $tr, true, "#^([^\(]+)\s+#");

            $seg['DepCode'] = $this->http->FindSingleNode("td[3]", $tr, true, "#[^\(]+\((\w{3})\)$#");
            $seg['ArrCode'] = $this->http->FindSingleNode("td[5]", $tr, true, "#[^\(]+\((\w{3})\)$#");

            $seg['DepDate'] = strtotime($this->http->FindSingleNode("td[1]", $tr) . ', ' . preg_replace("#\([^\)]+\)#", '', $this->http->FindSingleNode("td[2]", $tr)));
            $seg['ArrDate'] = strtotime($this->http->FindSingleNode("td[1]", $tr) . ', ' . preg_replace("#\([^\)]+\)#", '', $this->http->FindSingleNode("td[4]", $tr)));

            $it['TripSegments'][] = $seg;
        }

        return $it;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        // Parser toggled off as it is covered by emailETicketConfirmationChecker.php
        return null;

        if ($this->http->FindSingleNode("//*[contains(text(), 'Your reservation code is')]")
            || $this->http->FindSingleNode("//*[contains(text(), 'de reserva es')]")
           ) {
            $it = $this->ParsePlanEmail1($parser);
        } else {
            $it = $this->ParsePlanEmail2($parser);
        }

        return [
            'emailType'  => 'reservations',
            'parsedData' => [
                'Itineraries' => [$it],
            ],
        ];
    }

    public function parseSegments(&$itineraries)
    {
        $tripSegmentsTemp = $this->http->XPath->query(".//*[contains(text(),'Itinerario') or contains(text(),'Itinerary')]/../table/tbody/tr");
        $tripSegments = [];

        if (!empty($tripSegmentsTemp)) {
            foreach ($tripSegmentsTemp as $segment) {
                $tripSegments[] = $segment->nodeValue;
            }
        }

        $tripSegments = array_diff($tripSegments, ['']);
        $tempDate = '';
        $segments = [];

        foreach ($tripSegments as &$segment) {
            $text = utf8_encode($segment);
            $text = preg_replace('/ {2,}/', ' ', $text);
            $text = preg_replace('/[^a-z0-9\:\-\)\(\n \pL\']+/iu', '', $text);

            $text = preg_replace('/\s+$/m', ';', $text);
            $text = trim($text);
            $segment = $text;
            $monthsp = ["enero", "febrero", "marzo", "abril", "mayo", "junio", "julio", "agosto", "septiempre", 'octubre', "noviembre", "diciembre"];
            $monthen = ['january', 'february', 'march', 'april', 'may', 'june', 'jule', 'august', 'september', 'october', 'november', 'december'];

            $blocks = explode(';', $segment);

            if (count($blocks) < 5) {
                $blocks = array_merge($blocks, ['', '', '', '', '']);
            }
            $depdate = $blocks[0];
            $depdate = preg_replace('/[^a-z0-9\:\-\)\(\n ]+/iu', '', $depdate);

            if (!empty($depdate)) {
                $tempDate = $depdate;
            } else {
                $depdate = $tempDate;
            }
            $arrdate = $depdate . ' ' . $blocks[3];
            $depdate = $depdate . ' ' . $blocks[1];

            $depdate = str_ireplace($monthsp, $monthen, $depdate);
            $arrdate = str_ireplace($monthsp, $monthen, $arrdate);
            $depdate = date_parse_from_format('d F Y H:i:s', $depdate);
            $arrdate = date_parse_from_format('d F Y H:i:s', $arrdate);
            $depdate = mktime($depdate['hour'], $depdate['minute'], 0, $depdate['month'], $depdate['day'], $depdate['year']);
            $arrdate = mktime($arrdate['hour'], $arrdate['minute'], 0, $arrdate['month'], $arrdate['day'], $arrdate['year']);
            preg_match_all('#(.*)\((.*)\)#', $blocks[2], $depcodetemp);
            $depname = '';

            if (!empty($depcodetemp)) {
                if (!empty($depcodetemp[2])) {
                    $depcode = $depcodetemp[2][0];
                    $depname = $depcodetemp[1][0];
                } else {
                    $depcode = $depcodetemp[1][0];
                    $depname = $depcodetemp[1][0];
                }
            } else {
                $depcode = '';
            }
            preg_match_all('#(.*)\((.*)\)#', $blocks[4], $arrcodetemp);
            $arrname = '';

            if (!empty($arrcodetemp)) {
                if (!empty($arrcodetemp[2])) {
                    $arrcode = $arrcodetemp[2][0];
                    $arrname = $arrcodetemp[1][0];
                } else {
                    $arrcode = $arrcodetemp[1][0];
                    $arrname = $arrcodetemp[1][0];
                }
            } else {
                $arrcode = '';
            }

            $flnumber = $blocks[5];
            $class = $blocks[6];
            $segments[] = [
                'FlightNumber' => trim($flnumber),
                'DepCode'      => trim($depcode),
                'DepDate'      => $depdate,
                'DepName'      => trim($depname),
                'ArrCode'      => trim($arrcode),
                'ArrDate'      => $arrdate,
                'ArrName'      => trim($arrname),
                'Cabin'        => trim($class),
            ];
        }
        $itineraries['TripSegments'] = $segments;
    }

    public function normalizeFloat($float)
    {
        return floatval(str_replace(',', '.', str_replace('.', '', $float)));
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && preg_match("/[@\.]lan\.com/ims", $headers["from"]);
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return strpos($parser->getHTMLBody(), 'LAN.com') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@\.]lan\.com$/ims", $from);
    }

    public static function getEmailLanguages()
    {
        return ['en'];
        //todo: methods must return something
//        return [
//            'es',
//            'en'
//        ];
    }

    public static function getEmailTypesCount()
    {
        return 1;
//        return 2;
    }
}
