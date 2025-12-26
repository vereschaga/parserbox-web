<?php

namespace AwardWallet\Engine\recreation\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ReservationConfirmation extends \TAccountChecker
{
    public $mailFiles = "recreation/it-318748253.eml, recreation/it-564477364.eml";
    public $subjects = [
        'Recreation.gov Reservation Confirmation',
        'Recreation.gov Reservation Reminder',
        'Reservation Confirmation',
        'Reservation Cancellation',
        'Reservation Reminder',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'words' => [
                'Gear Up for Your Upcoming Trip!',
                'Your Trip is One Month Away!',
            ],
            'This email confirms your reservation' => ['This email confirms your reservation', 'This email is your confirmation that Reservation'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@recreation.gov') !== false) {
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
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'Recreation.gov')]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('View Site Details'))}]")->length > 0
            && ($this->http->XPath->query("//text()[{$this->eq($this->t('Reservation Details'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Modify Campsite / Dates'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Modify Order Details'))}]")->length > 0
            ) || ($this->http->XPath->query("//text()[{$this->eq($this->t('Cancellation Details'))}]")->length > 0
            );
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]recreation\.gov$/', $from) > 0;
    }

    public function ParseHotel(Email $email)
    {
        $h = $email->add()->hotel();

        $confirmation = $this->http->FindSingleNode("//text()[{$this->contains($this->t('This email confirms your reservation'))}]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('This email confirms your reservation'))}\s*([\d\-]+)/");

        if (empty($confirmation)) {
            $confs = array_unique($this->http->FindNodes("//a/@href[{$this->contains('.recreation.gov/account/orders/')}] | //a/@originalsrc[{$this->contains('.recreation.gov/account/orders/')}]",
                null, "/www\.recreation\.gov\/account\/orders\/([\d\-]+)\/reservations\//"));

            if (count($confs) === 1) {
                $confirmation = $confs[0];
            }
        }

        if (empty($confirmation) && $this->http->XPath->query("//text()[{$this->eq($this->t('words'))}]")->length > 0) {
            $h->general()
                ->noConfirmation();
        } else {
            $h->general()
                ->confirmation($confirmation);
        }

        if ($this->http->XPath->query("//node()[{$this->starts(['Cancellation Details', 'Reservation Cancellation'])}]")->length > 0) {
            $h->general()
                ->cancelled()
                ->status('Cancelled');
        }

        $traveller = $this->http->FindSingleNode("//text()[normalize-space()='Primary Occupant']/following::text()[normalize-space()][1]");

        if (empty($traveller) && $h->getCancelled()) {
            $traveller = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Hello '))}]", null, true,
                "/^\s*{$this->opt($this->t('Hello '))}\s*([[:alpha:]\- ]+?),\s*$/");
        }

        $h->general()
            ->traveller($traveller);

        $hotelInfo = implode("\n", $this->http->FindNodes("//text()[contains(normalize-space(), 'View Site Details')]/preceding::text()[normalize-space()][1]/ancestor::td[1]/descendant::text()[normalize-space()]"));

        if (preg_match("/^(?<type>.+?)\s*\n\s*(?<hotelName>.+?)\s*\n(?<address>.+)(?:\s+View Site Details)?$/", $hotelInfo, $m)) {
            $h->hotel()
                ->name($m['hotelName'])
                ->address($m['address']);

            $h->addRoom()->setType($m['type']);
        }

        if (!$h->getCancelled()) {
            $h->booked()
                ->guests($this->http->FindSingleNode("//text()[contains(normalize-space(), '# of Occupants')]/following::text()[normalize-space()][1]",
                    null, true, "/^(\d+)$/"));
        }

        $inDate = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Check In:')]/preceding::text()[normalize-space()][1]");
        $outDate = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Check Out:')]/preceding::text()[normalize-space()][1]");

        $inTime = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Check In:')]", null, true, "/{$this->opt($this->t('Check In:'))}\s*(.+)/");
        $outTime = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Check Out:')]", null, true, "/{$this->opt($this->t('Check Out:'))}\s*(.+)/");

        $h->booked()
            ->checkIn((!empty($inDate) && !empty($inTime)) ? strtotime($inDate . ', ' . $inTime) : null)
            ->checkOut((!empty($outDate) && !empty($outTime)) ? strtotime($outDate . ' ' . $outTime) : null);

        $cancellationPolicy = implode(", ", $this->http->FindNodes("//text()[normalize-space()='Need to cancel your reservation?']/ancestor::tr[1]/descendant::p"));

        if (!empty($cancellationPolicy)) {
            $h->general()
                ->cancellation($cancellationPolicy);
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

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field)) . ')';
    }
}
