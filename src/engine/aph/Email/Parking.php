<?php

namespace AwardWallet\Engine\aph\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Parking extends \TAccountChecker
{
	public $mailFiles = "aph/it-848936203.eml, aph/it-863132543.eml";
    public $subjects = [
        'Booking Confirmation',
    ];

    public $lang = 'en';

    public static $dictionary = [
        'en' => [
            'Thank you for your booking' => 'Thank you for your booking',
            'Car park details' => 'Car park details',
            'Booking summary' => 'Booking summary',
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], 'aph.com') !== false) {
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
        if (stripos($parser->getHeader('from'), 'aph.com') === false
            && $this->http->XPath->query("//*[{$this->contains(['Airport Parking and Hotels'])}]")->length === 0
        ) {
            return false;
        }

        foreach (self::$dictionary as $dict) {
            if (!empty($dict['Thank you for your booking']) && $this->http->XPath->query("//*[{$this->contains($dict['Thank you for your booking'])}]")->length > 0
                && !empty($dict['Car park details']) && $this->http->XPath->query("//*[{$this->contains($dict['Car park details'])}]")->length > 0
                && !empty($dict['Booking summary']) && $this->http->XPath->query("//*[{$this->contains($dict['Booking summary'])}]")->length > 0
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]aph\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->parseParking($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    private function parseParking(Email $email)
    {
        $p = $email->add()->parking();

        $p->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('Booking ref:'))}]/following::text()[normalize-space()][1]", null, false, "/^([A-Z\d]{5,7})$/"));

        $bookingDate = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Booking date:'))}]/following::text()[normalize-space()][1]", null, false, "/^(\d{1,2}[ ]+[[:alpha:]]+[ ]+\d{4})$/");

        if ($bookingDate !== null){
            $p->general()
                ->date(strtotime($bookingDate));
        }

        $firstName = $this->http->FindSingleNode("//text()[{$this->eq($this->t('First name:'))}]/following::text()[normalize-space()][1]", null, false, "/^([[:alpha:]][-.\''[:alpha:] ]*)$/");;
        $lastName = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Last name:'))}]/following::text()[normalize-space()][1]", null, false, "/^([[:alpha:]][-.\/\'’[:alpha:] ]*[[:alpha:]])$/");;

        if ($firstName !== null && $lastName !== null){
            $p->addTraveller($firstName . ' ' . $lastName, true);
        }

        $p->place()
            ->location($this->http->FindSingleNode("//text()[{$this->eq($this->t('Car park name:'))}]/following::text()[normalize-space()][1]"))
            ->address($this->http->FindSingleNode("//text()[{$this->eq($this->t('Car park address:'))}]/following::text()[normalize-space()][1]"));

        $p->booked()
            ->start(strtotime($this->http->FindSingleNode("//text()[{$this->eq($this->t('Arrive:'))}]/following::text()[normalize-space()][1]", null, false, "/^(\d{1,2}[ ]+[[:alpha:]]+[ ]+\d{4}[ ]+\d{1,2}\:\d{1,2}[ ]*A?P?M?)$/")))
            ->end(strtotime($this->http->FindSingleNode("//text()[{$this->eq($this->t('Return:'))}]/following::text()[normalize-space()][1][./preceding::text()[normalize-space()][3][{$this->eq($this->t('Arrive:'))}]]", null, false, "/^(\d{1,2}[ ]+[[:alpha:]]+[ ]+\d{4}[ ]+\d{1,2}\:\d{1,2}[ ]*A?P?M?)$/")));

        $carInfo = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Car:'))}]/following::text()[normalize-space()][1]");

        if (preg_match("/^(?<carPlate>[A-z0-9\- ]+)[ ]\-[ ](?<carName>.+)$/", $carInfo, $m)){
            $p->booked()
                ->car($m[2])
                ->plate($m[1]);
        }

        $phone = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Car park contact number:'))}]/following::text()[normalize-space()][1]", null, false, "/^([0-9\(\-\)\+ \/]+)$/");

        if ($phone !== null){
            $p->place()
                ->phone($phone);
        }

        $priceInfo = $this->http->FindSingleNode("//td[{$this->eq($this->t('Total'))}]/following-sibling::td[normalize-space()][1]");

        if (preg_match("/^(?<currency>\D{1,3})[ ]*(?<price>\d[\d\.\,\' ]*)$/", $priceInfo, $m) ||
            preg_match("/^(?<price>\d[\d\.\,\' ]*)[ ]*(?<currency>\D{1,3})$/", $priceInfo, $m) ) {
            $p->price()
                ->currency($currency = $this->normalizeCurrency($m['currency']))
                ->total(PriceHelper::parse($m['price'], $currency))
                ->cost(PriceHelper::parse($this->http->FindSingleNode("//td[{$this->eq($this->t('Total'))}]/ancestor::tr[2]/preceding-sibling::tr[./descendant::th[{$this->eq($this->t('Product'))}][1]]/following-sibling::tr[1]/descendant::td[normalize-space()][last()]", null, false, "/^\D{1,3}\s*(\d[\d\.\,\' ]*)$/"), $currency))
                ->tax(PriceHelper::parse($this->http->FindSingleNode("//td[{$this->eq($this->t('Total'))}]/ancestor::tr[2]/preceding-sibling::tr[{$this->contains($this->t('VAT'))}][1]/descendant::td[normalize-space()][last()]", null, false, "/^\D{1,3}\s*(\d[\d\.\,\' ]*)$/"), $currency));
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

    private function normalizeCurrency(string $string): string
    {
        $string = trim($string);
        $currences = [
            'RUB' => ['Руб.'],
            'GBP' => ['£'],
            'EUR' => ['€'],
            'USD' => ['$', 'US$'],
        ];

        foreach ($currences as $currencyCode => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $currencyCode;
                }
            }
        }

        return $string;
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
                return str_replace(' ', '\s+', preg_quote($s));
            }, $field)) . ')';
    }

    private function t($s)
    {
        if (!isset($this->lang, self::$dictionary[$this->lang], self::$dictionary[$this->lang][$s])) {
            return $s;
        }

        return self::$dictionary[$this->lang][$s];
    }

    private function contains($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) {
                return 'contains(' . $text . ',"' . $s . '")';
            }, $field)) . ')';
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
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

        return '(' . implode(' or ', array_map(function ($s) {
                return 'starts-with(normalize-space(.),"' . $s . '")';
            }, $field)) . ')';
    }
}
