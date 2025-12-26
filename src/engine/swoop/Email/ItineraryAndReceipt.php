<?php

namespace AwardWallet\Engine\swoop\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class ItineraryAndReceipt extends \TAccountChecker
{
    public $mailFiles = "swoop/it-137499731.eml, swoop/it-146061534.eml, swoop/it-144635491.eml, swoop/it-251811441.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'confNumber'   => ['Your reservation code is'],
            'departure'    => ['DEPARTURE:', 'Departure:', 'Departs', 'REVISED DEPARTURE:', 'Revised departure:'],
            'arrival'      => ['ARRIVAL:', 'Arrival:', 'Arrives', 'REVISED ARRIVAL:', 'Revised arrival:'],
        ],
    ];

    private $detectors = [
        'en' => ['Your Swoop Itinerary', 'Your Receipt', 'Your flight has changed', 'Your updated itinerary',
            'You’re leaving in just', ],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@ops.flyswoop.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['subject'], 'Your Swoop Itinerary and Receipt') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//*[contains(normalize-space(),"Thanks for booking with Swoop!") or contains(.,"FlySwoop.com")]')->length === 0) {
            return false;
        }

        return $this->detectBody() && $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }
        $email->setType('ItineraryAndReceipt' . ucfirst($this->lang));

        $this->parseFlight($email);

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function parseFlight(Email $email): void
    {
        $f = $email->add()->flight();

        $confirmation = implode(' ', $this->http->FindNodes("//tr[not(.//tr) and {$this->starts($this->t('confNumber'))}]/descendant::text()[normalize-space()]"));

        if (preg_match("/^({$this->opt($this->t('confNumber'))})[:\s]+([A-Z\d]{5,})(?:[ ]+{$this->opt($this->t('Issue date'))}|$)/", $confirmation, $m)) {
            $m[1] = preg_replace("/^Your\s+/i", '', $m[1]);
            $m[1] = preg_replace("/\s+is$/i", '', $m[1]);
            $f->general()->confirmation($m[2], $m[1]);
        }

        $travellers = [];

        $xpath = "//*[ count(*[normalize-space()])=2 and *[normalize-space()][1]/descendant::text()[normalize-space()][1][{$this->eq($this->t('departure'))}] and *[normalize-space()][2]/descendant::text()[normalize-space()][1][{$this->eq($this->t('arrival'))}] ]";
//        $this->logger->debug('$xpath = '.print_r( $xpath,true));
        $segments = $this->http->XPath->query($xpath);

        foreach ($segments as $root) {
            $s = $f->addSegment();

            $xpathLevelUp = "ancestor-or-self::*[ preceding-sibling::*[normalize-space()] ][1]/preceding-sibling::*[normalize-space()][1]";

            $date = 0;
            $route = $this->http->FindSingleNode($xpathLevelUp, $root);
            // segFormat-2 (it-144635491.eml)
            if (preg_match("/^(?<depName>.{2,}?)\s*\(\s*(?<depCode>[A-Z]{3})\s*\)\s+{$this->opt($this->t('to'))}\s+(?<arrName>.{2,}?)\s*\(\s*(?<arrCode>[A-Z]{3})\s*\)$/", $route, $m)) {
                // Hamilton, ON (YHM) to Las Vegas, NV USA (LAS)
                $s->departure()->name($m['depName'])->code($m['depCode']);
                $s->arrival()->name($m['arrName'])->code($m['arrCode']);

                $dateValue = $this->http->FindSingleNode($xpathLevelUp . '/' . $xpathLevelUp, $root, true, '/^.*\d.*$/');
                $date = strtotime($dateValue);
            }

            $flight = $this->http->FindSingleNode($xpathLevelUp . "/descendant::tr/*[not(.//tr) and normalize-space()][1]", $root)
                ?? $this->http->FindSingleNode($xpathLevelUp . '/' . $xpathLevelUp . '/' . $xpathLevelUp, $root, true, "/^{$this->opt($this->t('Flight'))}\s+((?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*\d+)$/")
            ;

            if (preg_match('/^(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<number>\d+)$/', $flight, $m)) {
                $s->airline()->name($m['name'])->number($m['number']);
            }

            $patterns['terminal'] = "/^{$this->opt($this->t('Terminal'))}\s*([-A-z\d\s]+)$/i";

            $depRows = $this->http->FindNodes("*[normalize-space()][1]/descendant::p[normalize-space()]", $root);

            if (count($depRows) > 3) {
                $s->departure()->date(strtotime($depRows[2], strtotime($depRows[1])));

                if (preg_match("/^(?<name>.{3,}?)\s*\(\s*(?<code>[A-Z]{3})\s*\)$/", $depRows[3], $m)) {
                    $s->departure()->name($m['name'])->code($m['code']);
                }

                if (!empty($depRows[4]) && preg_match($patterns['terminal'], $depRows[4], $m)) {
                    $s->departure()->terminal($m[1]);
                }
            } elseif (count($depRows) > 1) {
                // segFormat-2 (it-144635491.eml)
                if ($date) {
                    $s->departure()->date(strtotime($depRows[1], $date));
                }

                if (!empty($depRows[2]) && preg_match($patterns['terminal'], $depRows[2], $m)) {
                    $s->departure()->terminal($m[1]);
                }
            }

            $arrRows = $this->http->FindNodes("*[normalize-space()][2]/descendant::p[normalize-space()]", $root);

            if (count($arrRows) > 3) {
                $s->arrival()->date(strtotime($arrRows[2], strtotime($arrRows[1])));

                if (preg_match("/^(?<name>.{3,}?)\s*\(\s*(?<code>[A-Z]{3})\s*\)$/", $arrRows[3], $m)) {
                    $s->arrival()->name($m['name'])->code($m['code']);
                }

                if (!empty($arrRows[4]) && preg_match($patterns['terminal'], $arrRows[4], $m)) {
                    $s->arrival()->terminal($m[1]);
                }
            } elseif (count($arrRows) > 1) {
                // segFormat-2 (it-144635491.eml)
                if ($date) {
                    $s->arrival()->date(strtotime($arrRows[1], $date));
                }

                if (!empty($arrRows[2]) && preg_match($patterns['terminal'], $arrRows[2], $m)) {
                    $s->arrival()->terminal($m[1]);
                }
            }

            $passengerNames = $seats = [];
            $passengerRoots = $this->http->XPath->query($xpathLevelUp . "/" . $xpathLevelUp . "/descendant::tr[ count(*)=2 and *[1][normalize-space()='']/descendant::img and *[2][normalize-space()] ]/*[2]", $root);

            if ($passengerRoots->length === 0) {
                // segFormat-2 (it-144635491.eml)
                $passengerRoots = $this->http->XPath->query("ancestor::table[1]/following-sibling::table[ descendant::tr[not(.//tr) and normalize-space()][1]/*[1][descendant::img and normalize-space()=''] ]", $root);
            }

            foreach ($passengerRoots as $p) {
                $pRows = $this->http->XPath->query("descendant-or-self::*[ p[3] ][1]/p[normalize-space()]", $p);

                if ($pRows->length === 0) {
                    // segFormat-2 (it-144635491.eml)
                    $pRows = $this->http->XPath->query("descendant-or-self::*[ tr[3] ][1]/tr[normalize-space()]", $p);
                }

                if ($pRows->length !== 3
                    && $pRows->length > 3 && !preg_match("/^\s*{$this->opt($this->t('Passenger ID'))}/", $pRows->item(3)->nodeValue)
                ) {
                    continue;
                }

                if ($pRows->length > 0 && preg_match("/^\s*([[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]])\s*$/u", $pRows->item(0)->nodeValue, $m)) {
                    $passengerNames[] = preg_replace('/\s+/', ' ', $m[1]);
                }

                if ($pRows->length > 1 && preg_match("/^\s*(?:{$this->opt($this->t('Seat'))}\s+)?(\d+[A-Z])\s*$/", $pRows->item(1)->nodeValue, $m)) {
                    $seats[] = $m[1];
                }
            }

            if (empty($passengerNames)) {
                $passengerNames = $this->http->FindNodes("//img[contains(@src, 'icon-user') or contains(@src, 'profile-icon')]/ancestor::tr[1]");
            }

            if (count($passengerNames) > 0) {
                $travellers = array_merge($travellers, $passengerNames);
            }

            if (count($seats) > 0) {
                $s->extra()->seats($seats);
            }
        }

        if (count($travellers) > 0) {
            $f->general()->travellers(array_unique($travellers), true);
        }

        $xpathPayment = "//tr[{$this->eq($this->t('Your Receipt'))}]/following-sibling::tr";

        $totalPrice = $this->http->FindSingleNode($xpathPayment . "/descendant-or-self::tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Total'))}] ]/*[normalize-space()][2]");

        if (preg_match('/^(?<currency>[^\d)(]+?)[ ]*(?<amount>\d[,.\'\d ]*?)[ ]*(?<currencyCode>[A-Z]{3})$/', $totalPrice, $matches)) {
            // $1,592.04 CAD
            $f->price()->currency($matches['currencyCode'])->total(PriceHelper::parse($matches['amount'], $matches['currencyCode']));

            $taxes = $this->http->FindSingleNode($xpathPayment . "/descendant-or-self::tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Taxes, Fees, & Charges'))}] ]/*[normalize-space()][2]");

            if (preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.\'\d ]*)$/', $taxes, $m)) {
                $f->price()->tax(PriceHelper::parse($m['amount'], $matches['currencyCode']));
            }
        }
    }

    private function detectBody(): bool
    {
        if (!isset($this->detectors)) {
            return false;
        }

        foreach ($this->detectors as $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (!is_string($phrase)) {
                    continue;
                }

                if ($this->http->XPath->query("//*[{$this->contains($phrase)}]")->length > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['confNumber']) || empty($phrases['departure'])) {
                continue;
            }

            if ($this->http->XPath->query("//*[{$this->contains($phrases['confNumber'])}]")->length > 0
                && $this->http->XPath->query("//*[{$this->contains($phrases['departure'])}]")->length > 0
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function t(string $phrase)
    {
        if (!isset(self::$dictionary, $this->lang) || empty(self::$dictionary[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$this->lang][$phrase];
    }

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'contains(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'normalize-space(' . $node . ')=' . $s;
        }, $field)) . ')';
    }

    private function starts($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'starts-with(normalize-space(' . $node . '),' . $s . ')';
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
