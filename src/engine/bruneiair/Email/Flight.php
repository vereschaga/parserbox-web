<?php

namespace AwardWallet\Engine\bruneiair\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Flight extends \TAccountChecker
{
    public $mailFiles = "bruneiair/it-796692746.eml, bruneiair/it-798624302.eml, bruneiair/it-803301866.eml, bruneiair/it-804939842.eml";
    public $subjects = [
        'Your booking confirmation',
        'Your check-in confirmation',
        'Check-in online for your flight',
        'Flight Timing Change',
    ];

    public $lang = 'en';

    public static $dictionary = [
        'en' => [
            'Flight Summary' => ['Flight Summary', 'Flight Details', 'Updated Flight Details'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], 'flyroyalbrunei.com') !== false) {
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
        if ($this->http->XPath->query("//text()[{$this->contains($this->t('Royal Brunei Airlines'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Booking Reference Number (PNR)'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->eq($this->t('Flight Summary'))}]")->length > 0) {
            return true;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]flyroyalbrunei\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email): Email
    {
        $this->Flight($email);
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function Flight(Email $email)
    {
        $f = $email->add()->flight();

        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->contains($this->t('Booking Reference Number (PNR)'))}]/ancestor::td[1]", null, true, "/^.+\s*\:\s*([A-Z\d]{6})$/"), 'Booking Reference Number (PNR)');

        $travellers = $this->http->FindNodes("//text()[{$this->eq($this->t('Travellers(s) Details'))}]/following::table[1]/descendant::span/ancestor::div[1][{$this->contains($this->t('Frequent Flyer Number: '))}]", null, "/^\s*([[:alpha:]][-.\/\'’[:alpha:] ]*[[:alpha:]])\s*\(/u");

        if ($travellers == null) {
            $travellers = $this->http->FindNodes("//text()[{$this->contains($this->t('Dear'))}]", null, "/^Dear\s*([[:alpha:]][-.\/\'’[:alpha:] ]*[[:alpha:]])\s*\,/u");
        }
        $f->setTravellers(array_unique($travellers), true);

        foreach (array_unique($travellers) as $traveller) {
            $tickets = $this->http->FindNodes("//text()[{$this->eq($traveller)}]/ancestor::tr[1]/following-sibling::tr[1]", null, "/^.+\s*\:\s*([\d\-]+)\s*$/u");

            foreach (array_unique($tickets) as $ticket) {
                $f->addTicketNumber($ticket, true, $traveller);
            }
        }

        $segmentNodes = $this->http->XPath->query("//text()[{$this->eq($this->t('DEPART'))}]/ancestor::tr[2]");

        foreach ($segmentNodes as $root) {
            $s = $f->addSegment();

            $airInfo = $this->http->FindSingleNode("./descendant::table[{$this->contains($this->t('FLIGHT NUMBER'))}]/descendant::tr[2]", $root, true, '/^((?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*\d{1,4})$/');

            if (preg_match("/(?<aName>(?:[A-Z][A-Z\d]|[A-Z\d][A-Z]))(?<fNumber>\d{1,4})$/", $airInfo, $m)) {
                $s->airline()
                    ->name($m['aName'])
                    ->number($m['fNumber']);
            }

            $depInfo = $this->http->FindSingleNode("./descendant::table[{$this->contains($this->t('DEPART'))}]", $root);

            if (preg_match("/^DEPART\s*(?<depTime>[\d\:]+)\s*(?<depCity>.+)\s*\((?<depCode>\D{3})\).+\D{3}\s*(?<depDate>\d+\s*\w+\s*\d{4})$/u", $depInfo, $m)) {
                $s->departure()
                    ->name($m['depCity'])
                    ->date(strtotime($m['depDate'] . ' ' . $m['depTime']))
                    ->code($m['depCode']);
            }

            $arrInfo = $this->http->FindSingleNode("./descendant::table[{$this->contains($this->t('ARRIVE'))}]", $root);

            if (preg_match("/^ARRIVE\s*(?<arrTime>[\d\:]+)\s*(?:\(\s*\+\s*\d*\s*\w+\s*\))?\s*(?<arrCity>.+)\s*\((?<arrCode>\D{3})\).+\D{3}\s*(?<arrDate>\d+\s*\w+\s*\d{4})$/u", $arrInfo, $m)) {
                $s->arrival()
                    ->name($m['arrCity'])
                    ->date(strtotime($m['arrDate'] . ' ' . $m['arrTime']))
                    ->code($m['arrCode']);
            }

            $seatInfo = $this->http->FindNodes("//text()[{$this->eq($this->t('Travellers(s) Details'))}]/ancestor::table[2]/following::table[1]/descendant::tr[{$this->contains($airInfo)}]/descendant::tr[{$this->contains($this->t('Seat No.'))}]", null, '/^Seat\s*No\.\s*\:\s*(\d+[A-Z])/');

            foreach ($seatInfo as $seat) {
                $traveller = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Travellers(s) Details'))}]/ancestor::table[2]/following::table[1]/descendant::tr[{$this->contains($airInfo)}]/descendant::tr[{$this->contains($seat)}]/preceding::tr{$this->contains($this->t('Frequently Flyer Number'))}][1]", null, false, "/^([[:alpha:]][-.\/\'’[:alpha:] ]*[[:alpha:]])\s*\(\s*Frequently/u");

                $s->extra()
                    ->seat($seatInfo, true, true, $traveller);
            }

            $mealInfo = $this->http->FindNodes("//text()[{$this->eq($this->t('Travellers(s) Details'))}]/ancestor::table[2]/following::table[1]/descendant::tr[{$this->contains($airInfo)}]/descendant::tr[{$this->contains($this->t('Meal'))}]", null, '/^Meal\s*\:\s*(.+)$/');

            if ($mealInfo !== null) {
                $s->setMeals(array_unique($mealInfo));
            }
        }
        $resStatus = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Your booking is confirmed.'))}]");

        if ($resStatus !== null) {
            $f->general()
                ->status('Confirmed');
        }
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
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

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }
}
