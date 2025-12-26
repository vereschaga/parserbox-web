<?php

namespace AwardWallet\Engine\opodo\Email;

class OpodoFlightBookingConfirmationAllLang extends \TAccountChecker
{
    use \DateTimeTools;
    use \PriceTools;
    public static $dict = [
        'de' => [
            'Booking reference:'       => 'Buchungsnummer:',
            'Name(s) of traveller(s):' => 'Namen der Reisenden:',
            'One-way flight'           => 'Nur Hinflug',
            'Outbound:'                => 'Hinreise:',
            'Inbound:'                 => 'Rückreise:',
            'Leg'                      => 'Flug',
            'Payment summary'          => 'Zahlung',
            'Payment details'          => 'Zahlungsdetails',
            'Total'                    => 'Gesamt',
            'stop\(s\)'                => 'Stopp\(s\)',
            'Departing'                => 'Abreise',
            'Arriving'                 => 'Ankunft',
            'Duration:'                => 'Dauer:',
            'Aircraft type:'           => 'Flugzeugtyp:',
            'Flight details'           => 'Flugdetails',
        ],
        'fr' => [
            'Booking reference:'       => 'Référence de réservation :',
            'Name(s) of traveller(s):' => 'Passager(s):',
            'One-way flight'           => 'Aller simple',
            'Outbound:'                => 'Aller:',
            'Inbound:'                 => 'Retour:',
            'Leg'                      => 'Vol',
            'Payment summary'          => 'Montant de la réservation',
            'Payment details'          => 'Zahlungsdetails',
            'Total'                    => 'Total',
            'stop\(s\)'                => 'escale\(s\)',
            'Departing'                => 'Départ',
            'Arriving'                 => 'Arrivée',
            'Duration:'                => 'Durée :',
            'Aircraft type:'           => 'Flugzeugtyp:',
            'Flight details'           => 'Détails du vol',
        ],
        'en' => [
            'Booking reference:'       => 'Booking reference:',
            'Name(s) of traveller(s):' => 'Name(s) of traveller(s):',
        ],
    ];
    public $recordLocators;
    public $recordLocatorsNotUnique;
    public $totalCharge;
    public $mailFiles = "opodo/it-1.eml, opodo/it-2076960.eml, opodo/it-2305330.eml, opodo/it-3.eml, opodo/it-4415151.eml, opodo/it-4415152.eml, opodo/it-4429096.eml, opodo/it-4429102.eml, opodo/it-4429278.eml, opodo/it-4451017.eml, opodo/it-4451018.eml, opodo/it-4485266.eml, opodo/it-4975495.eml, opodo/it-5000374.eml, opodo/it-5024555.eml, opodo/it-5025260.eml, opodo/it-5045951.eml, opodo/it-5050867.eml, opodo/it-5061835.eml, opodo/it-5067961.eml, opodo/it-5087789.eml, opodo/it-5087805.eml, opodo/it-5090000.eml, opodo/it-5100986.eml, opodo/it-5661563.eml, opodo/it-5662687.eml, opodo/it-5883939.eml, opodo/it-6.eml";

    public $reSubject = [
        'de' => ["Ihre Buchungsbestätigung von Opodo", "Bestätigung Ihrer Flugbuchung bei Opodo"],
        'en' => ["Booking on request", "booking confirmation"],
        'fr' => ["Réservation en demande", "Votre réservation"],
    ];
    public $lang = 'en';
    public $pdf;
    public $date;
    public $pdfText;

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->date = strtotime($parser->getDate());

        $text = $parser->getHTMLBody();
        $this->AssignLang($text);
        //		echo $this->lang;
        $its = $this->parseEmail();
        //it's time to merge equal PNR data
        $its2 = $its;

        foreach ($its2 as $i => $it) {
            if ($i && ($j = $this->findRL($i, $it['RecordLocator'], $its)) != -1) {
                $its[$j]['TripSegments'] = array_merge($its[$j]['TripSegments'], $it['TripSegments']);
                unset($its[$i]);
                unset($its[$j]['BaseFare']); //'cause it's absurdly data in this case
            }
        }
        $parsedData['parsedData']['Itineraries'] = $its;
        $parsedData['emailType'] = "OpodoFlightBookingConfirmationAllLang";

        if (count($this->recordLocators) > 1) {
            if (preg_match("#" . $this->t('Total') . " (.+)#", $this->totalCharge, $m)) {
                $parsedData['parsedData']['TotalCharge'] = ['Amount' => cost($m[1]), 'Currency' => currency($m[1])];
                //$parsedData['parsedData']['Currency'] = currency($m[1]);
            }
        }

        return $parsedData;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href,'www.opodo.')]")->length > 0) {
            $body = $parser->getHTMLBody();

            foreach (self::$dict as $lang => $reBody) {
                if (stripos($body, $reBody['Booking reference:']) !== false || stripos($body, $reBody['Name(s) of traveller(s):']) !== false) {
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
                if (stripos($headers["subject"], $reSubject[0]) !== false || (isset($reSubject[1]) && stripos($headers["subject"], $reSubject[1]) !== false)) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, "opodo") !== false;
    }

    protected function findRL($g_i, $rl, $its)
    {
        foreach ($its as $i => $it) {
            if ($g_i != $i && $it['RecordLocator'] === $rl) {
                return $i;
            }
        }

        return -1;
    }

    private function AssignLang($body)
    {
        foreach (self::$dict as $lang => $reBody) {
            if (stripos($body, $reBody['Booking reference:']) !== false || stripos($body, $reBody['Name(s) of traveller(s):']) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        return true;
    }

    private function parseEmail()
    {
        $its = [];
        $costs = $this->http->FindNodes("//td[//img[contains(@src,'flights')] and not(.//td)]/following-sibling::td[contains(normalize-space(.), '" . $this->t('One-way flight') . "') and not(.//td)]/following-sibling::td[last()]");

        $this->totalCharge = $this->http->FindSingleNode("//td[contains(text(), '" . $this->t('Payment summary') . "') or contains(., '" . $this->t('Payment details') . "')]/ancestor::tr[1]/following-sibling::tr/descendant::tr/td[contains(text(), '" . $this->t('Total') . "')]");
        $this->recordLocatorsNotUnique = $this->http->FindNodes("//*[self::img[contains(@src, 'flights_icon_white.gif')] or self::*[normalize-space(text())='" . $this->t('Flight details') . "']]/ancestor::table[1]/../following-sibling::td[contains(.,'" . $this->t('Booking reference:') . "')]", null, '#' . $this->t('Booking reference:') . '\s*(.+)#');
        $this->recordLocators = array_unique($this->recordLocatorsNotUnique);

        foreach ($this->http->XPath->query("//*[self::img[contains(@src, 'flights_icon_white.gif')] or self::*[normalize-space(text())='" . $this->t('Flight details') . "']]/ancestor::table[1]/../following-sibling::td[contains(.,'" . $this->t('Booking reference:') . "')]/ancestor::table[1]") as $i => $itDOM) {
            $it = ['Kind' => 'T'];
            $it['RecordLocator'] = $this->recordLocatorsNotUnique[$i];
            $it['Passengers'] = array_unique(array_values(array_filter($this->http->FindNodes("//td/*[self::b or self::strong][contains(text(), '" . $this->t('Name(s) of traveller(s):') . "')]/following-sibling::node()"))));

            if ($costs != null && $costs[$i]) {
                $it['BaseFare'] = cost($costs[$i]);
                $it['Currency'] = currency($costs[$i]);
            }

            if (count($this->recordLocators) == 1) {
                if (preg_match("#" . $this->t('Total') . " (.+)#", $this->totalCharge, $m)) {
                    $it['TotalCharge'] = cost($m[1]);
                    $it['Currency'] = currency($m[1]);
                }
            }

            $segs = [];

            foreach ($this->http->XPath->query(".//td/descendant::*[self::b or self::strong][(contains(., '" . $this->t('Outbound:') . "') or contains(., '" . $this->t('Inbound:') . "') or contains(., '" . $this->t('Leg') . "'))  and not(contains(.,'" . $this->t('Flight details') . "')) ]", $itDOM) as $j => $segDOM) {
                $seg = [];
                $s = $this->http->FindSingleNode("./ancestor::tr[1]/following-sibling::tr[3]/td[1]//tr[1]/td[2]/descendant::*[self::b or self::strong]", $segDOM, false);

                if (preg_match('#([A-Z\d]+) ([0-9]+)#', $s, $m)) {
                    $seg['FlightNumber'] = $m[2];
                    $seg['AirlineName'] = $m[1];
                }
                $seg['DepCode'] = $this->http->FindSingleNode("./ancestor::tr[1]/following-sibling::tr[1]/td[2]", $segDOM, false, '/\(([A-Z]{3})\)/');

                if (($info = $this->http->FindSingleNode("./ancestor::tr[1]/following-sibling::tr[1]/td[2]", $segDOM))) {
                    if (preg_match('#,([^,]*)\s*\(#', $info, $m)) {
                        $seg['DepName'] = trim($m[1]);
                    }

                    if (preg_match('#Terminal: ([^,]*)#', $info, $m)) {
                        $seg['DepartureTerminal'] = trim($m[1]);
                    }
                }
                $dd = str_replace(' ', ' ', $segDOM->nodeValue);

                if (preg_match('#(\d{1,2}\,?\.?\s+\S*\s+\d{4})#', $dd, $m) || preg_match('#(\S+\s+\d{1,2}\,?\.?\s+\d{4})#', $dd, $m)) {
                    if (preg_match('#([0-9]+:[0-9]+)#', $this->http->FindSingleNode("./ancestor::tr[1]/following-sibling::tr[1]/td[2]/node()[4]", $segDOM), $m1) || preg_match('#([0-9]+:[0-9]+)#', $this->http->FindSingleNode("(./following::text()[contains(.,'" . $this->t('Departing') . "')])[1]/following::text()[string-length(normalize-space(.))>3][1]", $segDOM), $m1)) {
                        $seg['DepDate'] = strtotime($this->dateStringToEnglish($m[1] . ' ' . $m1[1]));
                    }

                    if (preg_match('#([0-9]+:[0-9]+)#', $this->http->FindSingleNode("./ancestor::tr[1]/following-sibling::tr[2]/td[2]/node()[4]", $segDOM), $m1) || preg_match('#([0-9]+:[0-9]+)#', $this->http->FindSingleNode("(./following::text()[contains(.,'" . $this->t('Arriving') . "')])[1]/following::text()[string-length(normalize-space(.))>3][1]", $segDOM), $m1)) {
                        $seg['ArrDate'] = strtotime($this->dateStringToEnglish($m[1] . ' ' . $m1[1]));
                    }
                }
                $seg['ArrCode'] = $this->http->FindSingleNode("./ancestor::tr[1]/following-sibling::tr[2]/td[2]", $segDOM, false, '/\(([A-Z]{3})\)/');

                if (($info = $this->http->FindSingleNode("./ancestor::tr[1]/following-sibling::tr[2]/td[2]", $segDOM))) {
                    if (preg_match('#,([^,]*)\s*\(#', $info, $m)) {
                        $seg['ArrName'] = trim($m[1]);
                    }

                    if (preg_match('#Terminal: ([^,]*)#', $info, $m)) {
                        $seg['ArrivalTerminal'] = trim($m[1]);
                    }
                    //try to fix days overlap !!!
                    if (preg_match('#\(\+([1-2])\s+#', $info, $m)) {
                        $seg['ArrDate'] = strtotime("+{$m[1]} day", $seg['ArrDate']);
                    }
                }

                if (preg_match('#(\d+)\s*' . $this->t('stop\(s\)') . '#', $segDOM->nodeValue, $m)) {
                    $seg['Stops'] = $m[1];
                }

                if (($info = $this->http->FindSingleNode("./ancestor::tr[1]/following-sibling::tr[3]/td[1]//tr[1]/td[1]", $segDOM))) {
                    if (preg_match('#' . $this->t('Duration:') . ' (.*)#', $info, $m)) {
                        $seg['Duration'] = trim($m[1]);
                    }
                }

                if (($info = $this->http->FindSingleNode("./ancestor::tr[1]/following-sibling::tr[3]/td[1]//tr[1]/td[4]", $segDOM, true, '#(\w+)#'))) {
                    if (strlen($info) == 1) {
                        $seg['BookingClass'] = $info;
                    } else {
                        $seg['Cabin'] = $info;
                    }
                }

                if (($info = $this->http->FindSingleNode("./ancestor::tr[1]/following-sibling::tr[3]/td[1]//tr[1]/td[3]", $segDOM, true))) {
                    if (preg_match('#' . $this->t('Aircraft type:') . ' (.*)#', $info, $m)) {
                        $seg['Aircraft'] = trim($m[1]);
                    }
                }
                $segs[] = $seg;
            }
            $it['TripSegments'] = $segs;
            $its[] = $it;
        }

        return $its;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }
}
