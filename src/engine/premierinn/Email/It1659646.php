<?php

namespace AwardWallet\Engine\premierinn\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class It1659646 extends \TAccountChecker
{
    public $mailFiles = "premierinn/it-1659646.eml, premierinn/it-1659648.eml, premierinn/it-1659649.eml, premierinn/it-2449421.eml, premierinn/it-2456556.eml, premierinn/it-3508649.eml";

    public $reBody = 'Premier Inn';
    public $reBody2 = 'Hotel details';
    public $reSubject = 'Premier Inn booking for';
    public $reFrom = ['premierinn.com', 'piconfirmations.co.uk'];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $r = $email->add()->hotel();
        $text = text($this->http->Response['body']);

        if (preg_match('/Booking\s+reference\s+number:\s*((?-i)[A-Z\d]{5,})/sui', $text, $m)) {
            $r->general()->confirmation($m[1]);
        }
        $name = $this->http->FindSingleNode('/descendant::text()[normalize-space(.)="Hotel details" or normalize-space(.)="Hotel Details"][1]/following::text()[(./ancestor::span or ./ancestor::font) and string-length(normalize-space(.))>2][1]');
        $details = $this->http->FindSingleNode('/descendant::text()[normalize-space(.)="Hotel details" or normalize-space(.)="Hotel Details"][1]/following::text()[(./ancestor::span or ./ancestor::font) and string-length(normalize-space(.))>2][1]/ancestor::*[1]');
        $name_ = preg_quote($name, '/');

        if ($details !== $name) {
            $addr = $this->re("/$name_\s*(.+?)\s*\(\s*See\s*map/isu", $details);
        } else {
            $addr = $this->re("/$name_\s*(.+?)\s*\(\s*See\s*map/isu", $text);
        }
        $r->hotel()
            ->name($name)
            ->address($addr);

        if (preg_match('/(?:Arrival\s*date|Date\s*of\s*arrival):\s*After\s*(.*?)\s*on\s*[^\d\s]*\s*(.+?)\s*\n/isu',
            $text, $m)) {
            $time = str_ireplace('midday', '12:00', $m[1]);
            $date = $this->timestamp_from_format($m[2], 'd / m / y|');
            $r->booked()
                ->checkIn(strtotime($time, $date));
        }

        if (preg_match('/(?:Departure\s*date|Departure\s*date):\s*By\s*(.*?)\s*on\s*[^\d\s]*\s*(.+?)\s*\n/isu', $text,
            $m)) {
            $time = str_ireplace('midday', '12:00', $m[1]);
            $date = $this->timestamp_from_format($m[2], 'd / m / y|');
            $r->booked()
                ->checkOut(strtotime($time, $date));
        }

        if (!empty($phone = $this->re('/Tel:\s*(.+?)\s*\n/isu', $text))) {
            $r->hotel()->phone($phone);
        }

        if (!empty($fax = $this->re('/Fax:\s*(.+?)\s*\n/isu', $text))) {
            $r->hotel()->fax($fax);
        }

        if (!empty($guestName = $this->re('/Guest\s*name:\s*(.+?)\s*\n/isu', $text))) {
            $r->general()->traveller($guestName);
        }

        if (preg_match('/Guests?:\s*(\d{1,3})\s*adults?(?:,\s*(\d{1,3})\s*children)?/isu', $text, $m)) {
            if (isset($m[1]) && !empty($m[1])) {
                $r->booked()->guests($m[1]);
            }

            if (isset($m[2]) && !empty($m[2])) {
                $r->booked()->kids($m[2]);
            }
        }

        $cancellationPolicyTexts = trim($this->re("#What happens if I need to amend or cancel my booking\?\s*(.+)#", implode(' ',
            $this->http->FindNodes('//tr[normalize-space(.) = "Further information"]/following-sibling::tr[1]//text()[normalize-space()]'))));

        if (empty($cancellationPolicyTexts)) {
            $cancellationPolicyTexts = implode(' ',
                $this->http->FindNodes('//*[(local-name()="strong" or local-name()="b" or local-name()="tr") and not(.//tr)][contains(normalize-space(.),"or amend my booking") or contains(normalize-space(.),"or cancel my booking") or contains(normalize-space(.),"cancellation and amendment policy")]/following-sibling::node()[normalize-space()][1][not(ancestor-or-self::strong) and not(ancestor-or-self::b)][normalize-space(.)]'));
        }

        if (!empty($cancellationPolicyTexts)) {
            $r->general()
                ->cancellation($cancellationPolicyTexts);
            $this->detectDeadLine($r, $cancellationPolicyTexts);
        }

        if (!empty($roomType = $this->re('/Type\s*of\s*room:\s*(.+?)\s*\n/isu', $text))) {
            $room = $r->addRoom();
            $room->setType($roomType);
        }

        if (stripos($text, 'Your booking is confirmed') !== false) {
            $r->general()->status('confirmed');
        }

        $yourTotalTexts = $this->http->FindNodes('//text()[normalize-space(.)="Your total" or normalize-space(.)="Your Total"]/ancestor::table[contains(normalize-space(.),"Total room cost") or contains(normalize-space(.),"Total amount")][1]/descendant::text()[normalize-space(.)!=""]');
        $yourTotalBody = implode("\n", $yourTotalTexts);

        if (preg_match("/(?:Total amount|Total room cost)\s*:\s*(.+?)\s*(?:\n|$)/isu", $yourTotalBody, $m)) {
            $tot = $this->getTotalCurrency($m[1]);

            if (!empty($tot['Total'])) {
                $r->price()
                    ->total($tot['Total'])
                    ->currency($tot['Currency']);
            }
        }

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        return strpos($body, $this->reBody) !== false
            && stripos($body, $this->reBody2) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return strpos($headers['subject'], $this->reSubject) !== false;
    }

    public static function getEmailTypesCount()
    {
        return 1;
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
        return ['en'];
    }

    public function IsEmailAggregator()
    {
        return false;
    }

    /**
     * Here we describe various variations in the definition of dates deadLine.
     */
    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h, string $cancellationText)
    {
        if (preg_match("#You must do this before (\d+)[ ]?([ap]m)? on the day of your arrival via our website or by calling #i", $cancellationText, $m) // en
        ) {
            $h->booked()
                ->deadlineRelative("0 day", $m[1] . ":00" . ($m[2] ?? ''));
        }

        if (stripos($cancellationText, 'Please note you have booked a non-cancellable and non-amendable reservation.') !== false) {
            $h->booked()->nonRefundable();
        }
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

    private function timestamp_from_format($s, $fmt = null)
    {
        if (!$fmt) {
            return strtotime($s);
        }
        $dt = \DateTime::createFromFormat($fmt, $s);

        return $dt ? $dt->getTimestamp() : null;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }
}
