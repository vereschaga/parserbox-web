<?php

namespace AwardWallet\Engine\sotc\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ElectronicTicket extends \TAccountChecker
{
    public $mailFiles = "sotc/it-206647922.eml";
    public $subjects = [
        'Electronic Ticket Details',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@sotc.in') !== false) {
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
        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'SOTC Travel')]")->length > 0) {
            return $this->http->XPath->query("//text()[{$this->contains($this->t('Electronic Ticket Details'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Corporate Account'))}]")->length > 0;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]sotc\.in$/', $from) > 0;
    }

    public function ParseFlight(Email $email)
    {
        $f = $email->add()->flight();

        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[normalize-space()='Test Reference Number']/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Test Reference Number'))}[\s\:]*([A-Z\d]{6,})/"))
            ->travellers(array_unique($this->http->FindNodes("//text()[normalize-space()='Name']/ancestor::tr[1]/following-sibling::tr[normalize-space()]/descendant::td[1][not(contains(normalize-space(), 'Meal') or contains(normalize-space(), 'Code') or contains(normalize-space(), 'Baggage'))]", null, "/^(.+)\s*\(/")), true)
            ->date(strtotime($this->http->FindSingleNode("//text()[normalize-space()='Generation Time']/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Generation Time'))}[\s\:]*(.+)/")));

        $status = $this->http->FindSingleNode("//text()[normalize-space()='Booking Status']/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Booking Status'))}[\s\:]*(\D+)/");

        if (!empty($status)) {
            $f->general()
                ->status($status);
        }

        $priceText = $this->http->FindSingleNode("//text()[normalize-space()='Total Price']/ancestor::tr[1]");

        if (preg_match("/\:\s*(?<total>[\d\,]+)\s*(?<currency>[A-Z]{3})\s/", $priceText, $m)) {
            $f->price()
                ->total(PriceHelper::parse($m['total'], $m['currency']))
                ->currency($m['currency']);

            $cost = $this->http->FindSingleNode("//text()[normalize-space()='Base Price']/ancestor::tr[1]", null, true, "/\:\s+([\d\,\.]+)/");

            if (!empty($cost)) {
                $f->price()
                    ->cost(PriceHelper::parse($cost, $m['currency']));
            }

            $tax = $this->http->FindSingleNode("//text()[normalize-space()='Airline Taxes and Fees']/ancestor::tr[1]", null, true, "/\:.*\s+([\d\,\.]+)\s+[A-Z]{3}/");

            if (!empty($tax)) {
                $f->price()
                    ->tax(PriceHelper::parse($tax, $m['currency']));
            }
        }

        $nodes = $this->http->XPath->query("//text()[normalize-space()='Flight']");

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $s->airline()
                ->name($this->http->FindSingleNode("./ancestor::tr[1]", $root, true, "/([A-Z\d]{2})\s*\-\s*\d{2,4}/"))
                ->number($this->http->FindSingleNode("./ancestor::tr[1]", $root, true, "/[A-Z\d]{2}\s*\-\s*(\d{2,4})/"));

            $depText = $this->http->FindSingleNode("./following::text()[normalize-space()='Departure'][1]/ancestor::tr[1]", $root);
            $this->logger->debug($depText);

            if (preg_match("/Departure\:(?<depDate>.+)\s+\:\s+\D+\(.*(?<depCode>[A-Z]{3})\)(?:[\s\:]+Terminal\s*(?:TERMINAL\s*)?(?<depTerminal>\d))?$/iu", $depText, $m)) {
                $s->departure()
                    ->code($m['depCode'])
                    ->date(strtotime($m['depDate']));

                if (isset($m['depTerminal'])) {
                    $s->departure()
                        ->terminal($m['depTerminal']);
                }
            }

            $arrText = $this->http->FindSingleNode("./following::text()[normalize-space()='Arrival'][1]/ancestor::tr[1]", $root);

            if (preg_match("/Arrival\:(?<arrDate>.+)\s+\:\s+\D+\(.*(?<arrCode>[A-Z]{3})\)(?:[\s\:]+Terminal\s*(?:TERMINAL\s*)?(?<arrTerminal>\d+))?$/iu", $arrText, $m)) {
                $s->arrival()
                    ->code($m['arrCode'])
                    ->date(strtotime($m['arrDate']));

                if (isset($m['arrTerminal'])) {
                    $s->arrival()
                        ->terminal($m['arrTerminal']);
                }
            }

            $cabin = $this->http->FindSingleNode("./following::text()[normalize-space()='Class'][1]/ancestor::tr[1]", $root, true, "/{$this->opt($this->t('Class'))}[\s\:]*(\w+)/");

            if (!empty($cabin)) {
                $s->extra()
                    ->cabin($cabin);
            }

            $meals = array_unique(array_filter($this->http->FindNodes("./following::text()[normalize-space()='Name'][1]/ancestor::tr[1]/following-sibling::tr[contains(normalize-space(), 'Meal')]", $root, "/{$this->opt($this->t('Meal'))}\:?\s*(\w+)\s\,/")));

            if (count($meals) > 0) {
                $s->setMeals($meals);
            }

            $seats = array_unique(array_filter($this->http->FindNodes("./following::text()[normalize-space()='Name'][1]/ancestor::tr[1]/following-sibling::tr[contains(normalize-space(), 'Seat')]", $root, "/{$this->opt($this->t('Seat'))}\:?\s*(\d{1,2}[A-Z])\s\,/")));

            if (count($seats) > 0) {
                $s->setSeats($seats);
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
