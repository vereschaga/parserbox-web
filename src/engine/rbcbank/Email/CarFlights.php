<?php

namespace AwardWallet\Engine\rbcbank\Email;

// it-3100331.eml
// it-3537538.eml
// it-3537541.eml
// it-3828039.eml

class CarFlights extends \TAccountChecker
{
    protected $lang = null;

    protected $langDetectors = [
        'en' => 'Thank you for booking with RBC Rewards Travel.',
        'fr' => 'Merci de faire vos réservations auprès de Voyages RBC Récompenses®.',
    ];

    protected $dict = [
        'Thank you for booking with RBC Rewards Travel.' => [
            'fr' => 'Merci de faire vos réservations auprès de Voyages RBC Récompenses®.',
        ],
        'Price' => [
            'fr' => 'Prix',
        ],
        'Payment Selection' => [
            'fr' => 'Sélection de paiement',
        ],
        'RBC Rewards Redemption Summary' => [
            'fr' => "Sommaire de l'encaissement des récompenses RBC",
        ],
        'Total Charged' => [
            'fr' => 'Total facturé',
        ],
        'Total Redemption' => [
            'fr' => 'Encaissement total',
        ],
        'Your Trip ID' => [
            'fr' => 'Votre code de voyage',
        ],
        'flights' => [
            'fr' => "billets d'avion",
        ],
        'Online check-in code' => [
            'fr' => 'Enregistrement en ligne code',
        ],
        'Passengers' => [
            'fr' => 'Passagers',
        ],
        'Depart' => [
            'fr' => 'Départ',
        ],
        'Arrive' => [
            'fr' => 'Arrivée',
        ],
        'Flight' => [
            'fr' => 'Vol',
        ],
        'Seat request' => [
            'fr' => 'Demande de siège',
        ],
        'Next day' => [
            'fr' => 'Jour suivant',
        ],
    ];

    protected $regexps = [
        'money' => [
            'en' => '/(?<currency>CAD) \$ (?<numbers>[.\d]+)/',
            'fr' => '/(?<numbers>[,\d]+) \$ (?<currency>CAD CA)/',
        ],
    ];

    // Standard Methods

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@rbcrewards.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], 'RBCRewardsTravel@rbcrewards.com') !== false
            || isset($headers['subject']) && (stripos($headers['subject'], 'RBC Rewards Travel Confirmation') !== false
            || stripos($headers['subject'], 'Confirmation Voyages RBC Récompenses') !== false);
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $this->detectLang();

        return isset($this->lang);
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $its = $this->ParseEmail();

        return [
            'parsedData' => [
                'Itineraries' => $its,
            ],
            'emailType' => 'CarFlights',
        ];
    }

    public static function getEmailLanguages()
    {
        return ['en', 'fr'];
    }

    public static function getEmailTypesCount()
    {
        // car and flights
        return 2;
    }

    protected function detectLang()
    {
        unset($this->lang);

        foreach ($this->langDetectors as $lang => $lines) {
            if (stripos($this->http->Response['body'], $lines) !== false) {
                $this->lang = $lang;

                return;
            }
        }
    }

    protected function translate($s)
    {
        if (isset($this->lang) && isset($this->dict[$s][$this->lang])) {
            return $this->dict[$s][$this->lang];
        } else {
            return $s;
        }
    }

    protected function ParseEmail()
    {
        $this->detectLang();
        $its = [];
        $common = [];
        $priceBlocks = $this->http->XPath->query('//tr[normalize-space(.)="' . $this->translate('Price') . '" and not(.//tr)]/ancestor::table[1]');
        $paymentBlocks = $this->http->XPath->query('//tr[normalize-space(.)="' . $this->translate('Payment Selection') . '" and not(.//tr)]/ancestor::table[contains(normalize-space(.),"' . $this->translate('RBC Rewards Redemption Summary') . '")][1]');

        if ($paymentBlocks->length > 0) {
            $paymentBlock = $paymentBlocks->item(0);
            $totalCharged = $this->http->FindSingleNode('.//tr[starts-with(normalize-space(.),"' . $this->translate('Total Charged') . '") and not(.//tr)]/following-sibling::tr[1]/td[1]', $paymentBlock);

            if (preg_match($this->regexps['money'][$this->lang], $totalCharged, $matches)) {
                $common['Currency'] = $matches['currency'];
                $common['TotalCharge'] = str_replace(',', '.', $matches['numbers']);
            }
            $common['SpentAwards'] = $this->http->FindSingleNode('.//tr[contains(normalize-space(.),"' . $this->translate('Total Redemption') . '") and not(.//tr)]/following-sibling::tr[1]/td[2]', $paymentBlock);
        }
        $blocks = $this->http->XPath->query('//tr[contains(.,"' . $this->translate('Your Trip ID') . ':") and not(.//tr)]/following-sibling::tr//tr[not(.//tr)]/parent::*');

        foreach ($blocks as $block) {
            switch (strtolower($this->http->FindSingleNode('./tr[1]', $block))) {
                case $this->translate('flights'):
                    $its[] = $this->ParseFlights($block, $priceBlocks, $common);

                    break;

                case 'car':
                    $its[] = $this->ParseCar($block, $priceBlocks, $common);

                    break;
            }
        }

        return $its;
    }

    protected function ParseCar($root, $priceBlocks, $common)
    {
        $it = [];
        $it['Kind'] = 'L';
        $it['Number'] = $this->http->FindSingleNode('.//strong[starts-with(normalize-space(.),"Confirmation number:")]/following-sibling::text()[1]', $root);
        $it['PickupLocation'] = $this->http->FindSingleNode('.//strong[starts-with(normalize-space(.),"Pick-up location:")]/following-sibling::text()[1]', $root);
        $it['DropoffLocation'] = $this->http->FindSingleNode('.//strong[starts-with(normalize-space(.),"Drop-off location:")]/following-sibling::text()[1]', $root);
        $it['CarType'] = $this->http->FindSingleNode('.//strong[starts-with(normalize-space(.),"Car type:")]/following-sibling::text()[1]', $root);
        $it['RenterName'] = $this->http->FindSingleNode('.//strong[starts-with(normalize-space(.),"Driver:")]/following-sibling::text()[1]', $root);
        $datetimePic = $this->http->FindSingleNode('.//strong[starts-with(normalize-space(.),"Pick-up date:")]/following-sibling::text()[1]', $root);
        $datetimeDro = $this->http->FindSingleNode('.//strong[starts-with(normalize-space(.),"Drop-off date:")]/following-sibling::text()[1]', $root);

        if (preg_match('/([\d]{2}:[\d]{2}(AM|PM)) [\w]+, ([\w]+ [\d]{1,2}, [\d]{4})/i', $datetimePic, $p) && preg_match('/([\d]{2}:[\d]{2}(AM|PM)) [\w]+, ([\w]+ [\d]{1,2}, [\d]{4})/i', $datetimeDro, $d)) {
            $datePic = str_replace(',', '', $p[3]);
            $dateDro = str_replace(',', '', $d[3]);
            $it['PickupDatetime'] = strtotime($datePic . ' ' . $p[1]);
            $it['DropoffDatetime'] = strtotime($dateDro . ' ' . $d[1]);
        }

        if ($priceBlocks->length > 0) {
            $priceBlock = $priceBlocks->item(0);
            $it['TotalTaxAmount'] = $this->http->FindSingleNode('.//tr[contains(normalize-space(.),"Taxes and Fees:") and not(.//tr)]/td[2]', $priceBlock, true, '/\$[\s]*([.\d]+)/');
        }

        if (isset($common['Currency'])) {
            $it['Currency'] = $common['Currency'];
        }

        if (isset($common['TotalCharge'])) {
            $it['TotalCharge'] = $common['TotalCharge'];
        }

        if (isset($common['SpentAwards'])) {
            $it['SpentAwards'] = $common['SpentAwards'];
        }

        return $it;
    }

    protected function ParseFlights($root, $priceBlocks, $common)
    {
        $it = [];
        $it['Kind'] = 'T';
        $nodes = $this->http->XPath->query('.//td[starts-with(normalize-space(.),"' . $this->translate('Online check-in code') . ':") and not(.//td)]', $root);

        if ($nodes->length > 0) {
            $recordLocator = $nodes->item(0);
            $it['RecordLocator'] = $this->http->FindSingleNode('.', $recordLocator, true, '/' . $this->translate('Online check-in code') . ': ([A-Z\d]{6})/');
        }
        $it['Passengers'] = [];
        $passengers = $this->http->XPath->query('.//td[normalize-space(.)="' . $this->translate('Passengers') . '"]/ancestor::tr[1]/following-sibling::tr[count(td)=3]', $root);

        foreach ($passengers as $p) {
            $it['Passengers'][] = $this->http->FindSingleNode('./td[1]', $p);
        }
        $it['TripSegments'] = [];
        $rows = $this->http->XPath->query('.//table[contains(.,"' . $this->translate('Depart') . ':") and not(.//table)]', $root);

        foreach ($rows as $row) {
            $seg = [];
            $nodes = $this->http->XPath->query('.//td[contains(.,"' . $this->translate('Depart') . ':") and not(.//td)]/following-sibling::td[3]', $row);

            if ($nodes->length > 0) {
                $flight = $nodes->item(0);
                $seg['AirlineName'] = $this->http->FindSingleNode('./text()[1]', $flight, true, '/(.+), ' . $this->translate('Flight') . ' [\d]+/');
                $seg['FlightNumber'] = $this->http->FindSingleNode('./text()[1]', $flight, true, '/' . $this->translate('Flight') . ' ([\d]+)/');
                $seg['Cabin'] = $this->http->FindSingleNode('./text()[2]', $flight);
            }
            $nodes = $this->http->XPath->query('.//td[contains(.,"' . $this->translate('Depart') . ':") and not(.//td)]/following-sibling::td[1]', $row);

            if ($nodes->length > 0) {
                $airports = $nodes->item(0);
                $seg['DepName'] = $this->http->FindSingleNode('./text()[1]', $airports, true, '/([-,.\w\d\s]+) \([\w]{3}\)/');
                $seg['ArrName'] = $this->http->FindSingleNode('./text()[2]', $airports, true, '/[\s]*([-,.\w\d\s]+) \([\w]{3}\)/');
                $seg['DepCode'] = $this->http->FindSingleNode('./text()[1]', $airports, true, '/[-,.\w\d\s]+ \(([\w]{3})\)/');
                $seg['ArrCode'] = $this->http->FindSingleNode('./text()[2]', $airports, true, '/[-,.\w\d\s]+ \(([\w]{3})\)/');
            }
            $seats = $this->http->FindSingleNode('.//td[contains(.,"' . $this->translate('Seat request') . ':") and not(.//td)]', $row);

            if (preg_match('/' . $this->translate('Seat request') . ': ([,\w\d\s]+)$/', $seats, $s)) {
                $seg['Seats'] = $s[1];
            }
            $dateIn = $this->http->XPath->query('./preceding-sibling::table', $row)->length !== 0;
            $dateXpath = $dateIn ? './preceding-sibling::table//td[1]' : './../../preceding-sibling::tr[contains(normalize-space(.),"' . $this->translate('Online check-in code') . ':")][1]//table[1]//td[1]';
            $date = $this->http->FindSingleNode($dateXpath, $row);
            $timeDep = $this->http->FindSingleNode('.//strong[starts-with(normalize-space(.),"' . $this->translate('Depart') . ':")]/following-sibling::text()[1]', $row);
            $timeArr = $this->http->FindSingleNode('.//strong[starts-with(normalize-space(.),"' . $this->translate('Arrive') . ':")]/following-sibling::text()[1]', $row);

            if ($date && $timeDep && $timeArr) {
                $date = str_replace(',', '', $date);
                $seg['DepDate'] = strtotime($date . ' ' . $timeDep);
                $nextDay = $this->http->XPath->query('.//strong[starts-with(normalize-space(.),"' . $this->translate('Arrive') . ':")]/following-sibling::*[contains(.,"' . $this->translate('Next day') . '")]', $row)->length !== 0;
                $seg['ArrDate'] = strtotime($date . ($nextDay ? ' +1 day ' : ' ') . $timeArr);
            }
            $it['TripSegments'][] = $seg;
        }

        if (isset($common['Currency'])) {
            $it['Currency'] = $common['Currency'];
        }

        if (isset($common['TotalCharge'])) {
            $it['TotalCharge'] = $common['TotalCharge'];
        }

        if (isset($common['SpentAwards'])) {
            $it['SpentAwards'] = $common['SpentAwards'];
        }

        return $it;
    }
}
