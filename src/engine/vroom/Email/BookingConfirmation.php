<?php

namespace AwardWallet\Engine\vroom\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class BookingConfirmation extends \TAccountChecker
{
    public $mailFiles = "vroom/it-177110161.eml";
    public $subjects = [
        'BOOKING CONFIRMATION: VroomVroomVroom',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'Booking Details' => ['Booking Details', 'BOOKING DETAILS'],
            'Driver Details'  => ['Driver Details', 'DRIVER DETAILS'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@vroomvroomvroom.com') !== false) {
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
        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'Vroom Vroom Vroom') or contains(normalize-space(), 'vroomvroomvroom.com')]")->length > 0) {
            return $this->http->XPath->query("//text()[{$this->contains($this->t('Booking Details'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Pickup Location'))}]")->length > 0;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]vroomvroomvroom\.com.*$/', $from) > 0;
    }

    public function ParseRental(Email $email)
    {
        $r = $email->add()->rental();

        $r->general()
            ->confirmation($this->http->FindSingleNode("//text()[normalize-space()='booking confirmation number']/following::text()[normalize-space()][1]", null, true, "/^([A-Z\d]+)$/"))
            ->traveller($this->http->FindSingleNode("//text()[normalize-space()='Driver Details']/following::text()[normalize-space()='Name:'][1]/following::text()[normalize-space()][1]"));

        $r->setCompany($this->http->FindSingleNode("//text()[normalize-space()='booking confirmation number']/preceding::text()[normalize-space()][1]"));

        $type = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'or similar')]/following::text()[starts-with(normalize-space(), 'Type')]/ancestor::tr[1]/descendant::td[2]");

        if (empty($type)) {
            $type = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'or similar')]/following::text()[starts-with(normalize-space(), 'Class')]", null, true, "/{$this->opt($this->t('Class'))}\s*\:\s*(.+)/");
        }
        $r->car()
            ->model($this->http->FindSingleNode("//text()[contains(normalize-space(), 'or similar')][not(contains(normalize-space(), '}'))]"))
            ->type($type);

        $r->pickup()
            ->location($this->http->FindSingleNode("//text()[contains(normalize-space(), 'Pickup Location')]/following::text()[contains(normalize-space(), ':')][1]/preceding::text()[normalize-space()][1]/ancestor::tr[1]"))
            ->date($this->normalizeDate($this->http->FindSingleNode("//text()[contains(normalize-space(), 'Pickup Location')]/following::text()[contains(normalize-space(), ':')][1]")));

        $r->dropoff()
            ->location($this->http->FindSingleNode("//text()[contains(normalize-space(), 'Return Location')]/following::text()[contains(normalize-space(), ':')][1]/preceding::text()[normalize-space()][1]/ancestor::tr[1]"))
            ->date($this->normalizeDate($this->http->FindSingleNode("//text()[contains(normalize-space(), 'Return Location')]/following::text()[contains(normalize-space(), ':')][1]")));

        $price = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Total Cost')]/ancestor::tr[1]/descendant::td[2]");

        if (empty($price)) {
            $price = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Estimated Total')]/following::text()[normalize-space()][1]");
        }

        if (preg_match("/^\s*(?<currency>[A-Z]{3})\s*\D+(?<total>[\d\.\,]+)$/", $price, $m)) {
            $r->price()
                ->total(PriceHelper::parse($m['total'], $m['currency']))
                ->currency($m['currency']);
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->ParseRental($email);

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
            "#^(\d+\s*\w+\s*\d{4})\D+([\d\:]+\s*A?P?M)$#u", //18 August 2022 at 9:00 AM
        ];

        $out = [
            "$1 $2 $3",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }
}
