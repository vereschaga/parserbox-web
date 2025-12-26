<?php

namespace AwardWallet\Engine\goldpassport\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class GrandHyattBookingConfirmation extends \TAccountChecker
{
    public $mailFiles = "goldpassport/it-32836033.eml, goldpassport/it-636097896.eml";

    public $reFrom = ["hyatt.com"];
    public $reBody = [
        'en' => ['Confirmation Number:', 'Service:'],
    ];
    public $reSubject = [
        '/Grand Hyatt .+? Booking Confirmation# \d+/',
    ];
    public $lang = '';
    public static $dict = [
        'en' => [],
    ];
    private $keywordProv = 'Hyatt';

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }
        $email->setType('GrandHyattBookingConfirmation' . ucfirst($this->lang));

        $this->parseEmail($email);

        if (stripos($parser->getCleanFrom(), 'destinationhotels.com') !== false
            || $this->http->XPath->query("//a[contains(@href, '.destinationhotels.com/')]")->length > 0
        ) {
            $email->setProviderCode('dhotels');
        }

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(normalize-space(),'Hyatt.com') or contains(normalize-space(),'hyatt.com')]")->length > 0
            && $this->http->XPath->query("//text()[contains(normalize-space(),'Hyatt Corporation. All rights reserved')]")
        ) {
            return $this->assignLang();
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->reFrom as $reFrom) {
            if (stripos($from, $reFrom) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        $fromProv = true;

        if (!isset($headers['from']) || $this->detectEmailFromProvider($headers['from']) === false) {
            $fromProv = false;
        }

        if (isset($headers["subject"])) {
            foreach ($this->reSubject as $reSubject) {
                if (($fromProv && preg_match($reSubject, $headers["subject"]) > 0)
                    || strpos($headers["subject"], $this->keywordProv) !== false
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    public static function getEmailProviders()
    {
        return ['goldpassport', 'dhotels'];
    }

    public function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = \AwardWallet\Engine\MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function parseEmail(Email $email): void
    {
        $confirmation = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Confirmation Number:'))}]");

        if (preg_match("/({$this->opt($this->t('Confirmation Number:'))})\s*([-A-Z\d]{5,})$/", $confirmation, $m)) {
            $email->ota()->confirmation($m[2], rtrim($m[1], ': '));
        }
        $xpath = "//text()[{$this->eq('Service:')}]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $r = $email->add()->event();

            $r->type()->event();

            $r->general()
                ->noConfirmation();

            $traveller = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Dear'))}]", null, false,
                "/{$this->opt($this->t('Dear'))}\s+([[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]])(?:\s*[,:;!?]|$)/u");

            if ($traveller) {
                $r->general()->traveller($traveller);
            }

            $name = $this->http->FindSingleNode("following::text()[normalize-space()!=''][1]", $root);
            $r->place()
                ->address($this->http->FindSingleNode("//text()[{$this->eq('Thank you for choosing')}]/following::text()[normalize-space()!=''][1]"))
                ->name($name);

            $date = $this->normalizeDate($this->http->FindSingleNode("ancestor::tr[1][{$this->starts($this->t('Service:'))}]/following-sibling::tr[1][{$this->starts('Date:')}]",
                $root, true, "/^\s*{$this->opt('Date:')}\s*(.+)/"));
            $time = $this->http->FindSingleNode("ancestor::tr[1][{$this->starts($this->t('Service:'))}]/following-sibling::tr[2][{$this->starts('Time:')}]",
                $root, true, "/^\s*{$this->opt('Time:')}\s*(.+)/");
            $start = strtotime($time, $date);
            $r->booked()
                ->start($start);

            if (preg_match("/.{2}[ ]+(\d{1,3})[ ]*min$/i", $name, $m)) {
                $r->booked()->end(strtotime("+ " . $m[1] . ' minutes', $start));
            } else {
                $r->booked()->noEnd();
            }

            $cost = $this->http->FindSingleNode("ancestor::tr[1][{$this->starts($this->t('Service:'))}]/following-sibling::tr[3][{$this->starts('Cost:')}]",
                $root, true, "/^\s*{$this->opt('Cost:')}\s*(.*\d.*)/");

            if ($cost !== null) {
                $total = $this->getTotalCurrency($cost);
                $r->price()->currency($total['Currency'])->total($total['Total']);
            }
        }
    }

    private function normalizeDate($date)
    {
        $in = [
            //Sunday, January 27, 2019
            '#^(\w+),\s+(\w+)\s+(\d+),\s+(\d{4})$#u',
        ];
        $out = [
            '$3 $2 $4',
        ];
        $str = strtotime($this->dateStringToEnglish(preg_replace($in, $out, $date)));

        return $str;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang(): bool
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if ($this->http->XPath->query("//*[contains(normalize-space(.),'{$reBody[0]}')]")->length > 0
                    && $this->http->XPath->query("//*[contains(normalize-space(.),'{$reBody[1]}')]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function getTotalCurrency($node)
    {
        $node = str_replace("€", "EUR", $node);
        $node = str_replace("$", "USD", $node);
        $node = str_replace("£", "GBP", $node);
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)
            || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m)
        ) {
            $cur = $m['c'];
            $tot = PriceHelper::cost($m['t']);
        }

        return ['Total' => $tot, 'Currency' => $cur];
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

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }
}
