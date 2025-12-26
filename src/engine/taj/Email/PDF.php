<?php

namespace AwardWallet\Engine\taj\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Common\Hotel;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class PDF extends \TAccountChecker
{
    public $mailFiles = "taj/it-403090321.eml";

    public $reFrom = "booksjc.London@tajhotels.com";
    public $reFromH = "A Taj Hotel";
    public $reBody = [
        'en' => [
            ['Thank you for choosing St. James’ Court, A Taj Hotel', 'www.stjamescourthotel.co.uk'],
            ['Thank you for choosing Wynn', 'Prior to your stay'],
            ['Thank you for choosing Taj', 'Check In Date'],
        ],
    ];
    public $reSubject = [
        'Reservations - St James Court',
    ];
    public $lang = '';
    public $pdf;
    public static $dict = [
        'en' => [
            'Arrival Date'     => ['Check In Date', 'Arrival Date'],
            'Departure Date'   => ['Departure Date', 'Check Out Date'],
            'Number of Guests' => ['Number of Guests', 'No. of Guests'],
            'Room Rate'        => ['Rate Applicable per day', 'Room Rate'],
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName(".*pdf");

        if (isset($pdfs) && count($pdfs) > 0) {
            $this->pdf = clone $this->http;
            $html = '';

            foreach ($pdfs as $pdf) {
                if (($html .= \PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_SIMPLE)) !== null) {
                } else {
                    return null;
                }
            }
            $this->pdf->SetBody($html);
        } else {
            return null;
        }

        if (stripos($html, 'A Taj Hotel') !== false) {
            $email->setProviderCode('taj');
        } elseif (stripos($html, 'choosing Wynn') !== false) {
            $email->setProviderCode('wynnlv');
        }
        $this->AssignLang($html);
        $this->parseEmail($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $pdf = $parser->searchAttachmentByName('.*pdf');

        if (isset($pdf[0])) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf[0]));

            return $this->AssignLang($text);
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], $this->reFromH) !== false && isset($this->reSubject)) {
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
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    public static function getEmailProviders()
    {
        return ['taj', 'wynnlv'];
    }

    public function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = \AwardWallet\Engine\MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function parseEmail(Email $email)
    {
        $h = $email->add()->hotel();

        $confirmation = $this->nextText($this->t('Confirmation Number'));

        if (empty($confirmation)) {
            $confirmation = $this->nextText($this->t('Confirmation'));
        }

        $h->general()
            ->confirmation($confirmation);

        $cancellation = $this->nextText('Cancellations Policy');
        $node = '';

        if (empty($cancellation)) {
            $i = 0;

            while (strpos($node, 'Check In Time') === false && $i < 10) {
                $cancellation .= ' ' . $node;
                $i++;
                $node = $this->nextText('Cancellation Policy', $i);
            }
        }

        $price = $this->pdf->FindSingleNode("//text()[starts-with(normalize-space(), 'Total Price')]/following::text()[normalize-space()][1]");

        if (preg_match("/^(?<currency>\D{1,3})(?<total>[\d\.\,]+)/", $price, $m)) {
            $currency = $this->normalizeCurrency($this->normalizeCurrency($m['currency']));
            $h->price()
                ->total(PriceHelper::parse($m['total'], $currency))
                ->currency($currency);
        }

        $h->general()
            ->cancellation(trim($cancellation, '• '));

        $node = $this->nextText('Number of Guests');

        if (preg_match("#(\d+) Adult\(s\) (\d+) Child\(ren\)#", $node, $m)) {
            $h->booked()
                ->guests($m[1])
                ->kids($m[2]);
        }

        //$inDate =
        $h->booked()
            ->checkIn(strtotime($this->normalizeDate($this->nextText('Arrival Date'))))
            ->checkOut(strtotime($this->normalizeDate($this->nextText('Departure Date'))));

        $inTime = $this->nextText('Check In Time');

        if (!empty($inTime)) {
            $h->booked()
                ->checkIn(strtotime($inTime, $h->getCheckInDate()));
        }

        $outTime = $this->nextText('Check Out Time');

        if (!empty($outTime)) {
            $h->booked()
                ->checkOut(strtotime($outTime, $h->getCheckOutDate()));
        }

        $traveller = $this->nextText('Guest:');

        if (empty($traveller)) {
            $traveller = str_replace(['Ms.', 'Mrs.', 'Mr.', 'Ms'], '', $this->pdf->FindSingleNode("//text()[starts-with(normalize-space(.),'Dear')]", null, true, "#Dear\s+(.+?)\s*,#"));
        }

        $h->general()
            ->traveller($traveller);

        $hotelName = $this->pdf->FindSingleNode("//text()[starts-with(normalize-space(.),'Thank you for choosing')]", null, true, "#Thank\s+you\s+for\s+choosing\s+(.+?)\s+for\s+your\s+upcoming#");

        if (empty($hotelName)) {
            $hotelName = $this->pdf->FindSingleNode("//text()[starts-with(normalize-space(.),'Kind Regards')]/following::text()[normalize-space()][1]");
        }
        $h->hotel()
            ->name($hotelName)
            ->noAddress();

        $roomTypeDescription = '';
        $i = 2;
        $node = $this->nextText('Room Type', $i);

        while (strpos($node, 'Number of Guests') === false && $i < 10) {
            $roomTypeDescription .= ' ' . $node;
            $i++;
            $node = $this->nextText('Room Type', $i);
        }
        $roomTypeDescription = trim($roomTypeDescription);

        $rate = $this->nextText('Room Rate');

        if (empty($rate) && !empty($this->nextText('Nightly Rate'))) {
            $roomTypeDescription = '';

            $i = 0;
            $node = $this->nextText('Room Type', $i);

            while (strpos($node, 'OUR POLICIES') === false && $i < 10) {
                $rate .= ' ' . $node;
                $i++;
                $node = $this->nextText('Nightly Rate', $i);
            }
        }
        $roomType = $this->nextText('Room Type');

        if (stripos($roomTypeDescription, 'Rate Details') !== false) {
            $roomTypeDescription = '';
        }

        if (!empty($roomTypeDescription) || !empty($rate) || !empty($roomType)) {
            $room = $h->addRoom();

            if (!empty($roomType)) {
                $room->setType($roomType);
            }

            if (!empty(preg_replace("/^\s+$/su", "", $rate))) {
                $room->setRate($rate);
            }

            if (!empty($roomTypeDescription)) {
                $room->setDescription($roomTypeDescription);
            }
        }

        $rooms = $this->nextText('No. of Rooms');

        if (!empty($rooms)) {
            $h->booked()
                ->rooms($rooms);
        }

        $this->detectDeadLine($h);
    }

    private function nextText($field, $n = 1)
    {
        return $this->pdf->FindSingleNode("//text()[{$this->starts($this->t($field))}]/following::text()[string-length(normalize-space(.))>0][{$n}]");
    }

    private function normalizeDate($date)
    {
        $in = [
            '#(\d+).*?\s+(\w+)\s+(\d+)#u',
        ];
        $out = [
            '$1 $2 $3',
        ];
        $str = $this->dateStringToEnglish(preg_replace($in, $out, $date));

        return $str;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function AssignLang($body)
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                foreach ($reBody as $words) {
                    if (stripos($body, $words[0]) !== false && stripos($body, $words[1]) !== false) {
                        $this->lang = $lang;

                        return true;
                    }
                }
            }
        }

        return false;
    }

    private function detectDeadLine(Hotel $h)
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        if (preg_match("/The deposit is fully refundable upon notice of cancellation at least (\d+\s*hours)/", $cancellationText, $m)) {
            $h->booked()
                ->deadlineRelative($m[1]);
        }
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "starts-with(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function normalizeCurrency($string)
    {
        $string = trim($string);
        $currencies = [
            'GBP' => ['£'],
            'AUD' => ['A$'],
            'EUR' => ['€', 'Euro'],
            'USD' => ['US Dollar'],
        ];

        foreach ($currencies as $code => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $code;
                }
            }
        }

        return $string;
    }
}
