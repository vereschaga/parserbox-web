<?php

namespace AwardWallet\Engine\indigo\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class YourItinerary extends \TAccountChecker
{
    public $mailFiles = "indigo/it-166694246.eml";
    public $subjects = [
        'Your IndiGo Itenerary',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@customer.goindigo.in') !== false) {
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
        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'IndiGo')]")->length > 0) {
            return $this->http->XPath->query("//text()[{$this->contains($this->t('Manage flights and add add-ons like XL Seat, Fast Forwards etc.'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Baggage Information'))}]")->length > 0;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]customer\.goindigo\.in$/', $from) > 0;
    }

    public function ParseFlight(Email $email)
    {
        $f = $email->add()->flight();

        $f->general()
            ->date(strtotime($this->http->FindSingleNode("//text()[normalize-space()='Booked on:']/following::text()[normalize-space()][1]")))
            ->confirmation($this->http->FindSingleNode("//text()[normalize-space()='Your booking code (PNR) is:']/following::text()[normalize-space()][1]", null, true, "/^[A-Z\d]{6}$/"))
            ->travellers($this->http->FindNodes("//text()[starts-with(normalize-space(), 'Passenger')]/following::table[1]/descendant::img[not(contains(@src, 'arrow'))]/following::text()[normalize-space()][not(contains(normalize-space(), 'Adult'))][1]"));

        $xpath = "//img[contains(@src, 'plan')]/ancestor::table[1]/ancestor::tr[1]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $s->departure()
                ->code($this->http->FindSingleNode("./descendant::td[normalize-space()][1]", $root));

            $depTerminal = $this->http->FindSingleNode("./following::tr[1]/descendant::td[1]", $root, true, "/\((.+)\)/");

            if (!empty($depTerminal)) {
                $s->departure()
                    ->terminal($depTerminal);
            }

            $s->arrival()
                ->code($this->http->FindSingleNode("./descendant::td[normalize-space()][last()]", $root));

            $arrTerminal = $this->http->FindSingleNode("./following::tr[1]/descendant::td[last()]", $root, true, "/\((.+)\)/");

            if (!empty($arrTerminal)) {
                $s->arrival()
                    ->terminal($arrTerminal);
            }

            $flightText = $this->http->FindSingleNode("./following::tr[contains(normalize-space(),'Flight')][1]/descendant::tr[2]", $root);

            if (preg_match("/^(?<fName>[A-Z\d]{1,2})\s*(?<fNumber>\d{2,4})\s*(?<date>.+)\s+(?<depTime>[\d\:]+)\s+(?<arrTime>[\d\:]+)$/", $flightText, $m)) {
                $s->airline()
                    ->name($m['fName'])
                    ->number($m['fNumber']);

                $s->departure()
                    ->date(strtotime($m['date'] . ', ' . $m['depTime']));

                $s->arrival()
                    ->date(strtotime($m['date'] . ', ' . $m['arrTime']));
            }

            $seat = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Manage Bookings')]/following::text()[starts-with(normalize-space(), 'Passenger')][1]/following::table[1]/descendant::img[contains(@src, 'arrow')]/ancestor::tr[1]/descendant::td[1][{$this->eq($s->getDepCode())}]/ancestor::tr[1]/following::tr[3]/descendant::td[1]",
                null, true, "/^(\d{1,2}[A-Z])$/");

            if (!empty($seat)) {
                $s->extra()
                    ->seat($seat);
            }
        }
        $price = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Total Fare')]/ancestor::tr[1]/descendant::td[2]");

        if (preg_match("/^(?<currency>[A-Z]{3})\s*(?<total>[\d\.\,]+)$/u", $price, $m)) {
            $f->price()
                ->total(PriceHelper::parse($m['total'], $m['currency']))
                ->currency($m['currency']);

            $feeNodes = $this->http->XPath->query("//text()[contains(normalize-space(), 'Fare Summary')]/ancestor::tr[1]/following-sibling::tr[not(contains(normalize-space(), 'Total Fare'))]");

            foreach ($feeNodes as $feeRoot) {
                $feeName = $this->http->FindSingleNode("./descendant::td[1]", $feeRoot);
                $feeSum = $this->http->FindSingleNode("./descendant::td[2]", $feeRoot, true, "/\s([\d\,\.]+)/");
                $f->price()
                    ->fee($feeName, PriceHelper::parse($feeSum, $f->getPrice()->getCurrencyCode()));
            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
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
