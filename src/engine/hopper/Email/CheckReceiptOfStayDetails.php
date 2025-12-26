<?php

namespace AwardWallet\Engine\hopper\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

// TODO: merge with parser capitalcards/IsBooked (in favor of hopper/CheckReceiptOfStayDetails)

class CheckReceiptOfStayDetails extends \TAccountChecker
{
    public $mailFiles = "hopper/it-49344910.eml, hopper/it-79737954.eml, hopper/it-86608222.eml, hopper/it-538753315.eml";

    private static $detectors = [
        // 'es' => [""],
        'en' => [
            "Check out your receipt with itinerary & stay details below.",
            "Your reservation has been canceled",
            "West has been confirmed",
            "Your stay at",
            "Your reservation has been cancelled!",
        ],
    ];

    private static $dictionary = [
        'es' => [
            "Confirmed"                           => ["confirmó"],
            "Reservation Details"                 => "Datos de la reserva",
            "Hopper confirmation:"                => "Confirmación de Hopper:",
            "Reservation code:"                   => "Código de reserva:",
            "Check In:"                           => "Check-in:",
            "Check Out:"                          => "Check-out:",
            "at"                                  => "de",
            "Room Type"                           => "Tipo de habitación",
            // "Bed Selection" => "",
            "room"                                => "habitación",
            "Your Stay:"                          => "Tu estancia:",
            "Guest Information:"                  => "Datos de los huéspedes:",
            "Room (per night):"                   => ["Habitación (1 noche):"],
            "totalPrice"                          => ["Total del viaje"],
            // "Paid Now" => [""],
            // "Cancellations or changes made after" => "",
            "feeNames"                            => [
                "Impuestos y tasas",
                "Impuestos y tasas:",
            ],
            // "Carrot Cash:" => "",
            "Cancellation:"  => "Cancelación:",
            "Non-refundable" => "No reembolsable",
        ],
        'en' => [
            "Confirmed"                           => ["Confirmed", "Canceled", "confirmed", "Cancelled"],
            "Reservation Details"                 => "Reservation Details",
            // "Hopper confirmation:" => "",
            // "Reservation code:" => "",
            "Check In:"                           => "Check In:",
            "Check Out:"                          => "Check Out:",
            // "at" => "",
            // "Room Type" => "",
            // "Bed Selection" => "",
            "room"                                => "room",
            "Your Stay:"                          => "Your Stay:",
            // "Guest Information:" => "",
            "Room (per night):"                   => ["Room (per night):", "Room (1 night):"],
            "totalPrice"                          => ["Total", "Trip Total"],
            "Paid Now"                            => ["Paid Now", "Pay at Hotel"],
            "Cancellations or changes made after" => "Cancellations or changes made after",
            "feeNames"                            => [
                "Tax Recovery Charges and Service Fees",
                "Tax Recovery Charges and Service Fees:",
                "Taxes and Fees",
                "Taxes and Fees:",
                "Sales Tax",
                "Sales Tax:",
                "Insurance:",
                "Resort Fees:",
                "Hotel Fees:",
            ],
            // "Carrot Cash:" => "",
            // "Cancellation:" => "",
            // "Non-refundable" => "",
        ],
    ];

    private $from = "@hopper.com";

    private $body = "hopper";

    private $subject = ["Your Hopper booking confirmation"];

    private $lang;

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->subject as $re) {
            if (stripos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->from) !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $text = $this->http->Response['body'];

        if (stripos($text, $this->body) === false) {
            return false;
        }

        if ($this->detectBody()) {
            return $this->assignLang();
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->debug("Can't determine a language");

            return $email;
        }

        $otaConfirmation = $this->http->FindSingleNode("//text()[normalize-space()='Hopper']/following::text()[normalize-space()][1]", null, true, '/^[-A-Z\d]{5,}$/');
        $otaConfirmationTitle = $this->http->FindSingleNode("//text()[normalize-space()='Hopper']", null, true, '/^(.+?)[\s:：]*$/u');

        if (!$otaConfirmation) {
            $otaConfirmation = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Hopper confirmation:'))}]/following::text()[normalize-space()][1]", null, true, '/^[-A-Z\d]{5,}$/');
            $otaConfirmationTitle = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Hopper confirmation:'))}]", null, true, '/^(.+?)[\s:：]*$/u');
        }

        $email->ota()->confirmation($otaConfirmation, $otaConfirmationTitle);

        $email->setType('CheckReceiptOfStayDetails');
        $this->parseEmail($email);

        return $email;
    }

    private function parseEmail(Email $email): void
    {
        if (!$this->detectBody()) {
            return;
        }

        $xpathBold = '(self::b or self::strong or ancestor-or-self::*[contains(@style,"bold")])';

        $r = $email->add()->hotel();
        $status = $this->http->FindSingleNode("//text()[normalize-space(.)='Hopper']/preceding::span[" . $this->contains($this->t('Confirmed')) . "]");

        if (!empty($status)) {
            $r->general()
                ->status($status);

            if ($status == 'Canceled' || $status == 'Cancelled') {
                $r->general()
                    ->cancelled();
            }
        }

        $confirmation = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Reservation code:'))}]/following::text()[normalize-space()][1]", null, true, '/^[-A-Z\d]{5,}$/');

        if ($confirmation) {
            $confirmationTitle = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Reservation code:'))}]", null, true, '/^(.+?)[\s:：]*$/u');
            $r->general()->confirmation($confirmation, $confirmationTitle);
        } elseif (preg_match("/^({$this->opt($this->t('Reservation code:'))})\s*([-A-Z\d]{5,})$/", $this->http->FindSingleNode("//text()[{$this->starts($this->t('Reservation code:'))}]"), $m)) {
            $r->general()->confirmation($m[2], rtrim($m[1], ': '));
        } elseif ($this->http->XPath->query("//text()[contains(normalize-space(),'For this reservation:')]")->length === 0 && empty($r->getCancelled())) {
            $r->general()->noConfirmation();
        }

        $guest = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Guest Information:'))}]/ancestor::tr[1]/descendant::td[2]");

        if (!empty($guest)) {
            $r->general()
                ->traveller($guest, true);
        }

        $xpathHotelName = "//tr[{$this->eq($this->t("Reservation Details"))}]/preceding::tr[not(.//tr) and normalize-space()][position()<4][ descendant::*[normalize-space() and {$xpathBold}] ][1]";

        $hotelName = $this->http->FindSingleNode($xpathHotelName);
        $address = $this->http->FindSingleNode($xpathHotelName . "/following::tr[not(.//tr) and normalize-space()][1]");
        $phone = $this->http->FindSingleNode($xpathHotelName . "/following::tr[not(.//tr) and normalize-space()][2]", null, true, "/^[+(\d][-. \d)(]{5,}[\d)]$/");
        $r->hotel()
            ->name($hotelName)
            ->address($address)
            ->phone($phone, false, true)
        ;

        // 2:00 pm
        $patterns['time'] = '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?';
        // Thursday, January 2, 2020 at 2:00 pm
        $patterns['dateTime'] = "(?<date>.{6,}?)(?:\s+{$this->opt($this->t('at'))}\s+(?<time>{$patterns['time']}))?";

        $checkIn = $this->http->FindSingleNode("//*[" . $this->starts($this->t('Check In:')) . "]/following-sibling::td[1]");

        if (preg_match("/^{$patterns['dateTime']}$/", $checkIn, $m)) {
            $dateCheckIn = strtotime($this->normalizeDate($m['date']));

            if (empty($m['time'])) {
                $r->booked()->checkIn($dateCheckIn);
            } else {
                $r->booked()->checkIn(strtotime($m['time'], $dateCheckIn));
            }
        }

        $checkOut = $this->http->FindSingleNode("//*[" . $this->starts($this->t('Check Out:')) . "]/following-sibling::td[1]");

        if (preg_match("/^{$patterns['dateTime']}$/", $checkOut, $m)) {
            $dateCheckOut = strtotime($this->normalizeDate($m['date']));

            if (empty($m['time'])) {
                $r->booked()->checkOut($dateCheckOut);
            } else {
                $r->booked()->checkOut(strtotime($m['time'], $dateCheckOut));
            }
        }

        $roomType = $this->http->FindSingleNode("//tr[{$this->eq($this->t("Room Type"))}]/following::tr[not(.//tr) and normalize-space()][1][not(descendant::*[{$xpathBold}])]");

        $roomDescription = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("Bed Selection")) . "]/following::td[1]");

        $roomRate = $this->http->FindSingleNode("//*[{$this->starts($this->t('Room (per night):'))}]/following-sibling::td[normalize-space()][1]", null, true, '/^(?:[^\-\d)(]+)?[ ]*\d[,.‘\'\d ]*$/u');

        if ($roomType || $roomDescription || $roomRate) {
            $room = $r->addRoom();

            if ($roomType) {
                $room->setType($roomType);
            }

            if ($roomDescription) {
                $room->setDescription($roomDescription);
            }

            if ($roomRate) {
                $room->setRate($roomRate);
            }
        }

        $rooms = $this->http->FindSingleNode("//*[" . $this->starts($this->t('Your Stay:')) . "]/following-sibling::td[1]",
            null, true, "/(\d+)[\s]?" . $this->opt($this->t("room")) . "/");

        if (!empty($rooms)) {
            $r->booked()->rooms($rooms);
        }

        $totalPrice = $this->http->FindSingleNode("//td[{$this->eq($this->t('totalPrice'))}]/following-sibling::td[normalize-space()][last()]");

        if (preg_match('/^(?<currency>[^\d)(]+?)[ ]*(?<amount>\d[,.\'\d ]*)$/', $totalPrice, $matches)) {
            // US$430.15
            $currency = $this->normalizeCurrency($matches['currency']);
            $currencyCode = preg_match('/^[A-Z]{3}$/', $currency) ? $currency : null;
            $r->price()->currency($currency)->total(PriceHelper::parse($matches['amount'], $currencyCode));

            $feeRows = $this->http->XPath->query("//tr[ preceding-sibling::tr[*[1][{$this->eq($this->t('Paid Now'))} or {$this->eq($this->t('Room (per night):'))}]] and following-sibling::tr[*[1][{$this->eq($this->t('totalPrice'))}]] and *[1][{$this->eq($this->t('feeNames'))}] and *[2][normalize-space()] ]");

            foreach ($feeRows as $feeRow) {
                $feeCharge = $this->http->FindSingleNode('*[2]', $feeRow);

                if (preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.\'\d ]*)$/', $feeCharge, $m)) {
                    $feeName = $this->http->FindSingleNode('*[1]', $feeRow, true, "/^(.+?)[: ]*$/");
                    $r->price()->fee($feeName, PriceHelper::parse($m['amount'], $currencyCode));
                }
            }

            $discount = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Carrot Cash:'))}]/ancestor::tr[1]/*[normalize-space()][2]", null, true, "/\D([\d\.]+)$/");

            if (!empty($discount)) {
                $r->price()
                    ->discount($discount);
            }
        }

        $cancellationTexts = array_filter($this->http->FindNodes("//td[{$this->starts($this->t("Cancellation:"))}]/following::td[not(.//tr) and position()<3]", null, "/^(.*(?:cancel|refund|reembols).*?)[,.!?;: ]*$/i"));

        if (count($cancellationTexts)) {
            $r->general()->cancellation(implode('; ', $cancellationTexts));

            if (preg_match("/{$this->opt($this->t('Non-refundable'))}/", implode('; ', $cancellationTexts))) {
                $r->setNonRefundable(true);
            }
        }

        if (preg_match("/{$this->opt($this->t("Cancellations or changes made after"))}\s+(.{6,}?{$patterns['time']})\s/", $r->getCancellation(), $m)) {
            $r->booked()->deadline2($m[1]);
        }
    }

    private function detectBody(): bool
    {
        foreach (self::$detectors as $phrases) {
            foreach ($phrases as $phrase) {
                if ($this->http->XPath->query("//*[{$this->contains($phrase)}]")->length > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    private function assignLang(): bool
    {
        foreach (self::$dictionary as $lang => $words) {
            if (isset($words["Reservation Details"])) {
                if ($this->http->XPath->query("//*[{$this->contains($words['Reservation Details'])}]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param string $string Unformatted string with currency
     */
    private function normalizeCurrency(string $string): string
    {
        $string = trim($string);
        $currencies = [
            // do not add unused currency!
            'USD' => ['US$'],
        ];

        foreach ($currencies as $currencyCode => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $currencyCode;
                }
            }
        }

        return $string;
    }

    /**
     * Dependencies `use AwardWallet\Engine\MonthTranslate` and `$this->lang`.
     *
     * @param string|null $text Unformatted string with date
     */
    private function normalizeDate(?string $text): ?string
    {
        if (preg_match('/\b(\d{1,2})[-,. ]+(?:de\s+)?([[:alpha:]]{3,})(?:\s+de)?[-,. ]+(\d{4})$/u', $text, $m)) {
            // Friday, 20 October, 2023    |    sábado, 23 de septiembre de 2023
            $day = $m[1];
            $month = $m[2];
            $year = $m[3];
        } elseif (preg_match('/\b([[:alpha:]]{3,})[-,. ]+(\d{1,2})[-,. ]+(\d{4})$/u', $text, $m)) {
            // Tuesday, April 27, 2021
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

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
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

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function opt($field): string
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
    }
}
