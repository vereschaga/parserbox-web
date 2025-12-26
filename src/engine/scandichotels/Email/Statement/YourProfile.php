<?php

namespace AwardWallet\Engine\scandichotels\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class YourProfile extends \TAccountChecker
{
    public $mailFiles = "scandichotels/statements/it-64895304.eml, scandichotels/statements/it-65183922.eml, scandichotels/statements/it-65254970.eml, scandichotels/statements/it-73594985.eml, scandichotels/statements/it-73648377.eml, scandichotels/statements/it-73688785.eml, scandichotels/statements/it-73783689.eml";

    public $dict = [
        'en' => [
            '!!detect' => 'Scandic hotels reserve themselves from any errors',
        ],
        'sv' => [
            '!!detect'        => 'Scandic hotels reserverar sig för eventuella fel',
            'Your Profile'    => 'Din profil',
            'Points to spend' => 'Poängsaldo idag',
        ],
        'de' => [
            '!!detect'        => 'Scandic Hotels behält sich Fehler',
            'Your Profile'    => 'Dein Profil',
            'Points to spend' => 'Verfügbare Punkte',
        ],
        'no' => [
            '!!detect'        => 'Scandic Hotels tar ikke ansvar for eventuelle feil',
            'Your Profile'    => 'Profilen din',
            'Points to spend' => 'Disponible poeng',
        ],
        'da' => [
            '!!detect'        => 'Scandic fralægger sig ansvaret for eventuelle fejl',
            'Your Profile'    => 'Din profil',
            'Points to spend' => 'Point du kan bruge',
        ],
        'fi' => [
            '!!detect'        => 'Scandic ei vastaa uutiskirjeiden tarjouksissa esiintyvistä virheistä',
            'Your Profile'    => 'JÄSENPROFIILISI',
            'Points to spend' => 'Pisteitä käytettävissä',
        ],
    ];

    private $lang;

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], 'scandicfriends@info.scandichotels.com') !== false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        return $this->detectLang($parser->getHTMLBody())
            && ($this->http->XPath->query("//tr[td[table[normalize-space(.) = '{$this->tr('Your Profile')}']]]/following-sibling::tr[contains(., '{$this->tr('Points to spend')}')]")->length > 0
                || $this->http->XPath->query("//text()[contains(normalize-space(), 'welcome to our coworking spaces')]"))
            ;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/\bscandichotels\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->detectLang($parser->getHTMLBody())) {
            return $email;
        }
        $rows = $this->http->FindNodes("//tr[td[table[normalize-space(.) = '{$this->tr('Your Profile')}']] and following-sibling::tr[contains(., '{$this->tr('Points to spend')}')]]/parent::*//tr[not(.//tr) and normalize-space(.) != '']");

        if (isset($rows[2]) && isset($rows[3])
            && preg_match('/\s*(\d{1,2}\.\d{2}\.20\d{2}): (\d[\d ]*)\s*\*/', $rows[2], $m) > 0
            && preg_match('/^(\d{5,})$/', $rows[3])
        ) {
            $t = $rows[2];
            $rows[2] = $rows[3];
            $rows[3] = $t;
        }

        if (count($rows) >= 4
            && stripos($rows[1], $this->tr('Points to spend')) !== false
            && preg_match('/(\d{1,2}\.\d{2}\.20\d{2}): (\d[\d ]*)\s*$/', $rows[1], $m)
            && preg_match('/^(\d{5,})$/', $rows[2])
        ) {
            $st = $email->add()->statement();
            $st->setBalance(str_replace(' ', '', $m[2]))
                ->parseBalanceDate($m[1])
                ->addProperty('Name', $rows[0])
                ->setNumber($rows[2])
                ->setLogin($rows[2]);

            if (preg_match('/\s*(\d{1,2}\.\d{2}\.20\d{2}): (\d[\d ]*)\s*\*/', $rows[3], $m2)) {
                $st->setExpirationDate(strtotime($m2[1]))
                    ->addProperty('PointsToExpire', str_replace(' ', '', $m2[2]));
            }
        } else {
            $name = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'to our coworking spaces')]/ancestor::td[1]", null, true, "/^(\w+)\s*\,\s*welcome to our coworking spaces/");

            if (!empty($name)) {
                $st = $email->add()->statement();
                $st->addProperty('Name', $name);
                $st->setNoBalance(true);
                $st->setMembership(true);
            }
        }

        return $email;
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    private function detectLang($html)
    {
        $this->lang = null;

        foreach ($this->dict as $lng => $d) {
            if (stripos($html, $d['!!detect']) !== false) {
                $this->lang = $lng;

                break;
            }
        }

        return null !== $this->lang;
    }

    private function tr($str)
    {
        return $this->lang ? ($this->dict[$this->lang][$str] ?? $str) : $str;
    }
}
