<?php

namespace AwardWallet\Engine\slh\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class BookingConfirmation extends \TAccountChecker
{
    public $mailFiles = "slh/it-105659034.eml, slh/it-175601055.eml, slh/it-176036478.eml";
    public $subjects = [
        '/SLH Booking Confirmation[\s\-]+.+[\-\s]+[A-Z\d]+/',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'Rate Policies:' => ['Rate Policies:', 'Rate policies and comments:'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@slh.com') !== false) {
            foreach ($this->subjects as $subject) {
                if (preg_match($subject, $headers['subject'])) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if (stripos($parser->getSubject(), 'SLH Booking Confirmation') !== false
            || stripos($parser->getSubject(), 'SLH Cancellation Confirmation') !== false
            || $this->http->XPath->query("//a[contains(@href, 'slh')]")->length > 0) {
            return $this->http->XPath->query("//text()[{$this->contains($this->t('Rate Policies:'))}]")->length > 0;
        }
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]slh\.com$/', $from) > 0;
    }

    public function ParseHotel(Email $email)
    {
        $xpath = "//text()[normalize-space()='Hotel:']";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $h = $email->add()->hotel();

            $cancellationNumber = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Your cancellation number is')]/following::text()[normalize-space()][1]", null, true, "/^([A-Z\d]{10,})$/");

            if (!empty($cancellationNumber)) {
                $h->general()
                    ->cancelled()
                    ->cancellationNumber($cancellationNumber);
            }

            $h->general()
                ->confirmation($this->http->FindSingleNode("./following::text()[normalize-space()='Reservation Number:'][1]/ancestor::tr[1]/descendant::td[2]", $root, true, "/^([A-Z\d]+)$/"))
                ->traveller(implode(' ', array_filter(str_replace(['First Name:', 'Last Name:'], '', $this->http->FindNodes("./following::text()[normalize-space()='Guest(s):'][1]/ancestor::tr[1]/descendant::td[2]/descendant::text()[normalize-space()]", $root)))))
                ->cancellation($this->http->FindSingleNode("./following::text()[{$this->eq($this->t('Rate Policies:'))}][1]/ancestor::tr[1]/descendant::td[2]", $root));

            $h->hotel()
                ->name($this->http->FindSingleNode("./ancestor::tr[1]/descendant::td[2]/descendant::text()[normalize-space()][1]", $root))
                ->address($this->http->FindSingleNode("./ancestor::tr[1]/descendant::td[2]/descendant::text()[normalize-space()][2]", $root))
                ->phone($this->http->FindSingleNode("./ancestor::tr[1]/descendant::td[2]/descendant::text()[normalize-space()][last()]", $root, true, "/Tel\.\s*(.+)/"));

            $h->booked()
                ->checkIn($this->normalizeDate($this->http->FindSingleNode("./following::text()[normalize-space()='Check In:'][1]/ancestor::tr[1]/descendant::td[2]", $root)))
                ->checkOut($this->normalizeDate($this->http->FindSingleNode("./following::text()[normalize-space()='Check Out:'][1]/ancestor::tr[1]/descendant::td[2]", $root)));

            $guestsText = $this->http->FindSingleNode("./following::text()[normalize-space()='Adults / Children:'][1]/ancestor::tr[1]/descendant::td[2]", $root);

            if (preg_match("/^\s*(\d+)\s*\/\s*(\d+)\s*$/", $guestsText, $m)) {
                $h->booked()
                    ->guests($m[1])
                    ->kids($m[2])
                    ->rooms(count($this->http->FindNodes("./following::text()[normalize-space()='Room:'][1]/ancestor::tr[1]/descendant::td[2]/descendant::text()[normalize-space()]", $root)));
            }

            $account = $this->http->FindSingleNode("./following::text()[normalize-space()='Membership No. (if any):'][1]/ancestor::tr[1]/descendant::td[2]", $root);

            if (!empty($account)) {
                $h->program()
                    ->account($account, false);
            }

            $total = trim($this->http->FindSingleNode("./following::text()[normalize-space()='Total Price inc. tax:'][1]/ancestor::tr[1]/descendant::td[2]", $root, true, "/^\s*[A-Z]{3}\s*([\d\.\,]+)\s*\.?\s*$/"), '.');
            $currency = $this->http->FindSingleNode("./following::text()[normalize-space()='Total Price inc. tax:'][1]/ancestor::tr[1]/descendant::td[2]", $root, true, "/^\s*([A-Z]{3})/");
            $h->price()
                ->total(PriceHelper::parse($total, $currency))
                ->currency($currency);

            $roomType = $this->http->FindSingleNode("./following::text()[normalize-space()='Room:'][1]/ancestor::tr[1]/descendant::td[2]/descendant::text()[normalize-space()]", $root);

            if (!empty($roomType)) {
                $room = $h->addRoom();

                $room->setType($roomType);

                $room->setRates(array_filter($this->http->FindNodes("./following::text()[normalize-space()='Daily Rate Breakdown:'][1]/ancestor::tr[1]/descendant::td[2]/descendant::text()[normalize-space()]", $root, "/\s([A-Z]{3}\s*[\d\,\.]+)/")));

                $description = $this->http->FindSingleNode("./following::text()[normalize-space()='Rate Details:'][1]/ancestor::tr[1]/descendant::td[2]", $root);

                if (!empty($description)) {
                    $room->setDescription($description);
                }
            }

            $this->detectDeadLine($h);
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

    private function normalizeDate($str)
    {
        $in = [
            //Tuesday, August 17, 2021, after 14:00 (2:00 PM)
            "#^\w+\,\s*(\w+)\s*(\d+)\,\s*(\d{4})\,\s*(?:after|before)\s*([\d\:]+)\s*\([\d\:]+(?:\s*[AP]M)?\)$#i",
            // Saturday, 6 May 2023, after 3:00 pm
            "#^\s*\w+\,\s*(\d+)\s+(\w+)\s+(\d{4})\s*\,\s*(?:after|before)\s*([\d\:]+(?:\s*[AP]M)?)\s*$#i",
        ];
        $out = [
            "$2 $1 $3, $4",
            "$1 $2 $3, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }

    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h)
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        if (preg_match('/Cancel by (\d+)\s*(A?P?M)\D+(\d+)\s*days?\s*prior/', $cancellationText, $m)
            || preg_match('/Reservations must be cancelled by (\d+)\s*(A?P?M), local time, (\d+)\s*days?/', $cancellationText, $m)
            || preg_match('/no penalty up to (\d+)\s*(A?P?M) \(local\) \- (\d+) days? prior to arrival/', $cancellationText, $m)) {
            $h->booked()->deadlineRelative($m[3] . 'days', $m[1] . ':00' . $m[2]);
        }

        if (preg_match('/Cancel up to (\d+) days? prior to arrival to avoid 100/', $cancellationText, $m)) {
            $h->booked()->deadlineRelative($m[1] . 'days');
        }

        if (preg_match("/Full prepayment is required at time of booking and it is not refundable in case of cancellation or modification/", $cancellationText)) {
            $h->booked()->nonRefundable();
        }
    }

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'normalize-space(' . $node . ')=' . $s;
        }, $field)) . ')';
    }
}
