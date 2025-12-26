<?php

namespace AwardWallet\Engine\kestrelflyer\Email;

class ConfirmationForReservation extends \TAccountChecker
{
    use \DateTimeTools;

    public $mailFiles = 'kestrelflyer/it-6151945.eml';

    protected $lang = null;

    protected $langDetectors = [
        'fr' => [
            'votre sélection de vols',
        ],
    ];

    protected $dict = [
        'Reservation Code' => [
            'fr' => 'Numéro de réservation',
        ],
        'Reservation status' => [
            'fr' => 'État du voyage',
        ],
        'Traveller information' => [
            'fr' => 'informations sur le voyageur',
        ],
        'information' => [
            'fr' => 'informations',
        ],
        'Your flight selection' => [
            'fr' => 'votre sélection de vols',
        ],
        ' to ' => [
            'fr' => ' à ',
        ],
        'Departure' => [
            'fr' => 'Départ',
        ],
        'Arrival' => [
            'fr' => 'Arrivée',
        ],
        'day' => [
            'fr' => 'jour',
        ],
        'Airline' => [
            'fr' => 'Compagnie',
        ],
        'Aircraft' => [
            'fr' => 'Appareil',
        ],
        'Fare type' => [
            'fr' => 'Type de tarif',
        ],
        'Flight payment and ticket' => [
            'fr' => 'Paiement et billet du vol',
        ],
        'Payment' => [
            'fr' => 'Paiement',
        ],
    ];

    protected $regexps = [
        'date' => [
            'fr' => '/(\d{1,2}\s+[^\d\s]+\s+\d{2,4})/',
        ],
    ];

    // Standard Methods

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['from'], 'reservations_mru@airmauritius.com') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@airmauritius.com') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//node()[contains(.,"AIR MAURITIUS")]')->length === 0 && $this->http->XPath->query('//node()[contains(.,"reservations_mru@airmauritius.com")]')->length === 0) {
            return false;
        }

        foreach ($this->langDetectors as $lines) {
            foreach ($lines as $line) {
                if ($this->http->XPath->query('//node()[contains(.,"' . $line . '")]')->length > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        foreach ($this->langDetectors as $lang => $lines) {
            foreach ($lines as $line) {
                if ($this->http->XPath->query('//node()[contains(.,"' . $line . '")]')->length > 0) {
                    $this->lang = $lang;
                }
            }
        }
        $it = $this->ParseEmail();

        return [
            'parsedData' => [
                'Itineraries' => [$it],
            ],
            'emailType' => 'ConfirmationForReservation',
        ];
    }

    public static function getEmailLanguages()
    {
        return ['fr'];
    }

    public static function getEmailTypesCount()
    {
        return 1;
    }

    public function IsEmailAggregator()
    {
        return false;
    }

    protected function translate($s)
    {
        if (isset($this->lang) && isset($this->dict[$s][$this->lang])) {
            return $this->dict[$s][$this->lang];
        } else {
            return $s;
        }
    }

    protected function priceNormalize($string)
    {
        $string = preg_replace('/\s+/', '', $string);			// 11 507.00	->	11507.00
        $string = preg_replace('/[,.](\d{3})/', '$1', $string);	// 2,790		->	2790		or	4.100,00	->	4100,00
        $string = preg_replace('/,(\d{2})$/', '.$1', $string);	// 18800,00		->	18800.00

        return $string;
    }

    protected function ParseEmail()
    {
        // предварительно, удаляем все мусорные узлы из дерева документа
        $nodesToStip = $this->http->XPath->query('//*[name(.)="script" and normalize-space(.)!=""]');

        foreach ($nodesToStip as $nodeToStip) {
            $nodeToStip->parentNode->removeChild($nodeToStip);
        }

        $it = [];
        $it['Kind'] = 'T';
        $it['RecordLocator'] = $this->http->FindSingleNode('(//tr[(starts-with(normalize-space(.),"' . $this->translate('Reservation Code') . '") or starts-with(normalize-space(.),"Booking reservation number")) and not(.//tr)]//text()[normalize-space(.)!=""])[last()]', null, true, '/([A-Z\d]{5,7})/');
        $it['Status'] = $this->http->FindSingleNode('(//tr[starts-with(normalize-space(.),"' . $this->translate('Reservation status') . '") and not(.//tr)]//text()[normalize-space(.)!=""])[last()]', null, true, '/([^\d]+)$/');
        $it['Passengers'] = $this->http->FindNodes('//table[contains(normalize-space(.),"' . $this->translate('Traveller information') . '") and not(.//table) and .//tr]/following::table[.//span[contains(@class,"textBold")]][1]//span[contains(@class,"textBold") and not(contains(.,"' . $this->translate('information') . '")) and not(contains(.,"Information")) and normalize-space(.)!=""]');
        $it['TripSegments'] = [];
        $segments = $this->http->XPath->query('//table[contains(normalize-space(.),"' . $this->translate('Your flight selection') . '") and not(.//table) and .//tr]/following::table[.//th[contains(.,"' . $this->translate(' to ') . '")]]//tr[(contains(.,"' . $this->translate('Departure') . '") or contains(.,"Andata")) and contains(.,"' . $this->translate('Airline') . '")]');

        foreach ($segments as $root) {
            $seg = [];
            $date = $this->http->FindSingleNode('./preceding-sibling::tr[normalize-space(.)!=""][1]//td[normalize-space(.)!=""][last()]', $root, true, $this->regexps['date'][$this->lang]);

            switch ($this->lang) {
                case 'es':
                    $date = preg_replace('/(\d{2})\s+de\s+([^\d\s]+)\s+de\s+(\d{2,4})/', '$1 $2 $3', $date);

                    break;
            }
            $xpathFragment = '(starts-with(normalize-space(.),"' . $this->translate('Departure') . '") or starts-with(normalize-space(.),"Andata")) and not(.//td)';
            $timeDep = $this->http->FindSingleNode('.//td[' . $xpathFragment . ']/following-sibling::td[contains(.,":") and string-length(normalize-space(.))>3][1]', $root, true, '/(\d{1,2}:\d{2})/');
            $nameDep = $this->http->FindSingleNode('(.//td[' . $xpathFragment . ']/following-sibling::td[normalize-space(.)!=""][last()]//text()[normalize-space(.)!=""])[1]', $root);

            if (preg_match('/(.+),\s+terminal\s+([A-Z\d]{1,3})/i', $nameDep, $matches)) {
                $seg['DepName'] = $matches[1];
                $seg['DepartureTerminal'] = $matches[2];
            } else {
                $seg['DepName'] = $nameDep;
            }
            $timeArr_temp = $this->http->FindSingleNode('.//td[starts-with(normalize-space(.),"' . $this->translate('Arrival') . '") and not(.//td)]/following-sibling::td[contains(.,":") and string-length(normalize-space(.))>3][1]', $root);

            if (preg_match('/(\d{1,2}:\d{2})(?:\s*[+]\s*(\d+)\s+' . $this->translate('day') . '|\s*$)/', $timeArr_temp, $matches)) {
                $timeArr = $matches[1];
                $overnight = $matches[2];
            }
            $nameArr = $this->http->FindSingleNode('(.//td[starts-with(normalize-space(.),"' . $this->translate('Arrival') . '") and not(.//td)]/following-sibling::td[normalize-space(.)!=""][last()]//text()[normalize-space(.)!=""])[1]', $root);

            if (preg_match('/(.+),\s+terminal\s+([A-Z\d]{1,3})/i', $nameArr, $matches)) {
                $seg['ArrName'] = $matches[1];
                $seg['ArrivalTerminal'] = $matches[2];
            } else {
                $seg['ArrName'] = $nameArr;
            }

            if ($date && $timeDep && $timeArr) {
                $date = strtotime($this->dateStringToEnglish($date));
                $seg['DepDate'] = strtotime($timeDep, $date);
                $seg['ArrDate'] = strtotime($timeArr . ($overnight ? ' +' . $overnight . ' days' : ''), $date);
            }
            $flight = $this->http->FindSingleNode('.//td[starts-with(normalize-space(.),"' . $this->translate('Airline') . '") and not(.//td)]/following-sibling::td[normalize-space(.)!=""][1]', $root);

            if (preg_match('/\s*([A-Z]{2})\s*(\d+)$/', $flight, $matches)) {
                $seg['AirlineName'] = $matches[1];
                $seg['FlightNumber'] = $matches[2];
            }
            $seg['Aircraft'] = $this->http->FindSingleNode('.//td[starts-with(normalize-space(.),"' . $this->translate('Aircraft') . '") and not(.//td)]/following-sibling::td[normalize-space(.)!=""][1]', $root);
            $seg['Cabin'] = $this->http->FindSingleNode('.//td[starts-with(normalize-space(.),"' . $this->translate('Fare type') . '") and not(.//td)]/following-sibling::td[normalize-space(.)!=""][1]', $root);
            $seg['DepCode'] = TRIP_CODE_UNKNOWN;
            $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
            $it['TripSegments'][] = $seg;
        }
        $payment = $this->http->FindSingleNode('//table[starts-with(normalize-space(.),"' . $this->translate('Flight payment and ticket') . '")]//td[starts-with(normalize-space(.),"' . $this->translate('Payment') . '") and not(.//td)]/following-sibling::td[normalize-space(.)!=""][1]');

        if (preg_match('/^([.,\d\s]+)\s*([^(.,\d]*)\s*/', $payment, $matches)) {
            $it['TotalCharge'] = $this->priceNormalize($matches[1]);
            $it['Currency'] = $matches[2];
        }

        return $it;
    }
}
