<?php

namespace AwardWallet\Engine\jsx\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class FlightBooking extends \TAccountChecker
{
    public $mailFiles = "jsx/it-630441514.eml";
    public $subjects = [
        'JSX Booking Confirmed - ',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@garethemery.com') !== false) {
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
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'JSX')]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Flight information'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Passenger details'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]garethemery\.com$/', $from) > 0;
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
            ->confirmation($this->http->FindSingleNode("//text()[normalize-space()='Confirmation']/ancestor::tr[1]/following::tr[1]/descendant::td[1]", null, true, "/^([A-Z\d]{6})$/"))
            ->status($this->http->FindSingleNode("//text()[normalize-space()='Status']/ancestor::tr[1]/following::tr[1]/descendant::td[string-length()>4][2]"))
            ->travellers($this->http->FindNodes("//text()[starts-with(normalize-space(), 'Flight:')]/preceding::text()[string-length()>2][1][not(contains(normalize-space(), 'Services:'))]"));

        $price = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Total amount')]");

        if (preg_match("/Total amount\s*(?<total>[\d\.]+)\s*(?<currency>[A-Z]{3})/", $price, $m)) {
            $f->price()
                ->total(PriceHelper::parse($m['total'], $m['currency']))
                ->currency($m['currency']);
        }

        $nodes = $this->http->XPath->query("//img[contains(@src, 'airplane-up')]/ancestor::table[1]");

        if ($nodes->length === 0) {
            $nodes = $this->http->XPath->query("//img[contains(@alt, 'to')]/ancestor::table[1]");
        }

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $depDate = $this->http->FindSingleNode("./preceding::text()[normalize-space()][1]", $root);

            $airInfo = $this->http->FindSingleNode("./descendant::tr[normalize-space()][1]/descendant::td[2]", $root);

            if (preg_match("/^(?<aName>(?:[A-Z][A-Z\d]|[A-Z\d][A-Z]))\s*(?<fNumber>\d{2,4})$/", $airInfo, $m)) {
                $s->airline()
                    ->name($m['aName'])
                    ->number($m['fNumber']);
            }

            $depTime = $this->http->FindSingleNode("./descendant::tr[normalize-space()][3]/descendant::td[1]", $root);

            $s->departure()
                ->code($this->http->FindSingleNode("./descendant::tr[normalize-space()][1]/descendant::td[1]", $root))
                ->date(strtotime($depDate . ', ' . $depTime))
                ->name($this->http->FindSingleNode("./descendant::tr[normalize-space()][2]/descendant::td[1]", $root));

            $arrTime = $this->http->FindSingleNode("./descendant::tr[normalize-space()][3]/descendant::td[3]", $root);

            $s->arrival()
                ->code($this->http->FindSingleNode("./descendant::tr[normalize-space()][1]/descendant::td[3]", $root))
                ->date(strtotime($depDate . ', ' . $arrTime))
                ->name($this->http->FindSingleNode("./descendant::tr[normalize-space()][2]/descendant::td[3]", $root));

            $duration = $this->http->FindSingleNode("./descendant::tr[normalize-space()][3]/descendant::td[2]", $root);

            if (!empty($duration)) {
                $s->extra()
                    ->duration($duration);
            }

            $seats = array_filter($this->http->FindNodes("//text()[{$this->starts($this->t('Passenger details'))}]/following::text()[{$this->starts($airInfo)}]/ancestor::tr[1]/descendant::td[{$this->starts($this->t('Seat'))}]", null, "/{$this->opt($this->t('Seat'))}[\s\:]*(\d+[A-Z])/"));

            if (count($seats) > 0) {
                $s->setSeats($seats);
            }

            if (empty($s->getAirlineName()) && empty($s->getFlightNumber()) && empty($s->getDepCode()) && empty($s->getDepDate())) {
                $f->removeSegment($s);
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
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }
}
