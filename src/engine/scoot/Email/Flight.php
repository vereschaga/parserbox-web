<?php

namespace AwardWallet\Engine\scoot\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Flight extends \TAccountChecker
{
    public $mailFiles = "scoot/it-718030554.eml";
    public $subjects = [
        'Your Scoot booking confirmation',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@itinerary.flyscoot.com') !== false) {
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
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'Scoot')]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Scoot Booking Ref'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('You\'re all set for your trip! This email has all the details of your booking on'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]itinerary\.flyscoot\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->Flight($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function Flight(Email $email)
    {
        $f = $email->add()->flight();

        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Booking Date:')]/following::text()[starts-with(normalize-space(), 'Booking Ref:')][1]", null, true, "/{$this->opt($this->t('Booking Ref:'))}\s*([A-Z\d]{6})/"))
            ->date(strtotime($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Booking Date:')]", null, true, "/{$this->opt($this->t('Booking Date:'))}\s*(.+\d{4})/")))
            ->traveller($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Hi')]", null, true, "/{$this->opt($this->t('Hi'))}\s*(\w+)\,/"));

        $status = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Booking Status:')]/ancestor::td[1]", null, true, "/{$this->opt($this->t('Booking Status:'))}\s*(\w+)/");

        if (!empty($status)) {
            $f->setStatus($status);
        }

        $nodes = $this->http->XPath->query("//text()[starts-with(normalize-space(), 'FLIGHT')][contains(translate(translate(normalize-space(),' +()-',''),'0123456789','ddddddddddd'),'d')]/ancestor::tr[2]");

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $airInfo = $this->http->FindSingleNode("./following-sibling::tr[1]", $root);

            if (preg_match("/^(?<aName>(?:[A-Z][A-Z\d]|[A-Z\d][A-Z]))\s*(?<fNumber>\d{1,4})\s+\[.*\]\s+[\s\-]+(?<duration>\d+(?:h|m).*)$/", $airInfo, $m)) {
                $s->airline()
                    ->name($m['aName'])
                    ->number($m['fNumber']);

                $s->setDuration($m['duration']);
            }

            $depInfo = implode("\n", $this->http->FindNodes("./following-sibling::tr[2]/descendant::tr[2]/td[1]/descendant::text()[normalize-space()]", $root));

            if (preg_match("/Departure\n(?<depName>.+)\n.+\((?<depCode>[A-Z]{3})\)\n(?:Terminal)?\s*(?<arrTerminal>\S+)\s*(?:Terminal)?\n(?<depDate>\d+\s+\w+.+)\s+HRS$/", $depInfo, $m)) {
                $s->departure()
                    ->name($m['depName'])
                    ->code($m['depCode'])
                    ->date(strtotime($m['depDate']));

                if (isset($m['depTerminal']) && !empty($m['depTerminal'])) {
                    $s->departure()
                        ->terminal($m['depTerminal']);
                }
            }

            $arrInfo = implode("\n", $this->http->FindNodes("./following-sibling::tr[2]/descendant::tr[2]/td[2]/descendant::text()[normalize-space()]", $root));

            if (preg_match("/Arrival\n(?<arrName>.+)\n.+\((?<arrCode>[A-Z]{3})\)\n(?:Terminal)?\s*(?<arrTerminal>\S+)\s*(?:Terminal)?\n(?<arrDate>\d+\s+\w+.+)\s+HRS$/", $arrInfo, $m)) {
                $s->arrival()
                    ->name($m['arrName'])
                    ->code($m['arrCode'])
                    ->date(strtotime($m['arrDate']));

                if (isset($m['arrTerminal']) && !empty($m['arrTerminal'])) {
                    $s->arrival()
                        ->terminal($m['arrTerminal']);
                }
            }

            $bookingCode = $this->http->FindSingleNode("./following-sibling::tr[2]/descendant::tr[1]/td[1]/descendant::text()[normalize-space()]", $root, true, "/^Fare Class\:\s*([A-Z]{1,2})$/");

            if (!empty($bookingCode)) {
                $s->extra()
                    ->bookingCode($bookingCode);
            }
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
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
    }
}
