<?php

namespace AwardWallet\Engine\jetcom\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class AboutFlight extends \TAccountChecker
{
    public $mailFiles = "jetcom/it-153204579.eml";
    public $subjects = [
        'Please read - Important notice about your Jet2.com flights',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@jet2.com') !== false) {
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
        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'Jet2.com Customer Service Team')]")->length > 0) {
            return $this->http->XPath->query("//text()[contains(normalize-space(), 'NEW Flight Times')]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Your Booking Reference Number(s)'))}]")->length > 0;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]jet2\.com$/', $from) > 0;
    }

    public function ParseFlight(Email $email)
    {
        $f = $email->add()->flight();

        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Your Booking Reference Number(s)')]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Your Booking Reference Number(s)'))}\s*([\dA-Z]+)/u"))
            ->traveller(trim($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Your flight to')]/preceding::text()[normalize-space()][1]"), ','));

        $xpath = "//text()[starts-with(normalize-space(), 'NEW Flight Times')]/ancestor::table[1]/descendant::tr[contains(normalize-space(), ':')]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            if (!empty($this->http->FindSingleNode("./descendant::td[2]", $root))) {
                $s = $f->addSegment();
                $s->airline()
                    ->name($this->http->FindSingleNode("./descendant::td[2]", $root, true, "/^([A-Z\d]{2})[A-Z\d]{2,4}/"))
                    ->number($this->http->FindSingleNode("./descendant::td[2]", $root, true, "/^[A-Z\d]{2}([A-Z\d]{2,4})/"));

                $date = $this->http->FindSingleNode("./descendant::td[1]", $root);
                $depTime = $this->http->FindSingleNode("./descendant::td[normalize-space()][4]", $root, true, "/^([\d\:]+)/u");

                $s->departure()
                    ->date(strtotime($date . ', ' . $depTime))
                    ->name($this->http->FindSingleNode("./descendant::td[normalize-space()][4]", $root, true, "/^[\d\:]+\s*(.+)/su"))
                    ->noCode();

                $arrTime = $this->http->FindSingleNode("./descendant::td[normalize-space()][7]", $root, true, "/^([\d\:]+)/u");

                $s->arrival()
                    ->date(strtotime($date . ', ' . $arrTime))
                    ->name($this->http->FindSingleNode("./descendant::td[normalize-space()][7]", $root, true, "/^[\d\:]+\s*(.+)/su"))
                    ->noCode();
            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
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
}
