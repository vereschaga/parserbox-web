<?php

namespace AwardWallet\Engine\goldpassport\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Confirmation extends \TAccountChecker
{
    public $mailFiles = "goldpassport/it-110436458.eml, goldpassport/it-124024725.eml, goldpassport/it-169262319.eml, goldpassport/it-623378521.eml, goldpassport/it-99252861.eml";
    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'Confirmation:' => ['Confirmation:', 'Confirmation: #', 'Conﬁrmation:', 'ConDrmation:'],
            'Checkout'      => ['Checkout', 'Check-out'],
        ],
    ];

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('.*\.pdf');

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (stripos($text, 'Thank you for choosing to stay with Hyatt Hotels & Resorts') !== false
                && stripos($text, 'Reservation Summary') !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]hyatt\.com$/', $from) > 0;
    }

    public function ParseHotel(Email $email, $text): void
    {
        $h = $email->add()->hotel();

        if (preg_match("/^[ ]*({$this->opt($this->t('Confirmation:'))})\s*([-A-Z\d]{5,})$/m", $text, $m)) {
            $h->general()->confirmation($m[2], str_replace(':', '', $m[1]));
        }

        if (preg_match("/{$this->opt($this->t('Confirmation:'))}\s*.+\n+(?<name>.+)\n(?<address>.+)\n *Tel\:\s*(?<phone>.+)/", $text, $m)) {
            $h->hotel()
                ->name($m['name'])
                ->address($m['address'])
                ->phone($m['phone']);
        }

        $textPart = $this->re("/\n( *Check-in.+Special Requests)/s", $text);

        if (empty($textPart)) {
            $textPart = $this->re("/\n( *Check-in.+Special Requests)/s", $text);
        }

        $colPos[] = stripos($textPart, 'Check-in');
        $colPos[] = stripos($textPart, 'Rate');

        $table = $this->SplitCols($textPart, $colPos);

        $h->general()
            ->traveller($this->re("/Guest Details\s+([[:alpha:] ]*[[:alpha:]])\n\S+[@]/", $table[0]), true);

        $h->booked()
            ->checkIn($this->normalizeDate($this->re("/Check-in(.+){$this->opt($this->t('Checkout'))}/s", $table[0])))
            ->checkOut($this->normalizeDate($this->re("/{$this->opt($this->t('Checkout'))}(.+)Room/s", $table[0])))
            ->rooms($this->re("/Room\s*\(\s*(\d{1,3})\s*\)/", $table[0]) ?? $this->re("/Room\s*(\d{1,3}) \d{1,3}\b/", $table[0]), false, true)
            ->guests($this->re("/Guest\s*(\d+)\s*Adult/", $table[0]));

        $kids = $this->re("/Guest\s+\d+\s*Adults?\n*(\d+)\s*Children/", $table[0]);

        if (!empty($kids)) {
            $h->booked()
                ->kids($kids);
        }

        $roomInfo = $this->re("/Room\s*\d{1,3} (\d{1,3}\b.{2,})/", $table[0]) ?? $this->re("/Room(?:\s*\(\s*\d{1,3}\s*\)\s*|\s+)(.{2,})/", $table[0]);
        $rateInfo = preg_replace("/\s+/", " ", preg_replace("/\s*\n\s*/", "; ", $this->re("/^[ ]*Total Cash Per Room\b.*\d.*\n{1,2}([\s\S]+?)\n{1,2}[ ]*Subtotal/m", $table[1])));

        if (!empty($roomInfo) || !empty($rateInfo)) {
            $room = $h->addRoom();

            if (!empty($roomInfo)) {
                $room->setDescription($roomInfo);
            }

            if (!empty($rateInfo)) {
                $room->setRate($rateInfo);
            }
        }

        $freenight = $this->re("/Total Awards[*]* +(\d+) *Free Night/", $table[1]);

        if (!empty($freenight)) {
            $h->booked()->freeNights($freenight);
        }

        if ((!empty($freenight) && !empty($this->re("/Total Cash Per Room/", $table[1])))
            || empty($freenight)
        ) {
            $totalPrice = $this->re("/^[ ]*Total Cash Per Room\b[* ]+(.*\d.*)$/m", $table[1]);

            if (preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.‘\'\d ]*?)[ ]*(?<currencyCode>[A-Z]{3})$/u', $totalPrice, $matches)
                || preg_match('/^(?<currencyCode>[A-Z]{3})[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $totalPrice, $matches)
                || preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $totalPrice, $matches)
            ) {
                // $1,065.00 USD    |    PHP104,748.02    |    $608.73
                if (empty($matches['currency'])) {
                    $matches['currency'] = '';
                }

                if (empty($matches['currencyCode'])) {
                    $matches['currencyCode'] = '';
                }

                $currency = empty($matches['currencyCode']) ? $this->normalizeCurrency($matches['currency']) : $matches['currencyCode'];
                $h->price()->currency($currency)->total(PriceHelper::parse($matches['amount'], $matches['currencyCode']));

                $subtotal = $this->re("/^[ ]*Subtotal\b[* ]+(.*\d.*)$/m", $table[1]);

                if (preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.‘\'\d ]*?)[ ]*(?:' . preg_quote($matches['currencyCode'], '/') . ')?$/u', $subtotal, $m)
                    || preg_match('/^(?:' . preg_quote($matches['currencyCode'], '/') . ')[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $subtotal, $m)
                    || preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $subtotal, $m)
                ) {
                    $h->price()->cost(PriceHelper::parse($m['amount'], $matches['currencyCode']));
                }

                $taxes = $this->re("/^[ ]*Taxes & Fees\b[* ]+(.*\d.*)$/m", $table[1]);

                if (preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.‘\'\d ]*?)[ ]*(?:' . preg_quote($matches['currencyCode'], '/') . ')?$/u', $taxes, $m)
                    || preg_match('/^(?:' . preg_quote($matches['currencyCode'], '/') . ')[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $taxes, $m)
                    || preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $taxes, $m)
                ) {
                    $h->price()->tax(PriceHelper::parse($m['amount'], $matches['currencyCode']));
                }
            }
        }

        // Program
        $account = $this->re("/World of Hyatt # *([A-Z\d]{5,})(?:\n|$)/", $table[0]);

        if (!empty($account)) {
            $h->program()
                ->account($account, false);
        }
    }

    public function ParseHotel2(Email $email, $text): void
    {
        $this->logger->debug(__METHOD__);
        // $this->logger->debug('$text = '.print_r( $text,true));

        $text = str_replace("\n:\n", "\n", $text);
        $h = $email->add()->hotel();

        $cancellation = $this->re("/Cancellation Policy\s*\n+(\s*\d+\s*(?:Hours|H\s+).+)/", $text);

        if (!empty($cancellation)) {
            $h->general()
                ->cancellation($cancellation);
        }

        if (preg_match("/^[ ]*({$this->opt($this->t('Confirmation:'))}) *#? *([-A-Z\d]{5,})$/m", $text, $m)) {
            $h->general()->confirmation($m[2], str_replace(':', '', $m[1]));
        }

        if (preg_match("/{$this->opt($this->t('Confirmation:'))}\s*.+\n+(?<name>.+)\n*(?<address>.+)\n* {0,10}Tel\:\s*(?<phone>.+)/", $text, $m)) {
            $h->hotel()
                ->name($m['name'])
                ->address($m['address'])
                ->phone($m['phone']);
        }

        $textPart = $this->re("/\n( *Check-in.+(?:Changes in taxes or fees will affect the total\s+price|Rate is Conﬁdential))/s", $text);

        $colPos[] = 0;
        $colPos[] = stripos($textPart, 'Name');

        $table = $this->SplitCols($textPart, $colPos);

        $h->general()
            ->traveller($this->re("/Name\s+([[:alpha:] ]*[[:alpha:]])\s*\n/", $table[1]), true);

        $roomsCount = $this->re("/Room\s*\((\d+)\)/", $table[0]);
        $h->booked()
            ->checkIn($this->normalizeDate($this->re("/Check-in(.+)Check[-]?out/s", $table[0])))
            ->checkOut($this->normalizeDate($this->re("/Check[-]?out(.+)Guests/s", $table[0])))
            ->rooms($roomsCount)
            ->guests($this->re("/Guests\s*(\d+)\s*Guest/", $table[0]));

        $roomsCount = !empty($roomsCount) ? $roomsCount : 1;
        $roomInfo = str_replace("\n", " ", $this->re("/Room\s*\(\d+\)\s*(.+)\s*Total Cash Per Room/s", $table[0]));
        $rateInfo = preg_replace("/\s+/", " ", preg_replace("/\s*\n\s*/", "; ", $this->re("/Total Cash Per Room\*\s*[^\s\d]{1,5}\d[\d\,\.]+\s*\n+(.+)\n?\nSubtotal/su", $table[0])));

        if (!empty($roomInfo) || !empty($rateInfo)) {
            for ($i = 0; $i < $roomsCount; $i++) {
                $room = $h->addRoom();

                if (!empty($roomInfo)) {
                    $room->setDescription($roomInfo);
                }

                if (!empty($rateInfo)) {
                    $room->setRates(explode(';', $rateInfo));
                }
            }
        }

        if (substr_count($text, 'Total Cash Per Room') > 1) {
            $h->price()
                ->total(null)
                ->currency(null);
        } elseif (stripos($text, 'Rate is Conﬁdential') == false) {
            $currency = $this->re("/Total Cash Per Room\*\s*([^\s\d]{1,5})\d[\d\,\.]+\s*/", $table[0]);
            $h->price()
                ->total($roomsCount * PriceHelper::parse($this->re("/Total Cash Per Room\*\s*[^\s\d]{1,5}(\d[\d\,\.]+)\s/", $table[0]), $currency))
                ->currency($currency)
                ->cost($roomsCount * PriceHelper::parse($this->re("/Subtotal\s*[^\s\d]{1,5}(\d[\d\,\.]+)\s/us", $table[0]), $currency))
                ->tax($roomsCount * PriceHelper::parse($this->re("/Taxes & Fees\s*[^\s\d]{1,5}(\d[\d\,\.]*)\s/", $table[0]), $currency));
        }

        // Program
        $account = $this->re("/World of Hyatt +([A-Z\d]{5,})\s+Membership #\s*(?:\n|$)/", $table[1]);

        if (!empty($account)) {
            $h->program()
                ->account($account, false);
        }

        $this->detectDeadLine($h);
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName('.*\.pdf');
        $type = '';

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (preg_match("/Reservation Summary\s*\n/", $text)) {
                $type = '1';
                $this->ParseHotel($email, $text);
            } elseif (preg_match("/\s*Reservation Summary\s*Guest Details\s*\n/", $text)) {
                $type = '2';
                $this->ParseHotel2($email, $text);
            }
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . $type . ucfirst($this->lang));

        return $email;
    }

    private function re($re, $str, $c = 1): ?string
    {
        if (preg_match($re, $str, $m) && array_key_exists($c, $m)) {
            return $m[$c];
        }

        return null;
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
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
    }

    private function SplitCols($text, $pos = false)
    {
        $cols = [];
        $rows = explode("\n", $text);

        if (!$pos) {
            $pos = $this->rowColsPos($rows[0]);
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

    private function rowColsPos($row)
    {
        $head = array_filter(array_map('trim', explode("|", preg_replace("#\s{2,}#", "|", $row))));
        $pos = [];
        $lastpos = 0;

        foreach ($head as $word) {
            $pos[] = mb_strpos($row, $word, $lastpos, 'UTF-8');
            $lastpos = mb_strpos($row, $word, $lastpos, 'UTF-8') + mb_strlen($word, 'UTF-8');
        }

        return $pos;
    }

    private function normalizeDate($date)
    {
        //$this->logger->debug('IN-'.$date);
        $in = [
            '#^\s*\w+\,\s*(\w+)\s*(\d+)\,\s*(\d{4})\s*([\d\:]+\s*A?P?M).*$#s', //Sun, Jul 18, 2021 04:00 PM
        ];
        $out = [
            '$2 $1 $3, $4',
        ];
        $str = preg_replace($in, $out, $date);

        //$this->logger->debug('OUT-'.$date);
        return strtotime($str);
    }

    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h): void
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        if (preg_match('/(\d+\s*hours?) Prior or \d+Night Fee:Credit Card Req/i', $cancellationText, $m)) {
            $h->booked()->deadlineRelative($m[1]);
        }

        if (preg_match('/(\d+)\s*H PRIOR TO ([\d\:]+\s*A?P?M) LOCAL TIME THE DAY OF ARRIVAL/ui', $cancellationText, $m)) {
            $h->booked()->deadlineRelative($m[1] . ' hours', $m[2]);
        }
    }

    /**
     * @param string $string Unformatted string with currency
     */
    private function normalizeCurrency(string $string): string
    {
        $string = trim($string);
        $currencies = [
            // do not add unused currency!
            'AUD' => ['A$'],
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
}
