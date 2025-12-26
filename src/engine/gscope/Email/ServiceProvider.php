<?php

namespace AwardWallet\Engine\gscope\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class ServiceProvider extends \TAccountChecker
{
    public $mailFiles = ""; //bcd

    public $reFrom = ["confirmations@groundscope.co.uk"];
    public $reBody = [
        'en' => ['Car Service', 'Pickup Date and Time'],
    ];
    public $reSubject = [
        '#Ref [\w\-]+ Pick up date \(.+?\) Passenger \(.+?\) Service provider \(.+?\)#',
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
            'Estimate ex VAT' => ['Estimate ex VAT', 'Estimate inc VAT'],
        ],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->debug('can\'t determine a language');

            return $email;
        }

        $a = explode('\\', __CLASS__);
        $class = end($a);
        $email->setType($class . ucfirst($this->lang));

        $this->parseEmail($email);

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[contains(.,'GroundScope ')]")->length > 0) {
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
                    if (preg_match($reSubject, $headers["subject"])) {
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

    private function parseEmail(Email $email)
    {
        $root = $this->http->XPath->query("//text()[{$this->eq($this->t('Car Service'))}]/following::table[1][{$this->contains($this->t('Booking Ref'))}]");

        if ($root->length !== 1) {
            $this->logger->debug('other format email');

            return false;
        }
        $root = $root->item(0);

        if (!empty($this->nextTd($this->t('Address 3'), $root))) {
            $looksLikeJunk = true;
//            $this->logger->info('necessary check/modify parsing on a few segments');
//            return false;
        }

        if ($this->nextTd($this->t('Service:'), $root, true) !== 'Transfer') {
            $this->logger->info('not Transfer - Car Service - necessary check/modify parsing');

            return false;
        }

        $r = $email->add()->transfer();
        $r->general()
            ->confirmation($this->nextTd($this->t('Booking Ref'), $root))
            ->traveller($this->nextTd($this->t('Passenger:'), $root, true));
        $s = $r->addSegment();

        $depNode = $this->nextTd('Address 1:', $root, true);
        $arrNode = $this->nextTd('Address 2:', $root, true);

        if (preg_match("#[A-Z\d]{2}\s*\d+ CTA [\d:]+ \(From [A-Z]{3}\) (.+)#", $depNode, $m)) {
            $depNode = $m[1];
        }

        if (preg_match("#[A-Z\d]{2}\s*\d+ CTD [\d:]+ \(To [A-Z]{3}\) (.+)#", $arrNode, $m)) {
            $arrNode = $m[1];
        }

        $s->departure()
            ->name($depNode)
            ->date(strtotime($this->nextTd($this->t('Pickup Date and Time'), $root)));
        $s->arrival()
            ->name($arrNode)
            ->noDate();

        $s->extra()
            ->type($this->nextTd($this->t('Vehicle'), $root));

        $node = $this->nextTd($this->t('Supplier Name'), $root);

        if (preg_match("#^(.+), ([\d\+ \(\)\-]+)$#", $node, $m)) {
            $r->program()
                ->phone($m[2], $m[1]);
        }

        $tot = $this->nextTd($this->t('Estimate ex VAT'), $root);
        $tot = $this->getTotalCurrency($tot);

        if (!empty($tot['Total'])) {
            $r->price()
                ->total($tot['Total'])
                ->currency($tot['Currency']);
        }

        // check if junk
        if (isset($looksLikeJunk) && $looksLikeJunk && $r->validate(false)) {
            $email->removeItinerary($r);
            $email->setIsJunk(true);
        }

        return true;
    }

    private function nextTd($filed, $root = null, $equal = false)
    {
        if ($equal) {
            return $this->http->FindSingleNode("./descendant::text()[{$this->eq($filed)}]/ancestor::td[1]/following-sibling::td[1][normalize-space()!='']",
                $root);
        } else {
            return $this->http->FindSingleNode("./descendant::text()[{$this->starts($filed)}]/ancestor::td[1]/following-sibling::td[1][normalize-space()!='']",
                $root);
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
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if ($this->http->XPath->query("//*[contains(normalize-space(.),'{$reBody[0]}')]")->length > 0
                    && $this->http->XPath->query("//*[contains(normalize-space(.),'{$reBody[1]}')]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function getTotalCurrency($node)
    {
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)
            || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m)
        ) {
            $cur = $m['c'];
            $tot = PriceHelper::cost(trim($m['t']));
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field));
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
