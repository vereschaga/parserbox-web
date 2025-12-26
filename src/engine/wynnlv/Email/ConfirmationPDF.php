<?php

namespace AwardWallet\Engine\wynnlv\Email;

use AwardWallet\Schema\Parser\Email\Email;

class ConfirmationPDF extends \TAccountChecker
{
    public $mailFiles = "wynnlv/it-66162126.eml, wynnlv/it-66366941.eml, wynnlv/it-98940599.eml";
    public $lang = 'en';

    public $htmlText;

    public $subjects = [
        '/Confirmation\# \d+ Wynn Las Vegas$/',
    ];

    public $detectBody = [
        'en' => [
            'Hotel Reservation Details', 'Room Rate Summary', 'Wynn Las Vegas',
        ],
    ];

    public static $dictionary = [
        "en" => [
            'cancelledPhrases' => [
                'your room reservation has been cancelled per your request',
                'your room reservation has been canceled per your request',
            ],
            'statusPhrases'   => ['your room reservation has been'],
            'statusVariants'  => ['cancelled', 'canceled'],
            'cancellationEnd' => ['During Your Stay:', 'Resort Fee and Additional Charges:'],
            'feeNames'        => ['Tax', 'Resort Fee Charge'],
        ],
    ];

    public function parseEmailHTML(Email $email): void
    {
        $h = $email->add()->hotel();
        $h->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->contains($this->t('CONFIRMATION DETAILS'))}]/following::text()[{$this->eq($this->t('Confirmation'))}][1]/following::text()[normalize-space()][1]", null, true, "/^(\d{8})$/"))
            ->traveller($this->http->FindSingleNode("//text()[{$this->contains($this->t('CONFIRMATION DETAILS'))}]/following::text()[{$this->eq($this->t('Guest Name'))}][1]/following::text()[normalize-space()][1]"), true)
            ->cancellation($this->http->FindSingleNode("//text()[{$this->contains($this->t('Cancellation Policy'))}]/following::text()[normalize-space()][1]"));

        $hotelName = $this->http->FindSingleNode("//text()[{$this->contains($this->t('We are looking forward to providing you with the Five-Star service you expect from'))}]", null, true, "/We are looking forward to providing you with the Five-Star service you expect from\s+(\D+)\.\s+During/");
        $h->hotel()->name($hotelName);
        $address = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Help'))}]/following::text()[{$this->contains($this->t('Wynn Resorts'))}]", null, true, "/^Wynn Resorts\.(.+)/");

        if ($address) {
            $h->hotel()->address($address);
        } elseif ($this->http->XPath->query("//text()[{$this->eq($this->t('Help'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->eq($this->t('Help'))}]/following::text()[normalize-space()]")->length === 0
        ) {
            $h->hotel()->noAddress();
        }

        $h->booked()
            ->rooms($this->http->FindSingleNode("//text()[{$this->contains($this->t('CONFIRMATION DETAILS'))}]/following::text()[{$this->eq($this->t('Number of Rooms'))}][1]/following::text()[normalize-space()][1]"));

        $dateIn = $this->http->FindSingleNode("//text()[{$this->contains($this->t('CONFIRMATION DETAILS'))}]/following::text()[{$this->eq($this->t('Arrival Date'))}][1]/following::text()[normalize-space()][1]");
        $dateOut = $this->http->FindSingleNode("//text()[{$this->contains($this->t('CONFIRMATION DETAILS'))}]/following::text()[{$this->eq($this->t('Departure Date'))}][1]/following::text()[normalize-space()][1]");

        $timeIn = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Check-in Time'))}]/following::text()[normalize-space()][1]");
        $timeOut = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Check-Out Time'))}]/following::text()[normalize-space()][1]");

        $h->booked()
            ->checkIn(strtotime($dateIn . ', ' . $timeIn))
            ->checkOut(strtotime($dateOut . ', ' . $timeOut));

        $roomType = $this->http->FindSingleNode("//text()[{$this->contains($this->t('CONFIRMATION DETAILS'))}]/following::text()[{$this->eq($this->t('Room Type'))}][1]/following::text()[normalize-space()][1]");

        if (!empty($roomType)) {
            $room = $h->addRoom();
            $room->setType($roomType);

            $room->setRate(str_replace("\n", " ", implode("\n", $this->http->FindNodes("//text()[{$this->contains($this->t('CONFIRMATION DETAILS'))}]/following::text()[{$this->eq($this->t('Nightly Rate'))}][1]/following::td[1]/descendant::text()[normalize-space()]"))));
        }

        $this->detectDeadLine($h);
    }

    public function parseEmailPdf(Email $email, $text): void
    {
        $h = $email->add()->hotel();

        if (preg_match("/^[ ]*Dear[ ]+([[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]])[ ,:;!?]*$/mu", $text, $m)) {
            $h->general()->traveller($m[1]);
        }

        if (preg_match("/{$this->opt($this->t('statusPhrases'))}\s+({$this->opt($this->t('statusVariants'))})(?:\s*[,.:;!?]|\s+per)/", $text, $m)) {
            $h->general()->status($m[1]);
        }

        if (preg_match("/{$this->opt($this->t('cancelledPhrases'))}/", $text)) {
            // it-98940599.eml
            $h->general()->cancelled();
        }

        $textPart = $this->re("/^([ ]*Hotel Reservation Details:.+?)(?:\n\n\n|\n[ ]*Hotel Information:)/ms", $text);

        $table = $this->SplitCols($textPart);

        if (preg_match("/^[ ]*(Confirmation Number)[ ]*[:]+[ ]*(\d+)$/m", $table[0], $m)) {
            $h->general()->confirmation($m[2], $m[1]);
        }

        if (preg_match("/\n[ ]*Deposit and Cancellation Information:\n+[ ]*([\s\S]+?)(?:\n\n|\n[ ]*{$this->opt($this->t('cancellationEnd'))}|$)/", $text, $m)) {
            $h->general()->cancellation(preg_replace('/\s+/', ' ', $m[1]));
        }

        $checkIn = $this->re("/Check-in Date\:\s+(\w+\,\s*\w+\s+\d+\,\s+\d{4})/", $table[0]);
        $checkOut = $this->re("/Check-out Date\:\s+(\w+\,\s*\w+\s+\d+\,\s+\d{4})/", $table[0]);
        $h->booked()
            ->checkIn(strtotime($checkIn))
            ->checkOut(strtotime($checkOut))
            ->rooms($this->re("/Number of Rooms:\s+(\d+)/", $table[0]))
            ->guests($this->re("/Number of Adults[ ]*[:]+[ ]*(\d{1,3})$/m", $table[0]), false, true)
            ->kids($this->re("/Number of Children[ ]*[:]+[ ]*(\d{1,3})$/m", $table[0]), false, true)
        ;

        $this->detectDeadLine($h);

        if (preg_match("/For questions and information about your reservation contact\:\s*\n(?<hotelName>\D+)\sRESERVATIONS\s*\ntel\:\s+(?<hotelPhone>[\)\(\d\s\-]+)\s*\n.+\nfax\:\s*(?<hotelFax>[\)\(\d\s\-]+)\s*\n.+\n(?<hotelAddress>.+)\n\s*CONCIERGE/", $text, $m)) {
            $h->hotel()
                ->name($m['hotelName'])
                ->address($m['hotelAddress'])
                ->fax($m['hotelFax'])
                ->phone($m['hotelPhone']);
        }

        $roomType = $this->re("/Room Type:\s+(.+)\n/", $table[0]);

        $rate = null;
        $rateText = $this->re("/^\s*Room Rate Summary:\n+[ ]*([\s\S]+?)(?:\n\n|$)/", $table[1]);
        $rateRange = $this->parseRateRange($rateText);

        if ($rateRange !== null) {
            $rate = $rateRange;
        } elseif (preg_match("/^[ ]*(Daily Average Rate\s+\d[,.\'\d ]*)$/m", $rateText, $m)) {
            $rate = $m[1];
        }

        if ($roomType || $rate !== null) {
            $room = $h->addRoom();

            if ($roomType) {
                $room->setType($roomType);
            }

            if ($rate !== null) {
                $room->setRate($rate);
            }
        }

        if (preg_match("/^[ ]*Total[ ]*[:]+[ ]*(.*\d.*)$/m", $table[0], $m0)
            && preg_match('/^(?<currency>[^\d)(]+?)[ ]*(?<amount>\d[,.\'\d ]*)$/', $m0[1], $matches)
        ) {
            // $ 5,107.76
            $h->price()->currency($matches['currency'])->total($this->normalizeAmount($matches['amount']));

            if (preg_match("/^[ ]*Subtotal[ ]*[:]+[ ]*(.*\d.*)$/m", $table[0], $m0)
                && preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.\'\d ]*?)$/', $m0[1], $m)
            ) {
                $h->price()->cost($this->normalizeAmount($m['amount']));
            }

            preg_match_all("/^[ ]*(?<name>{$this->opt($this->t('feeNames'))})[ ]*[:]+[ ]*(?<value>.*\d.*)$/m", $table[0], $feeMatches, PREG_SET_ORDER);

            foreach ($feeMatches as $feeM) {
                if (preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.\'\d ]*?)$/', $feeM['value'], $m)) {
                    $h->price()->fee($feeM['name'], $this->normalizeAmount($m['amount']));
                }
            }
        }
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        if (!empty($pdfs) && count($pdfs) > 0) {
            foreach ($pdfs as $pdf) {
                $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

                foreach ($this->detectBody as $detect) {
                    foreach ($detect as $word) {
                        if (empty($this->re("/({$word})/", $text))) {
                            return false;
                        }
                    }
                }

                return true;
            }
        } else {
            return $this->http->XPath->query("//text()[contains(normalize-space(), 'Wynn Las Vegas')]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('CONFIRMATION DETAILS'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Check-in Time'))}]")->length > 0;
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@wynnlasvegas.com') !== false) {
            foreach ($this->subjects as $subject) {
                if (preg_match($subject, $headers['subject'])) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]wynnlasvegas\.com/i', $from) > 0;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->htmlText = !empty($parser->getHTMLBody()) ? $parser->getHTMLBody() : $parser->getPlainBody();
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        if (!empty($pdfs) && count($pdfs) > 0) {
            foreach ($pdfs as $pdf) {
                if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) !== null) {
                    $this->parseEmailPdf($email, $text);
                }
            }
        } else {
            if (!empty($this->http->FindSingleNode("//text()[contains(normalize-space(), 'CONFIRMATION DETAILS')]/following::text()[normalize-space()='Confirmation'][1]/following::text()[normalize-space()][1]", null, true, "/^(\d{8})$/"))) {
                $this->parseEmailHTML($email);
            }
        }

        $email->setType('ConfirmationPDF' . ucfirst($this->lang));

        return $email;
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "contains(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function TableHeadPos($row)
    {
        $head = array_filter(array_map('trim', explode("%", preg_replace("#\s{2,}#", "%", $row))));
        $pos = [];
        $lastpos = 0;

        foreach ($head as $word) {
            $pos[] = mb_strpos($row, $word, $lastpos, 'UTF-8');
            $lastpos = mb_strpos($row, $word, $lastpos, 'UTF-8') + mb_strlen($word, 'UTF-8');
        }

        return $pos;
    }

    private function SplitCols($text, $pos = false)
    {
        $cols = [];
        $rows = explode("\n", $text);

        if (!$pos) {
            $pos = $this->TableHeadPos($rows[0]);
        }
        arsort($pos);

        foreach ($rows as $row) {
            foreach ($pos as $k => $p) {
                $cols[$k][] = trim(mb_substr($row, $p, null, 'UTF-8'));
                $row = mb_substr($row, 0, $p, 'UTF-8');
            }
        }
        ksort($cols);

        foreach ($cols as &$col) {
            $col = implode("\n", $col);
        }

        return $cols;
    }

    /**
     * Formatting over 3 steps:
     * 11 507.00  ->  11507.00
     * 2,790      ->  2790    |    4.100,00  ->  4100,00    |    1'619.40  ->  1619.40
     * 18800,00   ->  18800.00  |  2777,0    ->  2777.0.
     *
     * @param string|null $s Unformatted string with amount
     * @param string|null $decimals Symbols floating-point when non-standard decimals (example: 1,258.943)
     */
    private function normalizeAmount(?string $s, ?string $decimals = null): ?float
    {
        if (!empty($decimals) && preg_match_all('/(?:\d+|' . preg_quote($decimals, '/') . ')/', $s, $m)) {
            $s = implode('', $m[0]);

            if ($decimals !== '.') {
                $s = str_replace($decimals, '.', $s);
            }
        } else {
            $s = preg_replace('/\s+/', '', $s);
            $s = preg_replace('/[,.\'](\d{3})/', '$1', $s);
            $s = preg_replace('/,(\d{1,2})$/', '.$1', $s);
        }

        return is_numeric($s) ? (float) $s : null;
    }

    /**
     * Dependencies `$this->normalizeAmount()`.
     */
    private function parseRateRange(?string $string): ?string
    {
        if (preg_match_all('/\d{1,2}-\d{1,2}-\d{2,4}[ ]+(?<amount>\d[,.\'\d ]*)[ ]*(?<currency>[^\d)(]+?)$/m', $string, $rateMatches) // 09-18-21   1,785.00 USD
        ) {
            $rateMatches['currency'] = array_values(array_filter($rateMatches['currency']));

            if (count(array_unique($rateMatches['currency'])) === 1) {
                $rateMatches['amount'] = array_map(function ($item) {
                    return $this->normalizeAmount($item);
                }, $rateMatches['amount']);

                $rateMin = min($rateMatches['amount']);
                $rateMax = max($rateMatches['amount']);

                if ($rateMin === $rateMax) {
                    return number_format($rateMatches['amount'][0], 2, '.', '') . ' ' . $rateMatches['currency'][0] . ' / night';
                } else {
                    return number_format($rateMin, 2, '.', '') . '-' . number_format($rateMax, 2, '.', '') . ' ' . $rateMatches['currency'][0] . ' / night';
                }
            }
        }

        return null;
    }

    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h): void
    {
        if (preg_match('/The deposit is fully refundable upon notice of cancellation at least (?<prior>\d{1,3} hours?) prior to the arrival date/', $h->getCancellation(), $m)
            || preg_match("/Refunds (?i)will be issued on individual attendee reservations cancell?ed up to (?<prior>\d{1,3} hours?) prior to the scheduled arrival date\./", $h->getCancellation(), $m)
        ) {
            $h->booked()->deadlineRelative($m['prior'], '00:00');
        }
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field));
    }
}
