<?php

namespace AwardWallet\Engine\venetian\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class UpcomingStay extends \TAccountChecker
{
    public $mailFiles = "venetian/it-121892651.eml, venetian/it-135931678.eml, venetian/it-808504921.eml, venetian/it-812075031.eml";
    public $subjects = [
        'Your Upcoming Stay',
        'Your Suite Upgrade',
        'Your Reservation Confirmation #',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'CONFIRMATION #'               => ['CONFIRMATION #', 'RESERVATION ID', 'Confirmation Number:', 'CONFIRMATION NUMBER:'],
            'CHECK-IN'                     => ['CHECK-IN', 'ARRIVAL'],
            'CHECK-OUT'                    => ['CHECK-OUT', 'DEPARTURE'],
            'Suite:'                       => ['Suite:', 'SUITE'],
            'Reservations'                 => ['Reservations', 'RESERVATIONS'],
            'Total billed to suite:'       => ['Total billed to suite:', 'Room & Tax Total:', 'Stay Total:'],
            'Tax Total:'                   => ['Tax Total:', 'Resort Fee Total:'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@email.venetian.com') !== false) {
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
        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'The Venetian Resort')]")->length > 0) {
            return $this->http->XPath->query("//text()[contains(normalize-space(), 'TOWER') or contains(normalize-space(), 'HOTEL')]")->length > 0
                && $this->http->XPath->query("//text()[contains(normalize-space(), 'SUITE')]")->length > 0;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]email\.venetian\.com$/', $from) > 0;
    }

    public function ParseHotel(Email $email)
    {
        $h = $email->add()->hotel();

        $confirmation = $this->http->FindSingleNode("//text()[{$this->contains($this->t('CONFIRMATION #'))}]/following::text()[normalize-space()][1]", null, true, "/^([A-Z\d]+)$/");

        if (empty($confirmation)) {
            $confirmation = $this->http->FindSingleNode("//text()[{$this->starts($this->t('CONFIRMATION #'))}]", null, true, "/\s([A-Z\d]+)$/");
        }
        $h->general()
            ->confirmation($confirmation)
            ->traveller($this->http->FindSingleNode("//text()[contains(normalize-space(), 'GUEST NAME')]/following::text()[normalize-space()][1]"), true);

        $hotelNameText = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'All Right') or contains(normalize-space(), 'are used under license') or contains(normalize-space(), 'reserves all rights')]/ancestor::td[1]");

        if (preg_match("/^\D*\d{4}\s*\D+(?:Rights Reserved\.|under license\.?\s*)\s*(?:.+? \s+)?(.+)$/", $hotelNameText, $m)
            || preg_match("/^\D*\d{4}\s*.+(?:Rights Reserved\.|under license\.?\s*)\s*The Venetian Resort (.*\d.+)$/", $hotelNameText, $m)
        ) {
            $h->hotel()
                ->name($this->http->FindSingleNode("//text()[" . $this->starts(['We are excited to welcome you to', 'Thank you for choosing', 'Welcome to']) . "][1]/ancestor::*[self::th or self::td][1]",
                    null, true, "/(?:We are excited to welcome you to|Thank you for choosing|Welcome to) (.+?)\./"))
                ->address($m[1])
                ->phone($this->http->FindSingleNode("//text()[{$this->eq($this->t('Reservations'))}]/following::text()[string-length()>3][1]"));
        }

        $h->booked()
            ->checkIn($this->normalizeDate($this->http->FindSingleNode("//text()[{$this->eq($this->t('CHECK-IN'))}]/following::text()[normalize-space()][1]")))
            ->checkOut($this->normalizeDate($this->http->FindSingleNode("//text()[{$this->eq($this->t('CHECK-OUT'))}]/following::text()[normalize-space()][1]")));

        $guests = $this->http->FindSingleNode("//text()[contains(normalize-space(.), '# OF GUESTS')]/following::text()[normalize-space()][1]", null, true, "/^(\d+)/");

        if (!empty($guests)) {
            $h->booked()
                ->guests($guests);
        }

        $roomType = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Suite:'))}]/following::text()[normalize-space()][1]");
        $rateArray = array_filter($this->http->FindNodes("//text()[normalize-space()='Hotel Rates:']/following::span[normalize-space()][1]/descendant::text()[contains(normalize-space(), 'Confirmed')]", null, "/Confirmed\s*([\d\,\.]+)/"));

        if (empty($rateArray)) {
            $rateArray = array_filter($this->http->FindNodes("//text()[normalize-space() = 'Daily Rate Details']/following::tr[not(.//tr)][position() < 5][td[1][normalize-space() = 'SUNDAY']]/following-sibling::*/*[normalize-space()]",
                null, "/.*\d.*/"));
        }

        if (!empty($roomType) || count($rateArray) > 0) {
            $room = $h->addRoom();

            if (!empty($roomType)) {
                $room->setType($roomType);
            }

            if (count($rateArray) > 0) {
                $room->setRates($rateArray);
            }
        }

        $this->detectDeadLine($h);

        $total = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Total billed to suite:'))}]/ancestor::tr[1]/descendant::td[2]", null, true, "/^\D?([\d\,\.]+)$/");

        if (!empty($total)) {
            $h->price()
                ->total(PriceHelper::cost($total, ',', '.'));

            $currency = $this->http->FindSingleNode("//text()[normalize-space()='Charges']/ancestor::tr[1]/following::tr[1]/descendant::td[normalize-space()][3]", null, true, "/^(\D+)[\d\.\,]+$/");

            if (empty($currency)) {
                $currency = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Total billed to suite:'))}]/ancestor::tr[1]/descendant::td[2]", null, true, "/^(\D)[\d\,\.]+$/");
            }

            if (!empty($currency)) {
                $h->price()
                    ->currency($currency);
            }

            $cost = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Room Total:'))}]/ancestor::tr[1]/descendant::td[2]", null, true, "/^\D?([\d\,\.]+)$/");

            if (!empty($cost)) {
                $h->price()
                    ->cost(PriceHelper::cost($cost, ',', '.'));
            }

            foreach ($this->t('Tax Total:') as $name) {
                $tax = $this->http->FindSingleNode("//text()[{$this->eq($name)}]/ancestor::tr[1]/descendant::td[2]",
                    null, true, "/^\D?(\d[\d\,\.]+)$/");

                if (!empty($tax)) {
                    $h->price()
                        ->fee(trim($name, ':'), $tax);
                }
            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->ParseHotel($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
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

    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h)
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        if (preg_match('/The cancellation policy for your reservation is (\d+\s*\w+) prior to arrival/', $cancellationText, $m)) {
            $h->booked()->deadlineRelative($m[1]);
        }
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) {
            return "normalize-space(.)=\"{$s}\"";
        }, $field)) . ')';
    }

    private function normalizeDate($date)
    {
        //$this->logger->debug($date);
        $in = [
            // Thursday, May 26th, 2022 at 3 p.m.
            "/^\w+\,\s*(\w+)\s*(\d+)\w*\,\s*(\d{4})\s*at\s*(\d+)\s*(a?p?)\.(m)\.$/iu",
            // Nov 8, 2021 3pm
            "/^(\w+)\s*(\d+)\,\s*(\d{4})\s*(\d+a?p?m)$/u",
        ];
        $out = [
            "$2 $1 $3, $4$5$6",
            "$2 $1 $3, $4",
        ];
        $date = preg_replace($in, $out, $date);

        if (preg_match("/\d+\s+([[:alpha:]]+)\s+\d{4}/u", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        return strtotime($date);
    }
}
