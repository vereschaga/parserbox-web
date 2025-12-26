<?php

namespace AwardWallet\Engine\wizz\Email;

//use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class FlightReminder2 extends \TAccountChecker
{
    public $mailFiles = "wizz/it-14349740.eml";

    private $reFrom = "noreply@wizzair.com";
    private $reSubject = [
        "en" => "Flight reminder",
    ];
    private $reBody = 'Wizz Air';
    private $reBody2 = [
        "en" => "Your booking details",
        'he' => 'פרטי ההזמנה שלך',
        'es' => 'Datos de la reserva',
    ];

    private static $dictionary = [
        "en" => [
            //			"Confirmation number:" => "",
            //			"Passangers" => "",
            //			"Flight Number" => "",
        ],
        "he" => [
            "Confirmation number:" => "מספר אישור:",
            "Passangers"           => "נוסעים",
            "Flight Number"        => "מספר הטיסה:",
            'Departure:'           => 'נחיתה:',
            'Terminal'             => 'טרמינל',
        ],
        'es' => [
            'Confirmation number:' => 'Número de confirmación:',
            'Passangers'           => 'Pasajeros',
            'Departure:'           => 'Salida:',
            'Flight Number'        => 'Número de vuelo:',
        ],
    ];
    private $lang = "en";

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        foreach ($this->reBody2 as $lang => $re) {
            if (strpos($this->http->Response["body"], $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }
        $email->setType($a[count($a = explode('\\', __CLASS__)) - 1] . ucfirst($this->lang));

        $this->flights($email);

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (strpos($headers["from"], $this->reFrom) === false) {
            return false;
        }

        foreach ($this->reSubject as $re) {
            if (stripos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (strpos($body, $this->reBody) === false) {
            return false;
        }

        foreach ($this->reBody2 as $re) {
            if (strpos($body, $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    protected function flights(Email $email)
    {
        $f = $email->add()->flight();

        // General
        $travellers = array_filter(array_map('trim', $this->http->FindNodes("(//text()[" . $this->eq($this->t("Passangers")) . "])[1]/ancestor::table[1]/following-sibling::table[normalize-space()][1]//tr[normalize-space(.)][count(./td)=1]", null, "#^[^\d:]+$#u")));

        if (0 < count($travellers)) {
            $f->general()
                ->travellers($travellers, true);
        }
        $f->general()
            ->confirmation($this->nextText($this->t("Confirmation number:")));

        // Segments
        $xpath = "(//text()[" . $this->eq($this->t("Departure:")) . "])[1]/ancestor::tr[2]";
        $nodes = $this->http->XPath->query($xpath);

        if (0 === $nodes->length) {
            $this->logger->info($xpath);
        }

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            // Airline
            $node = implode("\n", $this->http->FindNodes("./ancestor::table[1]/preceding::table[normalize-space()][1][" . $this->contains($this->t("Flight Number")) . "]", $root));

            if (preg_match("#:\s+([A-Z\d]{2})[ ]*(\d{1,5})\b#", $node, $m)) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2]);
            }
            // Departure
            $node = implode("\n", $this->http->FindNodes("./td[1]//text()[normalize-space()]", $root));

            if (preg_match("#:\s+(.+?)(?: - (.*?{$this->t('Terminal')}.*))?\n\s*([\s\S]+)#", $node, $m)) {
                $s->departure()
                    ->noCode()
                    ->name($m[1])
                    ->terminal(!empty($m[2]) ? trim(str_ireplace($this->t('Terminal'), '', $m[2])) : null, true, true)
                    ->date($this->normalizeDate($m[3]));
            }

            // Arrival
            $node = implode("\n", $this->http->FindNodes("./td[3]//text()[normalize-space()]", $root));

            if (preg_match("#:\s+(.+?)(?: - (.*?{$this->t('Terminal')}.*))?\n\s*([\s\S]+)#", $node, $m)) {
                $s->arrival()
                    ->noCode()
                    ->name($m[1])
                    ->terminal(!empty($m[2]) ? trim(str_ireplace($this->t('Terminal'), '', $m[2])) : null, true, true)
                    ->date($this->normalizeDate($m[3]));
            }
        }

        return $email;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($str)
    {
        $in = [
            "#^\s*(\d{4})-(\d+)-(\d+)\s+(\d+:\d+)\s*$#",
        ];
        $out = [
            "$3.$2.$1 $4",
        ];
        $str = preg_replace($in, $out, $str);

        return strtotime($str);
    }

    private function nextText($field, $root = null)
    {
        $rule = $this->eq($field);

        return $this->http->FindSingleNode("(.//text()[{$rule}])[1]/following::text()[normalize-space(.)][1]", $root);
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field));
    }
}
