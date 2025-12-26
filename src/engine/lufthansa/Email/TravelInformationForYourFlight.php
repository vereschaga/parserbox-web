<?php

namespace AwardWallet\Engine\lufthansa\Email;

class TravelInformationForYourFlight extends \TAccountChecker
{
    public $mailFiles = "lufthansa/it-11180157.eml, lufthansa/it-4039116.eml, lufthansa/it-4043457.eml, lufthansa/it-4045517.eml, lufthansa/it-4423123.eml, lufthansa/it-4429655.eml, lufthansa/it-6223305.eml, lufthansa/it-7334755.eml"; // +bcdtravel

    protected $lang = null;

    protected $langDetectors = [
        'en' => [
            'Your flight booking',
            'Travel information for your flight on',
        ],
        'de' => [
            'Ihre Flugbuchung',
            'Ihre Fluginformationen',
        ],
        'pt' => [
            'A sua reserva',
        ],
        'fr' => [
            'Votre réservation de vol',
        ],
        'it' => [
            'La sua prenotazione',
        ],
        'es' => [
            'Su reserva',
        ],
        'ru' => [
            'Спасибо, что Вы выбрали Lufthansa для своего путешествия',
            'Спасибо, что Вы решили совершить часть Вашего путешествия с Lufthansa',
        ],
        'el' => [
            'Η αεροπορική σας κράτηση',
        ],
        'pl' => [
            'Ważne informacje dot. Twego lotu',
        ],
    ];

    protected $dict = [
        'Booking Code' => [
            'en' => ['Booking Code', 'BOOKING CODE'],
            'de' => ['Buchungscode', 'BUCHUNGSCODE'],
            'pt' => 'Código da reserva',
            'fr' => 'Code de réservation',
            'it' => 'Codice di prenotazione',
            'es' => 'Código de reserva',
            'ru' => 'Код бронирования',
            'el' => 'ΚΩΔΙΚΟΣ ΚΡΑΤΗΣΗΣ',
            'pl' => ['Kod rezerwacji', 'KOD REZERWACJI'],
        ],
        'Dear' => [
            'de' => ['Sehr geehrter', 'Sehr geehrte'],
            'pt' => ['Caro Sr.', 'Cara Srª.'],
            'fr' => ['Cher'],
            'it' => ['Egregio Signor', 'Gentile Signora'],
            'es' => ['Estimado Sr.'],
            'ru' => ['Уважаемый г-н', 'Уважаемая г-жа'],
            'el' => ['Αγαπητέ κύριε'],
            'pl' => ['Szanowny Panie', 'Szanownya Pani'],
        ],
        'The booking code cannot be read as you have not authorised unencrypted messages' => [
            //            'de' => ['Sehr geehrter'],
            //            'pt' => ['Caro Sr.', 'Cara Srª.'],
            //            'fr' => ['Cher'],
            'it' => ['Il codice di prenotazione non è leggibile perché manca la sua autorizzazione all’invio non cifrato'],
            //            'es' => ['Estimado Sr.'],
            //            'ru' => ['Уважаемый г-н', 'Уважаемая г-жа'],
            //            'el' => ['Αγαπητέ κύριε'],
            //            'pl' => ['Szanowny Panie', 'Szanownya Pani'],
        ],
    ];

    // Standard Methods

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@newsletter.lufthansa.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (!isset($headers['from'],$headers['subject'])) {
            return false;
        }

        if (stripos($headers['from'], 'information@newsletter.lufthansa.com') !== false) {
            return true;
        }
        $subjects = [
            'de' => 'Reise-Informationen für Ihren Lufthansa Flug',
            'en' => 'Travel information for your flight on',
        ];

        foreach ($subjects as $subject) {
            if (stripos($headers['subject'], $subject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (strpos($body, 'Lufthansa') === false) {
            return false;
        }

        foreach ($this->langDetectors as $phrases) {
            foreach ($phrases as $phrase) {
                if (stripos($body, $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();
        $softHyphen = chr(194) . chr(173);
        $body = str_replace($softHyphen, ' ', $body);
        $this->http->SetEmailBody($body);

        foreach ($this->langDetectors as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if (stripos($body, $phrase) !== false) {
                    $this->lang = $lang;

                    break 2;
                }
            }
        }
        $it = $this->parseEmail();

        return [
            'parsedData' => [
                'Itineraries' => [$it],
            ],
            'emailType' => 'TravelInformationForYourFlight' . ucfirst($this->lang),
        ];
    }

    public static function getEmailLanguages()
    {
        return ['en', 'de', 'pt', 'fr', 'it', 'es', 'ru', 'el', 'pl'];
    }

    public static function getEmailTypesCount()
    {
        return 9;
    }

    protected function translate($s)
    {
        if (isset($this->lang) && isset($this->dict[$s][$this->lang])) {
            return $this->dict[$s][$this->lang];
        } else {
            return $s;
        }
    }

    protected function parseEmail()
    {
        $patterns = [
            'allowedChars' => "#[^\w\d,. \t\r\n_/+:;\"'’<>?~`!@\#$%^&*\[\]=\(\)\-{}£¥₣₤₧€\$\|]#imsu",
            'time'         => '/^\d{1,2}:\d{2}$/',
        ];

        $it = [];

        $it['Kind'] = 'T';

        $recordLocator = $this->http->FindSingleNode('//text()[(' . $this->eq($this->translate('Booking Code')) . ')]/ancestor::td[1]/following-sibling::td[1]', null, true, '/([A-Z\d]{5,8})/');

        if (empty($recordLocator)) {
            $recordLocator = $this->http->FindSingleNode('//text()[(' . $this->contains($this->translate('Booking Code')) . ')]/ancestor::td[1]', null, true, '/' . $this->opt($this->translate('Booking Code')) . '[:]*\s+([A-Z\d]{5,8})/i');
        }

        if (empty($recordLocator)) {
            $recordLocator = $this->http->FindSingleNode('//img[(' . $this->contains($this->translate('Booking Code'), '@alt') . ')]/@alt', null, true, '/' . $this->opt($this->translate('Booking Code')) . '[:]*\s*([A-Z\d]{5,8})\b/i');
        }

        if (empty($recordLocator) && empty($this->http->FindSingleNode('(//*[(' . $this->contains($this->translate('Booking Code')) . ')] | //img[(' . $this->contains($this->translate('Booking Code'), '@alt') . ')]/@alt)[1]'))) {
            $it['RecordLocator'] = CONFNO_UNKNOWN;
        }
        $recordLocator = preg_replace($patterns['allowedChars'], '', $recordLocator);

        if (preg_match('/^([A-Z\d]{5,7})/', $recordLocator, $matches)) {
            $it['RecordLocator'] = $matches[1];
        } elseif ($this->http->XPath->query("//*[{$this->contains($this->translate('The booking code cannot be read as you have not authorised unencrypted messages'))}]")->length > 0) {
            $it['RecordLocator'] = CONFNO_UNKNOWN;
        }

        $it['Passengers'] = array_filter([str_replace(',', '', $this->http->FindSingleNode("(//text()[" . $this->contains($this->translate('Dear')) . "])[1]", null, true, '/' . $this->opt($this->translate('Dear')) . '\s+(.+)/u'))]);

        if (empty($it['Passengers'])) {
            $it['Passengers'] = array_filter([str_replace(',', '', $this->http->FindSingleNode("//img[" . $this->contains($this->translate('Dear'), '@alt') . "]/@alt", null, true, '/\b' . $this->opt($this->translate('Dear')) . '\s+(.+?),/'))]);
        }

        if (!empty($it['Passengers'][0]) && stripos($it['Passengers'][0], 'lufthansa') !== false) {
            unset($it['Passengers']);
        }
        $it['TripSegments'] = [];

        //		$xpathSegments = '//text()[normalize-space(.)="My baggage" or normalize-space(.)="My service" or normalize-space(.)="My options"]/ancestor::table[.//text()[normalize-space(.)="My baggage" or normalize-space(.)="My service" or normalize-space(.)="My options"] and position()>2 and position()<4][1]';
        $xpathSegments = '//img[(contains(@src,"icon_lh_logo.") or contains(@src,"star_alli2.")) and ./ancestor::*[not(contains(@style,"none"))]]/ancestor::table[5]';
        $segments = $this->http->XPath->query($xpathSegments);

        if ($segments->length == 0) {
            $xpathSegments = '//span[@class = "flightnr" and ./ancestor::*[not(contains(@style,"none"))]]/ancestor::table[5]';
            $segments = $this->http->XPath->query($xpathSegments);
        }

        foreach ($segments as $segment) {
            /** @var \AwardWallet\ItineraryArrays\AirTripSegment $seg */
            $seg = [];

            $xpathFragment1 = 'descendant::tr[contains(.,":") and string-length(normalize-space(.))>3 and not(.//tr)][1]';
            $xpathFragment2 = '/following-sibling::tr[string-length(normalize-space(.))>1][1]';

            $timeDep = $this->http->FindSingleNode($xpathFragment1, $segment);

            $seg['DepName'] = $this->http->FindSingleNode($xpathFragment1 . $xpathFragment2, $segment);

            $timeArr = $this->http->FindSingleNode($xpathFragment1 . '/ancestor::table[1]/following-sibling::table[1]/' . $xpathFragment1, $segment);

            $overnight = $this->http->XPath->query($xpathFragment1 . '/ancestor::table[1]/following-sibling::table[1]//img[contains(@src,"icon_plus_ein_tag.gif")]', $segment);

            $seg['ArrName'] = $this->http->FindSingleNode($xpathFragment1 . '/ancestor::table[1]/following-sibling::table[1]/' . $xpathFragment1 . $xpathFragment2, $segment);

            $date = $this->http->FindSingleNode($xpathFragment1 . '/ancestor::table[2]/preceding-sibling::table[1]/descendant::td[string-length(normalize-space(.))>5 and not(.//td)][1]', $segment);

            $flight = $this->http->FindSingleNode($xpathFragment1 . '/ancestor::table[2]/preceding-sibling::table[1]/descendant::td[string-length(normalize-space(.))>2 and not(.//td)][last()]', $segment);

            if (preg_match('/^([A-Z\d]{2})\s*(\d+)$/', $flight, $matches)) {
                $seg['AirlineName'] = $matches[1];
                $seg['FlightNumber'] = $matches[2];
            }

            $timeDep = preg_replace($patterns['allowedChars'], '', $timeDep);
            $timeArr = preg_replace($patterns['allowedChars'], '', $timeArr);
            $date = preg_replace($patterns['allowedChars'], '', $date);

            if (preg_match($patterns['time'], $timeDep) && preg_match($patterns['time'], $timeArr) && preg_match('/(\d{1,2}\.\d{1,2}\.\d{2,4})$/', $date, $matches)) {
                $seg['DepDate'] = strtotime($matches[1] . ', ' . $timeDep);
                $seg['ArrDate'] = strtotime($matches[1] . ', ' . $timeArr);

                if ($overnight->length > 0) {
                    $seg['ArrDate'] = strtotime('+1 days', $seg['ArrDate']);
                }
            }
            $seg['Seats'] = array_unique(array_values(array_filter($this->http->FindNodes($xpathFragment1 . '/ancestor::table[1]/following-sibling::table[2]//td[string-length(normalize-space(.))>1]', $segment, "#^\s*\d{1,3}[A-Z]\s*$#"))));

            if ($meal = $this->http->FindSingleNode('./descendant::img[contains(@src,"/meal_")]/ancestor::td[1]/following-sibling::td[not(.//img) and position()=1]', $segment)) {
                $seg['Meal'] = $meal;
            }

            if ($compartment = $this->http->FindSingleNode('./descendant::tr[./preceding-sibling::tr[starts-with(normalize-space(.),"My service")] and ./following-sibling::tr[starts-with(normalize-space(.),"My options")]]//tr[count(./td)=2][1]/td[1]', $segment)) {
                $seg['Cabin'] = $compartment;
            }

            if (!empty($seg['DepDate']) && !empty($seg['ArrDate']) && !empty($seg['FlightNumber'])) {
                $seg['DepCode'] = $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
                $it['TripSegments'][] = $seg;
            }
        }

        return $it;
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) { return "normalize-space(.)=\"{$s}\""; }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) { return "starts-with(normalize-space(.), \"{$s}\")"; }, $field));
    }

    private function contains($field, $text = '.')
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) use ($text) { return "contains(normalize-space({$text}), \"{$s}\")"; }, $field));
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", $field) . ')';
    }
}
