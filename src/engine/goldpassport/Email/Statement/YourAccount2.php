<?php

namespace AwardWallet\Engine\goldpassport\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class YourAccount2 extends \TAccountChecker
{
    public $mailFiles = "goldpassport/statements/it-673295048.eml, goldpassport/statements/it-673407326.eml, goldpassport/statements/it-674023391.eml, goldpassport/statements/it-674890661.eml, goldpassport/statements/it-678561116.eml, goldpassport/statements/it-678751764.eml, goldpassport/statements/it-678956060.eml, goldpassport/statements/it-86102176.eml, goldpassport/statements/it-92812564.eml";

    public $subjects = [
        // en
        'Your Account Summary',
        'Still in Your Plans?',
        // zh
        '您的账户摘要 ',
        '您的賬戶摘要',
        // ko
        '계정 요약 정보 - ',
        // de
        'Ihre Kontoübersicht –',
        // es
        'Resumen de su cuenta - ',
        // fr
        'Récapitulatif de votre compte – ',
        // ja
        'アカウントサマリー：',
    ];

    // !! only for incomplete data
    public $detectBody = [
        // en
        'Your next trip could be just around the corner.',
        'Register before you stay to earn thousands of points',
        "Here's a look at your year:",
        "benefits for World of Hyatt members",
        "have an award in your account that is waiting",
        "New rewards to earn and new ways to redeem points are coming",
        "Plus, you get a Guest of Honor Award",
        "You must be a member of World of Hyatt in good standing",
        "Earn Bonus Points at hotels within The Unbound Collection by Hyatt",
        "Earn with a World of Hyatt Business Credit Card, redeem",
        "Nights with the World of Hyatt Credit Card",
        "Your next stay could be on us with this new cardmember offer",
    ];

    public $lang = 'en';

    public static $dictionary = [
        // Explorist   |   8,742 Current Points   |   28,810 Lifetime Base Points
        //          0            5
        //        Base       Qualifying
        //       Points       Nights
        "zh" => [
            'Current Points'       => ['当前积分', '當前積分'],
            'Lifetime Base Points' => ['终身基本积分', '終身基本積分'],
            'Base Points'          => ['基本积分', '基本積分'],
            'Qualifying Nights'    => ['认可房晚', '認可房晚'],
            'detectProvider'       => ['凯悦酒店集团版权所有。保留所有权利。', '凱悅酒店集團版權所有。保留所有權利。'],
        ],
        "en" => [
            'Current Points'       => 'Current Points',
            'Lifetime Base Points' => 'Lifetime Base Points',
            'Base Points'          => 'Base Points',
            'Qualifying Nights'    => ['Qualifying Nights', 'Qualifying Night'],
            'detectProvider'       => 'Hyatt Corporation. All rights reserved',
        ],
        "ko" => [
            'Current Points'       => '현재 포인트',
            'Lifetime Base Points' => '라이프타임 기본 포인트',
            'Base Points'          => '기본 포인트',
            'Qualifying Nights'    => ['정규 숙박'],
            'detectProvider'       => 'Hyatt Corporation. All rights reserved',
        ],
        "de" => [
            'Current Points'       => 'aktuelle Punkte',
            'Lifetime Base Points' => 'Lifetime Basispunkte',
            'Base Points'          => 'Basispunkte',
            'Qualifying Nights'    => ['Qualifizierende Übernachtungen'],
            'detectProvider'       => 'Hyatt Corporation. Alle Rechte vorbehalten.',
        ],
        "es" => [
            'Current Points'       => 'Puntos actuales',
            'Lifetime Base Points' => 'Puntos Básicos Lifetime',
            'Base Points'          => 'Puntos Básicos',
            'Qualifying Nights'    => ['Noches válidas'],
            'detectProvider'       => 'Hyatt Corporation. Todos los derechos reservados.',
        ],
        "fr" => [
            'Current Points'       => 'Solde actuel :',
            'Lifetime Base Points' => 'Points de base Lifetime',
            'Base Points'          => 'Points de base',
            'Qualifying Nights'    => ['Nuits éligibles'],
            'detectProvider'       => 'Hyatt Corporation. Tous droits réservés.',
        ],
        "ja" => [
            'Current Points'       => '現在のポイント：',
            'Lifetime Base Points' => 'これまでに獲得した総ベースポイント数：',
            'Base Points'          => '対象ベースポイント数',
            'Qualifying Nights'    => ['対象宿泊数'],
            'detectProvider'       => 'Hyatt Corporation. All rights reserved.',
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->subjects as $phrase) {
            if (is_string($phrase) && stripos($headers['subject'], $phrase) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $detectedProvider = false;

        if ($this->http->XPath->query("//a/@href[{$this->contains('https://go.hyatt.com/link/v2')}]")->length > 3
        ) {
            $detectedProvider = true;
        }

        if ($this->http->XPath->query("//a/@href[{$this->contains('https://links.t1.hyatt.com/els')}]")->length > 3
        ) {
            $detectedProvider = true;
        }

        foreach (self::$dictionary as $dict) {
            if ($detectedProvider === false && (empty($dict['detectProvider']) || $this->http->XPath->query("//node()[{$this->contains($dict['detectProvider'])}]")->length === 0)) {
                continue;
            }

            if (empty($dict['Current Points']) || empty($dict['Lifetime Base Points']) || empty($dict['Base Points']) || empty($dict['Qualifying Nights'])) {
                continue;
            }

            $xpathStr = "//td[not(.//td)][count(.//text()[normalize-space()]) < 10][contains(., '|')][{$this->contains($dict['Current Points'])}][{$this->contains($dict['Lifetime Base Points'])}]";
            $xpathTable = "//tr[td[normalize-space()][1]//text()[{$this->eq($dict['Base Points'], true)}]][td[normalize-space()][2]//text()[{$this->eq($dict['Qualifying Nights'], true)}]]";
            // $this->logger->debug('$xpathStr = '.print_r( $xpathStr,true));
            // $this->logger->debug('$xpathTable = '.print_r( $xpathTable,true));
            if ($this->http->XPath->query($xpathStr)->length > 0
                && $this->http->XPath->query($xpathTable)->length > 0
            ) {
                // complete data
                return true;
            }

            if ($this->http->XPath->query("//node()[{$this->contains($this->detectBody)}]")->length > 0
                && $this->http->XPath->query("//td[not(.//td)][count(.//text()[normalize-space()]) < 10][contains(., '|')][{$this->contains($dict['Current Points'])}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($dict['Lifetime Base Points'])} or {$this->eq($dict['Base Points'], true)} or {$this->eq($dict['Qualifying Nights'], true)}]")->length === 0
            ) {
                // incomplete data
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@em.hyatt.com') !== false || stripos($from, '@m1.hpe-esp.hyatt.com') !== false;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict['Current Points'])
                && $this->http->XPath->query("//*[{$this->contains($dict['Current Points'])}]")->length > 0
            ) {
                $this->lang = $lang;

                break;
            }
        }

        $st = $email->add()->statement();

        $accountInfo = $this->http->FindSingleNode("//td[not(.//td)][count(.//text()[normalize-space()]) < 10][contains(., '|')][{$this->contains($this->t('Current Points'))}][{$this->contains($this->t('Lifetime Base Points'))}]");
        // $this->logger->debug('$accountInfo = '.print_r( $accountInfo,true));
        //Discoverist   |   25,825 Current Points   |   58,927 Lifetime Base Points
        if (
            preg_match("/^\s*(?<tier>[[:alpha:]]+)\s*\|\s*(?<cp>\d[\d\,]*)\s*{$this->opt($this->t('Current Points'))}\s*[\|\s]\s*(?<ltp>\d[\d\,]*)\s*{$this->opt($this->t('Lifetime Base Points'))}\s*$/u", $accountInfo, $m)
            || (in_array($this->lang, ['ko', 'ja']) && preg_match("/^\s*(?<tier>[[:alpha:]]+)\s*\|\s*{$this->opt($this->t('Current Points'))}\s*(?<cp>\d[\d\,]*)\s*점?\s*[\|\s]\s*{$this->opt($this->t('Lifetime Base Points'))}\s*(?<ltp>\d[\d\,]*)\s*점?\s*$/u", $accountInfo, $m))
            || ($this->lang === 'fr' && preg_match("/^\s*(?<tier>[[:alpha:]]+)\s*\|\s*{$this->opt($this->t('Current Points'))}\s*(?<cp>\d[\d\,]*)\s*[\|\s]\s*(?<ltp>\d[\d\,]*)\s*{$this->opt($this->t('Lifetime Base Points'))}\s*$/u", $accountInfo, $m))
        ) {
            $st->addProperty('Tier', $m['tier'])
                ->setBalance($m['cp'] !== null ? str_replace(',', '', $m['cp']) : null)
                ->addProperty('LifetimeBasePoints', $m['ltp'] !== null ? str_replace(',', '', $m['ltp']) : null);

            $basePoints = null;
            $nights = null;

            $tableXpath = "//tr[not(.//tr)][td[normalize-space()][1][{$this->eq($this->t('Base Points'), true)}]][td[normalize-space()][2][{$this->eq($this->t('Qualifying Nights'), true)}]]/preceding-sibling::tr[normalize-space()][1]";
            // $this->logger->debug('$tableXpath 1 = '.print_r( $tableXpath,true));
            if ($this->http->XPath->query($tableXpath)->length > 0) {
                // <tr> <td>Base Points</td> <td>Qualifying Nights</td> </tr>
                $basePoints = $this->http->FindSingleNode($tableXpath . "/*[normalize-space()][1]", null, true,
                    "/^\s*(\d[\d,]*)\s*$/");

                $nights = $this->http->FindSingleNode($tableXpath . "/*[normalize-space()][2]", null, true,
                    "/^\s*(\d{1,3})\s*$/");
            }

            $tableXpath = "//tr[*[normalize-space()][1]/descendant::tr[not(.//tr)][normalize-space()][2][{$this->eq($this->t('Base Points'), true)}]]"
                . "[*[normalize-space()][2]/descendant::tr[not(.//tr)][normalize-space()][2][{$this->eq($this->t('Qualifying Nights'), true)}]]";
            // $this->logger->debug('$tableXpath 2 = '.print_r( $this->http->XPath->query($tableXpath)->length,true));
            if ($basePoints === null && $nights === null && $this->http->XPath->query($tableXpath)->length > 0) {
                // <tr> <td>0<td> <td>Base Points</td> </tr>
                // <tr> <td>0<td> <td>Qualifying Nights</td> </tr>
                $basePoints = $this->http->FindSingleNode($tableXpath . "/*[normalize-space()][1]/descendant::tr[not(.//tr)][normalize-space()][1]", null, true,
                    "/^\s*(\d[\d,]*)\s*$/");

                $nights = $this->http->FindSingleNode($tableXpath . "/*[normalize-space()][2]/descendant::tr[not(.//tr)][normalize-space()][1]", null, true,
                    "/^\s*(\d{1,3})\s*$/");
            }

            $st->addProperty('BasePointsYTD', $basePoints !== null ? str_replace(',', '', $basePoints) : null);
            $st->addProperty('Nights', $nights);
        }

        if (empty($accountInfo) && $this->http->XPath->query("//node()[{$this->contains($this->detectBody)}]")->length > 0
            && $this->http->XPath->query("//td[not(.//td)][count(.//text()[normalize-space()]) < 10][contains(., '|')][{$this->contains($dict['Current Points'])}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($dict['Lifetime Base Points'])} or {$this->eq($dict['Base Points'], true)} or {$this->eq($dict['Qualifying Nights'], true)}]")->length === 0
        ) {
            // incomplete data
            $accountInfo = $this->http->FindSingleNode("//td[not(.//td)][count(.//text()[normalize-space()]) < 10][contains(., '|')][{$this->contains($this->t('Current Points'))}]");
            //Discoverist   |   25,825 Current Points
            if (
                preg_match("/^\s*(?<tier>[[:alpha:]\s]+)\s*\|\s*(?<cp>\d[\d\,]*)\s*{$this->opt($this->t('Current Points'))}\s*$/u", $accountInfo, $m)
                || (in_array($this->lang, ['ko', 'ja']) && preg_match("/^\s*(?<tier>[[:alpha:]\s]+)\s*\|\s*{$this->opt($this->t('Current Points'))}\s*(?<cp>\d[\d\,]*)\s*점?\s*$/u", $accountInfo, $m))
                || ($this->lang === 'fr' && preg_match("/^\s*(?<tier>[[:alpha:]\s]+)\s*\|\s*{$this->opt($this->t('Current Points'))}\s*(?<cp>\d[\d\,]*)\s*\s*$/u", $accountInfo, $m))
            ) {
                $st->addProperty('Tier', $m['tier'])
                    ->setBalance(str_replace(',', '', $m['cp']));
            }

            $basePoints = $this->http->FindSingleNode("//text()[normalize-space()='BASE POINTS']/ancestor::tr[1]/preceding::text()[normalize-space()][1]", null, true, "/^([\d\.\,]+)$/");

            if ($basePoints !== null) {
                $st->addProperty('BasePointsYTD', str_replace(',', '', $basePoints));
            }

            $nights = $this->http->FindSingleNode("//text()[normalize-space()='QUALIFYING NIGHTS']/ancestor::tr[1]/preceding::text()[normalize-space()][1]", null, true, "/^(\d+)$/");

            if ($nights !== null) {
                $st->addProperty('Nights', $nights);
            }

            $dateBalance = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), '*Current as of')]", null, true, "/^{$this->opt($this->t('*Current as of'))}\s*(\w+\s*\d+\,\s*\d{4})$/");

            if (!empty($dateBalance)) {
                $st->setBalanceDate(strtotime($dateBalance));
            }
        }

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

    private function eq($field, $deleteSpace = false)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }
        $text = 'normalize-space(.)';

        if ($deleteSpace == true) {
            $field = str_replace(' ', '', $field);
            $text = 'translate(normalize-space(.), " ", "")';
        }

        return '(' . implode(" or ", array_map(function ($s) use ($text) {
            return $text . "=\"{$s}\"";
        }, $field)) . ')';
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }
}
