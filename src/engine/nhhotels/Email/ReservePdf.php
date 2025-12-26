<?php

namespace AwardWallet\Engine\nhhotels\Email;

use AwardWallet\Schema\Parser\Email\Email;

class ReservePdf extends \TAccountChecker
{
    public $mailFiles = "nhhotels/it-48470942.eml";

    private static $detectors = [
        'en' => ["Your reservation is confirmed.", "Your room is guaranteed until on the day of arrival."],
    ];
    private static $dictionary = [
        'en' => [
            "Your reservation is confirmed."                       => ["Your reservation is confirmed."],
            "Your room is guaranteed until on the day of arrival." => ["Your room is guaranteed until on the day of arrival."],
            "Reservation number:"                                  => ["Reservation number:"],
            "Phone:"                                               => ["Phone:"],
            "Check-in:"                                            => ["Check-in:"],
            "Check-Out:"                                           => ["Check-Out:"],
            "Occupancy:"                                           => ["Occupancy:"],
            "After"                                                => ["After"],
            "Before"                                               => ["Before"],
            "Room"                                                 => ["Room"],
            "Rooms"                                                => ["Rooms"],
            "Adults"                                               => ["Adults"],
            "Child"                                                => ["Child"],
            "Total price"                                          => ["Total price"],
            "VAT"                                                  => ["VAT"],
            "Guest details"                                        => ["Guest details"],
            "Room Preferences"                                     => ["Room Preferences"],
        ],
    ];
    private $from = "@nh-hotels.com";

    private $body = "nhcollectionroyalsmartsuites@nh-hotels.com";

    private $lang;

    private $subject = ['RV: Reserva'];

    private $pdfNamePattern = ".*pdf";

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

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
        $pdf = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (isset($pdf[0])) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf[0]));

            if (stripos($text, $this->body) === false) {
                return false;
            }
        }

        if ($this->detectBody($parser)) {
            return $this->assignLang();
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->debug("Can't determine a language");

            return $email;
        }

        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (!empty($pdfs) && count($pdfs) > 0) {
            foreach ($pdfs as $pdf) {
                if (($html = \PDF::convertToHtml($parser->getAttachmentBody($pdf), 2)) !== null) {
                    $this->parseEmailPdf($email, $html);
                }
            }
        }

        return $email;
    }

    private function detectBody(\PlancakeEmailParser $parser)
    {
        $pdf = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (isset($pdf[0])) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf[0]));

            foreach (self::$detectors as $lang => $phrases) {
                foreach ($phrases as $phrase) {
                    if (!empty(stripos($text, $phrase)) && !empty(stripos($text,
                            $phrase))) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    private function assignLang()
    {
        foreach (self::$dictionary as $lang => $words) {
            if (isset($words["Your reservation is confirmed."], $words["Your room is guaranteed until on the day of arrival."])) {
                if ($this->http->XPath->query("//*[{$this->contains($words["Your reservation is confirmed."])}]")->length > 0
                    && $this->http->XPath->query("//*[{$this->contains($words["Your room is guaranteed until on the day of arrival."])}]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
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

    private function parseEmailPdf(Email $email, $html)
    {
        $httpComplex = clone $this->http;
        $httpComplex->SetBody($html);

        $r = $email->add()->hotel();

        $rn = $httpComplex->FindSingleNode("//*[" . $this->exact($this->t('Reservation number:')) . "]/following-sibling::p[1]");

        if (!empty($rn)) {
            $r->general()
                ->confirmation($rn, 'Reservation number');
        }

        $dateIn = $httpComplex->FindSingleNode("//*[" . $this->exact($this->t('Check-in:')) . "]/following-sibling::p[1]");

        if (preg_match('/(\d{1,2}[\/-]\d{1,2}[\/-]\d{2,4})\.\s' . $this->opt($this->t('After')) . '\s(\d{1,2}:\d{1,2}\s[ap]m)/',
            $dateIn, $m)) {
            $r->booked()
                ->checkIn(strtotime(str_replace('/', '-', $m[1]) . " " . $m[2]));
        }

        $dateOut = $httpComplex->FindSingleNode("//*[" . $this->exact($this->t('Check-Out:')) . "]/following-sibling::p[1]");

        if (preg_match('/(\d{1,2}[\/-]\d{1,2}[\/-]\d{2,4})\.\s' . $this->opt($this->t('Before')) . '\s(\d{1,2}:\d{1,2}\s[ap]m)/',
            $dateOut, $m)) {
            $r->booked()->checkOut(strtotime(str_replace('/', '-', $m[1]) . " " . $m[2]));
        }

        $child = $httpComplex->FindSingleNode("//*[" . $this->exact($this->t('Occupancy:')) . "]/following-sibling::p[1]",
            null, true, '/(\d+)\s' . $this->opt($this->t("Child")) . '/');

        if (!empty($child)) {
            $r->booked()->kids($child);
        }

        $adults = $httpComplex->FindSingleNode("//*[" . $this->exact($this->t('Occupancy:')) . "]/following-sibling::p[1]",
            null, true, '/(\d+)\s' . $this->opt($this->t("Adults")) . '/');

        if (!empty($adults)) {
            $r->booked()->guests($adults);
            $guest = $httpComplex->FindNodes("//*[" . $this->exact($this->t('Guest details')) . "]/following-sibling::p[position() <=" . $adults . "]");

            if (!empty($guest)) {
                $r->general()->travellers($guest, true);
            }
        }

        $room = $httpComplex->FindSingleNode("//*[" . $this->exact($this->t('Occupancy:')) . "]/following-sibling::p[1]",
            null, true, '/(\d+)\s' . $this->opt($this->t("Room")) . '/');

        if (!empty($room)) {
            $r->booked()->rooms($room);
        }

        $totalPrice = $httpComplex->FindSingleNode("//*[" . $this->exact($this->t('Total price')) . "]/following-sibling::p[1]");

        if (!empty($totalPrice)) {
            if (preg_match('/(\d+[.\d]+)\s(.+)/', $totalPrice, $m)) {
                $r->price()
                    ->total($m[1])
                    ->currency($m[2]);
            }
        }

        $cost = $httpComplex->FindSingleNode("//*[" . $this->exact($this->t('Room')) . "]/following-sibling::p[1]",
            null, true, '/(\d+[.\d]+)/');

        if (!empty($cost)) {
            $r->price()
                ->cost($cost);
        }

        $vat = $httpComplex->FindSingleNode("//*[" . $this->exact($this->t('VAT')) . "]/following-sibling::p[1]", null,
            true, '/(\d+[.\d]+)/');

        if (!empty($vat)) {
            $r->price()
                ->tax($vat);
        }

        $rooms = $httpComplex->FindSingleNode("//*[" . $this->exact($this->t('Rooms')) . "]/following-sibling::p[1]");

        if (!empty($rooms)) {
            $r->addRoom()
                ->setType($rooms);
        }

        $preferences = $httpComplex->FindSingleNode("//*[" . $this->exact($this->t('Room Preferences')) . "]/following-sibling::p[1]");

        if (!empty($preferences)) {
            $r->addRoom()
                ->setDescription($preferences);
        }

        $name = $httpComplex->FindSingleNode("//text()[" . $this->starts('NH') . "]");

        if (!empty($name)) {
            $r->hotel()
                ->name($name);
        }

        $address = $httpComplex->FindSingleNode("//*[" . $this->starts('NH') . "]/following-sibling::p[1]");

        if (!empty($address)) {
            if (preg_match('/(?:(.+)(\+[\d()\s]+)|(.+))/', $address, $m)) {
                $r->hotel()->address($m[1]);

                if (!empty($m[2])) {
                    $r->hotel()->phone($m[2]);
                }
            }
        }

        return $email;
    }

    private function exact($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
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
}
