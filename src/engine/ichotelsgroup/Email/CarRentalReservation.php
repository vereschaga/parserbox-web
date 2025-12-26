<?php

namespace AwardWallet\Engine\ichotelsgroup\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class CarRentalReservation extends \TAccountChecker
{
    public $mailFiles = "ichotelsgroup/it-167365624.eml";

    private $detectFrom = "IHGOneRewards@tx.ihg.com";
    private $detectSubject = [
        'car rental reservation is confirmed.', //Your Hertz Cayman Airport car rental reservation is confirmed.
    ];
    private $lang = 'en';
    private static $dictionary = [
        'en' => [
            'Member Number:' => 'Member Number:',
            'Vehicle:'       => 'Vehicle:',
        ],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->parseEmail($email);

        if (count($email->getItineraries()) > 0) {
            // Statement
            $text = implode("\n", $this->http->FindNodes("(//text()[{$this->eq($this->t("Sign In"))}]/ancestor::td)[1]//text()[normalize-space()]"));

            if (preg_match("/^\s*(?<name>[[:alpha:]][[:alpha:] \-\.]+)\s*\n\s*(?<number>\d{5,})\s*\n\s*{$this->opt($this->t("Sign In"))}/u", $text, $m)) {
                $st = $email->add()->statement();

                $st
                    ->setNoBalance(true)
                    ->addProperty('Name', $m['name'])
                    ->setLogin($m['number'])
                    ->setNumber($m['number'])
                ;
            }
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href, '.ihg.com')]")->length === 0) {
            return false;
        }

        foreach (self::$dictionary as $dict) {
            if (!empty($dict["Vehicle:"])
                && (!empty($dict["Member Number:"]) && $this->http->XPath->query("//text()[{$this->contains($dict["Member Number:"])}]/following::text()[normalize-space()][position() < 10][{$this->eq($dict["Vehicle:"])}]")->length > 0)
            || $this->http->XPath->query("//text()[contains(normalize-space(), 'Your Houston Hobby Ap car is confirmed')]/preceding::text()[normalize-space()='Sign In']")->length > 0) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (!isset($headers['from']) || !isset($headers["subject"])
            || stripos($headers['from'], $this->detectFrom) === false
        ) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers["subject"], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectFrom) !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function parseEmail(Email $email)
    {
        $r = $email->add()->rental();

        // General
        $r->general()
            ->noConfirmation();

        $fname = trim($this->http->FindSingleNode("//tr[not(.//tr) and count(*[normalize-space()]) = 2 and *[normalize-space()][1][{$this->eq($this->t("First Name:"))}]]/*[normalize-space()][2]"));
        $lname = trim($this->http->FindSingleNode("//tr[not(.//tr) and count(*[normalize-space()]) = 2 and *[normalize-space()][1][{$this->eq($this->t("Last name:"))}]]/*[normalize-space()][2]"));

        if (!empty($fname) && !empty($lname)) {
            $r->general()
                ->traveller($fname . ' ' . $lname);
        } else {
            $r->general()
                ->traveller(null);
        }

        // Pick Up
        $location = implode("\n", $this->http->FindNodes("//tr[not(.//tr) and count(*[normalize-space()]) = 2 and *[normalize-space()][1][{$this->eq($this->t("Pick up:"))}]]/*[normalize-space()][2]//text()[normalize-space()]"));

        if (preg_match("/\s*(.*\b\d{4}\b.*)\n(.+)\n(?:Airport\s*\n)?([\s\S]+)$/", $location, $m)) {
            $r->pickup()
                ->location(preg_replace("/\s*\n\s*/", ', ', trim($m[2] . "\n" . $m[3])))
                ->date($this->normalizeDate($m[1]))
            ;
        }
        // Drop Off
        $location = implode("\n", $this->http->FindNodes("//tr[not(.//tr) and count(*[normalize-space()]) = 2 and *[normalize-space()][1][{$this->eq($this->t("Drop off:"))}]]/*[normalize-space()][2]//text()[normalize-space()]"));

        if (preg_match("/\s*(.*\b\d{4}\b.*)\n(.+)\n(?:Airport\s*\n)?([\s\S]+)$/", $location, $m)) {
            $r->dropoff()
                ->location(preg_replace("/\s*\n\s*/", ', ', trim($m[2] . "\n" . $m[3])))
                ->date($this->normalizeDate($m[1]))
            ;
        }
        // Program
        $account = $this->http->FindSingleNode("//tr[not(.//tr) and count(*[normalize-space()]) = 2 and *[normalize-space()][1][{$this->eq($this->t("Hertz Gold® Member Number:"))}]]/*[normalize-space()][2]");

        if (empty($account)) {
            $account = $this->http->FindSingleNode("//text()[normalize-space()='First Name:']/preceding::text()[contains(normalize-space(), 'Sign In')][1]/preceding::text()[normalize-space()][1]");
        }

        if (!empty($account)) {
            $r->program()
                ->account($account, true);
        }

        // Car
        $r->car()
            ->type($this->http->FindSingleNode("//tr[not(.//tr) and count(*[normalize-space()]) = 2 and *[normalize-space()][1][{$this->eq($this->t("Vehicle:"))}]]/*[normalize-space()][2]"), true);

        // Total
        $total = $this->http->FindSingleNode("//tr[not(.//tr) and count(*[normalize-space()]) = 2 and *[normalize-space()][1][{$this->eq($this->t("Estimated Total:"))}]]/*[normalize-space()][2]");

        if (preg_match("#^\s*[$]\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[A-Z]{3})\s*$#", $total, $m)) {
            $r->price()
                ->total(PriceHelper::parse($m['amount'], $m['currency']))
                ->currency($m['currency'])
            ;
        } else {
            $r->price()
                ->total(null);
        }

        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'Here is a copy of your Houston Hobby Ap cancellation.')]")->length > 0) {
            $r->general()
                ->cancelled();
        }

        return $email;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dictionary[$this->lang][$s])) {
            return $s;
        }

        return self::$dictionary[$this->lang][$s];
    }

    private function normalizeDate($date)
    {
        $in = [
            // Sat 01,Apr 2023
            '/^\s*\w+[,\s]+(\d{1,2})[,\s]+([[:alpha:]]+)\s+(\d{4})\s*$/',
        ];
        $out = [
            '$1 $2 $3',
        ];
        $str = preg_replace($in, $out, $date);
//        $this->logger->debug('$str = '.print_r( $str,true));
        //		$str = $this->dateStringToEnglish($str);
        return strtotime($str);
    }

    private function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function nextText($field, $root = null, $regexp = null, $n = 1)
    {
        $nextText = $this->re("#{$this->opt($field)}\s+(.+)#", $root);

        if (isset($regexp)) {
            if (preg_match($regexp, $nextText, $m)) {
                return $m[$n];
            } else {
                return null;
            }
        }

        return $nextText;
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s*', preg_quote($s));
        }, $field)) . ')';
    }

    private function contains($field, $node = 'normalize-space(.)'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(' . $node . ",'" . $s . "')";
        }, $field)) . ')';
    }

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ")='" . $s . "'";
        }, $field)) . ')';
    }

    private function starts($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'starts-with(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }
}
