<?php

namespace AwardWallet\Engine\qantas\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;

class Balance extends \TAccountChecker
{
    public $mailFiles = "qantas/statements/it-63223360.eml, qantas/statements/it-63224400.eml, qantas/statements/it-63442865.eml, qantas/statements/it-63551101.eml, qantas/statements/it-75605272.eml, qantas/statements/it-75776761.eml, qantas/statements/it-75947572.eml";

    public static $dictionary = [
        'en' => [],
    ];

    private $detectFrom = [
        "qantasff@loyalty.qantas.com",
        "frequent_flyer@qantas.com.au",
        "qantasff@e.qantas.com",
        "qantas@e.qantas.com"
    ];

    private $lang = 'en';

    public function detectEmailFromProvider($from)
    {
        return $this->striposAll($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return self::detectEmailFromProvider($headers['from']);
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return false;
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $type = '';

        if (stripos($parser->getCleanFrom(), '@e.qantas.com') !== false) {
            $this->type1($email);
            // Frequent Flyer number, Qantas Points, Status Credits в одной полосе на белом фоне
            $type = '1';
        }

        if (stripos($parser->getCleanFrom(), 'qantasff@loyalty.qantas.com') !== false
            || stripos($parser->getCleanFrom(), 'frequent_flyer@qantas.com.au') !== false
        ) {
            if (!empty($this->http->FindSingleNode("(//tr[" . $this->eq("Frequent Flyer number") . "])[1]"))) {
                // Frequent Flyer number, Qantas Points и Status Credits на белом фоне
                $this->type2($email);
                $type = '2';
            } else {
                // Frequent Flyer number, Qantas Points, Status Credits на цветном фоне
                $this->type3($email);
                $type = '3';
            }
        }

        $class = explode('\\', __CLASS__);
        $email->setType('Statement' . end($class) . $type);

        return $email;
    }

    private function type1(Email $email)
    {
        $st = $email->add()->statement();

        // Number
        $number = $this->http->FindSingleNode("//tr[" . $this->eq("Frequent Flyer number") . "]/following-sibling::tr[normalize-space()][1]", null, true,
            "/^\s*(\d{5,})\s*$/u");

        $st->setNumber($number);

        // Name
        $name = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("Hi ")) . "]", null, true,
            "#^\s*" . $this->preg_implode($this->t("Hi ")) . "\s*([^\d\W]+(?: [^\d\W]+){0,4})\s*,\s*$#u");

        if (!empty($name)) {
            $st->addProperty('Name', $name);
        }

        // Balance
        $st->setBalanceDate(strtotime($this->http->FindSingleNode("//text()[" . $this->eq("Qantas Points and Status Credits shown are as at") . "]/following::text()[normalize-space()][1]")));
        $balance = $this->http->FindSingleNode("//tr[" . $this->eq("Qantas Points") . "]/following-sibling::tr[normalize-space()][1]");

        if (preg_match("/^\s*([\d,]+)\s*/", $balance)) {
            $st->setBalance((int) (str_replace(',', '', trim($balance))));
        }

        // StatusCredits
        $st->addProperty('StatusCredits', (int) (str_replace(',', '', $this->http->FindSingleNode("//tr[" . $this->eq("Status Credits") . "]/following-sibling::tr[normalize-space()][1]", null, true,
            "/^\s*([\d,]+)\s*$/u"))));

        // Membership Type
        if (!empty($this->http->FindSingleNode("//img[contains(@src, '/header-qff-gold.png')]/@src"))) {
            $st->addProperty('Type', 'Gold');
        }

        if (!empty($this->http->FindSingleNode("//img[contains(@src, '/header-qff-silver.png')]/@src"))) {
            $st->addProperty('Type', 'Silver');
        }

        if (!empty($this->http->FindSingleNode("//img[contains(@src, '/header-qff-bronze.png')]/@src"))) {
            $st->addProperty('Type', 'Bronze');
        }

        if (!empty($this->http->FindSingleNode("//img[contains(@src, '/header-qff-platinum.png')]/@src"))) {
            $st->addProperty('Type', 'Platinum');
        }

        if (!empty($this->http->FindSingleNode("//img[contains(@src, '/header-qff-platinumone.png')]/@src"))) {
            $st->addProperty('Type', 'Platinum One');
        }

        return $email;
    }

    private function type2(Email $email)
    {
        $st = $email->add()->statement();

        // Number
        $number = $this->http->FindSingleNode("(//tr[" . $this->eq("Frequent Flyer number") . "])[1]/following-sibling::tr[normalize-space()][1]", null, true,
            "/^\s*(\d{5,})\s*$/u");

        $st->setNumber($number);

        // Name
        $name = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("Dear ")) . "]", null, true,
            "#^\s*" . $this->preg_implode($this->t("Dear ")) . "\s*([^\d\W]+(?: [^\d\W]+){0,4})\s*,\s*$#u");

        if (!empty($name)) {
            $st->addProperty('Name', $name);
        }

        // Balance
        $date = strtotime($this->http->FindSingleNode("//text()[" . $this->contains("shown are as at") . "]", null, true, "#shown are as at[ \.]+(.+)#"));
        if (empty($date) && !empty($this->http->FindSingleNode("//text()[" . $this->contains("Thank you for joining Qantas Frequent Flyer") . "]"))) {

        } else {
            $st->setBalanceDate($date);
        }
        $balance = $this->http->FindSingleNode("//tr[" . $this->eq("Qantas Points") . "]/following-sibling::tr[normalize-space()][1]");

        if (preg_match("/^\s*([\d,]+)\s*/", $balance)) {
            $st->setBalance((int) (str_replace(',', '', trim($balance))));
        }

        // StatusCredits
        $st->addProperty('StatusCredits', (int) (str_replace(',', '', $this->http->FindSingleNode("//tr[" . $this->eq("Status Credits") . "]/following-sibling::tr[normalize-space()][1]", null, true,
            "/^\s*([\d,]+)\s*$/u"))));

        // Membership Type
        if (!empty($this->http->FindSingleNode("//img[contains(@src, 'status-identifier_bronze.png')]/@src"))) {
            $st->addProperty('Type', 'Bronze');
        }

        if (!empty($this->http->FindSingleNode("//img[contains(@src, 'status-identifier_silver.png')]/@src"))) {
            $st->addProperty('Type', 'Silver');
        }

        if (!empty($this->http->FindSingleNode("//img[contains(@src, 'status-identifier_gold.png')]/@src"))) {
            $st->addProperty('Type', 'Gold');
        }

        if (!empty($this->http->FindSingleNode("//img[contains(@src, 'status-identifier_platinum.png')]/@src"))) {
            $st->addProperty('Type', 'Platinum');
        }

        if (!empty($this->http->FindSingleNode("//img[contains(@src, 'status-identifier_platinumone.png')]/@src"))) {
            $st->addProperty('Type', 'Platinum One');
        }

        return $email;
    }

    private function type3(Email $email)
    {
        $st = $email->add()->statement();

        // Number
        $number = $this->http->FindSingleNode("//text()[" . $this->starts("Frequent Flyer number:") . "]", null, true,
            "/:\s*(\d{5,})\s*$/u");

        if (empty($number)) {
            $number = $this->http->FindSingleNode("//td[not(.//td) and descendant::text()[normalize-space()][1][" . $this->eq("Frequent Flyer number") . "]]", null, true,
                "/Frequent Flyer number\s*(\d{5,})\s*$/u");
        }

        $st->setNumber($number);

        // Name
        $name = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("Dear ")) . "]", null, true,
            "#^\s*" . $this->preg_implode($this->t("Dear ")) . "\s*([^\d\W]+(?: [^\d\W]+){0,4})\s*$#u");

        if (empty($name)) {
            $name = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("Dear ")) . "]", null, true,
                "#^\s*" . $this->preg_implode($this->t("Dear ")) . "\s*([A-Z]+[a-z\-]+), #u");
        }

        if (!empty($name)) {
            $st->addProperty('Name', $name);
        }

        // Balance
        $date = $this->http->FindSingleNode("//text()[" . $this->contains(["Credits shown as at", "Credits shown are as at"]) . "]", null, true, "# as at (.+)#");

        if (!empty($date) || !empty($this->http->FindSingleNode("//text()[" . $this->starts("Frequent Flyer number") . "]/preceding::text()[" . $this->contains(" as at") . "]"))) {
            $st->setBalanceDate(strtotime($date));
        }
        $balance = $this->http->FindSingleNode("//td[not(.//td) and descendant::text()[normalize-space()][1][" . $this->eq("Qantas Points") . "]]", null, true, "#Qantas Points\s*(.+)#");

        if (preg_match("/^\s*([\d,]+)\s*/", $balance)) {
            $st->setBalance((int) (str_replace(',', '', trim($balance))));
        }

        // StatusCredits
        $st->addProperty('StatusCredits', (int) (str_replace(',', '', $this->http->FindSingleNode("//td[not(.//td) and descendant::text()[normalize-space()][1][" . $this->eq("Status Credits") . "]]", null, true,
            "/Status Credits\s*([\d,]+)\s*$/u"))));

        // Membership Type
        if (!empty($this->http->FindSingleNode("//text()[" . $this->starts("Frequent Flyer number") . "]/preceding::img[contains(@src, '/Bronze.png')]/@src"))) {
            $st->addProperty('Type', 'Bronze');
        }

        if (!empty($this->http->FindSingleNode("//text()[" . $this->starts("Frequent Flyer number") . "]/preceding::img[contains(@src, '/newsilver.png')]/@src"))) {
            $st->addProperty('Type', 'Silver');
        }

        if (!empty($this->http->FindSingleNode("//text()[" . $this->starts("Frequent Flyer number") . "]/preceding::img[contains(@src, '/newgold.png')]/@src"))) {
            $st->addProperty('Type', 'Gold');
        }

        if (!empty($this->http->FindSingleNode("//text()[" . $this->starts("Frequent Flyer number") . "]/preceding::img[contains(@src, '/newplatinum.png')]/@src"))) {
            $st->addProperty('Type', 'Platinum');
        }

        if (!empty($this->http->FindSingleNode("//text()[" . $this->starts("Frequent Flyer number") . "]/preceding::img[contains(@src, '/newplatinumone.png')]/@src"))) {
            $st->addProperty('Type', 'Platinum One');
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

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field)) . ')';
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function preg_implode($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '#'); }, $field)) . ')';
    }

    private function striposAll($text, $needle): bool
    {
        if (empty($text) || empty($needle)) {
            return false;
        }

        if (is_array($needle)) {
            foreach ($needle as $n) {
                if (stripos($text, $n) !== false) {
                    return true;
                }
            }
        } elseif (is_string($needle) && stripos($text, $needle) !== false) {
            return true;
        }

        return false;
    }
}
