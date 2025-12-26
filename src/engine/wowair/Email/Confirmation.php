<?php

namespace AwardWallet\Engine\wowair\Email;

class Confirmation extends \TAccountChecker
{
    public $mailFiles = "wowair/it-10714264.eml, wowair/it-10714269.eml, wowair/it-3130276.eml, wowair/it-3925904.eml, wowair/it-5030822.eml, wowair/it-5030823.eml, wowair/it-6563232.eml";

    public $reBody = [
        'en' => ['Booking Confirmation and Itinerary', 'Description'],
        'nl' => ['Boekingsbevestiging en route', 'Beschrijving'],
        'fr' => ["Confirmation de la réservation", 'Description'],
    ];
    public $reSubject = [
        'Confirmation from WOW air',
        'Bevestiging van WOW air',
        'Confirmation de la part de WOW air',
    ];
    public $lang = 'en';
    public static $dict = [
        'en' => [
            'RL'           => ['Reservation number', 'Confirmation Number'],
            'FlightNumber' => 'Flight\s+Number',
        ],
        'nl' => [
            'RL'              => 'VIP nummer',
            'FlightNumber'    => 'Vluchtnummer',
            'From'            => 'Van',
            'To'              => 'Naar',
            'Original Amount' => 'Oorspronkelijk bedrag',
            'Description'     => 'Beschrijving',
            'Guest'           => 'Gast',
            'Total paid'      => 'Totaal betaald',
        ],
        'fr' => [
            'RL'              => 'Votre numéro spécial VIP',
            'FlightNumber'    => 'Vols numéro',
            'From'            => 'De',
            'To'              => 'à',
            'Original Amount' => "Montant d'origine",
            'Description'     => 'Description',
            'Guest'           => 'Invité',
            'Total paid'      => 'Total payé',
        ],
    ];

    public function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = \AwardWallet\Engine\MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $body = $this->http->Response['body'];
        $this->AssignLang($body);

        $its = $this->parseEmail();

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => $a[count($a = explode('\\', __CLASS__)) - 1] . ucfirst($this->lang),
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
        return stripos($from, "wowair") !== false;
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
        $rl = $this->t('RL');

        if (!is_array($rl)) {
            $rl = [$rl];
        }
        $rule = implode(" or ", array_map(function ($s) {
            return "contains(text(),'{$s}')";
        }, $rl));
        $it['RecordLocator'] = $this->http->FindSingleNode("//*[{$rule}]/ancestor::div[1]", null, true, "#\s*(\w+)$#");

        $it['Passengers'] = array_unique($this->http->FindNodes("//*[contains(text(),\"" . $this->t('Original Amount') . "\") or contains(text(),'" . $this->t('Description') . "')]/ancestor::table[1]//tr/td[position()=1 and not(contains(.,'" . $this->t('Guest') . "'))]//text()[string-length(normalize-space(.))>3 and not(contains(.,'span'))]"));
        $total = $this->http->FindSingleNode("//*[contains(text(),'" . $this->t('Total paid') . "')]/ancestor::tr[1]/td[4]");

        if (preg_match("#(\d+[\.\,]\d+)\s*([A-Z]{3})#", $total, $m)) {
            $it['TotalCharge'] = str_replace(",", ".", $m[1]);
            $it['Currency'] = $m[2];
        }
        $xpath = "//img[contains(@src, 'airplane-icon.png')]/ancestor::tr[1]/following::tr//text()[starts-with(.,'" . $this->t('From') . "')]/ancestor::tr[1][not(contains(., '" . $this->t('Guest') . "'))]";
        $roots = $this->http->XPath->query($xpath);

        foreach ($roots as $root) {
            $seg = [];
            $node = implode("\n", $this->http->FindNodes("./td[2]//text()[normalize-space(.)]", $root));
            $w = $this->t('FlightNumber');

            if (preg_match("#{$w}\s*(?:\:\s*|\(\w{2}\)\s*\:\s*)?(\w{2})\s*(\d+)#i", $node, $m)) {
                $seg['AirlineName'] = $m[1];
                $seg['FlightNumber'] = $m[2];
            }
            $w = $this->t('To');

            if (preg_match("#{$w}\s*:\s*(.+)\s*\(\s*([A-Z]{3})\s*\)#", $node, $m)) {
                $seg['ArrName'] = trim($m[1]);
                $seg['ArrCode'] = $m[2];
            }

            if (preg_match("#,\s*((?:\d+(?:\w+)?\s*\w+.*,\s*\d+\s*\-?\s*\d+\:\d+|\w+\s*\d+,\s*\d+\s*\-?\s*\d+\:\d+(?:\:\d+)?\s*(?:[AP]M)?))#", $node, $m)) {
                $seg['ArrDate'] = strtotime($this->normalizeDate($m[1]));
            }

            if (preg_match("#Terminal:\s+Arrivals\s*(.+)#i", $node, $m)) {
                $seg['ArrivalTerminal'] = $m[1];
            }

            if (preg_match("#Operated\s+by\s*:\s*(.+)#i", $node, $m)) {
                $seg['Operator'] = $m[1];
            }

            $node = implode("\n", $this->http->FindNodes("./td[1]//text()[normalize-space(.)]", $root));
            $w = $this->t('From');

            if (preg_match("#{$w}\s*:\s*(.+)\s*\(\s*([A-Z]{3})\s*\)#", $node, $m)) {
                $seg['DepName'] = trim($m[1]);
                $seg['DepCode'] = $m[2];
            }

            if (preg_match("#,\s*((?:\d+(?:\w+)?\s*\w+.*,\s*\d+\s*\-?\s*\d+\:\d+|\w+\s*\d+,\s*\d+\s*\-?\s*\d+\:\d+(?:\:\d+)?\s*(?:[AP]M)?))#", $node, $m)) {
                $seg['DepDate'] = strtotime($this->normalizeDate($m[1]));
            }

            if (preg_match("#Terminal:\s+Departures\s*(.+)#i", $node, $m)) {
                $seg['DepartureTerminal'] = $m[1];
            }

            if (preg_match("#Flight\s+duration\s*:\s*(.+)#i", $node, $m)) {
                $seg['Duration'] = $m[1];
            }
            $node = $this->http->FindNodes("./following-sibling::tr//td[position()=2 and contains(.,'Seat:')]", $root, "#Seat:\s*(\w+)#");

            if (count($node) == 0) {
                $node = $this->http->FindNodes("./following-sibling::tr//td[position()=1 and string-length(normalize-space(.))=3 or string-length(normalize-space(.))=2]", $root);
            }
            $seg['Seats'] = implode(',', $node);
            $seg = array_filter($seg);
            $it['TripSegments'][] = $seg;
        }

        return [$it];
    }

    private function normalizeDate($date)
    {
        $in = [
            '#^\s*(\d+)(?:\w+)?\s*(\w+).*,\s*(\d+)\s*\-?\s*(\d+\:\d+)\s*$#',
            '#^\s*(\w+)\s+(\d+),\s*(\d+)\s*\-?\s*(\d+\:\d+(?:\:\d+)?\s*(?:[AP]M)?)\s*$#',
        ];
        $out = [
            '$1 $2 $3 $4',
            '$2 $1 $3 $4',
        ];
        $str = $this->dateStringToEnglish(preg_replace($in, $out, $date));

        return $str;
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
