<?php

namespace AwardWallet\Engine\virgin\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ScheduleChange2 extends \TAccountChecker
{
    public $mailFiles = "virgin/it-404173882.eml, virgin/it-719433920.eml, virgin/it-723322765.eml";
    public $subjects = [
        'Schedule Change affecting your flight',
        ' - your holiday confirmation',
        ' - your flight details have changed',
    ];

    public $lang = 'en';
    public $travellers;
    public $head;

    public static $dictionary = [
        "en" => [
            'Updated flight details' => ['Updated flight details', 'Your holiday itinerary', 'There has been a change to your flight'],
            'Operating carrier'      => ['Operating carrier', 'Operating airline'],
            'Airline reference'      => 'Airline reference',
            'Lead guest'             => ['Lead guest', 'Remaining guests'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        $this->head = $headers;

        if (isset($headers['from']) && stripos($headers['from'], '@service.virginatlantic.com') !== false) {
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
        if ($this->http->XPath->query("//text()[{$this->contains(['Virgin Atlantic Airways Ltd', 'Virgin Holidays'])}]"
                . "| //a/@href[{$this->contains(['.virginholidays.'])}]")->length > 0) {
            return $this->http->XPath->query("//text()[{$this->contains($this->t('Updated flight details'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Operating carrier'))} or {$this->contains($this->t('Airline reference'))}]")->length > 0;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]service\.virginatlantic\.com$/', $from) > 0;
    }

    public function ParseFlight(Email $email)
    {
        $f = $email->add()->flight();

        $conf = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Booking ref:')]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Booking ref:'))}\s*([A-Z\d]{6,})\s*$/");

        if ($this->http->XPath->query("//*[{$this->contains(['Virgin Atlantic Holidays', 'Virgin Holidays'])}] | //a/@href[{$this->contains(['.virginholidays.'])}]")->length > 0) {
            $email->ota()
                ->confirmation($conf);
            $f->general()
                ->noConfirmation();
        } else {
            $f->general()
                ->confirmation($conf);
        }

        if (!empty($this->travellers)) {
            $f->general()
                ->travellers($this->travellers, true);
        }

        $xpath = "//text()[starts-with(normalize-space(), 'Departs')]/ancestor::table[1][.//*[{$this->starts($this->t('Arrives'))}]]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $s->airline()
                ->name($this->http->FindSingleNode("./descendant::text()[contains(normalize-space(), 'no.')]/ancestor::tr[1]/descendant::td[2]", $root, true, "/^([A-Z][A-Z\d]|[A-Z\d][A-Z])\s*\d+/"))
                ->number($this->http->FindSingleNode("./descendant::text()[contains(normalize-space(), 'no.')]/ancestor::tr[1]/descendant::td[2]", $root, true, "/^(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(\d+)/"))
                ->operator($this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Operating carrier'))}]/ancestor::tr[1]/descendant::td[2]", $root), true, true);

            $conf = $this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Airline reference'))}]/ancestor::tr[1]/descendant::td[2]", $root);

            if (!empty($conf)) {
                $s->airline()
                    ->confirmation($conf);
            }

            $depDate = $this->http->FindSingleNode("./descendant::text()[normalize-space()][1]/ancestor::tr[1]/following::tr[1]/descendant::td[1]",
                $root, true, "/.+(?:\d{4}| \d{2} ).+/");
            $arrDate = $this->http->FindSingleNode("./descendant::text()[normalize-space()][1]/ancestor::tr[1]/following::tr[1]/descendant::td[2]",
                $root, true, "/.+(?:\d{4}| \d{2} ).+/");

            if (empty($depDate) && empty($arrDate)) {
                $date = $this->http->FindSingleNode("./descendant::tr[td[1][{$this->eq($this->t('Date'))}]]/td[2]",
                    $root, true, "/.*\d{4}.*/");
                $old = "not(ancestor-or-self::*[contains(@style, 'line-through')])";
                $depTime = implode("\n", $this->http->FindNodes("./descendant::text()[normalize-space()][1]/ancestor::tr[1]/following::tr[1]/descendant::td[1]/descendant::text()[{$old}]", $root));
                $arrTime = implode("\n", $this->http->FindNodes("./descendant::text()[normalize-space()][1]/ancestor::tr[1]/following::tr[1]/descendant::td[2]/descendant::text()[{$old}]", $root));

                if (!empty($date) && preg_match("/^\D*\d{1,2}:\d{2}\D*$/", $depTime) && preg_match("/^\D*\d{1,2}:\d{2}\D*$/", $arrTime)) {
                    $depDate = $date . ' ' . $depTime;
                    $arrDate = $date . ' ' . $arrTime;
                }
            }

            $s->departure()
                ->code($this->http->FindSingleNode("./descendant::text()[normalize-space()][1]/ancestor::tr[1]/preceding::tr[1]/descendant::td[1]", $root, true, "/^([A-Z]{3})$/"))
                ->date(strtotime($depDate));

            $s->arrival()
                ->code($this->http->FindSingleNode("./descendant::text()[normalize-space()][1]/ancestor::tr[1]/preceding::tr[1]/descendant::td[3]", $root, true, "/^([A-Z]{3})$/"))
                ->date(strtotime($arrDate));

            $s->extra()
                ->cabin($this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Cabin'))}]/ancestor::tr[1]/descendant::td[2]", $root), true, true);
        }
    }

    public function ParseHotel(Email $email)
    {
        $xpath = "//text()[starts-with(normalize-space(), 'Check in')]/ancestor::table[1][.//*[{$this->starts($this->t('Check out'))}]]";
        // $this->logger->debug('$xpath = '.print_r( $xpath,true));
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $h = $email->add()->hotel();

            $h->general()
                ->noConfirmation();

            if (!empty($this->travellers)) {
                $h->general()
                    ->travellers($this->travellers, true);
            }

            $h->hotel()
                ->name(implode(", ", $this->http->FindNodes("./preceding::tr[not(.//tr)][position() < 3]", $root)))
                ->noAddress()
            ;

            $h->booked()
                ->checkIn(strtotime($this->http->FindSingleNode(".//tr[*[1][{$this->eq($this->t('Check in'))}]][*[2][{$this->eq($this->t('Check out'))}]]/following-sibling::*[1]/*[1]", $root)))
                ->checkOut(strtotime($this->http->FindSingleNode(".//tr[*[1][{$this->eq($this->t('Check in'))}]][*[2][{$this->eq($this->t('Check out'))}]]/following-sibling::*[1]/*[2]", $root)))
                ->rooms($this->http->FindSingleNode("./descendant::td[{$this->eq($this->t('Room type'))}]/ancestor::tr[1]/descendant::td[2]",
                    $root, true, "/^\s*(\d+) x /"));

            $h->addRoom()
                ->setType($this->http->FindSingleNode("./descendant::td[{$this->eq($this->t('Room type'))}]/ancestor::tr[1]/descendant::td[2]",
                    $root, true, "/^\s*\d+ x (.+)/"));
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->travellers = preg_replace("/^\s*(Ms|Miss|Mrs|Ms|Mr|Mstr|Dr|Master) /", '',
            $this->http->FindNodes("//tr[not(.//tr)][{$this->starts($this->t('Lead guest'))}]/following::tr[1]/ancestor::*[1]/*[not({$this->starts($this->t('Lead guest'))})]/*[1]"));

        $this->ParseFlight($email);
        $this->ParseHotel($email);

        $totalPrice = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Trip total'))}]/following::text()[normalize-space()][1]", null, true, '/^.*\d.*$/');

        if (preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $totalPrice, $matches)) {
            // GBP 1501.22
            // £2,576.37
            $currency = str_replace('£', 'GBP', $matches['currency']);
            $email->price()
                ->total(PriceHelper::parse($matches['amount'], $currency))
                ->currency($currency);
        }

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

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }
}
