<?php

namespace AwardWallet\Engine\triprewards\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

// TODO: move hotel-2 in new parser, because intersect keywords from $dict

class HotelReservation extends \TAccountChecker
{
    public $mailFiles = "triprewards/it-13549646.eml, triprewards/it-13956568.eml, triprewards/it-3711816.eml, triprewards/it-3770274.eml, triprewards/it-3771525.eml, triprewards/it-3927338.eml, triprewards/it-41478325.eml, triprewards/it-669851560.eml"; // +1 bcdtravel(html)[es]

    public $lang = '';
    public $hotelName;

    public static $dict = [
        'fr' => [
            // hotel-1
            'Confirmation Number:' => 'Numéro de confirmation:',
            // hotel-2
            'Check-In'             => 'Arrivée',
            'Checkout'             => 'Départ',
            'Number of Guests'     => "Nombre d'invités",
            'Adult'                => 'Adulte(s)',
            'Child'                => 'Enfant(s)',
            'Reservation Details'  => 'Détails de la réservation',
            'Room Description'     => 'Description de la chambre',
            //"You'll Earn"          => 'Gratuit pour moi',
            'Total for Stay'       => 'Total pour le séjour',
            'Cancellation Policy'  => "Politique d'annulation",
            'welcome->patterns'    => ['__NAME__, __WELCOME__ __STATUS__'],
            'welcome->texts'       => ['Votre réservation est'],
            'welcome->statuses'    => ['confirmée'],
            //            '' => '',
        ],
        'de' => [
            // hotel-1
            'Confirmation Number:' => 'Bestätigungsnummer:',
            'Check-In:'            => 'Check-in:',
            'Check-Out:'           => 'Check-out:',
            'Stay'                 => 'Aufenthalt',
            'Night'                => 'Übernachtungen',
            'Hotel Website'        => 'Website des Hotels',
            'Hotel Email'          => 'E-Mail-Adresse des Hotels',
            'Phone'                => 'Telefon',
            'Occupancy:'           => 'Belegung:',
            'Adult'                => 'Erwachsene',
            'Child'                => 'Kinder',
            'Total for Stay'       => 'Gesamtbetrag für Aufenthalt',
            'Cancellation Policy:' => 'Stornierungsrichtlinie:',
            // hotel-2
            //            '' => '',
        ],
        'es' => [
            // hotel-1
            //            '' => '',
            // hotel-2
            'Confirmation Number:' => 'Número de confirmación:',
            'Check-In'             => 'Check In:',
            'Checkout'             => 'Check Out:',
            'Number of Guests'     => 'Número de huéspedes',
            'Adult'                => 'Adulto',
            'Child'                => 'Niño',
            'Reservation Details'  => 'Detalles de la reserva',
            'Room Description'     => 'Descripción de la habitación',
            "You'll Earn"          => 'Ganarás',
            'Total for Stay'       => 'Total de la estancia',
            'Cancellation Policy'  => 'Política de cancelación',
            'welcome->patterns'    => ['__NAME__, __WELCOME__ __STATUS__'],
            'welcome->texts'       => ['tu reserva se encuentra'],
            'welcome->statuses'    => ['confirmada'],
        ],
        'en' => [
            // hotel-1
            //            '' => '',
            // hotel-2
            'welcome->patterns' => ['__WELCOME__ __STATUS__, __NAME__'],
            'welcome->texts'    => ['Your Reservation Is'],
            'welcome->statuses' => ['Confirmed'],
        ],
    ];

    private $subjects = [
        'fr' => ['Tout est prêt : Numéro de confirmation de réservation :'],
        'de' => ['Bestätigung Ihrer Reservierung bei'],
        'es' => ['Confirmación de la reserva'],
        'en' => ['Your reservation confirmation from'],
    ];

    private $langDetectors = [
        'fr' => ['Numéro de confirmation:'],
        'de' => ['Bestätigungsnummer:'],
        'es' => ['Número de confirmación:'],
        'en' => ['Confirmation Number:'],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }
        $this->parseEmail($email);
        $email->setType('HotelReservation' . ucfirst($this->lang));

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        $formats = 2;
        $cnt = $formats * count(self::$dict);

        return $cnt;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[.@]wyndhamhotelgroup\.com/i', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->subjects as $phrases) {
            foreach ($phrases as $phrase) {
                if (stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $condition1 = $this->http->XPath->query('//node()[contains(normalize-space(.),"Wyndham Rewards") or contains(.,"@emails.wyndhamhotelgroup.com") or contains(normalize-space(), "Wyndham Hotels & Resorts")]')->length === 0;
        $condition2 = $this->http->XPath->query('//a[contains(@href,"//www.wyndhamhotels.com") or contains(@href,".wyndhamhotelgroup.com/") or contains(@href,".wyndhamhotels.com/")]')->length === 0;

        if ($condition1 && $condition2) {
            return false;
        }

        return $this->assignLang();
    }

    protected function dates($start, $end, $dur)
    {
        if (preg_match("#^(\d+)/(\d+)/(\d{4}) (\d+:\d+):\d+$#", $start, $sm) && preg_match("#^(\d+)/(\d+)/(\d{4}) (\d+:\d+):\d+$#", $end, $em)) {
            $s = strtotime($sm[1] . '/' . $sm[2] . '/' . $sm[3] . ', ' . $sm[4]);
            $e = strtotime($em[1] . '/' . $em[2] . '/' . $em[3] . ', ' . $em[4]);
            $e = strtotime($end);
            $d = floor(($e - $s) / 86400);

            if (abs($dur - $d) <= 1) {
                return [$s, $e];
            } else {
                $s = strtotime($sm[2] . '/' . $sm[1] . '/' . $sm[3] . ', ' . $sm[4]);
                $e = strtotime($em[2] . '/' . $em[1] . '/' . $em[3] . ', ' . $em[4]);

                return [$s, $e];
            }
        } else {
            return [strtotime($start), strtotime($end)];
        }
    }

    private function parseEmail(Email $email)
    {
        $h = $email->add()->hotel();

        // confirmation number
        $confirmation = $this->http->FindSingleNode("(//text()[{$this->contains($this->t('Confirmation Number:'))}]/following::text()[normalize-space(.)][1])[1]", null, true, "#^[A-Z\d-]{5,}$#");

        if ($confirmation) {
            $confirmationTitle = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Confirmation Number:'))}]");
            $h->general()->confirmation($confirmation, preg_replace('/\s*:\s*$/', '', $confirmationTitle));
        } else {
            $confNumbers = $this->http->FindNodes("//text()[{$this->eq($this->t('Confirmation Number:'))}]/ancestor::tr[1]/following-sibling::tr[1]//strong");

            if (empty($confNumbers)) {
                $confNumbers = array_filter(explode(',', $this->http->FindSingleNode("//text()[{$this->eq($this->t('Confirmation Number:'))}]/following-sibling::a")));
            }

            foreach ($confNumbers as $confNumber) {
                $h->addConfirmationNumber($confNumber);
            }
        }

        // hotelName
        $this->hotelName = $this->http->FindSingleNode("(//strong[{$this->contains($this->t('Confirmation Number:'))}]/ancestor::tr/following-sibling::tr[2]/td/descendant::strong)[1]");

        if (!empty($this->hotelName)) {
            // it-13549646.eml
            $this->logger->debug('Hotel type: 1');
            $this->parseHotel_1($h);
        } else {
            // it-41478325.eml
            $this->logger->debug('Hotel type: 2');
            $this->hotelName = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Confirmation Number:'))}]/ancestor::tr[1]/preceding-sibling::tr[normalize-space()!=''][last()]");
            $this->parseHotel_2($h);
        }
        $h->hotel()->name($this->hotelName);

        // p.total
        // p.currencyCode
        // p.tax
        $total = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Total for Stay'))}]/ancestor::td[1]/following-sibling::td[1]");

        if (preg_match("/(\d[\d\.\,]+ Pts[\. ]*)/", $total, $m)) {
            $h->price()
                ->spentAwards($m[1]);
            $total = str_replace($m[1], '', $total);
        }
        $sum = $this->getTotalCurrency($total);

        if (!empty($sum['Currency'])) {
            $h->price()
                ->total($sum['Total'])
                ->currency($sum['Currency']);
        }
        $tax = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Tax'))}]/ancestor::td[1]/following-sibling::td[1]");
        $sum = $this->getTotalCurrency($tax);

        if (!empty($sum['Currency'])) {
            $h->price()
                ->tax($sum['Total'])
                ->currency($sum['Currency']);
        }
        $cost = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Rate'))}]/ancestor::td[1]/following-sibling::td[1]");

        if (!empty($cost)) {
            $sum = $this->getTotalCurrency($cost);
            $h->price()->cost($sum['Total']);
        }
        // deadline
        if ($cancellation = $h->getCancellation()) {
            $this->detectDeadLine($h, $cancellation);
        }

        return true;
    }

    private function parseHotel_1(\AwardWallet\Schema\Parser\Common\Hotel $h)
    {
        if ($status = $this->http->FindSingleNode("//text()[contains(normalize-space(),'YOUR ROOM RESERVATION HAS BEEN CONFIRMED')]", null, false, "/YOUR ROOM RESERVATION HAS BEEN (CONFIRMED)/")) {
            $h->general()->status($status);
        }
        // checkInDate
        // checkOutDate
        $dates = $this->dates($this->getNode($this->t('Check-In:')), $this->getNode($this->t('Check-Out:')), $this->http->FindSingleNode("//strong[{$this->contains($this->t('Stay'))}]/following::text()[1]", null, true, "#(\d+) {$this->t('Night')}#i"));
        $h->booked()
            ->checkIn($dates[0])
            ->checkOut($dates[1])
        ;

        // address
        // phone
        $xpath = "//strong[{$this->contains($this->t('Confirmation Number:'))}]/ancestor::tr/following-sibling::tr[2]/td/descendant::strong/ancestor::td[1]//text()[normalize-space(.) != ''][not(ancestor::strong) and not({$this->contains($this->t('Hotel Website'))}) and not({$this->contains($this->t('Hotel Email'))}) and not(contains(.,'|'))]/.";
        $phoneAddress = trim(implode("\n", array_filter($this->http->FindNodes($xpath))));

        if (preg_match("#^(?<address>.*?)\s*{$this->opt($this->t('Phone'))}\s*:\s+(?<phone>[+)(\d][-‑.\s\d)(]{5,}[\d)(])#s", $phoneAddress, $m)) {
            $h->hotel()
                ->address(preg_replace('/\s+/', ' ', $m['address']))
                ->phone($m['phone'])
            ;
        }

        // travellers
        $h->general()->travellers($this->http->FindNodes("//text()[{$this->contains($this->t('NAME:'))}]/following::text()[normalize-space(.)!=''][1]"));

        // guestCount
        // kidsCount
        $guests = $this->http->FindSingleNode("//strong[{$this->contains($this->t('Occupancy:'))}]/following::text()[1]");

        if (preg_match("#\b(\d{1,3})\s*{$this->t('Adult')}[\S\s]+?,\s*(\d{1,3})\s*{$this->t('Child')}\S*#", $guests, $m)) {
            $h->booked()
                ->guests($m[1])
                ->kids($m[2])
            ;
        }

        // rooms
        $rooms = $this->http->FindSingleNode("//strong[{$this->contains($this->t('Stay'))}]/following::text()[1]");

        if (preg_match("#\b(\d{1,3}) .*#", $rooms, $m)) {
            $h->booked()->rooms($m[1]);
        }

        // earnedAwards
        $earnedAwards = $this->http->FindSingleNode("//tr[ not(.//tr) and ./descendant::strong[{$this->contains($this->t('You have earned'))}] ]");

        if (preg_match("#.*earned ([\d\S]+ [\w]+ [\w]+ [\w]+) .*#", $earnedAwards, $m)) {
            $h->program()->earnedAwards($m[1]);
        }

        // cancellation
        $cancellation = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Cancellation Policy:'))}]", null, true, "/{$this->opt($this->t('Cancellation Policy:'))}\s*(.+)/");
        $h->general()->cancellation($cancellation, false, true);
    }

    private function parseHotel_2(\AwardWallet\Schema\Parser\Common\Hotel $h): bool
    {
        // status
        // travellers
        $welcomeText = $this->http->FindSingleNode("//text()[{$this->contains($this->t('welcome->texts'))}]");

        foreach ((array) $this->t('welcome->patterns') as $pattern) {
            $pattern = str_replace('__WELCOME__', $this->opt($this->t('welcome->texts')), $pattern);
            $pattern = str_replace('__STATUS__', "(?<status>{$this->opt($this->t('welcome->statuses'))})", $pattern);
            $pattern = str_replace('__NAME__', "(?<name>[[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])", $pattern);

            if (preg_match("/^{$pattern}[!?.;]*$/i", $welcomeText, $m)) {
                $h->general()
                    ->status($m['status'])
                    ->traveller($m['name']);

                break;
            }
        }

        if ($acc = $this->http->FindSingleNode("//text()[contains(normalize-space(),'Member #')]", null, false, "/\#\s*([A-Z\d]{5,})$/")) {
            $h->program()->account($acc, false);
        }

        // checkInDate
        // checkOutDate
        $xpathDates = "//text()[{$this->contains($this->t('Confirmation Number:'))}]/following::tr[normalize-space()][1]/descendant::tr[count(*)=3][1]";
        $dates['checkIn'] = implode(' ', $this->http->FindNodes($xpathDates . "/*[1]/descendant::text()[normalize-space()]"));
        $dates['checkOut'] = implode(' ', $this->http->FindNodes($xpathDates . "/*[3]/descendant::text()[normalize-space()]"));
        $dates = array_map(function ($item) {
            // FEB 29 SAT 2020
            return strtotime($this->normalizeDate($item));
        }, $dates);

        if (empty($dates['checkIn']) || empty($dates['checkOut'])) {
            $this->logger->debug('Other format dates table!');

            return false;
        }
        $times['checkIn'] = str_replace('.', '', $this->getNode($this->t('Check-In')));

        if (empty($times['checkIn'])) {
            $times['checkIn'] = $this->http->FindSingleNode("//text()[(contains(normalize-space(.),'Check-In'))]/following::text()[normalize-space()][1]");
        }

        $times['checkOut'] = str_replace('.', '', $this->getNode($this->t('Checkout')));

        if (empty($times['checkOut'])) {
            $times['checkOut'] = $this->http->FindSingleNode("//text()[(contains(normalize-space(.),'Checkout'))]/following::text()[normalize-space()][1]");
        }

        if (!preg_match('/^\d{1,2}(?:\D|$)/', $times['checkIn']) || !preg_match('/^\d{1,2}(?:\D|$)/', $times['checkOut'])) {
            $this->logger->debug('Other format times table!');

            return false;
        }
        $h->booked()
            ->checkIn(strtotime($times['checkIn'], $dates['checkIn']))
            ->checkOut(strtotime($times['checkOut'], $dates['checkOut']))
        ;

        // address
        // phone
        $phoneAddress = trim(implode("\n",
            $this->http->FindNodes("//text()[{$this->contains($this->t('Confirmation Number:'))}]/ancestor::tr[1]/preceding-sibling::tr[normalize-space()][last()-1]/descendant::text()[normalize-space()]")));

        if (preg_match("#^\s*(?<address>.{3,}?)\s+(?:{$this->opt($this->t('Phone'))}\s*:+)?(?<phone>[+(\d][-‑. \d)(]{5,}?[\d)])(?:\s+|$)#", $phoneAddress, $m)
        ) {
            // 8909 West Airport Drive, Spokane, WA US (509) 838‑5211 (509) 838‑5211
            $h->hotel()
                ->address(preg_replace('/\s+/', ' ', $m['address']))
                ->phone(str_replace(['-', '‑'], '-', $m['phone']));
        } elseif (empty($h->getAddress())) {
            $addressText = $this->http->FindSingleNode("//text()[normalize-space()='{$this->hotelName}']/following::text()[normalize-space()][1]/ancestor::tr[2]");

            if (preg_match("/{$this->hotelName}(.+){$this->opt($this->t('Confirmation Number:'))}/", $addressText, $m)) {
                $h->hotel()
                    ->address($m[1]);
            }
        }

        // guestCount
        // kidsCount
        $guests = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Number of Guests'))}]/following::text()[normalize-space()!=''][1]");

        if (preg_match("#\b(\d{1,3})\s*{$this->opt($this->t('Adult'))}[\S\s]+?[,\/]\s*(\d{1,3})\s*{$this->opt($this->t('Child'))}\S*#", $guests, $m)) {
            $h->booked()
                ->guests($m[1])
                ->kids($m[2])
            ;
        }

        // rooms
        $rooms = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Reservation Details'))}]/following::text()[normalize-space()!=''][1]");

        if (preg_match("#\b(\d{1,3}) .*#", $rooms, $m)) {
            $h->booked()->rooms($m[1]);
        }

        $r = $h->addRoom();
        $r
            ->setType($this->http->FindSingleNode("//text()[{$this->contains($this->t('Reservation Details'))}]/following::text()[normalize-space()!=''][2]"))
        ;

        $description = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Room Description'))}]/following::text()[normalize-space()!=''][1]");

        if (empty($description)) {
            $description = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Reservation Details'))}]/following::tr[1]/td[1]/text()[2]");
        }

        if (!empty($description)) {
            $r->setDescription($description);
        }

        // earnedAwards
        $earnedAwards = $this->http->FindSingleNode("//text()[{$this->contains($this->t("You'll Earn"))}]");

        if (preg_match("/.*Earn ([\d\S]+ [\w]+ [\w]+ [\w]+) .*/i", $earnedAwards, $m)
            || preg_match("/^{$this->opt($this->t("You'll Earn"))} (\d.*Wyndham Rewards.*)/i", $earnedAwards, $m)
        ) {
            $h->program()->earnedAwards($m[1]);
        }

        // cancellation
        $cancellation = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Cancellation Policy'))}]/following::text()[normalize-space()!=''][1]");
        $h->general()->cancellation($cancellation, false, true);

        return true;
    }

    /**
     * Here we describe various variations in the definition of dates deadLine.
     */
    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h, string $cancellation)
    {
        $patterns = [
            'time' => '\d{1,2}(?::\d{2})?(?:\s*[AaPp]\.?[Mm]\.?|\s*noon|\s*午[前後]|\s*[Uhr]*)?', // 4:19PM    |    2:00 p.m.    |    3pm    |    12 noon    |    3:10 午後 | 18:00 Uhr
        ];

        if (
        preg_match("/Cancel before\s*(?<hour>{$patterns['time']})\s*day of arrival to avoid a \d+ night charge plus applicable tax/i", $cancellation, $m) // en
        || preg_match("/Stornieren Sie Ihre Reservierung bis\s*(?<hour>{$patterns['time']})\s*am Tag der Ankunft, um Gebühren in Höhe von \d+ Übernachtung plus Steuern zu vermeiden./i", $cancellation, $m) // en
        || preg_match("/Cancel before (?<hour>{$patterns['time']}) day of arrival to avoid a 1 night plus tax charge./i", $cancellation, $m) // en
        ) {
            $hour = preg_replace("/\s*[Uhr]*/", '', $m['hour']);
            $h->booked()->deadlineRelative('0 day', $hour);

            return;
        } elseif (
            preg_match("/^Cancel (?<prior>\d+ Hours) Prior to (?<hour>{$patterns['time']})$/i", $cancellation, $m) // en
            || preg_match("/^Cancel (?<prior>\d+ Hours) Prior to (?<hour>{$patterns['time']}) day of arrival to avoid 1 Night charge plus/i",
                $cancellation, $m) // en
            || preg_match("/^Cancele (?<prior>\d+ horas) antes de la fecha de llegada antes de las (?<hour>{$patterns['time']}) para evitar el cargo de 1 noche más/i", $cancellation, $m) // es
            || preg_match("/^Cancel (?<prior>\d+ Hours) prior to arrival by (?<hour>{$patterns['time']}) to avoid 1 night plus tax charge/i", $cancellation, $m) // es
            || preg_match("/Annulez avant (?<hour>{$patterns['time']}), (?<prior>\d+ heures) avant l’arrivée/ui", $cancellation, $m) // es
            || preg_match("/Cancel (?<prior>\d+ Hours) prior to arrival by (?<hour>{$patterns['time']}) to avoid 1 Night charge plus tax/ui", $cancellation, $m) // es
        ) {
            $m['prior'] = str_ireplace([
                'es' => 'horas',
            ], [
                'es' => 'hours',
            ], $m['prior']);

            $m['prior'] = str_ireplace([
                'fr' => 'heures',
            ], [
                'fr' => 'hours',
            ], $m['prior']);

            if (preg_match("/^(\d+)\s*h$/", $m['hour'], $match)) {
                $m['hour'] = $match[1] . ':00';
            }

            $h->booked()->deadlineRelative($m['prior'], $m['hour']);

            return;
        }

        $h->booked()->parseNonRefundable('/Non Cancelable Non Refundable Full Amount plus applicable tax/i');
    }

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'normalize-space(' . $node . ")=\"" . $s . "\""; }, $field)) . ')';
    }

    private function starts($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'starts-with(normalize-space(' . $node . "),\"" . $s . "\")"; }, $field)) . ')';
    }

    private function contains($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'contains(normalize-space(' . $node . "),\"" . $s . "\")"; }, $field)) . ')';
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) { return preg_quote($s, '/'); }, $field)) . ')';
    }

    private function getNode($str)
    {
        return $this->http->FindSingleNode("//*[self::strong or self::b][{$this->contains($str)}]/following::text()[normalize-space(.)!=''][1]");
    }

    private function getTotalCurrency($node)
    {
        $node = str_replace("€", "EUR", $node);
        $node = str_replace("$", "USD", $node);
        $node = str_replace("£", "GBP", $node);
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)
            || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m)
        ) {
            $cur = $m['c'];
            $tot = PriceHelper::cost($m['t']);
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }

    /**
     * Dependencies `use AwardWallet\Engine\MonthTranslate` and `$this->lang`.
     */
    private function normalizeDate(?string $text): ?string
    {
        if (preg_match('/^([[:alpha:]]{3,})\s+(\d{1,2})\s+[-[:alpha:]]{2,}\s+(\d{2,4})$/u', $text, $m)) {
            // ABR 27 LUN 2020
            $month = $m[1];
            $day = $m[2];
            $year = $m[3];
        }

        if (isset($day, $month, $year)) {
            if (preg_match('/^\s*(\d{1,2})\s*$/', $month, $m)) {
                return $m[1] . '/' . $day . ($year ? '/' . $year : '');
            }

            if (($monthNew = MonthTranslate::translate($month, $this->lang)) !== false) {
                $month = $monthNew;
            }

            return $day . ' ' . $month . ($year ? ' ' . $year : '');
        }

        return null;
    }

    private function t($phrase)
    {
        if (!isset(self::$dict[$this->lang]) || !isset(self::$dict[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dict[$this->lang][$phrase];
    }

    private function assignLang(): bool
    {
        foreach ($this->langDetectors as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if ($this->http->XPath->query('//node()[contains(normalize-space(.),"' . $phrase . '")]')->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }
}
