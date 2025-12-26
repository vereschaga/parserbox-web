<?php

namespace AwardWallet\Engine\relais\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Common\Hotel;
use AwardWallet\Schema\Parser\Email\Email;

class ReservationConfirmation extends \TAccountChecker
{
    public $mailFiles = "relais/it-162645315.eml, relais/it-36557030.eml, relais/it-36581356.eml, relais/it-36975840.eml, relais/it-51491927.eml, relais/it-97236687.eml";

    public $reFrom = [
        "@relaischateaux.com",
        "@hotel.com.pl",
        "@villacrespi.it",
        "@hoteldulac-vevey.ch",
        "@hotelneri.com",
        "@ca-beachhotel.com",
        "@fermesaintsimeon.fr",
        "@11cadogangardens.com",
    ];
    public $reSubject = [
        'en' => 'Reservation Confirmation',
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
            'introPrefixes'                => ['We are pleased to confirm your itinerary', 'We are pleased to confirm your reservation'],
            'Your confirmation number is:' => ['Your confirmation number is:', 'Your reservation number is:', 'Confirmation number:'], // not to be confused with 'Your itinerary number is:'
            'Arrival Date:'                => 'Arrival Date:',
            'Check-in'                     => ['Check-in', 'Arrival after:'],
            'Check-out'                    => ['Check-out', 'Departure:'],
            'Number of rooms:'             => 'Number of rooms:',
            'Number of persons'            => ['Number of persons', 'Number of guests'],
            'Taxes/Fees/Service:'          => ['Taxes/Fees/Service:', 'Taxes/Service:'],
            'Cancellation policy:'         => ['Cancellation policy:', 'Guarantee and cancellation policies:'],
            'Room Description:'            => ['Room Description:', 'Room details:'],
            'Room Type:'                   => ['Room Type:', 'Room & Rate:'],
        ],
        'fr' => [
            'introPrefixes'                => ['Nous avons le plaisir de vous confirmer votre réservation à l\'établissement'],
            'Your confirmation number is:' => ['Votre numéro de confirmation est :'], // not to be confused with 'Your itinerary number is:'
            'Arrival Date:'                => 'Date d\'arrivée :',
            'Departure Date:'              => 'Date de départ :',
            'Check-in'                     => ['Arrivée à partir de :'],
            'Check-out'                    => ['Départ :'],
            'Number of rooms:'             => 'Nombre de chambres :',
            'Number of persons'            => ['Nombre de personnes* :'],
            'Taxes/Fees/Service:'          => ['Taxes/Service :'],
            'Cancellation policy:'         => ['Politiques d\'annulation :'],
            'Special information:'         => 'Information Spéciales :',
            'Room Type:'                   => ['Chambre & Tarif :'],
            'Telephone:'                   => ['Téléphone :'],
            'Daily Rate:'                  => 'Tarif journalier :',
            'Total Stay'                   => ['Total du séjour :'],
            'for'                          => 'pour',
        ],
    ];
    private $keywordProv = ['Relais Chateau', 'Relais & Châteaux'];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $this->parseEmail($email);

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//img[contains(@src,'.relaischateaux.com/')] | //a[contains(@href,'.relaischateaux.com/')] | //node()[contains(.,'@relaischateaux.com')]")->length > 0) {
            return $this->assignLang();
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->reFrom as $reFrom) {
            if (stripos($from, $reFrom) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true
            && !preg_match("#{$this->opt($this->keywordProv)}#i", $headers['subject'])
        ) {
            return false;
        }

        if (isset($headers["subject"])) {
            foreach ($this->reSubject as $reSubject) {
                if (stripos($headers['subject'], $reSubject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function parseEmail(Email $email)
    {
        $introHtml = $this->http->FindHTMLByXpath("//text()[{$this->contains($this->t('introPrefixes'))}]/ancestor::*[ descendant::*[self::b or self::strong][normalize-space()] ][1]");
        $introText = $this->htmlToText($introHtml);
        $guestName = $this->re("#{$this->opt($this->t('introPrefixes'))}.*?[ ]+{$this->opt($this->t('for'))}[ ]+([[:alpha:]][-&.\'[:alpha:] ]*[[:alpha:]])[!,. ]*$#mu", $introText);

        $confNo = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Your itinerary number is:'))}]/following::text()[normalize-space()!=''][1]");

        if (!empty($confNo)) {
            $email->ota()
                ->confirmation($confNo, $this->t('itinerary number'));
        }

        $xpath = "//text()[{$this->starts($this->t('Telephone:'))}]/ancestor::*[{$this->contains($this->t('Your confirmation number is:'))} and {$this->contains($this->t('Cancellation policy:'))} and (preceding-sibling::*[normalize-space()] or following-sibling::*[normalize-space()])][1]";
        $hotels = $this->http->XPath->query($xpath);
        $this->logger->debug("[XPATH]: " . $xpath);

        foreach ($hotels as $root) {
            $mainText = '';
            $textNodes = $this->http->XPath->query('descendant::text()[normalize-space()]', $root);

            foreach ($textNodes as $tNode) {
                $mainText .= $this->http->FindSingleNode('.', $tNode) . "\n";

                if ($this->http->XPath->query('following-sibling::node()[1][self::br]/following-sibling::node()[1][self::br]', $tNode)->length > 0) {
                    $mainText .= "\n";
                }
            }

            $r = $email->add()->hotel();

            // travellers
            if ($guestName) {
                $r->general()->traveller($guestName);
            }

            // cancellation
            $r->general()
                ->cancellation(str_replace("\n", " ", $this->re("#{$this->opt($this->t('Cancellation policy:'))}\s*(.+?)(?:\n{$this->opt($this->t('Check-in'))}|\s*{$this->opt($this->t('Room Type:'))}|\s*{$this->opt($this->t('Special information:'))}|\n{2}|$)#s",
                    $mainText)));

            // sums
            $totalStay = $this->http->FindSingleNode("descendant::text()[{$this->starts($this->t('Total Stay'))}]/ancestor::*[1]/following::text()[normalize-space()][1]",
                $root);

            if ($totalStay === null) {
                $totalStay = $this->http->FindSingleNode("following::text()[normalize-space()][position()<15][{$this->starts($this->t('Total Stay'))}]/ancestor::*[1]/following::text()[normalize-space()][1]", $root);
            }

            if ($totalStay !== null) {
                $sum = $this->getTotalCurrency($totalStay);
                $r->price()
                    ->total($sum['Total'])
                    ->currency($sum['Currency']);
            }

            $taxes = $this->http->FindSingleNode("descendant::text()[{$this->starts($this->t('Taxes/Fees/Service:'))}]/ancestor::*[1]/following::text()[normalize-space()][1]",
                $root);

            if ($taxes !== null) {
                $sum = $this->getTotalCurrency($taxes);

                if ($sum['Total'] !== '') {
                    $r->price()
                        ->tax($sum['Total']);
                }
            }

            $extras = $this->re("#{$this->opt($this->t('Extras:'))}\s+(.+?)\s+{$this->opt($this->t('Total Stay'))}#",
                $mainText);

            if ($extras !== null) {
                $sum = $this->getTotalCurrency($extras);

                if ($sum['Total'] !== '') {
                    $r->price()
                        ->fee(trim($this->t('Extras:'), ":"), $sum['Total']);
                }
            }

            // confirmation + hotel info
            if (preg_match("#{$this->opt($this->t('Your confirmation number is:'))}\s+([\w\-]{5,})(?:\s+{$this->t('for')}\s+(.+)|\n)#",
                $mainText, $m)) {
                $r->general()
                    ->confirmation($m[1]);

                if (isset($m[2]) && !empty($m[2])) {
                    $hotelName = $m[2];
                } else {
                    $hotelName = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Your confirmation number is:'))}]/following::text()[2]/ancestor::node()[1][self::strong]",
                        $root);

                    if (empty($hotelName)) {
                        $hotelName = $this->re("#{$this->t('We are pleased to confirm your reservation at')}\s+(.+)\s+{$this->t('for')}\s+#",
                            $mainText);
                    }

                    if (empty($hotelName)) {
                        $hotelName = $this->http->FindSingleNode("./descendant::text()[{$this->contains($this->t('Your confirmation number is:'))}]/following::text()[normalize-space()][2]", $root);
                    }

                    if (empty($hotelName)) {
                        $hotelName = $this->http->FindSingleNode("./descendant::text()[normalize-space()='Your reservation number is:']/following::text()[normalize-space()][2]", $root);
                    }
                }

                if (preg_match("#{$this->opt($hotelName)}.*\s+{$this->opt($hotelName)}\s+(.+?)\s+{$this->opt($this->t('Telephone:'))}\s+([\d\-\(\)\+ ]{5,})#s",
                        $mainText, $m)
                    || preg_match("#\n{$this->opt($hotelName)}\s+(.+?)\s+{$this->opt($this->t('Telephone:'))}\s+([\d\-\(\)\+ ]{5,})#s",
                        $mainText, $m)
                ) {
                    $address = trim(preg_replace("#\s+#", ' ', $m[1]));
                    $phone = trim($m[2]);
                    $r->hotel()
                        ->name($hotelName)
                        ->address($address)
                        ->phone($phone);
                }
            }

            // checkIn, checkOut, rooms, quests
            $checkIn = $this->normalizeDate($this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Arrival Date:'))}]/following::text()[normalize-space()!=''][1]",
                $root));
            $checkOut = $this->normalizeDate($this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Departure Date:'))}]/following::text()[normalize-space()!=''][1]",
                $root));
            $r->booked()
                ->checkIn($checkIn)
                ->checkOut($checkOut)
                ->rooms($this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Number of rooms:'))}]/ancestor::*[1]/following::text()[normalize-space()!=''][1]",
                    $root))
                ->guests($this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Number of persons'))}]/ancestor::*[1]/following::text()[normalize-space()!=''][not(contains(.,'*') or contains(.,':'))][1]",
                    $root));
            // time
            $checkInTime = $this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Check-in'))}]/following::text()[normalize-space()!=''][1]",
                $root, false, "#(\d+:\d+(?:\s*[ap]m)?)#i");

            if (empty($checkInTime)) {
                $checkInTime = $this->http->FindSingleNode("(./following::text()[normalize-space()!=''][position()<15][{$this->starts($this->t('Check-in'))}])[1]/following::text()[normalize-space()!=''][1]",
                    $root, false, "#(\d+:\d+(?:\s*[ap]m)?)#i");
            }

            if (!empty($checkInTime)) {
                $r->booked()
                    ->checkIn(strtotime($checkInTime, $r->getCheckInDate()));
            }

            $checkOutTime = $this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Check-out'))}]/following::text()[normalize-space()!=''][1]",
                $root, false, "#(\d+:\d+(?:\s*[ap]m)?)#i");

            if (empty($checkOutTime)) {
                $checkOutTime = $this->http->FindSingleNode("(./following::text()[normalize-space()!=''][position()<15][{$this->starts($this->t('Check-out'))}])[1]/following::text()[normalize-space()!=''][1]",
                    $root, false, "#(\d+:\d+(?:\s*[ap]m)?)#i");
            }

            if (!empty($checkOutTime)) {
                $r->booked()
                    ->checkOut(strtotime($checkOutTime, $r->getCheckOutDate()));
            }

            $roomType = $roomRateType = null;
            $roomAndRate = $this->http->FindSingleNode("descendant::text()[{$this->starts($this->t('Room & Rate'))}]/following::text()[normalize-space()][1]", $root);

            if (!empty($roomAndRate)
                && count($rr = preg_split('/\s+-\s+/', $roomAndRate)) === 2
            ) {
                // Superior Garden View - Virtuoso
                $roomType = $rr[0];
                $roomRateType = $rr[1];
            }

            // room
            $room = $r->addRoom();
            $rate = implode("; ", array_map("trim", explode("\n", preg_replace("#[ ]{2,}#", "\n",
                $this->re("#{$this->opt($this->t('Daily Rate:'))}\s+(.+?)\s+{$this->opt($this->t('Taxes/Fees/Service:'))}#s",
                    $mainText)))));
            $roomDescription = implode("; ", array_map("trim", explode("\n", preg_replace("#[ ]{2,}#", "\n",
                $this->re("#{$this->opt($this->t('Room Description:'))}\s+(.+?)(?:\s+{$this->opt($this->t('Room Requests:'))}|\n{2}|$)#s",
                    $mainText)))));

            if (!empty($roomDescription)) {
                $room->setDescription($roomDescription);
            }

            $rateType = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Rate Name:'))}]/following::text()[normalize-space()!=''][1]",
                $root);

            if (!empty($rateType) || !empty($roomRateType)) {
                $room->setRateType($rateType ?? $roomRateType);
            }

            $room
                ->setType($this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Room Type:'))}]/following::text()[normalize-space()!=''][1]",
                    $root) ?? $roomType)
                ->setRate($rate);

            $this->detectDeadLine($r);
        }

        return true;
    }

    /**
     * Here we describe various variations in the definition of dates deadLine.
     */
    private function detectDeadLine(Hotel $h)
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        if (preg_match("#Free cancellation/modification until (?<hour>[a-z]+|\d{1,2}(?::\d{2})?(?:\s*[AaPp]\.?[Mm]\.?)?), (?<prior>\d+) days? prior(?: to)? arrival\.#i", $cancellationText, $m)
        || preg_match("#Annulation gratuite jusqu’à (?<hour>\d{1,2})[a-z], (?<prior>\d+) jours? avant l’arrivée#i", $cancellationText, $m)) {
            if (strcasecmp($m['hour'], 'noon') === 0) {
                $m['hour'] = '12:00';
            } elseif (strcasecmp($m['hour'], 'midnight') === 0) {
                $m['hour'] = '00:00';
            } elseif (preg_match("/^\d+$/", $m['hour'])) {
                $m['hour'] = $m['hour'] . ':00';
            }
            $h->booked()->deadlineRelative($m['prior'] . ' days', $m['hour']);

            return;
        }

        $h->booked()
            ->parseNonRefundable("#This reservation is non-refundable\.#")
            ->parseNonRefundable("#no changes or cancellations are allowed#");
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang()
    {
        foreach (self::$dict as $lang => $words) {
            if (isset($words['Arrival Date:'], $words['Number of rooms:'])) {
                if ($this->http->XPath->query("//*[{$this->contains($words['Arrival Date:'])}]")->length > 0
                    && $this->http->XPath->query("//*[{$this->contains($words['Number of rooms:'])}]")->length > 0
                ) {
                    $this->lang = substr($lang, 0, 2);

                    return true;
                }
            }
        }

        return false;
    }

    private function getTotalCurrency($node)
    {
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

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function eq($field, $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ')="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field, $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'starts-with(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function contains($field, $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function htmlToText($s, $brConvert = true): string
    {
        if (!is_string($s) || $s === '') {
            return '';
        }
        $s = preg_replace('/<!--.*?-->/s', '', $s); // comments

        if ($brConvert) {
            $s = str_replace("\n", '', $s);
            $s = preg_replace('/<[Bb][Rr]\b.*?\/?>/', "\n", $s); // only <br> tags
        }
        $s = preg_replace('/<[A-z][A-z:]*\b.*?\/?>/', '', $s); // opening tags
        $s = preg_replace('/<\/[A-z][A-z:]*\b[ ]*>/', '', $s); // closing tags
        $s = html_entity_decode($s);
        $s = str_replace(chr(194) . chr(160), ' ', $s); // NBSP to SPACE

        return trim($s);
    }

    private function normalizeDate($str)
    {
        $in = [
            "#\w+\s*(\d+)\s+(\w+)\s+(\d{4})#", // mardi 27 juillet 2021
        ];
        $out = [
            "$1 $2 $3",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }
}
