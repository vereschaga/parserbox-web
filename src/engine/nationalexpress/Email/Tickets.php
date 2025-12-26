<?php

namespace AwardWallet\Engine\nationalexpress\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class Tickets extends \TAccountChecker
{
    // pdf parse in nationalexpress/TicketsPdf
    public $mailFiles = "nationalexpress/it-113082731.eml, nationalexpress/it-113709945.eml";

    private $detects = [
        'Please note your e-tickets are attached to this email',
    ];

    public $lang = '';
    public static $dict = [
        'en' => [
            'view on map' => 'view on map',
            'route' => ['Outbound', 'Return'],
            'duration' => [' hour', ' minute'],
        ],
    ];


    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        if (!$this->assignLang()) {
            $this->logger->debug('can\'t determine a language');

            return $email;
        }

        $this->parseHtml($email);

        return $email;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return (isset($headers['from']) && stripos($headers['from'], '@nationalexpress.com') !== false)
            || (isset($headers['subject']) && stripos($headers['subject'],
                    'National Express confirmation email') !== false);
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@nationalexpress.com') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href, '.nationalexpress.com')] | //*[".$this->contains(["@nationalexpress.com", "choosing National Express"])."]")->length == 0) {
            return false;
        }

        foreach ($this->detects as $detect) {
            if ($this->http->XPath->query("//*[".$this->contains($detect)."]")->length > 0) {
                return $this->assignLang();
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

    private function parseHtml(Email $email)
    {
        $b = $email->add()->bus();

        $b->general()
            ->traveller($this->http->FindSingleNode("//text()[".$this->contains('Adult')."]/preceding::text()[string-length(normalize-space()) > 1][1][contains(., '-')]/preceding::text()[normalize-space()][1]",
                null, true, "/^\s*([[:upper:]][[:alpha:]\-]+( [[:upper:]][[:alpha:]\-]+)+)\s*$/u"));


        // Price
        $paidText = $this->http->FindSingleNode("//text()[".$this->starts($this->t("Previously paid:"))."]");
        $paid = null;
        if (preg_match("/:\s*([^\d\s]{1,5})(\d[\d\.\, ]*)\s+/", $paidText, $m)) {
            $paidCurrency = $this->currency($m[1]);
            $paid = PriceHelper::parse($m[2], $paidCurrency);
        }
        $totalText = $this->http->FindSingleNode("//td[not(.//td)][".$this->eq($this->t("Total:"))."]/following-sibling::td[normalize-space()][1]");
        $total = null;
        if (preg_match("/^\s*([^\d\s]{1,5})(\d[\d\.\, ]*)\s*$/", $totalText, $m)) {
            $totalCurrency = $this->currency($m[1]);
            $total = PriceHelper::parse($m[2], $totalCurrency);
        }

        if (!empty($paidText) && !empty($totalText) && is_numeric($paid)) {
            if (is_numeric($total) && $paidCurrency === $totalCurrency) {
                $b->price()
                    ->total($total + $paid)
                    ->currency($totalCurrency);
            } elseif (preg_match("/^\s*free\s*$/i", $totalText, $m)) {
                $b->price()
                    ->total($paid)
                    ->currency($paidCurrency);
            }
        } elseif (empty($paidText) && !empty($totalText) && is_numeric($total)) {
            $b->price()
                ->total($total)
                ->currency($totalCurrency);
        }

        $b->setTicketNumbers($this->http->FindNodes("//text()[".$this->starts($this->t("Ticket Number:"))."]",
            null, "/:\s*([A-Z\d]{5,})\s*$/"), false);

        $confs = [];
        $xpath = "//tr[.//text()[".$this->eq($this->t("view on map"))."] and descendant::text()[normalize-space()][1][contains(translate(., '0123456789', '##########'), '#:##')] and following-sibling::tr[1][".$this->contains($this->t("duration"))."]]";
        $roots = $this->http->XPath->query($xpath);
        $this->logger->debug('Segments xpath: ' . $xpath);

        foreach ($roots as $root) {
            $s = $b->addSegment();

            $date = $this->normalizeDate($this->http->FindSingleNode("preceding::text()[normalize-space()][1]", $root, true, "/.*\b\d{4}\b.*/"));

            $re = "/^\s*(?<time>\d{1,2}:\d{2})\s*\([^)]*\)\s*(?<name>[\s\S]+?)\s*".$this->preg_implode($this->t("view on map"))."/";
            $node = implode("\n", $this->http->FindNodes(".//text()[normalize-space()]", $root));
            if (preg_match($re, $node, $m)) {
                $s->departure()
                    ->date((!empty($date))? strtotime($m['time'], $date) : null)
                    ->name($m['name']);
            }
            $node = implode("\n", $this->http->FindNodes("following-sibling::tr[2]//text()[normalize-space()]", $root));
            if (preg_match($re, $node, $m)) {
                $s->arrival()
                    ->date((!empty($date))? strtotime($m['time'], $date) : null)
                    ->name($m['name']);
            }
            if (preg_match("/^([^,]*(hour|minute)[^,]*),/", $this->http->FindSingleNode("following-sibling::tr[1]", $root), $mat)) {
                $s->extra()
                    ->duration($mat[1]);
            }
            $s->extra()
                ->number($this->http->FindSingleNode("preceding::text()[normalize-space()][2]", $root, true, '/^([A-Z ]*\d+)$/'));

            $conf = $this->http->FindSingleNode("preceding::text()[normalize-space()][4]", $root, true, '/^\s*([A-Z\d]{4}(?:-[A-Z\d]+)+)\s*$/');
            if (empty($conf)) {
                $conf = $this->http->FindSingleNode("preceding::text()[normalize-space()][3]", $root, true, '/^\s*([A-Z\d]{4}(?:-[A-Z\d]+)+)\s*$/');
            }
            $confs[] = $conf;

        }

        $confs = array_unique($confs);
        foreach ($confs as $conf) {
            $b->general()
                ->confirmation($conf);
        }


        return true;
    }

    private function normalizeDate($str)
    {
        $in = [
//            "#^([^\s\d]+) (\d+), (\d{4})$#", //OCTOBER 09, 2017
//            '/^(\d{1,2}) de (\w+) de (\d{2,4})$/i',
        ];
        $out = [
//            "$1 $2 $3",
//            '$1 $2 $3',
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang()
    {
        foreach (self::$dict as $lang => $dict) {
            if (!empty($dict['view on map']) && $this->http->XPath->query("//*[".$this->contains($dict['view on map'])."]")->length > 0
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
                return 'normalize-space(.)="' . $s . '"';
            }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function currency($s)
    {
        if (preg_match("#^\s*([A-Z]{3})\s*$#", $s)) {
            return $s;
        }
        $sym = [
            '£'=> 'GBP',
            '€'=> 'EUR',
            '$'=> 'USD',
        ];

        foreach ($sym as $f => $r) {
            if ($s == $f) {
                return $r;
            }
        }

        return null;
    }

    private function preg_implode($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '#'); }, $field)) . ')';
    }
}
