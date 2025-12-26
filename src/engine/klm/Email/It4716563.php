<?php

namespace AwardWallet\Engine\klm\Email;

class It4716563 extends \TAccountChecker
{
    use \DateTimeTools;
    use \PriceTools;

    public $mailFiles = "klm/it-5838636.eml, klm/it-2313812.eml, klm/it-2315723.eml, klm/it-2316378.eml, klm/it-2316398.eml, klm/it-2316399.eml, klm/it-4687462.eml, klm/it-4705573.eml, klm/it-4721863.eml, klm/it-4721865.eml, klm/it-4794077.eml, klm/it-5957620.eml";

    public $reBody = 'KLM';
    public $reBody2 = [
        "en"=> "Departing",
        "de"=> "Abflug ab",
        "nl"=> "Vertrek",
        "it"=> "Partenza",
        "fr"=> "Départ",
        "es"=> "Salida",
    ];

    public static $dictionary = [
        "en" => [],
        "de" => [
            "Booking code:" => "Buchungscode:",
            "Passenger Name"=> "Name des Passagiers",
            "Departing"     => "Abflug ab",
            "Receipt"       => "Rechnung",
            "Fare amount"   => "Ticketpreis",
            "Tax & Carrier" => "Steuer",
            "Total amount"  => "Gesamtsumme",
        ],
        "nl" => [
            "Booking code:" => "Boekingscode:",
            "Passenger Name"=> "Naam passagier",
            "Departing"     => "Vertrek",
            "Receipt"       => "Betalingsbewijs",
            "Fare amount"   => "Ticketprijs:",
            "Tax & Carrier" => "Belasting",
            "Total amount"  => "Totaalbedrag",
        ],
        "it" => [
            "Booking code:" => "Prenotazione code:",
            "Passenger Name"=> "Nome del passeggero",
            "Departing"     => "Partenza",
            "Receipt"       => "Quietanza",
            "Fare amount"   => "Tariffa per l",
            "Tax & Carrier" => "Tassa",
            "Total amount"  => "Importo totale",
        ],
        "fr" => [
            "Booking code:" => "Code de réservation:",
            "Passenger Name"=> "Nom du passager",
            "Departing"     => "Départ",
            "Receipt"       => "Reçu",
            "Fare amount"   => "Montant du billet",
            "Tax & Carrier" => "Taxes",
            "Total amount"  => "Montant total",
        ],
        "es" => [
            "Booking code:" => "Código de reserva:",
            "Passenger Name"=> "Nombre del pasajero",
            "Departing"     => "Salida",
            "Receipt"       => "Recibo",
            "Fare amount"   => "Precio de tarifa",
            "Tax & Carrier" => "Impuesto/tasa",
            "Total amount"  => "Importo totale",
        ],
    ];

    public $lang = 'en';

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['subject'], 'KLM e-Ticket') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@klm.com') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (stripos($body, $this->reBody) === false) {
            return false;
        }

        foreach ($this->reBody2 as $re) {
            if ($this->http->FindSingleNode("//*[name()='td' or name()='th'][normalize-space(.)='{$re}' or normalize-space(.)='" . strtoupper($re) . "']")) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->date = strtotime($parser->getHeader('date'));

        $this->http->FilterHTML = false;
        $this->http->setBody(str_replace(" ", " ", $this->http->Response['body'])); // bad fr char " :"

        foreach ($this->reBody2 as $lang=> $re) {
            if ($this->http->FindSingleNode("//*[name()='td' or name()='th'][normalize-space(.)='{$re}' or normalize-space(.)='" . strtoupper($re) . "']")) {
                $this->lang = $lang;

                break;
            }
        }

        $it = $this->ParseEmail();

        return [
            'emailType'  => 'reservations',
            'parsedData' => [
                'Itineraries' => [$it],
            ],
        ];
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    protected function ParseEmail()
    {
        $it = [];
        $it['Kind'] = 'T';

        $it['RecordLocator'] = $this->http->FindSingleNode('//tr[(starts-with(normalize-space(.),"' . $this->t('Booking code:') . '") or starts-with(normalize-space(.),"' . strtoupper($this->t('Booking code:')) . '")) and not(.//tr)]', null, true, '/(?:' . $this->t('Booking code:') . '|' . strtoupper($this->t('Booking code:')) . ')\s*([A-Z\d]{5,7})/');
        $it['Passengers'] = $this->http->FindNodes("//td[normalize-space(.)='" . $this->t("Passenger Name") . "' or normalize-space(.)='" . strtoupper($this->t("Passenger Name")) . "']/ancestor::tr[1]/following-sibling::tr/td[1]");
        $it['AccountNumbers'] = $this->http->FindNodes("//td[normalize-space(.)='" . $this->t("Passenger Name") . "' or normalize-space(.)='" . strtoupper($this->t("Passenger Name")) . "']/ancestor::tr[1]/following-sibling::tr/td[2]");

        $xpath = '//tr[.//td[normalize-space(.)="' . $this->t('Departing') . '" or normalize-space(.)="' . strtoupper($this->t('Departing')) . '"] and not(.//tr)]/following-sibling::tr[normalize-space(./td[1]) and normalize-space(./td[2]) and normalize-space(./td[5])]';
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length === 0) {
            $this->http->Log("segments root not found: $xpath", LOG_LEVEL_NORMAL);
        }

        foreach ($nodes as $root) {
            $itsegment = [];
            $dateDep = $this->normalizeDate($this->http->FindSingleNode('./td[1]', $root));
            $timeDep = $this->http->FindSingleNode('./following-sibling::tr[1]/td[1]', $root);

            if ($dateDep && $timeDep) {
                $itsegment['DepDate'] = strtotime($timeDep, strtotime($dateDep));
            }
            $dateArr = $this->normalizeDate($this->http->FindSingleNode('./td[5]', $root));
            $timeArr = $this->http->FindSingleNode('./following-sibling::tr[1]/td[5]', $root);

            if ($dateArr && $timeArr) {
                $itsegment['ArrDate'] = strtotime($timeArr, strtotime($dateArr));
            }
            $itsegment['AirlineName'] = $this->http->FindSingleNode("./td[2]", $root, true, "#^(\w{2})\s*\d+$#");
            $itsegment['FlightNumber'] = $this->http->FindSingleNode("./td[2]", $root, true, "#^\w{2}\s*(\d+)$#");
            $itsegment['DepName'] = $this->http->FindSingleNode("./td[3]", $root);
            $itsegment['ArrName'] = $this->http->FindSingleNode("./td[4]", $root);
            $itsegment['DepCode'] = $this->http->FindSingleNode("./following-sibling::tr[1]/td[3]", $root, true, "#^[A-Z]{3}$#");
            $itsegment['ArrCode'] = $this->http->FindSingleNode("./following-sibling::tr[1]/td[4]", $root, true, "#^[A-Z]{3}$#");
            $itsegment['Cabin'] = $this->http->FindSingleNode("./td[6]", $root);
            $it['TripSegments'][] = $itsegment;
        }

        $totalCharge = $this->http->FindSingleNode('//tr[(starts-with(normalize-space(.),"' . $this->t('Receipt') . '") or starts-with(normalize-space(.),"' . strtoupper($this->t('Receipt')) . '")) and not(.//tr)]/following-sibling::tr[starts-with(normalize-space(.),"' . $this->t('Total amount') . '") or starts-with(normalize-space(.),"' . strtoupper($this->t('Total amount')) . '")]//td[normalize-space(.)!=""][2]');

        if (preg_match('/([^\d\s]+)\s*([.\d]+)/', $totalCharge, $matches)) {
            $it['BaseFare'] = $this->http->FindSingleNode('//tr[(starts-with(normalize-space(.),"' . $this->t('Receipt') . '") or starts-with(normalize-space(.),"' . strtoupper($this->t('Receipt')) . '")) and not(.//tr)]/following-sibling::tr[starts-with(normalize-space(.),"' . $this->t('Fare amount') . '") or starts-with(normalize-space(.),"' . strtoupper($this->t('Fare amount')) . '")]//td[normalize-space(.)!=""][2]', null, true, '/' . $matches[1] . '\s*([.\d]+)/');
            $it['Tax'] = $this->http->FindSingleNode('//tr[(starts-with(normalize-space(.),"' . $this->t('Receipt') . '") or starts-with(normalize-space(.),"' . strtoupper($this->t('Receipt')) . '")) and not(.//tr)]/following-sibling::tr[starts-with(normalize-space(.),"' . $this->t('Tax & Carrier') . '") or starts-with(normalize-space(.),"' . strtoupper($this->t('Tax & Carrier')) . '")]//td[normalize-space(.)!=""][2]', null, true, '/' . $matches[1] . '\s*([.\d]+)/');
            $it['Currency'] = $matches[1];
            $it['TotalCharge'] = $matches[2];
        }

        return $it;
    }

    private function nextText($field, $root = null, $n = 1)
    {
        return $this->http->FindSingleNode("(.//text()[normalize-space(.)='{$field}'])[{$n}]/following::text()[normalize-space(.)][1]", $root);
    }

    private function nextCol($field, $root = null, $n = 1)
    {
        return $this->http->FindSingleNode("(.//td[not(.//td) and normalize-space(.)='{$field}'])[{$n}]/following-sibling::td[1]", $root);
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($str)
    {
        $year = date("Y", $this->date);
        $in = [
            "#^(\d+)(\w+)$#",
        ];
        $out = [
            "$1 $2 $year",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#[^\d\s-\./]#", $str)) {
            $str = $this->dateStringToEnglish($str);
        }

        return $str;
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
