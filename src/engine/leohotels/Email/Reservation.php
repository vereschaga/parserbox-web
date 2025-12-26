<?php

namespace AwardWallet\Engine\leohotels\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class Reservation extends \TAccountChecker
{
    public $mailFiles = "leohotels/it-179639575.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'confNumber' => ['Reservation:'],
            'checkIn'    => ['Check-in:'],
//            'Rooms:' => '',
//            'Guest name:' => '',
//            'Check-out:' => '',
//            'Status:' => '',
//            'Room #' => '',
//            'Number of guests:' => '',
//            'adult' => '',
//            'children' => '',
//            'Room type:' => '',
//            'Meal plan:' => '',
//            'Rate description:' => '',
//            'Total reservation price:' => '',
//            'points' => '',
//            'The points will be credited on your Leonardo AdvantageCLUB account' => '',
        ],
        'de' => [
            'confNumber' => ['Reservierung:'],
            'Guest name:' => 'Name:',
            'checkIn'    => ['Ankunftsdatum:'],
            'Check-out:' => 'Abreisedatum:',
            'Status:' => 'Status:',
            'Rooms:' => 'Zimmer:',
            'Room #' => 'Zimmer #',
            'Number of guests:' => 'Anzahl Gäste:',
            'adult' => 'Erwachsen',
            'children' => 'Kinder',
            'Room type:' => 'Zimmerkategorie:',
            'Meal plan:' => 'Verpflegung:',
            'Rate description:' => 'Reservierungdetails:',
            'Total reservation price:' => 'Gesamtpreis:',
            'points' => 'punkte', // no example, need to check
            'The points will be credited on your Leonardo AdvantageCLUB account' => 'Punkte werden Ihrem Leonardo AdvantageCLUB Konto',
        ],
        'it' => [
            'confNumber' => ['Prenotazione:'],
            'Guest name:' => "Nome dell'ospite:",
            'checkIn'    => ['Check-in:'],
            'Check-out:' => 'Check-out:',
            'Status:' => 'Status:',
            'Rooms:' => 'Camere:',
            'Room #' => 'Camera #',
            'Number of guests:' => 'Numero di ospiti:',
            'adult' => 'adult',
            'children' => 'bambini',
            'Room type:' => 'Tipologia di camera:',
            'Meal plan:' => 'Piano alimentare:',
            'Rate description:' => 'Dettagli sulla tariffa:',
            'Total reservation price:' => 'Totale:',
            'points' => 'punti', // no example, need to check
            'The points will be credited on your Leonardo AdvantageCLUB account' => 'I punti saranno aggiunti sul tuo conto di Leonardo AdvantageCLUB',
        ],
    ];

    private $subjects = [
        'en' => ['Reservation Confirmation'],
        'de' => ['Ihre Reservierungsbestätigung'],
        'it' => ['Conferma della prenotazione'],
    ];

    private $detectors = [
        'en' => ['Your stay'],
        'de' => ['Ihr Aufenthalt'],
        'it' => ['Il tuo soggiorno'],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@leonardo-hotels.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->subjects as $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (is_string($phrase) && stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->detectEmailFromProvider($parser->getHeader('from')) !== true
            && $this->http->XPath->query('//a[contains(@href,".leonardo-hotels.com/") or contains(@href,"www.leonardo-hotels.com")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"your Leonardo AdvantageCLUB account") or contains(.,"@leonardo-hotels.com")]')->length === 0
        ) {
            return false;
        }

        return $this->detectBody() && $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }
        $email->setType('Reservation' . ucfirst($this->lang));

        $this->parseHotel($email);

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function parseHotel(Email $email): void
    {
        $patterns = [
            'time'          => '\d{1,2}(?:[:：]\d{2})?(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.    |    3pm
            'phone'         => '[+(\d][-+. \d)(]{5,}[\d)]', // +377 (93) 15 48 52    |    (+351) 21 342 09 07    |    713.680.2992
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
        ];

        $xpathFilter = "not(preceding::table[{$this->eq($this->t('Rooms:'))}])";

        $h = $email->add()->hotel();

        $confirmation = $this->http->FindSingleNode("//text()[{$this->starts($this->t('confNumber'))}]/following::text()[normalize-space()][1]", null, true, '/^[A-Z\d]{5,}$/');

        if ($confirmation) {
            $confirmationTitle = $this->http->FindSingleNode("//text()[{$this->starts($this->t('confNumber'))}]", null, true, '/^(.+?)[\s:：]*$/u');
            $h->general()->confirmation($confirmation, $confirmationTitle);
        }

        $xpathHotel = "//img/ancestor::*[ following-sibling::*[normalize-space()] ][1][normalize-space()='']/following-sibling::*[normalize-space()][1][ descendant::text()[normalize-space()][1][ancestor::h5] ]/descendant-or-self::*[ *[normalize-space()][2] ][1]";

        $hotelName = $this->http->FindSingleNode($xpathHotel . "/*[normalize-space()][1]");
        $address = $this->http->FindSingleNode($xpathHotel . "/*[normalize-space()][2]");
        $phone = $this->http->FindSingleNode($xpathHotel . "/*[normalize-space()][3]", null, true, "/^{$patterns['phone']}$/");

        $h->hotel()->name($hotelName)->address($address)->phone($phone, false, true);

        $traveller = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Guest name:'))}] ][{$xpathFilter}]/*[normalize-space()][2]", null, true, "/^{$patterns['travellerName']}$/u");
        $checkInVal = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('checkIn'))}] ][{$xpathFilter}]/*[normalize-space()][2]", null, true, '/^.*\d.*$/');
        $checkOutVal = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Check-out:'))}] ][{$xpathFilter}]/*[normalize-space()][2]", null, true, '/^.*\d.*$/');
        $status = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Status:'))}] ][{$xpathFilter}]/*[normalize-space()][2]");

        $h->general()->traveller($traveller, true)->status($status);

        // Thursday 23.06.2022 (2:00 pm)
        $patterns['dateTime'] = "/^(?<date>[^)(]*\d[^)(]*?)\s*\(\s*(?<time>{$patterns['time']})\s*\)$/";

        // Sunday 04.12.2022 (Sun - Fri: 3:00 pm Sat and Jewish Holidays: 6:00 pm)
        $patterns['dateTime2'] = "/^(?<date>[^)(]*\d[^)(]*?)\s*\(.+\)$/";

        if (preg_match($patterns['dateTime'], $checkInVal, $m)
            || preg_match($patterns['dateTime2'], $checkInVal, $m)
        ) {
            if (!array_key_exists('time', $m)) {
                $m['time'] = '00:00';
            }
            $h->booked()->checkIn(strtotime($m['time'], strtotime($m['date'])));
        } elseif ($checkInVal) {
            $h->booked()->checkIn2($checkInVal);
        }

        if (preg_match($patterns['dateTime'], $checkOutVal, $m)
            || preg_match($patterns['dateTime2'], $checkOutVal, $m)
        ) {
            if (!array_key_exists('time', $m)) {
                $m['time'] = '00:00';
            }
            $h->booked()->checkOut(strtotime($m['time'], strtotime($m['date'])));
        } elseif ($checkOutVal) {
            $h->booked()->checkOut2($checkOutVal);
        }

        $adults = $kids = $rateDescriptionList = [];

        $rooms = $this->http->XPath->query("//table[{$this->eq($this->t('Rooms:'))}]/following-sibling::table[ descendant::text()[normalize-space()][1][{$this->starts($this->t('Room #'), 'translate(normalize-space(),"0123456789","##########")')}] ]");

        foreach ($rooms as $root) {
            $guestsVal = $this->http->FindSingleNode("descendant::tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Number of guests:'))}] ]/*[normalize-space()][2]", $root);

            if (preg_match("/\b(\d{1,3})\s*{$this->opt($this->t('adult'))}/", $guestsVal, $m)) {
                $adults[] = $m[1];
            }

            if (preg_match("/\b(\d{1,3})\s*{$this->opt($this->t('children'))}/", $guestsVal, $m)) {
                $kids[] = $m[1];
            }

            $room = $h->addRoom();

            $roomType = $this->http->FindSingleNode("descendant::tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Room type:'))}] ]/*[normalize-space()][2]", $root);
            $room->setType($roomType);

            $mealPlan = $this->http->FindSingleNode("descendant::tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Meal plan:'))}] ]/*[normalize-space()][2]", $root);
            $room->setDescription($mealPlan);

            $rateDescriptionList[] = $this->http->FindSingleNode("descendant::tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Rate description:'))}] ]/*[normalize-space()][2]", $root);
        }

        $guestsVal = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Number of guests:'))}] ]/*[normalize-space()][2]");

        if (count($adults) > 0) {
            $h->booked()->guests(array_sum($adults));
        }

        if (count($kids) > 0) {
            $h->booked()->kids(array_sum($kids));
        }

        $rateDescriptionList = array_filter($rateDescriptionList);
        $rateDescription = count(array_unique($rateDescriptionList)) === 1 ? array_shift($rateDescriptionList) : null;

        if (preg_match("/Cancellable (?i)until (?<hour>{$patterns['time']}) on the day of arrival\./", $rateDescription, $m)
        ) {
            $h->booked()->deadlineRelative('1 days', $m['hour']);
        }

        $totalPrice = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Total reservation price:'))}] ]/*[normalize-space()][2]", null, true, '/^.*\d.*$/');

        if (preg_match("/^(?<price>.*?\d.*?)\s*[,]+\s*(?<points>\d+\s+{$this->opt($this->t("points"))})$/i", $totalPrice, $m)) {
            // ₪ 2192.31, 10257 points
            $totalPrice = $m['price'];
            $h->price()->spentAwards($m['points']);
        }

        if (preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.\'\d ]*)$/', $totalPrice, $matches)) {
            // € 5077.90
            $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
            $h->price()->currency($matches['currency'])->total(PriceHelper::parse($matches['amount'], $currencyCode));
        }

        $earnedPoints = $this->http->FindSingleNode("descendant::*[{$this->contains($this->t('The points will be credited on your Leonardo AdvantageCLUB account'))}][last()]", null, true, "/^(\d+)\s*{$this->opt($this->t('The points will be credited on your Leonardo AdvantageCLUB account'))}/");

        if ($earnedPoints !== null) {
            $h->program()->earnedAwards($earnedPoints . ' points');
        }
    }

    private function detectBody(): bool
    {
        if (!isset($this->detectors)) {
            return false;
        }

        foreach ($this->detectors as $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (!is_string($phrase)) {
                    continue;
                }

                if ($this->http->XPath->query("//*[{$this->contains($phrase)}]")->length > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['confNumber']) || empty($phrases['checkIn'])) {
                continue;
            }

            if ($this->http->XPath->query("//*[{$this->contains($phrases['confNumber'])}]")->length > 0
                && $this->http->XPath->query("//*[{$this->contains($phrases['checkIn'])}]")->length > 0
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function t(string $phrase, string $lang = '')
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return $phrase;
        }

        if ($lang === '') {
            $lang = $this->lang;
        }

        if (empty(self::$dictionary[$lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$lang][$phrase];
    }

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'contains(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'normalize-space(' . $node . ')=' . $s;
        }, $field)) . ')';
    }

    private function starts($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'starts-with(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }
}
