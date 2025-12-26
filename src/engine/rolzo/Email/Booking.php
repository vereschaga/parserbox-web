<?php

namespace AwardWallet\Engine\rolzo\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Booking extends \TAccountChecker
{
    public $mailFiles = "rolzo/it-188086743.eml";
    public $subjects = [
        'Booking modified #',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'Your booking was' => ['Your booking was', 'Your booking is'],
            'Model:'           => ['Model:', 'Vehicle'],
            'Car rental'       => ['Car rental', 'Additional driver'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@protravelinc.com') !== false) {
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
        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'business.rolzo.com')]")->length > 0) {
            return $this->http->XPath->query("//text()[{$this->contains($this->t('Your booking was'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Car rental'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Model:'))}]")->length > 0;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]protravelinc\.com$/', $from) > 0;
    }

    public function ParseRental(Email $email)
    {
        $r = $email->add()->rental();

        $r->general()
            ->confirmation($this->http->FindSingleNode("//text()[normalize-space()='Booking number:']/following::text()[normalize-space()][1]"))
            ->travellers($this->http->FindNodes("//text()[normalize-space()='Driver:']/following::text()[normalize-space()][1]"));

        $status = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Your booking was')]", null, true, "/{$this->opt($this->t('Your booking was'))}\s*(\w+)/");

        if (!empty($status)) {
            $r->general()
                ->status($status);
        }

        $r->pickup()
            ->location($this->http->FindSingleNode("//text()[normalize-space()='Delivery location:']/following::text()[normalize-space()][1]"))
            ->date(strtotime($this->http->FindSingleNode("//text()[normalize-space()='Delivery date and time:']/following::text()[normalize-space()][1]")));

        $r->dropoff()
            ->location($this->http->FindSingleNode("//text()[normalize-space()='Collection location:']/following::text()[normalize-space()][1]"))
            ->date(strtotime($this->http->FindSingleNode("//text()[normalize-space()='Collection date and time:']/following::text()[normalize-space()][1]")));

        $r->car()
            ->model($this->http->FindSingleNode("//text()[normalize-space()='Vehicle:']/following::text()[normalize-space()][1]"));

        $carType = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Category:')]", null, true, "/{$this->opt($this->t('Category:'))}\s*(.+)/");

        if (!empty($carType)) {
            $r->car()
                ->type($carType);
        }

        $price = $this->http->FindSingleNode("//text()[normalize-space()='Total:']/following::text()[normalize-space()][1]");

        if (preg_match("/^(?<total>[\d\,\.]+)\s*(?<currency>[A-Z]{3})/", $price, $m)) {
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
}
