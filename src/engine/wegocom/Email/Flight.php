<?php

namespace AwardWallet\Engine\wegocom\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Common\Hotel;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Flight extends \TAccountChecker
{
	public $mailFiles = "wegocom/it-804771777.eml, wegocom/it-812319522.eml, wegocom/it-821263781.eml";
    public $subjects = [
        'Here are your travel documents',
    ];

    public $lang = 'en';

    public static $dictionary = [
        'en' => [
            'detectPhrase' => ['Thank you for booking with Wego.'],
            'from' => ['from', 'after', 'before']
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], 'wego.com') !== false) {
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
        if ($this->http->XPath->query("//text()[{$this->contains($this->t('detectPhrase'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Itinerary Details'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->eq($this->t('Passenger Details'))}]")->length > 0) {
            return true;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]wego\.com$/', $from) > 0;
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

        $f->ota()
            ->confirmation($this->http->FindSingleNode("(//text()[{$this->starts($this->t('BOOKING ID'))}])[1]", null, false, "/^{$this->t('BOOKING ID')}\s*([\dA-Z\-]+)$/"), 'BOOKING ID');

        $f->setTravellers($this->http->FindNodes("//tr[td[{$this->eq($this->t('Passenger Details'))}]]/following-sibling::tr[position() > 2]/descendant::text()[normalize-space()][2]", null, "/^(?:MR|MRS|MSTR|MS|MISS|DR) ([[:alpha:]][-.\/\'’[:alpha:] ]*[[:alpha:]])$/u"), true);

        $confimationNumbers = array_unique($this->http->FindNodes("//text()[{$this->eq($this->t('PNR'))}]/ancestor::td[1]/descendant::text()[normalize-space()][2]", null, "/^[\dA-Z]{5,7}$/"));

        foreach ($confimationNumbers as $number){
            $f->general()
                ->confirmation($number, 'PNR');
        }

        $segmentNodes = $this->http->XPath->query("//img[contains(@src, '/time.png')]/ancestor::tr[2]");

        foreach ($segmentNodes as $root) {
            $s = $f->addSegment();

            $airInfo = $this->http->FindSingleNode("./preceding::td[not(.//td)][normalize-space()][3]", $root, true, '/^.+\s+((?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\d{1,4})/');

            if (preg_match("/^(?<aName>(?:[A-Z][A-Z\d]|[A-Z\d][A-Z]))(?<fNumber>\d{1,4})$/", $airInfo, $m)) {
                $s->airline()
                    ->name($m['aName'])
                    ->number($m['fNumber']);
            }
            $depDate = $this->http->FindSingleNode("./descendant::tr[.//td][normalize-space()][1]/descendant::text()[normalize-space()][1]", $root, null, "/^\w+\s*\,\s*(\d+\s*\w+\s*\d{4})$/");
            $depTime = $this->http->FindSingleNode("./preceding::td[.//td][normalize-space()][2]", $root, null, "/^[A-Z]{3}\s*(\d+:\d+(?:\s*[AP]M)?\s*)$/");

            if ($depDate !== null && $depTime !== null) {
                $s->departure()
                    ->date(strtotime($depDate . ' ' . $depTime));
            }

            $s->departure()
                ->name($this->http->FindSingleNode("./descendant::tr[.//td][normalize-space()][1]/descendant::text()[normalize-space()][2]", $root, null, "/^(.+)\,$/"))
                ->code($this->http->FindSingleNode("./preceding::td[.//td][normalize-space()][2]", $root, null, "/^([A-Z]{3})\s*\d+:\d+(?:\s*[AP]M)?\s*$/"));

            $depTerminal = $this->http->FindSingleNode("./descendant::tr[.//td][normalize-space()][1]/descendant::text()[normalize-space()][4]", $root, null, "/^{$this->opt($this->t('Terminal'))}\s*([\d\D]+)$/");

            if ($depTerminal !== null){
                $s->departure()
                    ->terminal($depTerminal);
            }

            $arrDate = $this->http->FindSingleNode("./descendant::tr[.//td][normalize-space()][3]/descendant::text()[normalize-space()][1]", $root, null, "/^\w+\s*\,\s*(\d+\s*\w+\s*\d{4})$/");
            $arrTime = $this->http->FindSingleNode("./preceding::td[.//td][normalize-space()][1]", $root, null, "/^\D{3}\s*(\d+:\d+(?:\s*[AP]M)?\s*)(?:\(\+\d+\)|$)/");

            if ($arrDate !== null && $arrTime !== null) {
                $s->arrival()
                    ->date(strtotime($arrDate . ' ' . $arrTime));
            }

            $s->arrival()
                ->name($this->http->FindSingleNode("./descendant::tr[.//td][normalize-space()][3]/descendant::text()[normalize-space()][2]", $root, null, "/^(.+)\,$/"))
                ->code($this->http->FindSingleNode("./preceding::td[.//td][normalize-space()][1]", $root, null, "/^([A-Z]{3})\s*\d+:\d+(?:\s*[AP]M)?\s*(?:\(\+\d+\)|$)/"));

            $arrTerminal = $this->http->FindSingleNode("./descendant::tr[.//td][normalize-space()][3]/descendant::text()[normalize-space()][4]", $root, null, "/^{$this->opt($this->t('Terminal'))}\s*([\d\D]+)$/");

            if ($arrTerminal !== null){
                $s->arrival()
                    ->terminal($arrTerminal);
            }

            $s->extra()
                ->cabin($this->http->FindSingleNode("./ancestor::tr[2]/preceding::tr[1]/descendant::td[1]/descendant::text()[normalize-space()][3]", $root))
                ->duration($this->http->FindSingleNode("./descendant::tr[.//td][normalize-space()][2]", $root, null, "/^(\d+\s*h\s*\d+m)$/"));
        }

        $priceInfo = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Total Fare'))}]/following::text()[normalize-space()][1]", null, true, "/^(\D{1,3}\s*\d[\d\.\,\`]*)$/");

        $discountArray[] = 0;

        if (preg_match("/^(?<currency>\D{1,3})\s*(?<price>\d[\d\.\,\']*)$/", $priceInfo, $m)
            || preg_match("/^(?<price>[\d\.\,\']+)\s*(?<currency>\D{1,3})$/", $priceInfo, $m)) {
            $f->price()
                ->total(PriceHelper::parse($m['price'], $m['currency']))
                ->currency($m['currency']);

            $cost = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Price Breakdown'))}]/following::tr[2]/descendant::td[2]", null, true, "/^\D{1,3}\s*(\d[\d\.\,\`]*)$/");

            if ($cost !== null) {
                $f->price()
                    ->cost(PriceHelper::parse($cost, $m['currency']));
            }

            $taxes = $this->http->XPath->query("//text()[{$this->eq($this->t('Total Tax'))}]/ancestor::td[2]/descendant::tr[position() > 4]");

            if ($taxes !== null) {
                foreach ($taxes as $root) {
                    $feeName = $this->http->FindSingleNode("./descendant::td[1]", $root);
                    $feeSum = $this->http->FindSingleNode("./descendant::td[2]", $root, true, '/^\D{1,3}\s*(\d[\d\.\,\`]*)/');

                    if ($feeName !== null && $feeSum !== null) {
                        $f->price()
                            ->fee($feeName, PriceHelper::parse($feeSum, $m['currency']));
                    }
                }
            }

            if ($feeName !== null){
                $feesNodes = $this->http->XPath->query("//tr[preceding-sibling::tr[{$this->contains($feeName)}] and following-sibling::tr[{$this->contains($this->t('Total Fare'))}]]");

                if ($feesNodes !== null) {
                    foreach ($feesNodes as $root) {
                        $feeName = $this->http->FindSingleNode("./descendant::text()[normalize-space()][1]", $root);
                        $feeSum = $this->http->FindSingleNode("./descendant::text()[normalize-space()][2]", $root, true, '/^\D{1,3}\s*([\d\.\,\`]+)/');

                        if ($feeName !== null && $feeSum !== null && stripos($feeName, 'Discount') !== 0) {
                            $f->price()
                                ->fee($feeName, PriceHelper::parse($feeSum, $m['currency']));
                        }

                        if (preg_match("/{$this->t('Discount')}/", $feeName)){
                            $discountArray[] = PriceHelper::parse($feeSum, $m['currency']);
                        }
                    }
                }
            }
        }

        if (array_sum($discountArray) !== 0){
            $f->price()
                ->discount(array_sum($discountArray));
        }
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
                return str_replace(' ', '\s+', preg_quote($s, '/'));
            }, $field)) . ')';
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
}
