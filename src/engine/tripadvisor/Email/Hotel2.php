<?php

namespace AwardWallet\Engine\tripadvisor\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Hotel2 extends \TAccountChecker
{
    public $mailFiles = "tripadvisor/it-108168163.eml, tripadvisor/it-108168275.eml";
    public $subjects = [
        'Booked! Your reservation at',
        'You have canceled your booking at',
    ];

    public $lang = 'en';
    public $subject;

    public static $dictionary = [
        "en" => [
            'thousands' => ',',
            'decimals'  => '.',
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@t1.tripadvisor.com') !== false) {
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
        if ($this->http->XPath->query("//a[contains(@href, 'viator.com')]")->length > 0
            || $this->http->XPath->query("//a[contains(@href, 'tripadvisor.com')]")->length > 0) {
            return $this->http->XPath->query("//text()[contains(normalize-space(), 'Hotel') or contains(normalize-space(), 'Tripadvisor LLC')]")->length > 0
                && $this->http->XPath->query("//text()[contains(normalize-space(), 'Room details')]")->length > 0;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]t1\.tripadvisor\.com$/', $from) > 0;
    }

    public function ParseHotel(Email $email)
    {
        $h = $email->add()->hotel();

        if (stripos($this->subject, 'confirmed') !== false) {
            $h->general()
                ->status('confirmed');
        }

        if (stripos($this->subject, 'canceled') !== false) {
            $cancellation_number = array_values(array_unique($this->http->FindNodes("//text()[normalize-space()='Cancelation number']/following::text()[normalize-space()][1]")));

            if (count($cancellation_number) == 1) {
                $h->general()
                    ->cancellationNumber($cancellation_number[0], 'Cancelation number');
            }

            $h->general()
                ->status('canceled')
                ->cancelled();
        }

        $cancellation = $this->http->FindSingleNode("//text()[normalize-space()='Cancellation policy']/following::text()[normalize-space()][1]");

        if (!empty($cancellation)) {
            $h->general()
                ->cancellation($cancellation);
        }

        $confirmation = $this->http->FindSingleNode("//text()[normalize-space()='Confirmation number:']/ancestor::tr[1]", null, true, "/\:\s*([\D\d]{5,})/");

        if (empty($confirmation)) {
            $confirmationArray = array_values(array_unique($this->http->FindNodes("//text()[normalize-space()='Confirmation number']/following::text()[normalize-space()][1]")));

            if (count($confirmationArray) == 1) {
                $confirmation = $confirmationArray[0];
            }
        }

        $h->general()
            ->traveller($this->http->FindSingleNode("//text()[normalize-space()='Plus member' or normalize-space()='Tripadvisor Plus member']/following::text()[normalize-space()][1]"), true)
            ->confirmation($confirmation, 'Confirmation number');

        $h->hotel()
            ->name($this->http->FindSingleNode("//text()[normalize-space()='Hotel']/following::text()[normalize-space()][1]"))
            ->address(implode(', ', $this->http->FindNodes("//text()[normalize-space()='Hotel']/following::text()[normalize-space()][1]/ancestor::tr[1]/following-sibling::tr[not(contains(normalize-space(), 'guests') or contains(normalize-space(), 'Guests'))]")));

        $h->booked()
            ->checkIn(strtotime($this->http->FindSingleNode("//text()[normalize-space()='Check in']/following::text()[normalize-space()][1]")))
            ->checkOut(strtotime($this->http->FindSingleNode("//text()[normalize-space()='Check out']/following::text()[normalize-space()][1]")))
            ->guests($this->http->FindSingleNode("//text()[normalize-space()='Guests']/following::text()[normalize-space()][1]", null, true, "/(\d+)\s*\w+/"))
            ->rooms($this->http->FindSingleNode("//text()[normalize-space()='Rooms']/following::text()[normalize-space()][1]", null, true, "/(\d+)\s*\w+/"));

        $roomDesc = $this->http->FindSingleNode("//text()[normalize-space()='Room details']/following::text()[normalize-space()][1]");

        if (!empty($roomDesc)) {
            $room = $h->addRoom();
            $room->setDescription($roomDesc);
        }

        $total = $this->http->FindSingleNode("//text()[normalize-space()='Total']/ancestor::tr[1]/descendant::td[2]", null, true, "/\D([\d\.\,]+)$/");
        $currency = $this->http->FindSingleNode("//text()[normalize-space()='Total']/ancestor::tr[1]/descendant::td[2]", null, true, "/^\s*(\D)[\d\.\,]+/");

        if (!empty($total) && !empty($currency)) {
            $h->price()
                ->total(PriceHelper::cost($total, $this->t('thousands'), $this->t('decimals')))
                ->currency($currency);

            $cost = $this->http->FindSingleNode("//text()[normalize-space() = 'Summary of charges']/following::text()[normalize-space()][1]/ancestor::tr[1]/descendant::td[2]", null, true, "/\D([\d\.\,]+)/");

            if (!empty($cost)) {
                $h->price()
                    ->cost(PriceHelper::cost($cost, $this->t('thousands'), $this->t('decimals')));
            }

            $tax = $this->http->FindSingleNode("//text()[normalize-space() = 'Taxes and fees']/ancestor::tr[1]/descendant::td[2]", null, true, "/\D([\d\.\,]+)/");

            if (!empty($tax)) {
                $h->price()
                    ->tax(PriceHelper::cost($tax, $this->t('thousands'), $this->t('decimals')));
            }
        }

        $account = $this->http->FindSingleNode("//text()[normalize-space()='Membership number']/following::text()[normalize-space()][1]");

        if (!empty($account)) {
            $h->program()
                ->account($account, false);
        }

        $this->detectDeadLine($h);
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->subject = $parser->getSubject();

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

        if (
            preg_match('/Free cancellation before\s*(\w+\s*\d+\,\s*\d{4}\D+[\d\:]+)\./', $cancellationText, $m)
            || preg_match('/Free cancellation before\s*(\w+\s*\d+\,\s*\d{4}\D+[\d\:]+\s*A?P?M)/', $cancellationText, $m)
        ) {
            $h->booked()->deadline(strtotime($m[1]));
        }
    }
}
