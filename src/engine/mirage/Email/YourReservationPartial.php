<?php

namespace AwardWallet\Engine\mirage\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class YourReservationPartial extends \TAccountChecker
{
    public $mailFiles = "mirage/it-120796019.eml, mirage/it-137141364.eml, mirage/it-140364575.eml, mirage/it-140522276.eml, mirage/it-72881768.eml, mirage/it-72883703.eml";

    public $lang = "en";
    public static $dictionary = [
        "en" => [
            'Guest Name:' => ['Guest Name:', 'Guest name:'],
            'confNumber'  => ['Room Confirmation:', 'Confirmation Number:', 'Passkey Confirmation Number:'],
            //            'Arrival:' => '',
            'Departure:' => ['Departure:', 'Departures:'],
            //            'Privacy Policy' => '',
            'statusPhrases'  => ['Your Reservation Has Been'],
            'statusVariants' => ['Confirmed', 'Cancelled'],
        ],
    ];
    private $detectFrom = "@ee.mgmresorts.com";
    private $detectSubject = [
        // en
        "Save time for the fun stuff – check in now.",
        "Day of Departure - Checkout",
        "You’re all checked in.",
        "Your Reservation Confirmation",
        "Your room’s ready and your keys are waiting!",
    ];

    private $detectBody = [
        "en" => [
            "We’re getting ready for you", "Ready to Check Out?", "Start Your Getaway Right Away",
            ", your room is ready!", "Your Reservation Has Been Confirmed",
            "Your Reservation Has Been Cancelled",
            "Your Reservation Has Been Updated",
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->parseHotel($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers["from"]) || empty($headers["subject"])) {
            return false;
        }

        if (strpos($headers["from"], $this->detectFrom) === false && strpos($headers['subject'], 'MGM Resorts') === false) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers["subject"], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href,'.mgmresorts.com')]")->length === 0
            && $this->http->XPath->query("//*[contains(normalize-space(),'MGM Resorts International®. All rights reserved') or contains(normalize-space(),'CityCenter Land, LLC. All rights reserved')]")->length === 0
        ) {
            return false;
        }

        foreach ($this->detectBody as $detectBody) {
            if ($this->http->XPath->query("//text()[" . $this->contains($detectBody) . "]")->length > 0) {
                return true;
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function parseHotel(Email $email): void
    {
        $h = $email->add()->hotel();

        // General
        $status = $this->http->FindSingleNode("//text()[{$this->contains($this->t('statusPhrases'))}]", null, true, "/{$this->opt($this->t('statusPhrases'))}[:\s]+({$this->opt($this->t('statusVariants'))})(?:\s*[,.;!?]|$)/i");

        if ($status) {
            $h->general()->status($status);
        }
        $confirmation = $this->http->FindSingleNode("//tr[not(.//tr) and {$this->starts($this->t('confNumber'))}]")
            ?? $this->http->FindSingleNode("//text()[{$this->starts('Ack#')}]") // it-137141364.eml
        ;

        if (preg_match("/^(.{2,}?)\s*:\s*([-A-Z\d]{5,})\s*$/", $confirmation, $m)
            || preg_match("/^({$this->opt('Ack#')})[:\s]+([-A-Z\d]{5,})\s*$/", $confirmation, $m)
        ) {
            $h->general()->confirmation($m[2], $m[1]);
        }

        if (empty($confirmation)) {
            $confirmation = $this->http->FindSingleNode("//text()[{$this->eq($this->t("Your Cancellation Confirmation Number is:"))}]/following::text()[normalize-space()][1]");

            if (!empty($confirmation)) {
                $h->general()->confirmation($confirmation);
            }
        }

        if (empty($confirmation)) {
            // no confirmation
            if (
                empty($this->http->FindSingleNode("(//*[{$this->starts($this->t('confNumber'))}])[1]"))
                && !empty($this->http->FindSingleNode("(//*[{$this->eq('YOUR RESERVATION')}])[1]"))
                && empty($this->http->FindSingleNode("//*[{$this->eq('YOUR RESERVATION')}]/following::text()[{$this->contains(['#', 'Conf', 'CONF'])}]"))
            ) {
                $h->general()->noConfirmation();
            }
        }
        $guestName = $this->http->FindSingleNode("//tr[not(.//tr) and {$this->starts($this->t('Guest Name:'))}]", null, true, "/:\s*(.+?)\s*$/");

        if (!preg_match("/Please make/i", $guestName)
            && preg_match("/^[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]$/u", $guestName)
        ) {
            $h->general()->traveller($guestName);
        }

        // Hotel
        $hotel = implode("\n", $this->http->FindNodes("//a[" . $this->contains($this->t("Privacy Policy")) . "]/following::text()[normalize-space()][1]/ancestor::td[1][not(" . $this->contains($this->t("Privacy Policy.")) . ")]//text()[normalize-space()]"));

        if (preg_match("/^\s*(.+)\n([\s\S]+\d[\s\S]+)/", $hotel, $m)) {
            $h->hotel()
                ->name($m[1])
                ->address(preg_replace('/\s*\n\s*/', ', ', trim($m[2])))
            ;
        }

        if ($this->http->FindSingleNode("(//text()[{$this->contains(["Your Reservation Has Been Cancelled", "Your Cancellation Confirmation Number is"])}])[1]")) {
            $h->general()
                ->status('Cancelled')
                ->cancelled();

            return;
        }

        // Booked
        $checkIn = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Arrival:'))}]", null, true, "/:\s*(.*\d.*?)\s*$/")
            ?? $this->http->FindSingleNode("//text()[{$this->starts($this->t('Arrival:'))}]/following::text()[normalize-space()][1]", null, true, "/^.*\d.*$/")
        ;
        $checkOut = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Departure:'))}]", null, true, "/:\s*(.*\d.*?)\s*$/")
            ?? $this->http->FindSingleNode("//text()[{$this->starts($this->t('Departure:'))}]/following::text()[normalize-space()][1]", null, true, "/^.*\d.*$/")
        ;
        $h->booked()
            ->checkIn($this->normalizeDate($checkIn))
            ->checkOut($this->normalizeDate($checkOut))
        ;
        $time = $this->http->FindSingleNode("//text()[" . $this->contains($this->t("check-out time for")) . "]",
            null, true, "/check-out time for .+ is (\d{1,2}(?:\:\d{2}){1,2} [AP]M)\s*(?:\.|$)/");

        if (!empty($time) && !empty($h->getCheckOutDate())) {
            $h->booked()->checkOut(strtotime($time, $h->getCheckOutDate()));
        }

        $guestCount = $this->http->FindSingleNode("//tr[not(.//tr) and {$this->starts($this->t('No. of Guests:'))}]", null, true, "/:\s*(\d{1,3})\s*$/");
        $h->booked()->guests($guestCount, false, true);

        $roomType = $this->http->FindSingleNode("//tr[not(.//tr) and {$this->starts($this->t('Room type:'))}]", null, true, "/:\s*(.+?)\s*$/");

        $rates = [];
        $rateTableText = $this->htmlToText($this->http->FindHTMLByXpath("//tr[not(.//tr) and descendant::text()[{$this->starts($this->t('Date'))}] and {$this->contains($this->t('Guest(s)'))} and {$this->contains($this->t('Status'))} and {$this->contains($this->t('Rate'))}]"));
        $rateRows = preg_split("/[ ]*\n+[ ]*/", $rateTableText);

        foreach ($rateRows as $i => $rRow) {
            if ($i === 0 || preg_match("/^[ ]*({$this->opt($this->t('Date'))}|{$this->opt('Ack#')})/", $rRow)) {
                continue;
            }

            if (preg_match("/^.*\d{4}.*[ ]+Confirmed[ ]+(\d[,.\'\d ]*)$/i", $rRow, $m)) {
                // Oct 19, 2021 1 Confirmed 189.00
                $rates[] = $m[1];
            } else {
                $rates = [];

                break;
            }
        }

        if ($roomType || count($rates) > 0) {
            $room = $h->addRoom();
            $room->setType($roomType, false, true);

            if (count($rates) > 0) {
                $room->setRates($rates);
            }
        }
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
            //            "#^[^\s\d]+,\s*([^\s\d]+)\s*(\d{1,2})[a-z]{2}?,\s*(\d{4})\s*$#iu",// Friday, February 9th, 2018
        ];
        $out = [
            //            "$2 $1 $3",
        ];
        $str = preg_replace($in, $out, $str);
//        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
//            if ($en = MonthTranslate::translate($m[1], $this->lang))
//                $str = str_replace($m[1], $en, $str);
//        }

        return strtotime($str);
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'starts-with(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'contains(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field));
    }

    private function htmlToText(?string $s, bool $brConvert = true): string
    {
        if (!is_string($s) || $s === '') {
            return '';
        }
        $s = str_replace("\r", '', $s);
        $s = preg_replace('/<!--.*?-->/s', '', $s); // comments

        if ($brConvert) {
            $s = preg_replace('/\s+/', ' ', $s);
            $s = preg_replace('/<[Bb][Rr]\b.*?\/?>/', "\n", $s); // only <br> tags
        }
        $s = preg_replace('/<[A-z][A-z\d:]*\b.*?\/?>/', '', $s); // opening tags
        $s = preg_replace('/<\/[A-z][A-z\d:]*\b[ ]*>/', '', $s); // closing tags
        $s = html_entity_decode($s);
        $s = str_replace(chr(194) . chr(160), ' ', $s); // NBSP to SPACE

        return trim($s);
    }
}
