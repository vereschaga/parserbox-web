<?php

namespace AwardWallet\Engine\qantas\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class FlightChanged extends \TAccountChecker
{
    public $mailFiles = "qantas/it-450316330.eml, qantas/it-569738519.eml, qantas/it-569738540.eml, qantas/it-569793091.eml, qantas/it-570036175.eml";
    public $subjects = [
        'The departure date for your flight has changed',
        'Important changes to your flight to',
        'Action needed - The departure date for your flight has changed',
        'Prepare for your flight',
    ];

    public $lang = 'en';
    public $subject;

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@yourbooking.qantas.com.au') !== false) {
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
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'Qantas Airways Limited')]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Updated flight details'))} or {$this->contains($this->t('until your upcoming trip'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]yourbooking\.qantas\.com\.au$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->subject = $parser->getSubject();
        $this->ParseFlight($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function ParseFlight(Email $email)
    {
        $f = $email->add()->flight();

        if (preg_match("/your flight has\s*(\w+)/", $this->subject, $m)
            || preg_match("/Important (\w+) to your flight/", $this->subject, $m)
            || preg_match("/Your flight to\D+has\s+(\w+)/", $this->subject, $m)) {
            $f->setStatus($m[1]);
        }

        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Booking reference:')]", null, true, "/{$this->opt($this->t('Booking reference:'))}\s*([A-Z\d]{6})/"), 'Booking reference');

        $travellers = $this->http->FindNodes("//text()[normalize-space()='View/Change seat']/ancestor::*[count(.//text()[normalize-space()='View/Change seat']) = 1 and not(.//text()[normalize-space()='Passengers'])][last()]/descendant::td[not(.//td)][normalize-space()][1][not(following::text()[normalize-space()][1][contains(., 'Infant')])]");

        if (empty($this->http->FindSingleNode("(//text()[normalize-space()='View/Change seat'])[1]"))) {
            $travellers = $this->http->FindNodes("//text()[normalize-space()='Passengers']/ancestor::tr[1]/descendant::tr[normalize-space()]/td[string-length()>3][1][1]/descendant::text()[normalize-space()][1]");
        }

        $f->general()
            ->travellers(preg_replace("/^(?:Mrs|Mr|Mstr|Ms|Miss|Dr|Prof)\s+/", "", $travellers), true);

        $infants = $this->http->FindNodes("//text()[normalize-space()='View/Change seat']/ancestor::*[count(.//text()[normalize-space()='View/Change seat']) = 1][last()]/descendant::td[not(.//td)][normalize-space()][1][following::text()[normalize-space()][1][contains(., 'Infant')]]");

        if (!empty($infants)) {
            $f->general()
                ->infants($infants, true);
        }

        $accounts = $this->http->FindNodes("//text()[normalize-space()='Passengers']/ancestor::tr[1]/descendant::tr[normalize-space()]/descendant::text()[contains(normalize-space(), '•')]/ancestor::tr[1]");

        foreach ($accounts as $account) {
            if (preg_match("/^[^\d•]+\s+[•]\s+(?<number>\d{5,})(?:\s+[•]|\s*$)/u", $account, $m)) {
                $f->program()
                    ->account($m['number'], false);
            }
        }

        $nodes = $this->http->XPath->query("//descendant::img[contains(@src, 'plane-icon')]/ancestor::tr[1][not(preceding::text()[normalize-space()='Old flight details'])]");
        $date = '';

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $dateTemp = $this->http->FindSingleNode("./preceding::td[2]", $root, true, "/^\w*\s*(\d+\s*\w+\s*\d{4})$/");

            if (!empty($dateTemp)) {
                $date = $dateTemp;
            }

            $s->airline()
                ->name($this->http->FindSingleNode("./following::tr[./descendant::img[1]][1]", $root, true, "/^((?:[A-Z][A-Z\d]|[A-Z\d][A-Z]))/"))
                ->number($this->http->FindSingleNode("./following::tr[./descendant::img[1]][1]", $root, true, "/^(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(\d{1,4})/"));

            $depTime = $this->http->FindSingleNode("./preceding::td[1]/descendant::text()[normalize-space()][1]", $root, true, "/^(\d+\:\d+\s*A?P?M?)$/i");

            $s->departure()
                ->code($this->http->FindSingleNode("./preceding::td[1]/descendant::text()[normalize-space()][2]", $root))
                ->date(strtotime($date . ', ' . $depTime));

            $arrTime = $this->http->FindSingleNode("./following::td[1]/descendant::text()[normalize-space()][1]", $root, true, "/^(\d+\:\d+\s*A?P?M?)$/i");
            $s->arrival()
                ->code($this->http->FindSingleNode("./following::td[1]/descendant::text()[normalize-space()][2]", $root))
                ->date(strtotime($date . ', ' . $arrTime));

            $operator = $this->http->FindSingleNode("./following::tr[./descendant::img[1]][1]/descendant::text()[normalize-space()][1]", $root, true, "/{$this->opt($this->t('operated by'))}\s*(.+)/");

            if (!empty($operator)) {
                $s->setOperatedBy($operator);
            }

            $classInfo = $this->http->FindSingleNode("./following::tr[./descendant::img[1]][1]/descendant::text()[normalize-space()][2]", $root);

            if (preg_match("/^(?<cabin>\D+)\s\((?<bookingCode>[A-Z])\)$/", $classInfo, $m)
            || preg_match("/^(?<cabin>\D+)$/", $classInfo, $m)) {
                $s->extra()
                    ->cabin($m['cabin']);

                if (isset($m['bookingCode']) && !empty($m['bookingCode'])) {
                    $s->extra()
                        ->bookingCode($m['bookingCode']);
                }
            }

            if ($nodes->length === 1) {
                $seats = array_unique(array_filter($this->http->FindNodes("//text()[normalize-space()='View/Change seat']/preceding::text()[normalize-space()][1]", null, "/^\s*(\d{1,3}[A-Z])\s*$/")));

                if (!empty($seats)) {
                    $s->extra()
                        ->seats($seats);
                }
            }
        }
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary) * 2;
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
