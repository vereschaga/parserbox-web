<?php

namespace AwardWallet\Engine\rapidrewards\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Schema\Parser\Email\Email;

class LetsComplete extends \TAccountChecker
{
    public $mailFiles = "rapidrewards/it-28524675.eml, rapidrewards/it-675058015.eml, rapidrewards/it-76183199.eml";

    public $reFrom = ["SouthwestAirlines@iluv.southwest.com"];
    public $reBody = [
        'en' => ['Full itinerary'],
    ];
    public $reSubject = [
        'Your flight\'s booked. Let\'s complete your travel plans',
        'Have you booked your ',
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
            'PASSENGER' => ['PASSENGER', 'PASSENGERS'],
        ],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        foreach (self::$dict as $lang => $dict) {
            if (isset($dict['PASSENGER']) && $this->http->XPath->query("//*[" . $this->contains($dict['PASSENGER']) . "]")->length > 0
            ) {
                $this->lang = $lang;

                break;
            }
        }

        if (empty($this->lang)) {
            $this->logger->debug('can\'t determine a language');

            return $email;
        }

        $a = explode('\\', __CLASS__);
        $class = end($a);
        $email->setType($class . ucfirst($this->lang));

        if ($this->http->XPath->query("//text()[contains(normalize-space(.),'Updated flight information')]")->length > 0) {
            $this->parseEmail2($parser, $email);
        } else {
            $this->parseEmail($parser, $email);
        }

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//img[contains(@alt,'Southwest')] | //a[contains(@href,'.southwest.com')]")->length > 0) {
            return $this->assignLang();
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && isset($headers["subject"])) {
            $flag = false;

            foreach ($this->reFrom as $reFrom) {
                if (stripos($headers['from'], $reFrom) !== false) {
                    $flag = true;
                }
            }

            if ($flag) {
                foreach ($this->reSubject as $reSubject) {
                    if (stripos($headers["subject"], $reSubject) !== false) {
                        return true;
                    }
                }
            }
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

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function parseEmail(\PlancakeEmailParser $parser, Email $email)
    {
        $xpath = "//text()[{$this->contains($this->t('Full itinerary'))}]/ancestor::table[{$this->contains($this->t('Confirmation'))}][1]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $texts = $this->http->FindNodes(".//text()[normalize-space()!=''][not({$this->contains($this->t('Full itinerary'))})]",
                $root);

            if (count($texts) > 7) {
                $text = implode("\n", $texts);
                $regExp = "/^\s*(?<dDay>[^\n]+?)\s*(?:\-\s*(?<aDay>[^\n]+))?\s+(?<dCode>[A-Z]{3})\s+(?<aCode>[A-Z]{3})\s+.*\s+" .
                    "{$this->opt($this->t('Confirmation'))}\s*\#\s+(?<pnr>[A-Z\d]{5,})\s+" .
                    "{$this->opt($this->t('PASSENGER'))}\s+(?<pax>.+)/is";

                if (preg_match($regExp, $text, $m)) {
                    if (isset($m['aDay']) && !empty($m['aDay'])) {
                        //FE:  December 23 - December 28
                        //no parse day's. it's better parse like junk (aDay - isn't arrDay)
                        $m['dDay'] = $m['aDay'] = null;
                    }

                    $f = $email->add()->flight();
                    $pax = array_filter(array_map("trim", explode("\n", $m['pax'])));

                    $f->general()
                        ->confirmation($m['pnr']);

                    foreach ($pax as $p) {
                        if (preg_match("/^\s*(.+?)\s*\(\s*{$this->opt($this->t('Lap Child'))}\s*\)\s*$/", $p, $pm)) {
                            $f->general()
                                ->infant($pm[1], true);
                        } else {
                            $f->general()
                                ->traveller($p, true);
                        }
                    }

                    $s = $f->addSegment();
                    $s->airline()
                        ->name('WN')//Southwest
                        ->noNumber();
                    $s->departure()
                        ->code($m['dCode'])
                        ->noDate();

                    if (isset($m['dDay']) && !empty($m['dDay'])) {
                        $s->departure()
                            ->day(EmailDateHelper::calculateDateRelative($m['dDay'], $this, $parser));
                    }
                    $s->arrival()
                        ->code($m['aCode'])
                        ->noDate();
                }
            }
        }

        return true;
    }

    private function parseEmail2(\PlancakeEmailParser $parser, Email $email)
    {
        $f = $email->add()->flight();

        $conf = $this->http->FindSingleNode("//text()[contains(normalize-space(.),'Confirmation #')]/following::text()[normalize-space()][1]", null, true, "/^([A-Z\d]+)$/");
        $f->general()
            ->confirmation($conf)
            ->travellers($this->http->FindNodes("//text()[contains(normalize-space(.),'PASSENGER')]/ancestor::tr[1]/descendant::text()[normalize-space()][not({$this->contains($this->t('Confirmation #'))} or {$this->contains($this->t('PASSENGER'))} or {$this->contains($conf)})]"))
            ->status('updated');

        $date = $this->http->FindSingleNode("//text()[contains(normalize-space(.),'Updated flight information')]/following::text()[normalize-space()][1]", null, true, "/^(\w+\,.*\d{4})/");

        $xpath = "//text()[contains(normalize-space(.),'Updated flight information')]/ancestor::table[1]/descendant::text()[contains(normalize-space(), 'DEPARTS')]/ancestor::tr[1]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $s->airline()
                ->name('WN')
                ->number($this->http->FindSingleNode("./descendant::td[normalize-space()][1]", $root, true, "/{$this->opt($this->t('FLIGHT'))}\s*[#]\s*(\d{1,4})$/"));

            $depInfo = $this->http->FindSingleNode("./descendant::td[normalize-space()][2][{$this->contains($this->t('DEPARTS'))}]", $root);

            if (preg_match("/^{$this->opt($this->t('DEPARTS'))}\s*(?<depCode>[A-Z]{3})\s*(?<depTime>[\d\:]+A?P?M)\s*(?<depName>.+)$/", $depInfo, $m)) {
                $s->departure()
                    ->code($m['depCode'])
                    ->name($m['depName'])
                    ->date(strtotime($date . ', ' . $m['depTime']));
            }

            $arrInfo = $this->http->FindSingleNode("./descendant::td[normalize-space()][3][{$this->contains($this->t('ARRIVES'))}]", $root);

            if (preg_match("/^{$this->opt($this->t('ARRIVES'))}\s*(?<arrCode>[A-Z]{3})\s*(?<arrTime>[\d\:]+A?P?M)\s*(?<arrName>.+)$/", $arrInfo, $m)) {
                $s->arrival()
                    ->code($m['arrCode'])
                    ->name($m['arrName'])
                    ->date(strtotime($date . ', ' . $m['arrTime']));
            }
        }
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
        if ($this->http->XPath->query("//*[contains(translate(., '0123456789','##########'), '#:##')]")->length > 0
            && $this->http->XPath->query("//text()[" . $this->contains(['Your complete itinerary', 'Your itinerary']) . "]")->length > 0) {
            $this->logger->debug("contains full itinerary. go to rapidrewards/ReviewItinerary");

            return false;
        }

        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if ($this->http->XPath->query("//a[" . $this->eq($reBody) . "]")->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
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

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
    }
}
