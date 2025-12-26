<?php

namespace AwardWallet\Engine\mozio\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class TransferConfirmed extends \TAccountChecker
{
    public $mailFiles = "mozio/it-788383424.eml, mozio/it-788651245.eml, mozio/it-815992571.eml";
    public $subjects = [
        'Your transfer is confirmed with ',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'Your reservation details' => 'Your reservation details',
            'PICK UP'                  => 'PICK UP',
            'DROP OFF'                 => 'DROP OFF',
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@mozio.com') !== false) {
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
        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'Mozio Inc.')]")->length === 0
         && $this->http->XPath->query("//text()[contains(normalize-space(), 'Opodo')]")->length === 0) {
            return false;
        }

        foreach (self::$dictionary as $dict) {
            if (!empty($dict['Your reservation details']) && !empty($dict['PICK UP']) && !empty($dict['DROP OFF'])
                && $this->http->XPath->query("//text()[{$this->eq($dict['Your reservation details'])}]")->length > 0
                && $this->http->XPath->query("//tr[*[1]/descendant::text()[normalize-space()][1][{$this->eq($dict['PICK UP'])}]][*[2]/descendant::text()[normalize-space()][1][{$this->eq($dict['DROP OFF'])}]]")->length > 0
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]mozio\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict['Your reservation details'])
                && $this->http->XPath->query("//text()[{$this->eq($dict['Your reservation details'])}]")->length > 0
            ) {
                $this->lang = $lang;

                break;
            }
        }

        $this->ParseTransfer($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function ParseTransfer(Email $email)
    {
        $t = $email->add()->transfer();

        $pax = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Passenger info'))}]/following::text()[{$this->eq($this->t('Name:'))}][1]/following::text()[normalize-space()][1]", null, true, "/^([[:alpha:]][-.\/\'’[:alpha:] ]*[[:alpha:]])$/");

        if (empty($pax)) {
            $pax = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Passenger info'))}]/following::text()[{$this->starts($this->t('Name:'))}][1]", null, true, "/{$this->opt($this->t('Name:'))}\s*([[:alpha:]][-.\/\'’[:alpha:] ]*[[:alpha:]])$/");
        }

        $t->general()
            ->traveller($pax)
            ->notes($this->http->FindSingleNode("//text()[{$this->starts($this->t('Pickup Instructions for'))}]/ancestor::*[{$this->starts($this->t('Pickup Instructions for'))}][last()]"))
            ->cancellation($this->http->FindSingleNode("//text()[{$this->eq($this->t('Changes and cancellation policy'))}]/following::text()[normalize-space()][1][not(ancestor::a)]/ancestor::*[not(.//text()[{$this->eq($this->t('Changes and cancellation policy'))}])][last()]"));

        $tableXpath = "//tr[*[1]/descendant::text()[normalize-space()][1][{$this->eq($this->t('PICK UP'))}]][*[2]/descendant::text()[normalize-space()][1][{$this->eq($this->t('DROP OFF'))}]]";

        $nodes = $this->http->XPath->query($tableXpath);

        foreach ($nodes as $root) {
            $s = $t->addSegment();

            $t->general()
                ->confirmation($this->http->FindSingleNode("./preceding::text()[{$this->eq($this->t('Confirmation number:'))}][1]/following::text()[normalize-space()][1]",
                    $root, true, "/^\s*([\dA-z\-]{5,})\s*$/"));

            $date = strtotime($this->http->FindSingleNode("./preceding::table[1][count(descendant::text()[normalize-space()]) = 3]/descendant::text()[normalize-space()][1]", $root));
            $deps = $this->http->FindNodes("./*[1]/descendant::text()[normalize-space()][position() > 1]", $root);

            if (count($deps) >= 2) {
                if (!empty($date) && preg_match("/^\s*\d{1,2}:\d{2}(?: *[ap]m)?\s*$/i", $deps[0])) {
                    $s->departure()
                        ->date(strtotime($deps[0], $date));
                }

                if (preg_match("/^\s*([A-Z]{3})\s*^/", $deps[1], $m)) {
                    $s->departure()
                        ->code($deps[1]);
                } else {
                    $s->departure()
                        ->name($deps[1]);
                }
            }

            $arrs = $this->http->FindNodes("./*[2]/descendant::text()[normalize-space()][position() > 1]", $root);

            if (count($arrs) >= 1) {
                if (preg_match("/^\s*([A-Z]{3})\s*^/", $arrs[0], $m)) {
                    $s->arrival()
                        ->code($arrs[0]);
                } else {
                    $s->arrival()
                        ->name($arrs[0]);
                }
                $s->arrival()
                    ->noDate();
            }

            // Extra
            $adults = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Passenger info'))}]/following::text()[{$this->eq($this->t('Number of Passengers:'))}][1]/following::text()[normalize-space()][1]",
                null, true, "/^\s*(\d+)\s*$/");

            if (empty($adults)) {
                $adults = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Passenger info'))}]/following::text()[{$this->starts($this->t('Number of Passengers:'))}][1]",
                    null, true, "/^{$this->opt($this->t('Number of Passengers:'))}\s*(\d+)\s*$/");
            }

            $s->extra()
                ->adults($adults)
                ->type($this->http->FindSingleNode("//text()[{$this->eq($this->t('Your reservation details'))}]/following::text()[normalize-space()][1]"))
            ;
        }

        // Price
        $totals = $this->http->FindNodes("//text()[{$this->eq($this->t('Payment Information'))}]/following::text()[normalize-space()][1][not(ancestor::a)]/ancestor::*[not(.//text()[{$this->eq($this->t('Payment Information'))}])][last()]"
            . "/descendant::tr[count(*) = 2][normalize-space()]/*[2]");
        $total = '';

        if (count($totals) === 1) {
            $total = $totals[0];
        }

        if (preg_match("/^\s*(?<currency>[^\d]{1,8}?) *(?<amount>\d[\d.,]+)\n*/", $total, $m)
            || preg_match("/^\s*(?<amount>\d[\d.,]+?) ?(?<currency>[^\d\s]{1,8})\n/", $total, $m)
        ) {
            $currency = $this->normalizeCurrency($m['currency']);
            $email->price()
                ->total(PriceHelper::parse($m['amount']), $currency)
                ->currency($currency);
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

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field));
    }

    private function starts($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'starts-with(normalize-space(' . $node . '),"' . $s . '")'; }, $field)) . ')';
    }

    private function contains($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'contains(normalize-space(' . $node . '),"' . $s . '")'; }, $field)) . ')';
    }

    private function normalizeCurrency($string)
    {
        $string = trim($string);

        if (preg_match("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $string, $m)) {
            return $m[1];
        }

        $currencies = [
            'GBP' => ['£'],
            'AUD' => ['A$'],
            'EUR' => ['€', 'Euro'],
            'USD' => ['US Dollar', 'US$'],
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
