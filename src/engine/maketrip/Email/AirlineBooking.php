<?php

namespace AwardWallet\Engine\maketrip\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class AirlineBooking extends \TAccountChecker
{
    public $mailFiles = "maketrip/it-659213348.eml";
    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[{$this->contains('Quest2Travel')}]")->length > 0
            || $this->http->XPath->query("//text()[{$this->contains('https://www.quest2travel.in')}]")->length > 0) {
            return $this->http->XPath->query("//text()[{$this->contains($this->t('Transaction History for Request No.'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Sector'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('PNR'))}]")->length > 0;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]quest2travel\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->ParseFlight($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function ParseFlight(Email $email)
    {
        $f = $email->add()->flight();

        $travellers = $this->http->FindNodes("//text()[contains(normalize-space(), 'Passenger(s)')]/ancestor::tr[1]/following-sibling::tr/descendant::td[normalize-space()][1]/descendant::text()[normalize-space()][1]");
        $corporate = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Corporate')]/ancestor::tr[1]/descendant::td[last()]");
        $travellers = preg_replace("#\/\s*{$corporate}\s*#", "", $travellers);
        $f->general()
            ->travellers(preg_replace("/(?:Mrs\.|Mr\.|Ms\.)/", "", $travellers));

        $tickets = $this->http->FindNodes("//text()[contains(normalize-space(), 'Passenger(s)')]/ancestor::tr[1]/following-sibling::tr/descendant::td[normalize-space()][last()]", null, "/^(\d{5,})$/");

        if (count($tickets) > 0) {
            $f->setTicketNumbers($tickets, false);
        }

        $totalWithoutCurrency = $this->http->FindSingleNode("//text()[normalize-space()='Total Fare']/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Total Fare'))}\s*\:\s*([\d\.\,]+)$/");

        if ($totalWithoutCurrency !== null) {
            $f->price()
                ->total(PriceHelper::parse($totalWithoutCurrency));
        }

        $bookingDate = $this->http->FindSingleNode("//text()[normalize-space()='Booking Date']/ancestor::tr[1]");

        if (preg_match("/Booking Date\s*\:\s*(\d+\-\w+\-\d{4}\s*\d+\:\d+)/u", $bookingDate, $m)) {
            $bookingDate = str_replace('-', ' ', $m[1]);
            $f->general()
                ->date(strtotime($bookingDate));
        }

        $nodes = $this->http->XPath->query("//text()[normalize-space()='Sector']/ancestor::tr[1][contains(normalize-space(), 'Airline')]/following-sibling::tr");

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $airlineInfo = $this->http->FindSingleNode("./descendant::td[normalize-space()][last()][1]", $root);

            if (preg_match("/\/(?<aName>[A-Z\d]+)\-(?<fNumber>\d{2,4})$/", $airlineInfo, $m)) {
                $s->airline()
                    ->name($m['aName'])
                    ->number($m['fNumber']);
            }

            $codeInfo = $this->http->FindSingleNode("./descendant::td[normalize-space()][1]", $root);

            if (preg_match("/^(?<depCode>[A-Z]{3})\-(?<arrCode>[A-Z]{3})$/", $codeInfo, $m)) {
                $s->departure()
                    ->code($m['depCode']);
                $s->arrival()
                    ->code($m['arrCode']);
            }

            $confNumber = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Passenger(s)')]/ancestor::tr[1]/following-sibling::tr/descendant::td[{$this->eq($codeInfo)}][1]/following::td[3]", null, true, "/^([A-Z\d]{6})\s*/");
            $f->general()
                ->confirmation($confNumber);

            $cabinInfo = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Passenger(s)')]/ancestor::tr[1]/following-sibling::tr/descendant::td[{$this->eq($codeInfo)}][1]/following::td[2]", null, true, "/^(.+\/.+)/");

            if (preg_match("/^(?<cabin>.+)\/(?<bookingCode>[A-Z]{1,2})$/", $cabinInfo, $m)) {
                $s->extra()
                    ->cabin($m['cabin'])
                    ->bookingCode($m['bookingCode']);
            }

            $depDate = str_replace('-', ' ', $this->http->FindSingleNode("./descendant::td[normalize-space()][2]", $root));
            $depTime = $this->http->FindSingleNode("./descendant::td[normalize-space()][3]", $root, true, "/^([\d\:]+)$/");
            $arrTime = $this->http->FindSingleNode("./descendant::td[normalize-space()][4]", $root, true, "/^([\d\:]+)$/");

            $s->departure()
                ->date(strtotime($depDate . ', ' . $depTime));

            $s->arrival()
                ->date(strtotime($depDate . ', ' . $arrTime));
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

    private function eq($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
    }
}
