<?php

namespace AwardWallet\Engine\mabuhay\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

// TODO: merge with parsers malaysia/FlightRetiming(object), aviancataca/Air(object), flyerbonus/TripReminder(object), thaiair/Cancellation(object), rapidrewards/Changes, lotpair/FlightChange(object) (in favor of malaysia/FlightRetiming)

class FlightChange extends \TAccountChecker
{
    public $mailFiles = "mabuhay/it-82565655.eml";
    public $subjects = [
        '/Your Flight\s*[A-Z\d][6]\s*/',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'SORRY, YOUR FLIGHT SCHEDULE HAS BEEN' => ['SORRY, YOUR FLIGHT SCHEDULE HAS BEEN', 'SORRY, YOUR FLIGHT HAS BEEN', 'YOUR FLIGHT SCHEDULE HAS BEEN'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@philippineairlines.com') !== false) {
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
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'Philippine Airlines')]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('New Flight Details'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Original Flight Details'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]philippineairlines\.com$/', $from) > 0;
    }

    public function ParseEmail(Email $email)
    {
        $f = $email->add()->flight();

        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[normalize-space()='Booking reference:']/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Booking reference:'))}\s*([A-Z\d]+)/"))
            ->status($this->http->FindSingleNode("//text()[{$this->starts($this->t('SORRY, YOUR FLIGHT SCHEDULE HAS BEEN'))}]", null, true, "/{$this->opt($this->t('SORRY, YOUR FLIGHT SCHEDULE HAS BEEN'))}\s*(\w+)\./"))
            ->traveller($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Dear')]", null, true, "/{$this->opt($this->t('Dear'))}\s*(.+)\,/"));

        $xpath = "//text()[normalize-space()='Original Flight Details']/preceding::tr[contains(normalize-space(), 'From')][1]/following::tr[1]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $s->airline()
                ->name($this->http->FindSingleNode(".", $root, true, "/\s((?:[A-Z][A-Z\d]|[A-Z\d][A-Z]))\d{1,4}\s*[A-Z]$/"))
                ->number($this->http->FindSingleNode(".", $root, true, "/\s(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])(\d{1,4})\s*[A-Z]$/"));

            $depDate = $this->http->FindSingleNode("./following::tr[1]/descendant::text()[normalize-space()][1]", $root);

            if (empty($depDate)) {
                $depDate = $this->http->FindSingleNode(".", $root, true, "/\s\d+\:\d+\s*(\w+\s\d+\,\s*\d{4})\s*(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])/");
            }

            $depTime = $this->http->FindSingleNode("./descendant::td[5]", $root, true, "/^[\d\:]+$/");

            if (empty($depTime)) {
                $depTime = $this->http->FindSingleNode(".", $root, true, "/\s(\d+\:\d+)\s*\w+\s\d+\,\s*\d{4}\s*(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])/");
            }

            $s->departure()
                ->name($this->http->FindSingleNode("./descendant::td[1]", $root, true, "/(.+)\s[A-Z]{3}/"))
                ->code($this->http->FindSingleNode("./descendant::td[1]", $root, true, "/([A-Z]{3})/"))
                ->date(strtotime($depDate . ' ' . $depTime));

            $arrDate = $this->http->FindSingleNode("./following::tr[1]/descendant::text()[normalize-space()][2]", $root);
            $arrTime = $this->http->FindSingleNode("./descendant::td[7]", $root, true, "/^[\d\:]+$/");

            if (!empty($arrDate) && !empty($arrTime)) {
                $s->arrival()
                    ->date(strtotime($arrDate . ' ' . $arrTime));
            } else {
                $s->arrival()
                    ->noDate();
            }

            $s->arrival()
                ->name($this->http->FindSingleNode("./descendant::td[3]", $root, true, "/(.+)\s[A-Z]{3}/"))
                ->code($this->http->FindSingleNode("./descendant::td[3]", $root, true, "/([A-Z]{3})/"));

            $s->extra()
                ->cabin($this->http->FindSingleNode("./descendant::td[normalize-space()][last()]", $root));
        }

        return true;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->ParseEmail($email);

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
        return 0;
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
