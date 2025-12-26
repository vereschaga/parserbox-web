<?php

namespace AwardWallet\Engine\transavia\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class AirBooking extends \TAccountChecker
{
    public $mailFiles = "transavia/it-177992733.eml";
    public $subjects = [
        '/Booking confirmation \D+ to \D+ with Transavia Smart Connect$/',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@dohop.com') !== false) {
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
        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'Transavia Smart Connect')]")->length > 0) {
            return $this->http->XPath->query("//text()[{$this->contains($this->t('How does Transavia Smart Connect work'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Booking number'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Summary'))}]")->length > 0;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]dohop.com$/', $from) > 0;
    }

    public function ParseFlight(Email $email)
    {
        $f = $email->add()->flight();

        $confNumbers = array_filter(array_unique($this->http->FindNodes("//text()[normalize-space()='Booking number']/following::text()[normalize-space()][1]")));

        foreach ($confNumbers as $confNumber) {
            $f->general()
                ->confirmation($confNumber);
        }

        $f->general()
            ->travellers(array_unique($this->http->FindNodes("//text()[normalize-space()='Summary']/ancestor::tr[1]/following::table[1]/descendant::tr/td[1][contains(normalize-space(), '. ')]", null, "/^\d\.\s+(\D+)$/")));

        $nodes = $this->http->XPath->query("//img[contains(@src, 'takeoff')]/ancestor::tr[1]");

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $s->airline()
                ->name($this->http->FindSingleNode("./following::tr[1]/descendant::text()[normalize-space()][2]", $root, true, "/^([A-Z\d]{2})/"))
                ->number($this->http->FindSingleNode("./following::tr[1]/descendant::text()[normalize-space()][2]", $root, true, "/^[A-Z\d]{2}\s*(\d{2,4})/"));

            $date = $this->http->FindSingleNode("./preceding::text()[contains(normalize-space(), 'flights -')]", $root, true, "/{$this->opt($this->t('flights -'))}\s*(.+)/");
            $depTime = $this->http->FindSingleNode("./descendant::td[normalize-space()][1]", $root, true, "/^([\d\:]+)$/");

            $s->departure()
                ->date(strtotime($date . ', ' . $depTime))
                ->code($this->http->FindSingleNode("./descendant::td[normalize-space()][last()]", $root, true, "/\s([A-Z]{3})$/"));

            $arrTime = $this->http->FindSingleNode("./following::img[contains(@src, 'landing')][1]/ancestor::tr[1]/descendant::td[1]", $root);
            $s->arrival()
                ->date(strtotime($date . ', ' . $arrTime))
                ->code($this->http->FindSingleNode("./following::img[contains(@src, 'landing')][1]/ancestor::tr[1]/descendant::td[normalize-space()][last()]", $root, true, "/\s([A-Z]{3})$/"));

            $s->extra()
                ->duration($this->http->FindSingleNode("./following::tr[1]/descendant::text()[normalize-space()][1]", $root));
        }

        $price = $this->http->FindSingleNode("//text()[normalize-space()='Total price including all taxes and fees']/following::text()[normalize-space()][1]");

        if (preg_match("/^(?<currency>\D)\s*(?<total>[\d.\,]+)$/", $price, $m)) {
            $currency = $this->normalizeCurrency($m['currency']);

            $f->price()
                ->total(PriceHelper::parse($m['total'], $currency))
                ->currency($currency);
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $email->ota()
            ->confirmation($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Thank you for booking with')]/following::text()[contains(normalize-space(), 'booking number')][1]/following::text()[normalize-space()][1]"),
                $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Thank you for booking with')]/following::text()[contains(normalize-space(), 'booking number')][1]"));

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

    private function normalizeCurrency(string $string)
    {
        $string = trim($string);
        $currencies = [
            'GBP' => ['£'],
            'EUR' => ['€'],
            '$'   => ['$'],
            'INR' => ['Rs.'],
            'USD' => ['US$'],
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
}
