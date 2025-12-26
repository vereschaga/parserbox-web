<?php

namespace AwardWallet\Engine\thon\Email;

use AwardWallet\Schema\Parser\Email\Email;

class Confirmation extends \TAccountChecker
{
    public $mailFiles = "";

    public $reFrom = "@thonhotels.no";

    public $reBody = [
        'en'  => ['Thon', 'We have the pleasure of confirming'],
        'en2' => ['Four Seasons', 'Thank you for choosing Four Seasons Hotel'],
    ];

    public $reSubject = [
        'Hotel confirmation',
    ];

    public $lang = '';

    /** @var \HttpBrowser */
    public $pdf;

    public static $dict = [
        'en' => [
        ],
    ];

    private static $provs = [
        'fseasons' => [
            'Thank you for choosing Four Seasons Hotel',
        ],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName(".*pdf");

        if (isset($pdfs) && count($pdfs) > 0) {
            $this->pdf = clone $this->http;

            foreach ($pdfs as $pdf) {
                if (($text = \PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_SIMPLE)) !== null) {
                    $text = text($text);
                    $this->AssignLang($text);
                    $html = \PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_COMPLEX);
                    $this->pdf->SetBody($html);
                    $this->parseEmail($text, $email);
                } else {
                    return null;
                }
            }
        } else {
            return null;
        }

        if (!empty($text) && ($prov = $this->getProviderCode($text))) {
            $email->setProviderCode($prov);
        }

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdf = $parser->searchAttachmentByName('.*pdf');

        if (isset($pdf[0])) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf[0]));

            if (stripos($text, 'thonhotels') !== false || stripos($text, 'Thon Hotel') !== false || stripos($text, 'Four Seasons') !== false) {
                return $this->AssignLang($text);
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($this->reSubject)) {
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
        return array_keys(self::$provs);
    }

    public function findСutSection($input, $searchStart, $searchFinish)
    {
        $inputResult = null;
        $left = mb_strstr($input, $searchStart);

        if (is_array($searchFinish)) {
            $i = 0;

            while (empty($inputResult)) {
                if (isset($searchFinish[$i])) {
                    $inputResult = mb_strstr($left, $searchFinish[$i], true);
                } else {
                    return false;
                }
                $i++;
            }
        } elseif (!empty($searchFinish)) {
            $inputResult = mb_strstr($left, $searchFinish, true);
        } else {
            $inputResult = $left;
        }

        return mb_substr($inputResult, mb_strlen($searchStart));
    }

    private function parseEmail($plainText, Email $email)
    {
        $h = $email->add()->hotel();

        $text = $this->findСutSection($plainText, 'Reservation Details', 'Hotel Details');

        if (empty($text)) {
            $text = $this->findСutSection($plainText, 'ROOM RESERVATION DETAILS', 'Thank you for your reservation');
        }

        if ($conf = $this->re("#Confirmation\s+Number:?\s*([A-Z\d]+)#", $text)) {
            $h->general()
                ->confirmation($conf);
        }

        $pax = $this->pdf->FindSingleNode("//text()[starts-with(.,'Dear')]", null, false, "#Dear\s+(.+)#");

        if ($pax) {
            $h->addTraveller($pax);
        }

        $checkinDate = strtotime($this->normalizeDate($this->re("#Arrival\s+Date:?\s*(.+)#i", $text)));
        $checkoutDate = strtotime($this->normalizeDate($this->re("#Departure\s+Date:?\s*(.+)#i", $text)));

        if (($checkinTime = $this->re('/Check-in Time\s*\:?[ ]*(\d{1,2}:\d{2}[ ]*[pa]m)/i', $text)) && $checkinDate) {
            $h->booked()
                ->checkIn(strtotime($checkinTime, $checkinDate));
        }

        if (($checkoutTime = $this->re('/Check-out Time\s*\:?[ ]*(\d{1,2}:\d{2}[ ]*(?:[pa]m|noon))/i', $text)) && $checkoutDate) {
            $h->booked()
                ->checkOut(strtotime($checkoutTime, $checkoutDate));
        }

        if ($rooms = $this->re("#Number\s+of\s+Rooms:?\s*(\d+)#i", $text)) {
            $h->booked()
                ->rooms($rooms);
        }

        if ($guestsCount = $this->re("#Number\s+of\s+(?:Adults|Guests):?\s*(\d+)#i", $text)) {
            $h->booked()
                ->guests($guestsCount);
        }

        if ($hName = $this->pdf->FindSingleNode("(//text()[starts-with(.,'Tel')]/preceding::text()[contains(.,'Hotel')])[1]/ancestor::p[1]")) {
            $h->hotel()
                ->name($hName);
        }

        if ($address = implode(" ", $this->pdf->FindNodes("(//text()[starts-with(.,'Tel:')]/preceding::text()[contains(.,'Hotel')])[1]/ancestor::p[1]/following::p[string-length(normalize-space(.))>2][1]//text()"))) {
            $h->hotel()
                ->address($address);
        }

        if ($phone = $this->pdf->FindSingleNode("//text()[starts-with(.,'Tel:')]", null, false, "#:\s*(.+)#")) {
            $h->hotel()
                ->phone($phone);
        }

        if ($fax = $this->pdf->FindSingleNode("//text()[starts-with(.,'Fax:')]", null, false, "#:\s*(.+)#")) {
            $h->hotel()
                ->fax($fax);
        }

        $r = $h->addRoom();

        if ($rate = $this->re("#Room\s+Rate:?\s*(.+)#i", $text)) {
            $r->setRate($rate);
        }

        if ($type = $this->re("#(?:Room\s+Type|Accommodation):?\s*(.+)#i", $text)) {
            $r->setType($type);
        }

        $tot = $this->re("#Total\s+Amount:?\s*(.+)#i", $text);

        if (strpos($tot, ',') !== false && strpos($tot, '.') !== false) {
            if (strpos($tot, ',') < strpos($tot, '.')) {
                $tot = str_replace(',', '', $tot);
            } else {
                $tot = str_replace('.', '', $tot);
                $tot = str_replace(',', '.', $tot);
            }
        }

        $h->price()->total(str_replace(",", ".", str_replace(' ', '', $tot)), true, true);

        if ($cancel = $this->re('/Cancellation Notice\s+(.+)Check-in Time/is', $text)) {
            $h->general()
                ->cancellation($cancel);
        }

        if (!empty($h->getCancellation()) && preg_match('/Our cancellation policy is (\d{1,2} hours) prior to the day of arrival, before (\d{1,2}[ap]m), local time/i', $h->getCancellation(), $m)) {
            $h->booked()->deadlineRelative($m[1], $m[2]);
        }
    }

    private function getProviderCode(string $text): ?string
    {
        foreach (self::$provs as $prov => $detects) {
            foreach ($detects as $detect) {
                if (false !== stripos($text, $detect)) {
                    return $prov;
                }
            }
        }

        return null;
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
                if (stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false) {
                    $this->lang = substr($lang, 0, 2);

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

    private function normalizeDate($date)
    {
        $in = [
            //Tuesday, March 28, 2017
            '#^\w+,\s+(\w+)\s+(\d+),\s+(\d+)$#u',
            //30.March 2017
            '#^(\d+)\.\s*(\w+)\s+(\d+)$#u',
        ];
        $out = [
            '$2 $1 $3',
            '$1 $2 $3',
        ];

        return preg_replace($in, $out, $date);
    }
}
