<?php

namespace AwardWallet\Engine\jetblue\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ItineraryReceipt extends \TAccountChecker
{
    public $mailFiles = "jetblue/it-112769594.eml, jetblue/it-112773511.eml";
    public $subjects = [
        'Itinerary receipt notice',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@email.jetblue.com') !== false) {
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
        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'Thanks for flying JetBlue!')]")) {
            return $this->http->XPath->query("//text()[{$this->contains($this->t('Travel date'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Date of requested receipt'))}]")->length > 0;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]email\.jetblue\.com$/', $from) > 0;
    }

    public function ParseFlight(Email $email)
    {
        $f = $email->add()->flight();

        $f->general()
            ->date(strtotime($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Date of requested receipt:')]", null, true, "/\:\s*(\d{4}\-\d+\-\d+)/")))
            ->travellers(preg_replace("/(?:\sMRS|\sMR|\sMS)$/", "", $this->http->FindNodes("//text()[normalize-space()='Traveler(s)']/ancestor::table[1]/descendant::tr/td[1][not(contains(normalize-space(), ':') or contains(normalize-space(), '('))]")), true)
            ->confirmation($this->http->FindSingleNode("//text()[normalize-space()='Record Locator:']/following::text()[normalize-space()][1]", null, true, "/^([\dA-Z]{6,})$/"));

        $total = $this->http->FindSingleNode("//text()[normalize-space()='Base fare total:']/ancestor::tr[1]/descendant::td[2]", null, true, "/^\s*\D([\d\.\,]+)\s*$/");
        $currency = $this->http->FindSingleNode("//text()[normalize-space()='Taxes & fees total:']/ancestor::tr[1]/descendant::td[3]", null, true, "/^([A-Z]{3})$/");

        if (!empty($total) && !empty($currency)) {
            $f->price()
                ->total(PriceHelper::parse($total, $currency))
                ->currency($currency);
        }

        $tax = $this->http->FindSingleNode("//text()[normalize-space()='Taxes & fees total:']/ancestor::tr[1]/descendant::td[2]", null, true, "/^\s*\D([\d\.\,]+)\s*$/");

        if (!empty($tax)) {
            $f->price()
                ->tax($tax);
        }

        $cost = $this->http->FindSingleNode("//text()[normalize-space()='Base fare:']/ancestor::tr[1]/descendant::td[2]", null, true, "/^\s*\D([\d\.\,]+)/");

        if (!empty($cost)) {
            $f->price()
                ->cost(PriceHelper::parse($cost, $currency));
        }

        $tickets = $this->http->FindNodes("//text()[normalize-space()='Traveler(s)']/ancestor::table[1]/descendant::tr/td[1][not(contains(normalize-space(), ':') or contains(normalize-space(), '('))]/following::text()[normalize-space()][1]", null, "/^(\d+)/");

        if (!empty($tickets)) {
            $f->setTicketNumbers($tickets, false);
        }

        $nodes = $this->http->XPath->query("//text()[normalize-space()='Travel date']");

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $s->departure()
                ->code($this->http->FindSingleNode("./ancestor::tr[1]/preceding::tr[1]/descendant::td[1]", $root, true, "/([A-Z]{3})/"))
                ->date(strtotime($this->http->FindSingleNode("./ancestor::tr[1]/descendant::td[2]", $root)));

            $s->arrival()
                ->code($this->http->FindSingleNode("./ancestor::tr[1]/preceding::tr[1]/descendant::td[normalize-space()][2]", $root, true, "/([A-Z]{3})/"))
                ->noDate();

            $s->airline()
                ->name('JetBlue')
                ->number($this->http->FindSingleNode("./ancestor::tr[1]/following::tr[1]/td[2]", $root, true, "/^(\d+)$/"));
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
