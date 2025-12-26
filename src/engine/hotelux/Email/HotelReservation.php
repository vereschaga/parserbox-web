<?php

namespace AwardWallet\Engine\hotelux\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Common\Hotel;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class HotelReservation extends \TAccountChecker
{
    public $mailFiles = "hotelux/it-771532192.eml, hotelux/it-783972563.eml, hotelux/it-785448626.eml, hotelux/it-789576098.eml, hotelux/it-797193318.eml";
    public $subjects = [
        "<HoteLux> Booking confirmation",
    ];

    public $detectLang = [
        'en' => ['Confirmation'],
    ];

    public $pdfNamePattern = ".*pdf";

    public $lang = '';

    public static $dictionary = [
        'en' => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@hoteluxapp.com') !== false) {
            foreach ($this->subjects as $subject) {
                if (stripos($headers['subject'], $subject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]hoteluxapp\.com/', $from) > 0;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = preg_replace("//", ' ', \PDF::convertToText($parser->getAttachmentBody($pdf)));

            $this->assignLang($text);

            if ($this->detectEmailFromProvider($parser->getHeader('from')) !== true) {
                return false;
            }

            if (strpos($text, $this->t('Reservation confirmation letter')) !== false
                && strpos($text, $this->t('Check-in')) !== false
                && strpos($text, $this->t('Room Nights')) !== false) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = preg_replace("//", ' ', \PDF::convertToText($parser->getAttachmentBody($pdf)));

            $this->assignLang($text);

            $this->HotelConfirmation($email, $text);
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function HotelConfirmation(Email $email, $text)
    {
        $h = $email->add()->hotel();

        $h->general()
            ->confirmation($this->re("/{$this->t('Confirmation')}\s*[\#\：]\s*([A-Z\d]+)(?:\n|\s)/u", $text), "{$this->t('Confirmation')}");

        $email->ota()
            ->confirmation($this->re("/{$this->t('Order')}\s*[\#\：]\s*([A-Z\d]+)(?:\n|\s)/u", $text), "{$this->t('Order')}");

        $hotelText = preg_replace('/Price\:/', '', $this->re("/{$this->t('Order')}\#[A-Z\d]+\n+([\s\S]*?)(?=\n\s*www\.hoteluxapp\.com|\n\s*Include).+{$this->t('Cancellation')}/su", $text));

        $spaces = strlen($this->re('/\n(.+)Total/', $hotelText));

        if ($spaces > strlen($this->re('/\n(.+)\d{4}\-\d+\-\d+/', $hotelText))) {
            $spaces = strlen($this->re('/\n(.+)\d{4}\-\d+\-\d+/', $hotelText));
        }

        $hotelArray = $this->splitCols($hotelText, [0, $spaces]);

        $h->hotel()
            ->name(preg_replace('/\n/', ' ', $this->re("/^(.+)\n+{$this->t('Addr')}/su", $hotelArray[0])))
            ->address(preg_replace('/\n/', ' ', $this->re("/{$this->t('Addr')}\s*\:\s*(.+)\n+{$this->t('Telephone')}/su", $hotelArray[0])));

        $traveller = $this->re("/{$this->t('Guests')}\s*\:\s*([[:alpha:]][-.\/\'’[:alpha:] ]*[[:alpha:]])\n/u", $hotelArray[0]);
        $h->addTraveller($traveller);

        $roomType = preg_replace('/\n/', ' ', $this->re("/{$this->t('Room type')}\s*\:\s*(.+)\n*{$this->t('Rate plan')}/su", $hotelArray[0]));

        if ($roomType !== null) {
            $h->addRoom()
                ->setType($roomType);
        }

        if (preg_match_all("/(?:\d{4}\-\d+\-\d+\s+[\d\.\,\'\-]+\s*\D{1,3}|[\d\.\,\'\-]+\s*\D{1,3}[\s\n]*\d{4}\-\d+\-\d+)/u", $hotelArray[1], $rates)) {
            foreach ($rates[0] as $rate) {
                if (preg_match("/(?<date>\d{4}\-\d+\-\d+)\s*(?<rate>[\d\.\,\'\-]+\s*\D{1,3})/", $rate, $x)) {
                    $h->getRooms()[0]
                        ->addRate($x['date'] . " : " . $x['rate']);
                } elseif (preg_match("/(?<rate>[\d\.\,\'\-]+\s*\D{1,3})[\n\s]*(?<date>\d{4}\-\d+\-\d+)/", $rate, $x)) {
                    $h->getRooms()[0]
                        ->addRate($x['date'] . " : " . $x['rate']);
                }
            }
        }

        //it-771532192.eml
        $freeNights = preg_match("/\d{4}\-\d+\-\d+\s+\-+\s*\D{1,3}/u", $hotelArray[1], $nights);

        if (isset($freeNights) && count($nights) > 0) {
            $h->booked()
                ->freeNights(count($nights));
        }

        $hotelPhone = $this->re("/{$this->t('Telephone')}\s*\:\s*([\+\-\s\d\(\)]+)\n/u", $hotelArray[0]);

        if ($hotelPhone !== null) {
            $h->hotel()
                ->phone($hotelPhone);
        }

        $h->booked()
            ->checkIn($this->normalizeDate($this->re("/{$this->t('Check-in')}\s*\:\s*(\d{4}\-\d+\-\d+\s*[\d\:]+)\s*A?P?M/u", $hotelArray[0])))
            ->checkOut($this->normalizeDate($this->re("/{$this->t('Check-out')}\s*\:\s*(\d{4}\-\d+\-\d+\s*[\d\:]+)\s*A?P?M/u", $hotelArray[0])));

        $occupancyInfo = $this->re("/{$this->t('occupancy')}\s*\:\s*(\d+)\s*\w+/", $hotelArray[0]);

        if ($occupancyInfo !== null) {
            $h->booked()
                ->guests($occupancyInfo);
        }

        $priceInfo = $this->re("/{$this->t('Total')}\s*([\d\.\,\']+\s*\D{1,3})/", $hotelArray[1]);

        if (preg_match("/^(?<price>[\d\.\,\']+)\s*(?<currency>\D{1,3})$/", $priceInfo, $m)) {
            $h->price()
                ->currency($m['currency'])
                ->total(PriceHelper::parse($m['price'], $m['currency']));

            $tax = $this->re("/{$this->t('Taxes and service fees')}\s*([\d\.\,\']+)\s*\D{1,3}/", $hotelArray[1]);

            if ($tax !== null) {
                $h->price()
                    ->tax(PriceHelper::parse($tax, $m['currency']));
            }

            $points = $this->re("/{$this->t('Received points')}\s*(\d+)\n/", $hotelArray[1]);

            if ($points !== null) {
                $h->ota()
                    ->earnedAwards($points . ' points');
            }

            $discount = $this->re("/\-([\d\.\,\']+)\s*{$m['currency']}/", $hotelArray[1]);

            if ($discount !== null) {
                $h->price()
                    ->discount(PriceHelper::parse($discount, $m['currency']));
            }
        }

        $cancellation = preg_replace('/\s{2,}/', ' ', preg_replace('/\n/', ' ', $this->re("/{$this->t('Cancellation\n*policy')}\s*\:\s*(.+?)[\n\s]*(?=Reservation confirmation letter|$)/s", $text)));

        if ($cancellation !== null) {
            $h->general()
                ->cancellation($cancellation);
        }
        $this->detectDeadLine($h, $this->re("/{$this->t('Check-in')}\s*\:\s*(\d{4})\-\d+\-\d+\s*[\d\:]+\s*A?P?M/", $hotelArray[0]));
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
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

    private function detectDeadLine(Hotel $h, $year)
    {
        if (empty($cancellation = $h->getCancellation())) {
            return;
        }

        if (preg_match("/Free cancellation before\s*(\d+\-\d+\s*[\d\:]+)/", $cancellation, $m)) {
            $h->booked()
                ->deadline($this->normalizeDate($year . ' ' . $m[1]));
        }

        if (preg_match("/^Non\-refundable/", $cancellation)) {
            $h->booked()
                ->nonRefundable();
        }
    }

    private function assignLang($text)
    {
        foreach ($this->detectLang as $lang => $dBody) {
            foreach ($dBody as $word) {
                if (stripos($text, $word) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
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

    private function normalizeDate($str)
    {
        $in = [
            //2024 10-10 10:10
            "#^(\d{4})[\s\-]*(\d+)\-(\d+)\s*([\d\:]+)$#",
        ];
        $out = [
            "$3.$2.$1 $4",
        ];
        $str = preg_replace($in, $out, $str);

        return strtotime($str);
    }
}
