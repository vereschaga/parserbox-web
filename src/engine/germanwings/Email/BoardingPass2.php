<?php

namespace AwardWallet\Engine\germanwings\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class BoardingPass2 extends \TAccountChecker
{
    public $mailFiles = "germanwings/it-267419608.eml";
    public $subjects = [
        'Your mobile boarding pass for',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@information.eurowings-discover.com') !== false) {
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
        if ($this->http->XPath->query("//text()[{$this->contains($this->t('Your Eurowings Discover team'))}]")->length > 0) {
            return $this->http->XPath->query("//text()[{$this->contains($this->t('Boarding Pass'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Flight information'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('If the boarding pass is not displayed correctly, please use the'))}]")->length > 0;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]information\.eurowings\-discover\.com$/', $from) > 0;
    }

    public function ParseFlight(Email $email)
    {
        $f = $email->add()->flight();

        $traveller = $this->http->FindSingleNode("//text()[normalize-space()='Name']/following::text()[normalize-space()][1]");
        $confirmation = $this->http->FindSingleNode("//text()[normalize-space()='Your Booking code']/following::text()[normalize-space()][1]");

        $f->general()
            ->confirmation($confirmation)
            ->traveller($traveller);

        $ticket = $this->http->FindSingleNode("//text()[normalize-space()='e-ticket number']/following::text()[normalize-space()][1]", null, true, "/^(\d+)$/");

        if (!empty($ticket)) {
            $f->issued()->ticket($ticket, false);
        }

        $s = $f->addSegment();

        $s->airline()
            ->name($this->http->FindSingleNode("//text()[normalize-space()='Flight Number']/following::text()[normalize-space()][1]", null, true, "/^([A-Z\d]{2})\s*\d{2,4}$/"))
            ->number($this->http->FindSingleNode("//text()[normalize-space()='Flight Number']/following::text()[normalize-space()][1]", null, true, "/^[A-Z\d]{2}\s*(\d{2,4})$/"));

        $seat = $this->http->FindSingleNode("//text()[normalize-space()='Seat']/following::text()[normalize-space()][1]", null, true, "/^(\d+[A-Z])$/");

        if (!empty($seat)) {
            $s->extra()
                ->seat($seat);
        }

        $cabin = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Your travel class')]/following::text()[normalize-space()][1][not(contains(normalize-space(), 'Name'))]", null, true, "/^(\w+)$/");

        if (!empty($cabin)) {
            $s->extra()
                ->cabin($cabin);
        }

        $xpath = "//text()[starts-with(normalize-space(), 'Departure:')]/ancestor::tr[1]";

        $depText = implode("\n", $this->http->FindNodes($xpath . "/descendant::td[1]/descendant::text()[normalize-space()]"));

        if (preg_match("/^(?<depCode>[A-Z]{3})\n*.+\n*(?<depDate>[\d\.]+)\n*Departure:\s*(?<depTime>[\d\:]+)$/", $depText, $m)) {
            $s->departure()
                ->code($m['depCode'])
                ->date(strtotime($m['depDate'] . ', ' . $m['depTime']));
        }

        $arrText = implode("\n", $this->http->FindNodes($xpath . "/descendant::td[3]/descendant::text()[normalize-space()]"));

        if (preg_match("/^(?<arrCode>[A-Z]{3})\n*.+\n*(?<arrDate>[\d\.]+)\n*Arrival:\s*(?<arrTime>[\d\:]+)$/", $arrText, $m)) {
            $s->arrival()
                ->code($m['arrCode'])
                ->date(strtotime($m['arrDate'] . ', ' . $m['arrTime']));
        }

        $bp = $email->add()->bpass();
        $bp->setUrl($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Your travel class')]/preceding::img[contains(@src, 'barcode')]/@src"));
        $bp->setDepCode($s->getDepCode());
        $bp->setDepDate($s->getDepDate());
        $bp->setFlightNumber($s->getAirlineName() . ' ' . $s->getFlightNumber());
        $bp->setRecordLocator($confirmation);
        $bp->setTraveller($traveller);
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
}
