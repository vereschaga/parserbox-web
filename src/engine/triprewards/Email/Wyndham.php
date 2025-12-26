<?php

namespace AwardWallet\Engine\triprewards\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class Wyndham extends \TAccountChecker
{
    public $mailFiles = "triprewards/it-1973486.eml, triprewards/it-1974272.eml";

    public $reFrom = "wyn.com";
    public $reBody = [
        'Wyndham',
        'wyn.com',
    ];
    public $reSubject = [
        'Your Wyndham Reservation',
        'Reservation Cancellation',
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $a = explode('\\', __CLASS__);
        $class = end($a);
        $email->setType($class);

        $this->parseEmail($email);

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return $this->stripos(($body = $this->http->Response['body']), $this->reBody) && (strpos($body, 'your reservation has been cancelled') !== false || strpos($body, 'Room Type') !== false);
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], $this->reFrom) !== false && isset($this->reSubject)) {
            foreach ($this->reSubject as $reSubject) {
                if (stripos($headers["subject"], $reSubject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->reFrom) !== false;
    }

    public static function getEmailLanguages()
    {
        return ['en'];
    }

    public static function getEmailTypesCount()
    {
        return 1;
    }

    private function parseEmail(Email $email)
    {
        $text = text($this->http->Response['body']);

        $h = $email->add()->hotel();
        $confNo = $this->re("#\n\s*Confirmation\s*:\s*([A-Z\d\-]+)#", $text);

        if (empty($confNo)) {
            $confNo = $this->re("#confirmation\s+number\s+is\s+([A-Z\d\-]+)#", $text);
        }

        if ($status = $this->re("#has\s+been\s+(\w+)#i", $text)) {
            $h->general()
                ->status($status);

            if ($status === 'cancelled') {
                $h->general()->cancelled();
            }
        }

        $h->general()
            ->confirmation($confNo)
            ->traveller($this->re("#\n\s*Dear\s+([^\n,]+)#", $text))
            ->cancellation($this->http->FindSingleNode("//*[contains(text(), 'Cancellation Policy')]/ancestor-or-self::td[1]",
                null, true, "#Cancellation Policy\s*:\s*(.+)#"), false, $h->getCancelled());

        $h->hotel()
            ->name($this->re("#(?:has been cancelled for|you\s+are\s+coming\s+to)\s+(.*?)\.#", $text))
            ->address(trim($this->re("#^\s*Wyndham\s+[A-Za-z\-, ]+\n([^\n]+)\s+Reservations:#m", $text), ', '))
            ->phone($this->re("#\n\s*Reservations\s*:\s*([\d-\(\)+ ]+)#", $text));

        $h->booked()
            ->guests($this->re("#\n\s*Adults\s*:\s*(\d+)#", $text), false, $h->getCancelled())
            ->kids($this->re("#\s*Children\s*:\s*(\d+)#", $text), false, $h->getCancelled())
            ->rooms($this->re("#\s*Rooms\s*:\s*(\d+)#", $text), false, $h->getCancelled());

        $checkIn = strtotime($this->re("#\n\s*Check-In\s*:\s*([^\n]+)#i", $text));
        $checkOut = strtotime($this->re("#\n\s*Check-Out\s*:\s*([^\n]+)#i", $text));

        if (!empty($checkIn) && !empty($checkOut)) {
            $h->booked()
                ->checkIn($checkIn)
                ->checkOut($checkOut);
        }

        $type = $this->re("#\n\s*Room Type\s*:\s*([^\n]+)#", $text);

        if (!empty($type) && !$h->getCancelled()) {
            $room = $h->addRoom();
            $room->setType($type, false, $h->getCancelled());
        }

        if (!$h->getCancelled()) {
            $tot = $this->re("#\n\s*Taxes\s*:\s*([^\n]+)#", $text);
            $tot = $this->getTotalCurrency($tot);
            $h->price()
                ->tax($tot['Total'])
                ->currency($tot['Currency']);

            $tot = $this->re("#\n\s*(?:Amount Paid|Reservation Total)\s*:\s*([^\n]+)#", $text);
            $tot = $this->getTotalCurrency($tot);
            $h->price()
                ->total($tot['Total'])
                ->currency($tot['Currency']);
        }

        if (!empty($node = $h->getCancellation())) {
            $this->detectDeadLine($h, $node);
        }

        return true;
    }

    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h, string $cancellationText)
    {
        //Here we describe various variations in the definition of dates deadLine
        if (preg_match("#reservations may be cancelled without penalty up to [\w\-]+ \((\d+)\) hours prior to scheduled check-in#i",
            $cancellationText, $m)
        ) {
            $h->booked()
                ->deadlineRelative($m[1] . ' hours');
        } elseif (preg_match("#reservations may be cancelled without penalty up until ((?:\d+:\d+|\d+)\s*[ap]\.?m\.?) [\w\-]+ \((\d+)\) days prior to arrival#i",
            $cancellationText, $m)
        ) {
            $h->booked()
                ->deadlineRelative($m[2] . ' days', str_replace(".", '', $m[1]));
        }
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

    private function stripos($haystack, $arrayNeedle)
    {
        foreach ($arrayNeedle as $needle) {
            if (stripos($haystack, $needle) !== false) {
                return true;
            }
        }

        return false;
    }
}
