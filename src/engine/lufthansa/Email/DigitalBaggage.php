<?php

namespace AwardWallet\Engine\lufthansa\Email;

use AwardWallet\Schema\Parser\Email\Email;

class DigitalBaggage extends \TAccountChecker
{
    public $mailFiles = "lufthansa/it-30440146.eml, lufthansa/it-30562870.eml, lufthansa/it-30564921.eml, lufthansa/it-30564925.eml";

    public $reFrom = ["service@fly-lh.lufthansa.com", "service@your.lufthansa-group.com"];
    public $reBody = [
        'en' => ['Your baggage receipt', 'From'],
        'de' => ['Ihr Gepäckbeleg', 'From'],
        'de2' => ['Ihr Gepäckbeleg', 'Von'],
        'es' => ['Su recibo del equipaje', 'From'],
        'pt' => ['O seu recibo de bagagem', 'From'],
        'fr' => ['Votre reçu bagage', 'From'],
        'zh' => ['您的行李标签', 'From'],
    ];
    public $reSubject = [
        // en
        'Your digital baggage receipt for flight',
        // de
        'Ihr digitaler Gepäckbeleg für Flug',
        // es
        'Su recibo digital del equipaje para el vuelo',
        // pt
        'O seu recibo de bagagem digital para o voo',
        // fr
        'Votre reçu bagage numérique pour votre vol',
        // zh
        '您的数字行李标签 ：航班',
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
        ],
    ];
    private $keywordProv = 'Lufthansa';

    public static function getEmailProviders()
    {
        return ['lufthansa', 'austrian'];
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        // this parser send email to junk. so it has strong detects. by Subject including
        $detectedSubject = false;
        foreach ($this->reSubject as $reSubject) {
            if (stripos($parser->getSubject(), $reSubject) !== false) {
                $detectedSubject = true;
                break;
            }
        }
        if ($detectedSubject === true
            && $this->detectEmailByBody($parser) === true
        ) {
            $email->setIsJunk(true);
            return $email;
        }

//        if (!$this->assignLang()) {
//            $this->logger->debug('can\'t determine a language');
//
//            return $email;
//        }

//        if (!$this->parseEmail($email)) {
//            return null;
//        }

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if (
            $this->http->XPath->query("//a[contains(@href,'fly-lh.lufthansa.com') or contains(@href,'.lufthansa-group.com')]")->length > 0
            || $this->http->XPath->query("//a[contains(@href,'//smile.austrian.com')]")->length > 0
        ) {
            return $this->assignLang();
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->reFrom as $reFrom) {
            if (stripos($from, $reFrom) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        $fromProv = true;

        if (!isset($headers['from']) || self::detectEmailFromProvider($headers['from']) === false) {
            $fromProv = false;
        }

        if (isset($headers["subject"])) {
            foreach ($this->reSubject as $reSubject) {
                if (($fromProv && stripos($headers["subject"], $reSubject) !== false)
                    || strpos($headers["subject"], $this->keywordProv) !== false
                ) {
                    return true;
                }
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
        return 0;
    }

    //endregion

    private function parseEmail(Email $email)
    {
        $r = $email->add()->flight();
        $r->general()
            ->noConfirmation()
            ->traveller($this->http->FindSingleNode("//text()[{$this->starts($this->t('Your baggage receipt'))}]/following::text()[normalize-space()!=''][1]"));

        $xpath = "//text()[{$this->eq($this->t('Flight Number'))}]/ancestor::table[2][{$this->contains($this->t('Flight date'))}]";
        $nodes = $this->http->XPath->query($xpath);
        $roots = [];
        $flights = [];

        foreach ($nodes as $root) {
            $flight = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Flight Number'))}]/preceding::text()[normalize-space()!=''][1]",
                $root);
            // it's because of it-30564925.eml (fail email - exclude wrong flight - was checked by fs and look at it-30564921.eml)
            if (!in_array($flight, $flights)) {
                $roots[] = $root;
                $flights[] = $flight;
            }
        }

        $depCode = $this->http->FindSingleNode("//text()[{$this->eq($this->t('From'))}]/ancestor::table[./following-sibling::table[{$this->contains($this->t('To'))}]]/descendant::text()[normalize-space()!=''][1]");
        $depName = $this->http->FindSingleNode("//text()[{$this->eq($this->t('From'))}]/ancestor::table[./following-sibling::table[{$this->contains($this->t('To'))}]]/descendant::text()[normalize-space()!=''][3]");

        foreach (array_reverse($roots) as $i => $root) {
            $s = $r->addSegment();
            $flight = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Flight Number'))}]/preceding::text()[normalize-space()!=''][1]",
                $root);

            if (preg_match("/^([A-Z\d]{2})\s*(\d+)$/", $flight, $m)) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2]);
            }
            $rootSeg = $this->http->XPath->query("./ancestor-or-self::table[{$this->contains($this->t('To'))} or {$this->contains($this->t('Via'))}][1]",
                $root);

            if ($rootSeg->length !== 1) {
                $this->logger->debug("other format: {$i} - segment");

                return false;
            }
            $rootSeg = $rootSeg->item(0);
            //don't collect depName/depCode/depDay (for go to junk)
//            $s->departure()
//                ->name($depName)
//                ->code($depCode)
//                ->noDate()
//                ->day2($this->http->FindSingleNode("./descendant::text()[normalize-space()!=''][4]", $rootSeg));
            $s->departure()
                ->noCode()
                ->noDate();
            $depCode = $this->http->FindSingleNode("./descendant::text()[normalize-space()!=''][1]", $rootSeg);
            $depName = $this->http->FindSingleNode("./descendant::text()[normalize-space()!=''][3]", $rootSeg);
            //don't collect arrName/arrCode (for go to junk)
//            $s->arrival()
//                ->name($depName)
//                ->code($depCode)
            $s->arrival()
                ->noCode()
                ->noDate();
        }

        return true;
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
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if ($this->http->XPath->query("//*[contains(normalize-space(.),'{$reBody[0]}')]")->length > 0
                    && $this->http->XPath->query("//*[contains(normalize-space(.),'{$reBody[1]}')]")->length > 0
                ) {
                    $this->lang = substr($lang, 0, 2);

                    return true;
                }
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

        return implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field));
    }
}
