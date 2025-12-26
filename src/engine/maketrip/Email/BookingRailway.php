<?php

namespace AwardWallet\Engine\maketrip\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class BookingRailway extends \TAccountChecker
{
    public $mailFiles = "maketrip/it-689098180.eml";
    public $subjects = [
        'Booking confirmation details for Railway',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@quest2travel.com') !== false) {
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
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'Quest2Travel')]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Train No'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('The booking for the following request has been made and the PNR is given below. Please find the attached e-ticket.'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]quest2travel\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->parseRailways($email);
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function parseRailways(Email $email)
    {
        $t = $email->add()->train();

        $t->general()
            ->confirmation($this->http->FindSingleNode("//text()[normalize-space()='PNR No']/ancestor::tr[1][starts-with(normalize-space(), 'Passenger Name')]/following::tr[1]/descendant::td[3]"))
            ->travellers(preg_replace("/^(?:MS|MR|MRS)\.\s+/", "", $this->http->FindNodes("//text()[normalize-space()='PNR No']/ancestor::tr[1][starts-with(normalize-space(), 'Passenger Name')]/following-sibling::tr/td[1]")));

        $nodes = $this->http->XPath->query("//text()[normalize-space()='Departure Date']/ancestor::tr[1]/following-sibling::tr");

        foreach ($nodes as $root) {
            $s = $t->addSegment();

            $departureName = $this->http->FindSingleNode("./descendant::td[normalize-space()][1]", $root);

            if (preg_match("/^(?<depName>.+)\s*\-\s*(?<depCode>[A-Z]{2,4})$/su", $departureName, $m)) {
                $s->departure()
                    ->name($m['depName'])
                    ->code($m['depCode']);
            }

            $arrivalName = $this->http->FindSingleNode("./descendant::td[normalize-space()][2]", $root);
            $this->logger->debug($arrivalName);

            if (preg_match("/^(?<arrName>.+)\s*\-\s*(?<arrCode>[A-Z]{2,4})$/su", $arrivalName, $m)) {
                $s->arrival()
                    ->name($m['arrName'])
                    ->code($m['arrCode'])
                    ->noDate();
            }

            $depDateTime = $this->http->FindSingleNode("./descendant::td[normalize-space()][3]", $root);

            if (preg_match("/^(\d+)\-(\d+)\-(\d+)\s*(\d+\:\d+)\:\d+\s*(A?P?M)$/", $depDateTime, $m)) {
                $s->departure()
                    ->date(strtotime($m[1] . '.' . $m[2] . '.' . $m[3] . ', ' . $m[4] . $m[5]));
            }

            $trainNo = $this->http->FindSingleNode("./descendant::td[normalize-space()][4]", $root, true, "/^(\d+)$/");

            if (!empty($trainNo)) {
                $s->setNumber($trainNo);
            }

            $serviceName = $this->http->FindSingleNode("./descendant::td[normalize-space()][5]", $root);

            if (!empty($serviceName)) {
                $s->setServiceName($serviceName);
            }

            $travelClass = $this->http->FindSingleNode("./descendant::td[normalize-space()][6]", $root);

            if (!empty($travelClass)) {
                $s->setCabin($travelClass);
            }

            $price = $this->http->FindSingleNode("//text()[normalize-space()='Total Fare']/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Total Fare'))}\s*\:\s*([A-z\.]{3}\s+[\d\,\.]+)\s+/");

            if (preg_match("/^(?<currency>[A-z\.]{3})\s+(?<total>[\d\,\.]+)/us", $price, $m)) {
                $currency = $this->normalizeCurrency($m['currency']);
                $t->price()
                    ->total(PriceHelper::parse($m['total'], $currency))
                    ->currency($currency);
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

    private function normalizeCurrency($string)
    {
        $string = trim($string);
        $currencies = [
            'GBP' => ['£'],
            'INR' => ['Rs.'],
            'EUR' => ['€', 'Euro'],
            'USD' => ['US Dollar'],
        ];

        foreach ($currencies as $code => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $code;
                }
            }
        }

        return $string;
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
