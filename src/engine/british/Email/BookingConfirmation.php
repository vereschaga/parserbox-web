<?php

namespace AwardWallet\Engine\british\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class BookingConfirmation extends \TAccountChecker
{
    public $mailFiles = "british/it-802957161.eml";
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
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'British Airways')]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Flight info'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Flight number'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('passenger details'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]crm\.ba\.com$/', $from) > 0;
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

        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[normalize-space()='Booking reference:']/ancestor::tr[1]", null, true, "/^{$this->opt($this->t('Booking reference:'))}\s*([A-Z\d]{6})$/"))
            ->travellers(array_unique(array_filter($this->http->FindNodes("//text()[normalize-space()='Passenger Name']/ancestor::table[1]/following-sibling::table/descendant::tr/td[1]", null, "/^([[:alpha:]][-.\/\'’[:alpha:] ]*[[:alpha:]])$/"))));

        $tickets = array_filter($this->http->FindNodes("//text()[contains(normalize-space(), 'Ticket Number(s)')]/ancestor::tr[1]/descendant::td[2]/descendant::text()[normalize-space()]", null, "/^([\d\-]{5,})/"));

        if (count($tickets) > 0) {
            $f->setTicketNumbers($tickets, false);
        }

        $total = $this->http->FindSingleNode("//text()[normalize-space()='Total']/ancestor::tr[1]/descendant::td[2]");

        if (preg_match("/^(?<total>[\d\.\,\']+)\s*(?<currency>\D{1,3})$/", $total, $m)) {
            $f->price()
                ->total(PriceHelper::parse($m['total']), $m['currency'])
                ->currency($m['currency']);
        }

        $nodes = $this->http->XPath->query("//text()[normalize-space()='Outbound' or normalize-space()='Inbound']/ancestor::table[1]/following-sibling::table");

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $airInfo = $this->http->FindSingleNode("./preceding::text()[normalize-space()][1]/ancestor::td[1]", $root);

            if (preg_match("/{$this->opt($this->t('Flight number'))}\s*(?<aName>(?:[A-Z][A-Z\d]|[A-Z\d][A-Z]))\s*(?<fNumber>\d{1,4})/", $airInfo, $m)) {
                $s->airline()
                    ->name($m['aName'])
                    ->number($m['fNumber']);
            }

            $year = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Payment Date')]/ancestor::tr[1]/descendant::td[2]", null, true, "/(\d{4})$/");

            $depInfo = implode("\n", $this->http->FindNodes("./descendant::td[1]/descendant::text()[normalize-space()]", $root));

            if (preg_match("/Operated by\s+(?<operator>.+)\n(?<depTime>\d+\:\d+)\s+(?<depCode>[A-Z]{3})\n(?<depDate>\w+\s*\d+\s*\w+)\n(?<depName>.+)\n*(?:Terminal\s*(?<depTeminal>.+))?/", $depInfo, $m)) {
                $s->departure()
                    ->name($m['depName'])
                    ->code($m['depCode'])
                    ->date($this->normalizeDate($m['depDate'] . ' ' . $year . ', ' . $m['depTime']));

                $s->airline()
                    ->operator($m['operator']);
            }

            $durationInfo = implode("\n", $this->http->FindNodes("./descendant::td[1]/following-sibling::td[1]/descendant::text()[normalize-space()]", $root));

            if (preg_match("/^(?<duration>\d+(?:h|m).+)\ndirect/iu", $durationInfo, $m)) {
                $s->extra()
                    ->duration($m['duration']);
            }

            $arrInfo = implode("\n", $this->http->FindNodes("./descendant::td[1]/following-sibling::td[2]/descendant::text()[normalize-space()]", $root));

            if (preg_match("/^(?<arrTime>\d+\:\d+)\s+(?<arrCode>[A-Z]{3})\n(?<arrDate>\w+\s*\d+\s*\w+)\n(?<arrName>.+)\n*(?:Terminal\s*(?<arrTeminal>.+))?$/", $arrInfo, $m)) {
                $s->arrival()
                    ->name($m['arrName'])
                    ->code($m['arrCode'])
                    ->date($this->normalizeDate($m['arrDate'] . ' ' . $year . ', ' . $m['arrTime']));
            }

            $seats = [];
            $cabin = [];
            $flightArrow = $this->http->FindSingleNode("./preceding::img[1]/following::text()[normalize-space()][1]", $root);

            if (stripos($flightArrow, 'Outbound') !== false) {
                $cabin = $this->http->FindNodes("//text()[normalize-space()='Outbound passenger details']/following::text()[normalize-space()='Passenger Name']/ancestor::table[1]/following-sibling::table/descendant::tr/td[3]");
                $seats = array_filter($this->http->FindNodes("//text()[normalize-space()='Outbound passenger details']/following::text()[normalize-space()='Passenger Name'][1]/ancestor::table[1]/following-sibling::table/descendant::tr"));
            } elseif (stripos($flightArrow, 'Inbound') !== false) {
                $cabin = $this->http->FindNodes("//text()[normalize-space()='Return passenger details']/following::text()[normalize-space()='Passenger Name']/ancestor::table[1]/following-sibling::table/descendant::tr/td[3]");
                $seats = array_filter($this->http->FindNodes("//text()[normalize-space()='Return passenger details']/following::text()[normalize-space()='Passenger Name'][1]/ancestor::table[1]/following-sibling::table/descendant::tr"));
            }

            if (count($cabin) > 0) {
                $s->extra()
                    ->cabin(implode(", ", array_filter(array_unique($cabin))));
            }

            foreach ($seats as $seat) {
                if (preg_match("/^(?<pax>[[:alpha:]][-.\/\'’[:alpha:] ]*[[:alpha:]])\s+(?<seat>\d+[A-Z])/", $seat, $m)) {
                    $s->addSeat($m['seat'], true, true, $m['pax']);
                }
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

    private function normalizeDate($str)
    {
        $this->logger->debug('$str = ' . print_r($str, true));

        $in = [
            //Fri 31 Jan 2024, 17:25
            "#^(\w+)\s+(\d+\s+\w+\s+\d{4}\,\s+\d+\:\d+)$#i",
        ];
        $out = [
            "$1, $2",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#(.*\d+\s+)([^\d\s]+)(\s+\d{4}.*)#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[2], $this->lang)) {
                $str = $m[1] . $en . $m[3];
            }
        }

        if (preg_match("/^(?<week>\w+), (?<date>\d+ \w+ .+)/u", $str, $m)) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($m['week'], $this->lang));
            $str = EmailDateHelper::parseDateUsingWeekDay($m['date'], $weeknum);
        } elseif (preg_match("/\b\d{4}\b/", $str)) {
            $str = strtotime($str);
        } else {
            $str = null;
        }

        return $str;
    }
}
