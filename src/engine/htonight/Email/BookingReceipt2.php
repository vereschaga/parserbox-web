<?php

namespace AwardWallet\Engine\htonight\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class BookingReceipt2 extends \TAccountChecker
{
    public $mailFiles = "htonight/it-24535670.eml, htonight/it-24781180.eml, htonight/it-25844876.eml, htonight/it-39381817.eml"; // +1bcd (fr)

    public $reFrom = ["HotelTonight", "hoteltonight."];
    public $reBody = [
        'en' => ['Receipt & Details', 'Need to Know'],
        'de' => ['Beleg und Details', 'Was du wissen musst'],
        'es' => ['Recibo y detalles', 'Debes saber'],
        'fr' => ['Reçu et détails', 'À savoir'],
    ];
    public $reSubject = [
        'HotelTonight Booking Receipt',
        'HotelTonight-Buchungsbeleg',
        'Confirmation de réservation :',
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
            'Guest:'            => ['Guest:', 'Guest name (for check-in):'],
            'confNo'            => ['HotelTonight booking ID:', 'Itinerary ID:'],
            'confNoPrimary'     => 'HotelTonight booking ID:',
            'travelAgencyPhone' => [
                'For urgent questions or concerns, give us a ring at',
                'For any questions or concerns, give us a ring at',
            ],
            'cancellationText' => [
                'This is a non-refundable',
                'This booking offers free cancellation until',
                'You may cancel this reservation before',
            ],
        ],
        'de' => [
            'Guest:'            => ['Gast:'],
            'Room type:'        => 'Zimmer:',
            'confNo'            => ['HotelTonight-Buchungs-ID:'],
            'confNoPrimary'     => 'HotelTonight-Buchungs-ID:',
            'Booked:'           => 'Gebucht am:',
            'Get Directions'    => 'Anfahrtsbeschreibung',
            'Total'             => 'Endsumme',
            'travelAgencyPhone' => [
                'Ruf uns bei dringenden Fragen und Anliegen einfach an',
            ],
            'cancellationText' => [
                'und kann nicht erstattet werden',
            ],
        ],
        'es' => [
            'Guest:'            => ['Huésped:'],
            'Room type:'        => 'Tipo de habitación:',
            'confNo'            => ['Código de reserva de HotelTonight: '],
            'confNoPrimary'     => 'Código de reserva de HotelTonight: ',
            'Booked:'           => 'Reservado:',
            'Get Directions'    => 'Cómo llegar',
            'Total'             => 'Total',
            'travelAgencyPhone' => [
                'Para dudas urgentes, llámanos al',
            ],
            'cancellationText' => [
                'Esta es una reserva no reembolsable y prepagada',
            ],
        ],
        'fr' => [
            'Guest:'         => ['Client:'],
            'confNo'         => ['Nº de réservation HotelTonight:', 'Numéro de confirmation:'],
            'Booked:'        => 'Réservé le:',
            'Check-in:'      => 'Check-in le:',
            'Check-out:'     => 'Départ le:',
            'Get Directions' => 'Itinéraire',
            'Total'          => 'Total',
            'Room type:'     => 'Type de chambre:',
            'confNoPrimary'  => 'Nº de réservation HotelTonight:',
            //            'travelAgencyPhone' => [
            //                'For urgent questions or concerns, give us a ring at',
            //            ],
            'cancellationText' => [
                "Cette réservation vous propose l'annulation gratuite jusqu'au",
            ],
        ],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->debug('can\'t determine a language');

            return $email;
        }

        $a = explode('\\', __CLASS__);
        $class = end($a);
        $email->setType($class . ucfirst($this->lang));

        $this->parseEmail($email);

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//img[@alt='Hotel Tonight' or contains(@src,'hoteltonight.com')] | //a[contains(@href,'hoteltonight.com')]")->length > 0) {
            return $this->assignLang();
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && isset($headers["subject"])) {
            $flag = false;

            foreach ($this->reFrom as $reFrom) {
                if (stripos($headers['from'], $reFrom) !== false) {
                    $flag = true;
                }
            }

            if ($flag) {
                foreach ($this->reSubject as $reSubject) {
                    if (stripos($headers["subject"], $reSubject) !== false) {
                        return true;
                    }
                }
            }
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
        $email->obtainTravelAgency();
        $descrs = (array) $this->t('confNo');

        foreach ($descrs as $descr) {
            $confNo = $this->http->FindSingleNode("//text()[{$this->starts($descr)}]", null, false,
                "#{$this->opt($descr)}\s*(.+)#");

            if (!empty($confNo)) {
                if ($descr == $this->t('confNoPrimary')) {
                    $email->ota()->confirmation($confNo, trim($descr, " :"), true);
                } else {
                    $email->ota()->confirmation($confNo, trim($descr, " :"));
                }
            }
        }
        $phone = $this->http->FindSingleNode("//text()[{$this->contains($this->t('travelAgencyPhone'))}]/following::text()[normalize-space()!=''][1]",
            null, false, "#^\s*([\d\-\+ ]+)\s*$#");

        if (!empty($phone)) {
            $email->ota()->phone($phone);
        }

        $tot = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Total'))}]/ancestor::td[1]/following-sibling::td[1]");
        $tot = $this->getTotalCurrency($tot);
        $email->price()
            ->total($tot['Total'])
            ->currency($tot['Currency']);

        $r = $email->add()->hotel();
        $r->hotel()
            ->name($this->http->FindSingleNode("//text()[{$this->eq($this->t('Get Directions'))}]/ancestor::tr[position()<=2]/preceding-sibling::tr[normalize-space()!=''][last()]/descendant::text()[normalize-space()!=''][1]"))
            ->address(implode(",",
                $this->http->FindNodes("//text()[{$this->eq($this->t('Get Directions'))}]/ancestor::tr[position()<=2]/preceding-sibling::tr[normalize-space()!=''][last()]/descendant::text()[normalize-space()!=''][position()>1]")))
            ->phone($this->http->FindSingleNode("//text()[{$this->eq($this->t('Get Directions'))}]/ancestor::tr[position()<=2]/preceding-sibling::tr[normalize-space()!=''][1]", null, true, "#^\s*([\d\(\)\-\+ \.]{5,})\s*$#"), true, true);

        $confNo = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Confirmation number:'))}]", null,
            false, "#{$this->opt($this->t('Confirmation number:'))}\s*(.+?)(\||$)#");

        if (!empty($confNo)) {
            $r->general()
                ->confirmation($confNo);
        } else {
            $r->general()
                ->noConfirmation();
        }
        $r->general()
            ->traveller($this->http->FindSingleNode("//text()[{$this->starts($this->t('Guest:'))}]", null, false,
                "#{$this->opt($this->t('Guest:'))}\s*(.+)#"))
            ->date($this->normalizeDate($this->http->FindSingleNode("//text()[{$this->starts($this->t('Booked:'))}]",
                null, false, "#{$this->opt($this->t('Booked:'))}\s*(.+)#")))
            ->cancellation(trim($this->http->FindSingleNode("//text()[{$this->contains($this->t('cancellationText'))}]"),
                " ."));

        $r->booked()
            ->checkIn($this->normalizeDate($this->http->FindSingleNode("//text()[{$this->starts($this->t('Check-in:'))}]",
                null, false, "#{$this->opt($this->t('Check-in:'))}\s*(.+)#")))
            ->checkOut($this->normalizeDate($this->http->FindSingleNode("//text()[{$this->starts($this->t('Check-out:'))}]",
                null, false, "#{$this->opt($this->t('Check-out:'))}\s*(.+)#")));

        $room = $r->addRoom();
        $room->setType($this->http->FindSingleNode("//text()[{$this->starts($this->t('Room type:'))}]", null, false,
            "#{$this->opt($this->t('Room type:'))}\s*(.+)#"));

        if (!empty($node = $r->getCancellation())) {
            $this->detectDeadLine($r, $node);
        }

        return true;
    }

    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h, string $cancellationText)
    {
        //Here we describe various variations in the definition of dates deadLine
        if (preg_match("#This booking offers free cancellation until\s*(.+)#i", $cancellationText, $m)
        || preg_match("#Cette réservation vous propose l'annulation gratuite jusqu'au\s*(.+)#i", $cancellationText, $m)
        || preg_match("#You may cancel this reservation before\s*(.+)#i", $cancellationText, $m)
        ) {
            $h->booked()
                ->deadline($this->normalizeDate($m[1]));
        }
        $h->booked()
            ->parseNonRefundable("#und kann nicht erstattet werden#")
            ->parseNonRefundable("#This is a non-refundable#");
    }

    private function normalizeDate($date)
    {
        //$this->logger->warning($date);
        $in = [
            //21:41 - 16 Sep, 2018 PDT
            '#^\s*(\d+:\d+(?: *[ap]m)?)\s*\-\s*(\d+)\s*(\w+)\.?,\s*(\d{4})(?:\s*[A-Z]{3,4})?\s*$#iu',
            //4:25 PM - Sep 7, 2018 PDT
            '#^\s*(\d+:\d+(?: *[ap]m)?)\s*\-\s*(\w+)\s*(\d+),\s*(\d{4})(?:\s*[A-Z]{3,4})?\s*$#iu',
            //Sep 6, 2018 at 11:59 PM PDT
            '#^\s*(\w+)\s*(\d+),\s*(\d{4})\s*[\-at]+\s*(\d+:\d+(?: *[ap]m)?)\s*(?:\s*\(?[A-Z]{3}\)?)?\s*$#iu',
            //12:33 - 13 fév, 2020 GMT
            '#^\s*(\d+:\d+(?: *[ap]m)?)\s*\-\s*(\d+\s*\w+,\s*\d{4})(?:\s*[A-Z]{3,4})?\s*$#iu',
            //6:24 PM - May 5, 2024 +04
            '#^\s*(\d+:\d+(?: *[ap]m)?)\s*\-\s*(\w+)\s*(\d+)\.?,\s*(\d{4})\s*[+]\d+$#iu',
        ];
        $out = [
            '$2 $3 $4, $1',
            '$3 $2 $4, $1',
            '$2 $1 $3, $4',
            '$2, $1',
            '$3 $2 $4, $1',
        ];
        $str = strtotime($this->dateStringToEnglish(preg_replace($in, $out, $date)));

        return $str;
    }

    private function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
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
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if ($this->http->XPath->query("//*[contains(normalize-space(.),'{$reBody[0]}')]")->length > 0
                    && $this->http->XPath->query("//*[contains(normalize-space(.),'{$reBody[1]}')]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
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

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }
}
