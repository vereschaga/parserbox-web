<?php

namespace AwardWallet\Engine\prestigia\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Common\Hotel;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class HotelReservationPDF extends \TAccountChecker
{
    public $mailFiles = "prestigia/it-791861303.eml";
    public $subjects = [
        'Confirmation of payment',
    ];

    public $pdfNamePattern = ".*pdf";

    public $lang = 'en';

    public static $dictionary = [
        'en' => [
            'Night from' => ['Night from', 'Nights from'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@prestigia.com') !== false) {
            foreach ($this->subjects as $subject) {
                if (stripos($headers['subject'], $subject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        $text = [];

        foreach ($pdfs as $pdf) {
            $text[] = \PDF::convertToText($parser->getAttachmentBody($pdf));
        }

        $text = implode("\n\n\n\n", $text);

        if (strpos($text, "Thank you for choosing Prestigia") !== false
            && strpos($text, $this->t('Booking confirmation')) !== false
            && strpos($text, $this->t('Booking management')) !== false
            && strpos($text, $this->t('Room')) !== false) {
            return true;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]prestigia\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        $text = [];

        foreach ($pdfs as $pdf) {
            $text[] = \PDF::convertToText($parser->getAttachmentBody($pdf));
        }

        $text = implode("\n\n\n\n", $text);

        $this->HotelReservationPDF($email, $text);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function HotelReservationPDF(Email $email, $text)
    {
        $h = $email->add()->hotel();

        $h->general()
            ->confirmation($this->re("/{$this->t('Booking confirmation')}[\n\s]*([A-Z\d\-]+)\n*/", $text));

        $h->hotel()
            ->name($this->re("/\n*\s*(.+)\s*\-\s*\d*\s*Stars\s*\-\s*/", $text))
            ->address($this->re("/\n*\s*Address\s*(.+)\n/", $text));

        $traveller = $this->re("/\n*\s*([[:alpha:]][-.\/\'’[:alpha:] ]*[[:alpha:]])\s*Thank\s*you/u", $text);
        $h->addTraveller($traveller);

        $reservationInfo = $this->re("/\n*\s*\d+\s*Nights?\s*from\s*\w+\s*(\d+\s*\w+\s*\d{4}\s*to\s*\w+\s*\d+\s*\w+\s*\d{4})\s*\n*/", $text);

        if (preg_match("/^(?<checkIn>\d+\s*\w+\s*\d{4})\s*to\s*\w+\s*(?<checkOut>\d+\s*\w+\s*\d{4})$/", $reservationInfo, $m)) {
            $h->booked()
                ->checkIn(strtotime($m['checkIn']))
                ->checkOut(strtotime($m['checkOut']));
        }

        $priceInfo = $this->re("/\n*\s*{$this->t('Total amount of the stay')}\s*(\D{1,3}\s*[\d\.\,\']+)\s*\n*/", $text);

        if (preg_match("/^(?<currency>\D{1,3})\s*(?<price>[\d\.\,\']+)$/", $priceInfo, $m)) {
            $currency = $this->normalizeCurrency($m['currency']);

            $h->price()
                ->currency($currency)
                ->total(PriceHelper::parse($m['price'], $currency));
        }

        $roomInfo = $this->splitCols($this->re("/No\°.+Quantity\n*(.+)\n+\s+Cancellation/s", $text), [0, 20, 80, 110, 140]);

        $r = $h->addRoom();

        $r->setType(preg_replace("/\n/", ' ', $this->re("/(.+)/s", $roomInfo[1])));

        $roomsCount = $this->re("/^\s*(\d+)/", $roomInfo[4]);

        if ($roomsCount !== null) {
            $h->booked()
                ->rooms($roomsCount);
        }

        $guestInfo = $this->re("/(\d+)[\s\n]*pers\./", $roomInfo[2]);

        if ($guestInfo !== null) {
            $h->booked()
                ->guests($guestInfo);
        }

        $kidsInfo = $this->re("/pers\.[\,\s\n]*(\d+)[\n\s]*\w+/", $roomInfo[2]);

        if ($kidsInfo !== null) {
            $h->booked()
                ->kids($kidsInfo);
        }

        $cancellation = $this->re("/(?:{$this->t('Cancellation and payment policies')}|{$this->t('Cancellation policies')})\s*for.+\s\:\n\n(.+)\n\n\s+All the dates/s", $text);

        if ($cancellation !== null) {
            $h->general()
                ->cancellation(preg_replace('/\n/', ' ', preg_replace('/\s{2,}/', ' ', $cancellation)));
        }

        $this->detectDeadLine($h);
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function normalizeCurrency($string)
    {
        $string = trim($string);

        $currences = [
            'EUR' => ['€'],
            'CAD' => ['C$'],
        ];

        foreach ($currences as $currencyCode => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $currencyCode;
                }
            }
        }

        return $string;
    }

    private function detectDeadLine(Hotel $h)
    {
        if (empty($cancellation = $h->getCancellation())) {
            return;
        }

        if (preg_match("/Before\s*\w+\s*(\d+\s*\w+\s*\d{4})\s*\,\s*([\d\:]+\s*A?P?M?)\s*\:\s*Free\s*cancellation/", $cancellation, $m)
            || preg_match("/to\s*\w+\s*(\d+\s*\w+\s*\d{4})\s*\,\s*([\d\:]+\s*A?P?M?)\s*/", $cancellation, $m)) {
            if ($m[2] == "00:00 AM") {
                $m[2] = "00:00";
            }

            $h->booked()
                ->deadline(strtotime($m[1] . $m[2]));
        }

        if (preg_match("/In\s*case\s*of\s*cancellation\,\s*no\-show\s*or\s*modification\,\s*the\s*total\s*amount\s*of\s*the\s*booking\s*is\s*not\s*refunded\./", $cancellation)) {
            $h->booked()
                ->nonRefundable();
        }
    }

    private function splitCols($text, $pos = false)
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
        $head = array_filter(array_map('trim', explode("|", preg_replace("/\s{2,}/", "|", $row))));
        $pos = [];
        $lastpos = 0;

        foreach ($head as $word) {
            $pos[] = mb_strpos($row, $word, $lastpos, 'UTF-8');
            $lastpos = mb_strpos($row, $word, $lastpos, 'UTF-8') + mb_strlen($word, 'UTF-8');
        }

        return $pos;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
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
}
