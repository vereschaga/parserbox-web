<?php

namespace AwardWallet\Engine\hertz\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class RewardsAccount extends \TAccountChecker
{
    public $mailFiles = "hertz/statements/it-62673561.eml, hertz/statements/it-62902818.eml, hertz/statements/it-62953325.eml, hertz/statements/it-62997631.eml, hertz/statements/it-63100285.eml, hertz/statements/it-63101065.eml, hertz/statements/it-63201819.eml, hertz/statements/it-72867690.eml";
    public $lang = '';

    public $detectLang = [
        'da' => 'Medlemsnummer',
        'en' => 'Member Number',
        'es' => ['Numero De Socio', 'Numero de Socio', 'hasta'],
        'pt' => ['Número de sócio', 'Minha conta'],
        "de" => 'Mitgliedsnummer',
        "it" => 'Accedi al tuo account',
        "ko" => '나의 회원정보',
    ];

    public static $dictionary = [
        "da" => [
            'As of'         => ['Konto Opdatering Fra'],
            'View Account'  => 'Vis',
            'Member Number' => 'Medlemsnummer',
            'points'        => ['points', 'Points', 'Point'],
        ],
        "en" => [
            'points' => ['points', 'Points'],
            'As of'  => ['As of', 'Account Update As Of'],
        ],
        "es" => [
            'As of'         => 'hasta',
            'View Account'  => ['Ver Cuenta', 'Mi Cuenta', 'Mi cuenta'],
            'Member Number' => ['Numero De Socio', 'Número de socio'],
            'points'        => ['Puntos', 'points', 'Points', 'Point'],
        ],
        "pt" => [
            'As of'         => ['até', 'Até'],
            'View Account'  => 'Minha conta',
            'Member Number' => 'Número de sócio',
            'points'        => ['Pontos', 'pontos'],
            'Member #'      => ['Member #', '# de sócio', 'Membro #'],
        ],
        "de" => [
            //'As of' => '',
            'View Account'  => ['Kontoinformationen', 'Konto ansehen'],
            'Member Number' => 'Mitgliedsnummer',
            'points'        => 'Punkte',
        ],
        "it" => [
            'As of'         => 'dal',
            'View Account'  => ['Accedi al tuo account'],
            'Member Number' => 'Numero socio',
            'points'        => 'Punti',
        ],
        "ko" => [
            //'As of' => '',
            'View Account'  => ['나의 회원정보'],
            'Member Number' => '회원번호',
            //            'points' => '',
        ],
    ];

    private $reFrom = [
        'HertzGoldOffers@emails.hertz.com',
        'webmaster@goldplusrewards.hertz.com',
        'marketing@emails.hertz.com',
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->detectLang();
        $this->logger->notice($this->lang);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        if ($this->http->XPath->query("//td[not(normalize-space())][//img/ancestor::a[@title = 'Hertz' and contains(@href, 'emails.hertz.com')]]/"
                . "following-sibling::td[normalize-space()][" . $this->eq($this->t("RESERVATIONS | DISCOUNTS & COUPONS")) . "]")->length > 0) {
            $email->setIsJunk(true);

            $this->logger->debug('setIsJunk = ');

            return $email;
        }

        $st = $email->add()->statement();

        //it-63100285, it-63201819
        if (preg_match("/^{$this->opt($this->t('View Account'))}\s*(\D+)\s*{$this->opt($this->t('Member Number'))}\s*(\d{8})\s*{$this->opt($this->t('points'))}?\s*(\d+)?$/u", $this->http->FindSingleNode("//text()[{$this->starts($this->t('View Account'))}]/ancestor::tr[3]"), $m)) {
            $this->logger->debug($this->http->FindSingleNode("//text()[{$this->starts($this->t('View Account'))}]/ancestor::tr[3]"));

            if (isset($m[3])) {
                $st->setBalance($m[3]);
            } else {
                $st->setNoBalance(true);
            }

            $st->addProperty('Name', $m[1]);
            $st->setNumber($m[2])
                ->setLogin($m[2]);

            return $email;
        }

        if (empty($this->http->FindSingleNode("//a[{$this->starts($this->t('View Account'))}]/preceding::text()[{$this->contains($this->t('points'))}][1]", null, true, "/(\d[\d\,]+\s*{$this->opt($this->t('points'))})/"))
            && empty($this->http->FindSingleNode("//a[{$this->starts($this->t('View Account'))}]/following::td[{$this->contains($this->t('points'))}][1]", null, true, "/(\d[\d\,]+\s*{$this->opt($this->t('points'))})/"))
            && empty($this->http->FindSingleNode("//a[{$this->starts($this->t('View Account'))}]/following::text()[{$this->contains($this->t('points'))}][1]", null, true, "/(\d+\s*{$this->opt($this->t('points'))})/"))
        ) {
            $st->setNoBalance(true);
        } else {
            $points = $this->http->FindSingleNode("//a[{$this->starts($this->t('View Account'))}]/preceding::text()[{$this->contains($this->t('points'))}][1]", null, true, "/([\d\,]+)\s*{$this->opt($this->t('points'))}/");

            if (empty($points)) {
                $points = $this->http->FindSingleNode("//a[{$this->starts($this->t('View Account'))}]/following::td[{$this->contains($this->t('points'))}][1]", null, true, "/([\d\,]+)\s*{$this->opt($this->t('points'))}/");
            }
            $st->setBalance(str_replace(",", "", $points));
        }

        $name = implode(' ', $this->http->FindNodes("//a[{$this->starts($this->t('View Account'))}]/ancestor::tr[1]/preceding::tr[1]/descendant::td[1]//text()[normalize-space()][not(ancestor::*[contains(@style, 'display: none;') or contains(@style, 'display:none;')])]"));

        if (empty($name)) {
            $name = $this->http->FindSingleNode("//a[{$this->starts($this->t('View Account'))}]/ancestor::tr[1]", null, true, "/^(\D+){$this->opt($this->t('View Account'))}/");
        }
        //it-62953325
        if (empty($name)) {
            $name = $this->http->FindSingleNode("//a[starts-with(normalize-space(.), 'Vis') and contains(normalize-space(), 'Konto')]/ancestor::tr[1]", null, true, "/^(\D+){$this->opt($this->t('View Account'))}/");
        }
        $st->addProperty('Name', $name);

        $number = $this->http->FindSingleNode("//td[{$this->starts($this->t('Member Number'))}]/ancestor::tr[1]/preceding::tr[1]/descendant::td[last()]", null, true, "/^(\d{8})$/");

        if (empty($number)) {
            $number = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Member Number'))}]/ancestor::td[1]", null, true, "/^(\d+)\s*{$this->opt($this->t('Member Number'))}/");
        }

        if (empty($number)) {
            $number = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Member Number'))}]/ancestor::tr[1]", null, true, "/^(\d+)\s*{$this->opt($this->t('Member Number'))}/");
        }

        if (empty($number)) {
            $number = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Member #'))}]", null, true, "/^{$this->opt($this->t('Member #'))}\s*(\d{5,})\s*$/");
        }
        $st->setNumber($number)
            ->setLogin($number);

        $balanceDate = $this->http->FindSingleNode("//td[{$this->starts($this->t('As of'))}]", null, true, "/^{$this->opt($this->t('As of'))}\s+(\d+\/\d+\/\d{4})/u");

        if (empty($balanceDate)) {
            $balanceDate = $this->http->FindSingleNode("//text()[{$this->starts($this->t('As of'))}]", null, true, "/^{$this->opt($this->t('As of'))}\s+(\d+\/\d+\/\d{4})/u");
        }

        if (!empty($balanceDate) && $st->getNoBalance() == false) {
            if (checkdate($this->re("/^(\d+)\/\d+\/\d{4}$/", $balanceDate), $this->re("/^\d+\/(\d+)\/\d{4}$/", $balanceDate), $this->re("/^\d+\/\d+\/(\d{4})$/", $balanceDate)) !== true) {
                $st->setBalanceDate($this->normalizeDate($balanceDate));
            } else {
                $st->setBalanceDate(strtotime($balanceDate));
            }
        }

        $pointsExpiring = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Hertz Gold Plus Rewards'))}]/following::text()[{$this->contains($this->t('will expire on'))}]/following::text()[normalize-space()][1]", null, true, "/^(\d+\/\d+\/\d{4}\s+[\d\:]+\s*A?P?M)$/");

        if (empty($pointsExpiring)) {
            $pointsExpiring = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Hertz Gold Plus Rewards'))}]", null, true, "/{$this->opt($this->t('will expire on'))}\s*(\d+\/\d+\/\d{4})\s*due/");
        }

        if (!empty($pointsExpiring)) {
            $st->addProperty('PointsExpiring', $this->normalizeDate($pointsExpiring));
        }

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return $this->arrikey($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
//        if (self::detectEmailFromProvider($headers['from']) !== true) {
//            return false;
//        }
//        return $this->arrikey($headers['subject'], $this->reSubject) !== false;
        return self::detectEmailFromProvider($headers['from']);
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->detectLang() == true) {
            return (
                    $this->http->XPath->query("//text()[{$this->contains($this->t('HertzGold'))}]")->length > 0
                    || $this->http->XPath->query("//text()[{$this->contains($this->t('Gold Plus Rewards'))} or {$this->contains($this->t('extending your loyalty status'))}]")->length > 0
                    || preg_match("/Gold Plus Rewards/", $parser->getSubject())
                    || $this->http->XPath->query("//img[contains(@alt, 'GOLD PLUS REWARDS')]")->count() > 0
                    || preg_match("/(?:Your reward is confirmed|Your reward is available|you have a reward available|was a rewarding year for you|you could win a weekly rental with your points)/", $parser->getSubject())
                )
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Hertz'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Member Number'))} or {$this->starts($this->t('Member #'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('View Account'))}]")->length > 0;
        }

        return false;
    }

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ')="' . $s . '"';
        }, $field)) . ')';
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

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }

    private function normalizeDate($str)
    {
        $in = [
            //10/21/2020 12:00:00 AM
            '#^(\d+)\/(\d+)\/(\d{4})\s+(\d+\:\d+)\:\d+\s*(A?P?M)$#u',
            //10/21/2020
            '#^(\d+)\/(\d+)\/(\d{4})$#u',
        ];
        $out = [
            '$2.$1.$3, $4',
            '$1.$2.$3',
        ];
        $str = preg_replace($in, $out, $str);

        return strtotime($str);
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function detectLang()
    {
        $body = $this->http->Response['body'];

        foreach ($this->detectLang as $lang => $detects) {
            if (is_array($detects)) {
                foreach ($detects as $word) {
                    if (stripos($body, $word) !== false) {
                        $this->lang = $lang;

                        return true;
                    }
                }
            } elseif (is_string($detects) && stripos($body, $detects) !== false) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function arrikey($haystack, array $arrayNeedle)
    {
        foreach ($arrayNeedle as $key => $needles) {
            if (is_array($needles)) {
                foreach ($needles as $needle) {
                    if (stripos($haystack, $needle) !== false) {
                        return $key;
                    }
                }
            } else {
                if (stripos($haystack, $needles) !== false) {
                    return $key;
                }
            }
        }

        return false;
    }
}
