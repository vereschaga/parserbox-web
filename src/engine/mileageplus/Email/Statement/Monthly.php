<?php

namespace AwardWallet\Engine\mileageplus\Email\Statement;

class Monthly extends \TAccountChecker
{
    public $mailFiles = "mileageplus/statements/it-46991221.eml, mileageplus/statements/it-6970910.eml, mileageplus/statements/it-7056371.eml";

    protected $lang = null;

    protected $langDetectors = [
        "en" => [[
            "My account",
            "Earn award miles",
            "Use award miles",
        ], [
            "My account",
            "Earn miles",
            "Use miles",
        ]],
        "es" => [[
            "Mi cuenta",
            "Gane millas de premio",
            "Use sus millas de premio",
        ], [
            "Mi cuenta",
            "Ganar millas",
            "Usar millas",
        ]],
        "ja" => [[
            "マイアカウント",
            "特典マイルの獲得",
            "特典マイルのご利用",
        ], [
            "マイアカウント",
            "マイルの獲得",
            "マイルの利用",
        ]],
        "pt" => [[
            "Minha conta",
            "Ganhe milhas-prêmio",
            "Use milhas-prêmio",
        ], [
            "Minha conta",
            "Ganhe milhas",
            "Use milhas",
        ]],
        "zh" => [[
            "我的帐户",
            "赢取奖励里程",
            "使用奖励里程",
        ], [
            "我的帐户",
            "赢取里程",
            "使用里程",
        ]],
    ];

    protected $dict = [
        "MileagePlus status" => [
            "es" => "Estatus de MileagePlus",
            "ja" => "マイレージプラス・ステータス",
            "pt" => "Status MileagePlus",
            "zh" => "前程万里 (MileagePlus) 身份",
        ],
        "Your award miles expire" => [
            "es" => null,
            "ja" => null,
            "pt" => null,
            "zh" => null,
        ],
        "Premier qualifying miles" => [
            "es" => "Millas que califican para Premier",
            "ja" => "プレミア資格対象マイル",
            "pt" => "Milhas de qualificação Premier",
            "zh" => "贵宾合格里程",
        ],
        "Premier qualifying segments" => [
            "es" => "Segmentos que califican para Premier",
            "ja" => "プレミア資格対象区間",
            "pt" => "Segmentos de qualificação Premier",
            "zh" => "贵宾合格航段",
        ],
        "Premier qualifying dollars" => [
            "es" => null,
            "ja" => null,
            "pt" => null,
            "zh" => null,
        ],
        "award miles" => [
            "es" => "millas de premio",
            "ja" => "特典マイル",
            "pt" => "milhas-prêmio",
            "zh" => "英里奖励里程",
        ],
        "miles" => [
            "es" => "millas",
            "ja" => "マイル",
            "pt" => "milhas",
            "zh" => "英里里程",
        ],
        "Lifetime flight miles" => [
            "ja" => "ライフタイムフライトマイル",
        ],
    ];
    protected $propertyLines = [
        "MemberStatus"          => "MileagePlus status",
        "AccountExpirationDate" => "Your award miles expire",
        "EliteMiles"            => "Premier qualifying miles",
        "EliteSegments"         => "Premier qualifying segments",
        "EliteDollars"          => "Premier qualifying dollars",
        "LifetimeMiles"         => "Lifetime flight miles",
    ];
    protected $propertyRegexps = [
        "MemberStatus"          => "(.+)",
        "AccountExpirationDate" => "\D*(\d+\/\d+\/\d+)",
        "EliteMiles"            => "([\d,]+)",
        "EliteSegments"         => "([\d\.]+)",
        "EliteDollars"          => "(\\\$[\d,]+)",
        "LifetimeMiles"         => "([\d,]+)",
    ];

    public static function getEmailLanguages()
    {
        return ["en", "es", "ja", "pt", "zh"];
    }

    public static function getEmailTypesCount()
    {
        return 2;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->http->FilterHTML = false;
        $this->http->SetBody($parser->getHTMLBody());
        $props = $this->ParseEmail();

        return [
            "parsedData" => ["Properties" => $props],
            "emailType"  => "MonthlyStatement", // don't edit please!
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers["from"]) && stripos($headers["from"], "mymileageplus@news.united.com") !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $this->detectLang();

        return isset($this->lang)
            || ($this->http->XPath->query('//img[contains(@src, "logo_mileagePlus.jpg") or contains(@src,"logo_mileagePlus_")] | //a[contains(.,"MyMileagePlus@news.united.com") or contains(@href,"news.united.com")]')->length > 0
                && $this->http->XPath->query("//text()[starts-with(normalize-space(),'Premier qualifying miles')]")->length > 0);
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@\.]united\.com/", $from) > 0;
    }

    protected function ParseEmail()
    {
        $props = [];
        $this->detectLang();

        foreach ($this->http->XPath->query("//sup") as $sup) {
            $sup->nodeValue = "";
        }
        $number = $this->http->FindSingleNode("//text()[contains(.,'XXXXX')]", null, true, "/XXXXX([A-Z\d]{3})/");

        if ($number) {
            $props["PartialLogin"] = $props["PartialNumber"] = $number . "$";
        }
        $props["Name"] = $this->http->FindSingleNode("//tr[td[contains(.,'XXXXX') and not(.//td)]]/td[1]");

        if (!isset($props['Name'])) {
            $props['Name'] = $this->http->FindSingleNode('//div[contains(@class,"usr-name")]');
        }
        $awardMiles = $this->translate('award miles');
        $balance = $this->http->FindSingleNode("//td[ not(.//td) and ./descendant::text()[normalize-space(.)='*'] and ./descendant::text()[normalize-space(.)='{$awardMiles}'] ]", null, true, "/^([,\d]+)\*? {$awardMiles}/");
        $nodes = $this->http->XPath->query("//text()[normalize-space(.)='{$awardMiles}']");

        if (!isset($balance) && $nodes->length > 0) {
            $node = $nodes->item(0);

            for ($i = 5; $i > 0 && !isset($balance) && isset($node); $i--) {
                if ($this->http->XPath->query("parent::*//sup", $node)->length > 0 && preg_match("/^([\d,]+)\*? *{$awardMiles}$/", CleanXMLValue($node->nodeValue), $m)) {
                    $balance = $m[1];
                }
                $nodes = $this->http->XPath->query("parent::*", $node);

                if ($nodes->length > 0) {
                    $node = $nodes->item(0);
                } else {
                    unset($node);
                }
            }
        }

        if (!isset($balance)) {
            foreach (['award miles', 'miles'] as $str) {
                if (!is_null($balance = $this->http->FindSingleNode('//div[contains(normalize-space(@id),"myMiles")]', null, true, "/^([,\d]+)\*? {$this->translate($str)}/i"))) {
                    break;
                }
            }
        }

        if (!isset($balance)) {
            foreach (['award miles', 'miles'] as $str) {
                if (!is_null($balance = $this->http->FindSingleNode("//text()[contains(normalize-space(.),'*') and contains(normalize-space(.),'{$this->translate($str)}')]/ancestor::div[1]", null, true, "/^([,\d]+)\*? {$this->translate($str)}/i"))) {
                    break;
                }
            }
        }

        if (isset($balance)) {
            $props['Balance'] = str_replace([',', '*'], '', $balance);
        }

        foreach ($this->propertyLines as $field => $text) {
            $text = $this->translate($text);
            $xpath = sprintf("//div[contains(normalize-space(.),\"%s\") and not(.//div)]", $text);
            $regexp = sprintf("/%s[^:]*: %s$/", str_replace(["(", ")"], ["\\(", "\\)"], $text), $this->propertyRegexps[$field]);
            $property = $this->http->FindSingleNode($xpath, null, true, $regexp);

            if (!isset($property)) {
                $xpath = sprintf("//div[starts-with(normalize-space(.),\"%s\") and not(.//div)]", $text);
                $property = $this->http->FindSingleNode($xpath, null, true, $regexp);
            }

            if (!isset($property)) {
                $property = $this->http->FindSingleNode('//td[contains(.,"Year to date") and not(contains(.,"award miles"))]' . $xpath, null, true, $regexp);
            }

            if (!isset($property)) {
                if ('EliteMiles' === $field) {
                    $check = 'EliteSegments';
                } else {
                    $check = 'EliteMiles';
                }
                $check = $this->translate($this->propertyLines[$check]);
                $xpath = sprintf("//div[contains(normalize-space(.),\"%s\") and not(.//div) and parent::*[contains(normalize-space(.),\"%s\")]]", $text, $check);
                $property = $this->http->FindSingleNode($xpath, null, true, $regexp);
            }

            if (isset($property)) {
                if ($field === 'EliteSegments' && stripos($property, '.') === 0) {
                    $props[$field] = $property === '.0' ? '0' : (($property === '.5') ? '0.5' : $property);
                } else {
                    $props[$field] = $property;
                }
            }
        }

        if (isset($props["AccountExpirationDate"])) {
            $expDate = strtotime($props["AccountExpirationDate"]);

            if ($expDate > mktime(0, 0, 0, 1, 1, 1990)) {
                $props["AccountExpirationDate"] = $expDate;
            } else {
                unset($props["AccountExpirationDate"]);
            }
        }

        if (!isset($props['Name'])) {
            unset($props["Balance"]);
        }

        return $props;
    }

    protected function detectLang()
    {
        unset($this->lang);

        foreach ($this->langDetectors as $lang => $arrLines) {
            foreach ($arrLines as $lines) {
                $links = array_map(function ($s) {
                    return sprintf(".//a[contains(normalize-space(.), '%s')]", $s);
                }, $lines);
                $xpath = sprintf("//tr[not(.//tr) and %s]", implode(" and ", $links));

                if ($this->http->XPath->query($xpath)->length > 0) {
                    $this->lang = $lang;

                    return;
                }
            }
        }
    }

    protected function translate($s)
    {
        if (isset($this->lang) && isset($this->dict[$s][$this->lang])) {
            return $this->dict[$s][$this->lang];
        } else {
            return $s;
        }
    }
}
