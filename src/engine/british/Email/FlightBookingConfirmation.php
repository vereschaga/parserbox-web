<?php

namespace AwardWallet\Engine\british\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class FlightBookingConfirmation extends \TAccountChecker
{
    public $mailFiles = "british/it-859754688.eml";
    public $subjects = [
        'Your booking confirmation',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@crm.ba.com') !== false) {
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
        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'British Airways')]")->length === 0) {
            return false;
        }

        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'Thank you for booking with')]")->length > 0
            && $this->http->XPath->query("//text()[normalize-space()='Booking reference:']")->length > 0
            && $this->http->XPath->query("//text()[normalize-space()='Flights']")->length > 0
            && $this->http->XPath->query("//text()[contains(normalize-space(), 'Duration:')]")->length > 0
            && $this->http->XPath->query("//text()[contains(normalize-space(), 'Passengers')]")->length > 0
            && $this->http->XPath->query("//text()[contains(normalize-space(), 'Manage my booking')]")->length > 0) {
            return true;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]crm\.ba\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->parseFlights($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function parseFlights(Email $email)
    {
        $f = $email->add()->flight();

        $travellers = array_unique($this->http->FindNodes("//text()[starts-with(normalize-space(), 'Passenger Name')]/following::table[1]/descendant::text()[contains(normalize-space(), ' to ')]/ancestor::td[1]/following::table[1]/descendant::table[1]/descendant::tr[normalize-space()]/td[1]/descendant::text()[normalize-space()]", null, "/^([[:alpha:]][-.\/\'’[:alpha:] ]*[[:alpha:]])$/"));
        $travellers = explode(';', strtoupper(implode(';', $travellers)));

        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Booking reference:')]/ancestor::table[2]", null, true, "/{$this->opt($this->t('Booking reference:'))}\s*([A-Z\d]{6})/"))
            ->travellers($travellers);

        $tickets = $this->http->FindNodes("//text()[normalize-space()='Ticket number(s)']/ancestor::tr[1]/descendant::td[normalize-space()][last()]/descendant::text()[normalize-space()]", null, "/^([\d\-]+)$/");

        $f->issued()
            ->tickets($tickets, false);

        $total = $this->http->FindSingleNode("//text()[normalize-space()='Total']/ancestor::tr[3][contains(normalize-space(), '(incl. taxes, fees and charges)')]/descendant::td[last()]");

        if (preg_match("/^(?<currency>[A-Z]{3})\s*(?<total>[\d\,\.\']+)$/", $total, $m)) {
            $f->price()
                ->total(PriceHelper::parse($m['total'], $m['currency']))
                ->currency($m['currency']);
        }

        $nodes = $this->http->XPath->query("//text()[normalize-space()='Departs:']");

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $airInfo = $this->http->FindSingleNode("./preceding::text()[normalize-space()][1]/ancestor::tr[1]", $root);
            $airports = ''; // for search seats

            if (preg_match("/(?<aName>(?:[A-Z][A-Z\d]|[A-Z\d][A-Z]))\s*(?<fNumber>\d{1,4})\s*(?<airports>.+{$this->opt($this->t(' to '))}.+)/", $airInfo, $m)) {
                $s->airline()
                    ->name($m['aName'])
                    ->number($m['fNumber']);

                $airports = preg_replace("/\-/u", "{$this->t('to')}", $m['airports']);
            }

            $depInfo = implode("\n", $this->http->FindNodes("./ancestor::table[normalize-space()][2]/descendant::text()[normalize-space()]", $root));

            if (preg_match("/Departs:\n(?<depDate>.+\d{4})\n(?<depName>.+)\n(?<depTime>[\d\:]+)\n(?<depCode>[A-Z]{3})[\s\-]*(?:Terminal\s+(?<depTerminal>.+))?$/", $depInfo, $m)) {
                $s->departure()
                    ->name($m['depName'])
                    ->code($m['depCode'])
                    ->date(strtotime($m['depDate'] . ', ' . $m['depTime']));

                if (isset($m['depTerminal'])) {
                    $s->departure()
                        ->terminal($m['depTerminal']);
                }
            }

            $arrInfo = implode("\n", $this->http->FindNodes("./following::text()[normalize-space()='Arrives:']/ancestor::table[normalize-space()][2]/descendant::text()[normalize-space()]", $root));

            if (preg_match("/Arrives:\n(?<arrDate>.+\d{4})\n(?<arrName>.+)\n(?<arrTime>[\d\:]+)\n(?<arrCode>[A-Z]{3})[\s\-]*(?:Terminal\s+(?<arrTerminal>.+))?(?:\n|$)/", $arrInfo, $m)) {
                $s->arrival()
                    ->name($m['arrName'])
                    ->code($m['arrCode'])
                    ->date(strtotime($m['arrDate'] . ', ' . $m['arrTime']));

                if (isset($m['arrTerminal'])) {
                    $s->arrival()
                        ->terminal($m['arrTerminal']);
                }
            }

            $extraInfo = implode("\n", $this->http->FindNodes("./ancestor::table[normalize-space()][2]/following::table[normalize-space()][1]/following::table[1]/descendant::text()[normalize-space()]", $root));

            if (preg_match("/^Cabin:\nDuration:\nFare:\n(?<cabin>.+)\n(?<duration>\d+.+)\n.+$/u", $extraInfo, $m)) {
                $s->extra()
                    ->cabin($m['cabin'])
                    ->duration($m['duration']);
            }

            $operatedInfo = implode("\n", $this->http->FindNodes("./ancestor::table[normalize-space()][2]/following::table[normalize-space()][1]/following::table[1]/following::table[1]/descendant::text()[normalize-space()]", $root));

            if (preg_match("/^Operated by:\nOperating airline flight number:\n(?<operator>.+)\n/", $operatedInfo, $m)) {
                $s->airline()
                    ->operator($m['operator']);
            }

            $seats = array_filter($this->http->FindNodes("//text()[{$this->eq($airports)}]/ancestor::td[1]/following::table[1]/descendant::table[1]/descendant::tr[normalize-space()]/td[2]/descendant::text()[normalize-space()]", null, "/^(\d+[A-Z])$/"));

            foreach ($seats as $seat) {
                $pax = strtoupper($this->http->FindSingleNode("//text()[{$this->eq($airports)}]/ancestor::td[1]/following::table[1]/descendant::table[1]/descendant::tr[normalize-space()]/td[2]/descendant::text()[{$this->eq($seat)}]/preceding::text()[normalize-space()][1]"));
                $s->extra()
                    ->seat($seat, true, true, $pax);
            }
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
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
    }
}
