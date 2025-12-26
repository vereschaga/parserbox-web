<?php

namespace AwardWallet\Engine\kestrelflyer\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ConfirmationForPlain extends \TAccountChecker
{
    public $mailFiles = "kestrelflyer/it-536350464.eml";
    public $subjects = [
        'Confirmation for reservation ',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@airmauritius.com') !== false) {
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
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'Thank you for choosing Air Mauritius')]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Contact Information'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Traveller information'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Services'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]airmauritius\.com$/', $from) > 0;
    }

    public function ParseFlight(Email $email)
    {
        $f = $email->add()->flight();

        $travellers = $this->http->FindNodes("//text()[starts-with(normalize-space(), 'Traveller ') and contains(translate(normalize-space(.),'0123456789','dddddddddd--'),'d')]", null, "/\d+\:\s*(.+)/");

        if (empty($travellers)) {
            $travellers = $this->http->FindNodes("//text()[starts-with(normalize-space(), 'Traveller information')]/ancestor::td/following::tr[normalize-space()][not(contains(normalize-space(), 'Travel document:'))][1][not(contains(normalize-space(), 'Contact Information'))]");
            $travellers = preg_replace("/(Â)/", "", $travellers);
        }

        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Booking reservation number:')]/following::text()[normalize-space()][1]", null, true, "/^([A-Z\d]{6})$/u"))
            ->travellers(preg_replace("/^(?:Mrs|Mr|Ms)\s*/", "", array_filter($travellers)));

        $accounts = $this->http->FindNodes("//text()[starts-with(normalize-space(), 'Frequent flyer(s):')]/following::text()[normalize-space()][1]", null, "/^([A-Z\d\s]+)$/");

        if (!empty($accounts)) {
            $f->setAccountNumbers($accounts, false);
        }

        $status = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Trip status:')]/following::text()[normalize-space()][1]");

        if (!empty($status)) {
            $f->general()
                ->status($status);
        }

        $tickets = $this->http->FindNodes("//text()[starts-with(normalize-space(), 'Document number:')]", null, "/{$this->opt($this->t('Document number:'))}\s*([\d\-]+)/");

        if (count($tickets) > 0) {
            $f->setTicketNumbers(array_unique($tickets), false);
        }

        $nodes = $this->http->XPath->query("//text()[starts-with(normalize-space(), 'Departure:')]/ancestor::table[normalize-space()][2]");

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $date = $this->http->FindSingleNode("./preceding::text()[normalize-space()][contains(normalize-space(), ',')][1]", $root);

            $airlineInfo = $this->http->FindSingleNode("./descendant::text()[starts-with(normalize-space(), 'Airline')]/following::text()[normalize-space()][1]/ancestor::td[1]", $root);

            if (preg_match("/.+\s+(?<aName>(?:[A-Z][A-Z\d]|[A-Z\d][A-Z]))\s*(?<fNumber>\d{1,4})/", $airlineInfo, $m)) {
                $s->airline()
                    ->name($m['aName'])
                    ->number($m['fNumber']);
            }

            $operator = $this->http->FindSingleNode("./descendant::text()[starts-with(normalize-space(), 'Operated by')]", $root, true, "/{$this->opt($this->t('Operated by'))}\s*(.+)/");

            if (!empty($operator)) {
                $s->airline()
                    ->operator($operator);
            }

            $aircraft = $this->http->FindSingleNode("./descendant::text()[starts-with(normalize-space(), 'Aircraft:')]/following::text()[normalize-space()][1]", $root);

            if (!empty($aircraft)) {
                $s->extra()
                    ->aircraft($aircraft);
            }

            $depTime = $this->http->FindSingleNode("./descendant::text()[starts-with(normalize-space(), 'Departure:')]/following::text()[normalize-space()][1]", $root);
            $s->departure()
                ->date(strtotime($date . ', ' . $depTime));

            $arrTime = $this->http->FindSingleNode("./descendant::text()[starts-with(normalize-space(), 'Arrival:')]/following::text()[normalize-space()][1]", $root);

            if ($this->http->XPath->query("./descendant::text()[starts-with(normalize-space(), 'Arrival:')]/following::text()[normalize-space()][2][starts-with(normalize-space(), '+1')]", $root)->length > 0) {
                $s->arrival()
                    ->date(strtotime('+1 day', strtotime($date . ', ' . $arrTime)));
            } else {
                $s->arrival()
                    ->date(strtotime($date . ', ' . $arrTime));
            }

            $depInfo = $this->http->FindSingleNode("./descendant::text()[starts-with(normalize-space(), 'Departure:')]/following::text()[normalize-space()][2]/ancestor::td[1]", $root);

            if (preg_match("/(?<name>.+)\,\s*terminal\s*(?<terminal>.+)/", $depInfo, $m)
                || preg_match("/(?<name>.+)/", $depInfo, $m)) {
                $s->departure()
                    ->noCode()
                    ->name($m['name']);

                if (isset($m['terminal']) && !empty($m['terminal'])) {
                    $s->departure()
                        ->terminal($m['terminal']);
                }
            }

            $arrInfo = $this->http->FindSingleNode("./descendant::text()[starts-with(normalize-space(), 'Arrival:')]/following::text()[normalize-space()][not(contains(normalize-space(), 'day'))][string-length()>5][2]/ancestor::td[1]", $root);

            if (preg_match("/(?<name>.+)\,\s*terminal\s*(?<terminal>.+)/", $arrInfo, $m)
                || preg_match("/(?<name>.+)/", $arrInfo, $m)) {
                $s->arrival()
                    ->noCode()
                    ->name($m['name']);

                if (isset($m['terminal']) && !empty($m['terminal'])) {
                    $s->arrival()
                        ->terminal($m['terminal']);
                }
            }

            $price = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Total for all travellers')]/ancestor::tr[1]", null, true, "/^(.+)\s*{$this->opt($this->t('Total for all travellers'))}/");

            if (preg_match("/^(?<total>[\d\.\,\s]+)\s*(?<currency>[A-Z]{3})/", $price, $m)) {
                $f->price()
                    ->total(PriceHelper::parse($m['total'], $m['currency']))
                    ->currency($m['currency']);
            }

            $flightName = str_replace("to", "-", $this->http->FindSingleNode("./preceding::text()[starts-with(normalize-space(), 'Flight')][1]/preceding::text()[normalize-space()][1]", $root));

            $seatText = implode(',', array_filter($this->http->FindNodes("//text()[normalize-space()='Services']/following::text()[{$this->eq($flightName)}]/following::*[1]", null, "/{$this->opt($this->t('Seats'))}\s*(\d+[A-Z])/")));

            if (!empty($seatText)) {
                $s->setSeats(explode(",", $seatText));
            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $body = $parser->getHTMLBody();
        $body = preg_replace("/(‎)/", "", $body);
        $this->http->SetBody($body);

        $this->ParseFlight($email);

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

    protected function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field)) . ')';
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
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
}
