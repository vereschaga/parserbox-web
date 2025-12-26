<?php

namespace AwardWallet\Engine\houston\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

// TODO: merge with parsers laxparking/YourParking, parkbost/ParkingReservation (in favor of laxparking/YourParking)

class ParkingHtml extends \TAccountChecker
{
    public $mailFiles = "houston/it-531643823.eml";
    public $subjects = [
        ' - Your Parking for ',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'Your Reservation Confirmation'        => ['Your Reservation Confirmation', 'Your Parking Plus Reservation Confirmation'],
            'is located at'                        => ['is located at', 'are located at'],
            'George Bush Intercontinental Airport' => ['George Bush Intercontinental Airport', 'William P. Hobby Airport'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@fly2houston.com') !== false) {
            foreach ($this->subjects as $subject) {
                if (preg_match($subject, $headers['subject'])) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query("//text()[{$this->contains($this->t('George Bush Intercontinental Airport'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('is located at'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Your Reservation Confirmation'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]fly2houston\.com$/', $from) > 0;
    }

    public function ParseParking(Email $email)
    {
        $p = $email->add()->parking();

        $p->general()
            ->confirmation($this->http->FindSingleNode("//text()[normalize-space()='Confirmation Number:']/ancestor::tr[1]/descendant::td[2]", null, true, "/^[A-Z\-\d]+$/"))
            ->traveller($this->http->FindSingleNode("//text()[{$this->eq($this->t('Your Reservation Confirmation'))}]/following::text()[starts-with(normalize-space(), 'Hello')][1]", null, true, "/{$this->opt($this->t('Hello'))}\s*(.+)\,/"), false)
            ->date($this->normalizeDate($this->http->FindSingleNode("//text()[normalize-space()='Reservation Made:']/ancestor::tr[1]/descendant::td[2]")));

        $location = $this->http->FindSingleNode("//text()[normalize-space()='Parking Location:']/following::text()[normalize-space()][1]");

        if (!empty($location)) {
            if (strlen($location) > 2) {
                $p->setLocation($location);
            } else {
                $p->setLocation('Location: ' . $location);
            }
        }

        $p->setAddress($this->http->FindSingleNode("//text()[{$this->contains($this->t('is located at'))}]/following::a[1]"));

        $p->setStartDate($this->normalizeDate($this->http->FindSingleNode("//text()[normalize-space()='Entry:']/ancestor::tr[1]/descendant::td[2]")));
        $p->setEndDate($this->normalizeDate($this->http->FindSingleNode("//text()[normalize-space()='Exit:']/ancestor::tr[1]/descendant::td[2]")));

        $price = $this->http->FindSingleNode("//text()[normalize-space()='Total:']/ancestor::tr[1]/descendant::td[2]");

        if (preg_match("/^(?<currency>\D{1,3})\s+(?<total>[\d\.\,]+)$/", $price, $m)) {
            $p->price()
                ->currency($m['currency'])
                ->total(PriceHelper::parse($m['total'], $m['currency']));
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->ParseParking($email);

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

    private function normalizeDate($str)
    {
        $this->logger->debug('$str = ' . print_r($str, true));

        $in = [
            "#^(\w+)\s*(\d+)\,\s*(\d{4})\s*at\s*(\d+\:\d+)\:\d+\s*(A?P?M)$#u", //Oct 3, 2023 at 5:00:00 AM
        ];
        $out = [
            "$2 $1 $3, $4$5",
        ];
        $str = preg_replace($in, $out, $str);
//        $this->logger->debug('$str = '.print_r( $str,true));

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'normalize-space(' . $node . ')=' . $s;
        }, $field)) . ')';
    }
}
