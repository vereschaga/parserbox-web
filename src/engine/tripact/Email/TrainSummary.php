<?php

namespace AwardWallet\Engine\tripact\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class TrainSummary extends \TAccountChecker
{
    public $mailFiles = "tripact/it-122619665.eml";
    public $subjects = [
        '/(?:Canceled|Confirmed)\s*\-.+\s*\|\D+\s*\([A-Z\d]+\)/',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@tripactions.com') !== false) {
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
        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'TripActions Inc')]")->length > 0) {
            return $this->http->XPath->query("//text()[{$this->contains($this->t('Transaction ID'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Please allow 20 minutes to collect your ticket at the station'))}]")->length > 0;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]tripactions.com$/', $from) > 0;
    }

    public function ParseTrain(Email $email)
    {
        $t = $email->add()->train();

        $t->general()
            ->noConfirmation();

        $t->general()
            ->traveller($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Passenger:')]/following::text()[normalize-space()][1]"), true);

        $cost = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Subtotal')]/following::text()[normalize-space()][1]");

        if (!empty($cost)) {
            $t->price()
                ->cost(PriceHelper::cost($cost, ',', '.'));
        }

        $tax = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Taxes')]/following::text()[normalize-space()][1]");

        if (!empty($tax)) {
            $t->price()
                ->tax(PriceHelper::cost($tax, ',', '.'));
        }

        $price = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Total')]/following::text()[normalize-space()][1]/ancestor::div[1]");

        if (preg_match("/^([\d\.\,]+)\s*([A-Z]{3})/", $price, $m)) {
            $t->price()
                ->total(PriceHelper::cost($m[1], ',', '.'))
                ->currency($m[2]);
        }

        $nodes = $this->http->XPath->query("//img[contains(@src, 'train-arrow')]");

        foreach ($nodes as $root) {
            $s = $t->addSegment();

            $date = $this->http->FindSingleNode("./preceding::text()[string-length()>1][4]", $root);
            $depTime = $this->http->FindSingleNode("./preceding::text()[string-length()>1][2]", $root);

            $s->departure()
                ->name($this->http->FindSingleNode("./preceding::text()[string-length()>1][1]", $root))
                ->date(strtotime($date . ' ' . $depTime));

            $arrTime = $this->http->FindSingleNode("./following::text()[string-length()>1][2]", $root);

            $s->arrival()
                ->name($this->http->FindSingleNode("./following::text()[string-length()>1][3]", $root))
                ->date(strtotime($date . ' ' . $arrTime));

            $s->extra()->number($this->http->FindSingleNode("./preceding::text()[string-length()>1][3]", $root));
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        if (preg_match("/(?:Canceled|Confirmed)\s*\-.+\s*\|\D+\s*\(([A-Z\d]+)\)/", $parser->getSubject(), $m)) {
            $email->ota()->confirmation($m[1]);
        }

        $this->ParseTrain($email);

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

    private function normalizeDate($date)
    {
        $in = [
            '#^\w+\,\s*(\w+)\s*(\d+)\,\s*(\d{4})\s*at\s*([\d\:]+\s*A?P?M)$#', //Sun, Nov 7, 2021 at 3:00PM
        ];
        $out = [
            '$2 $1 $3, $4',
        ];
        $str = preg_replace($in, $out, $date);

        return strtotime($str);
    }
}
