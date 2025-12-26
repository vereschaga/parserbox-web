<?php

namespace AwardWallet\Engine\sbb\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Common\Train;
use AwardWallet\Schema\Parser\Email\Email;

class YourTicket extends \TAccountChecker
{
    public $mailFiles = "sbb/it-11757892.eml, sbb/it-16856804-de.eml, sbb/it-39194811.eml, sbb/it-41522555-de.eml, sbb/it-60577535.eml, sbb/it-795969437.eml";
    protected $langDetectors = [
        'en' => ['Reference nr.:', 'Reference no.:'],
        'de' => ['Referenz-Nr.:'],
        'fr' => ['No référence:'],
    ];
    protected $lang = '';
    protected static $dict = [
        'en' => [
            'Full fare' => ['Full fare', 'Reduced fare'],
            //            'exclude1' => '',
            'exclude2'       => ['via', 'Upgrade'],
            'Reference nr.:' => ['Reference nr.:', 'Reference no.:'],
        ],
        'de' => [
            'Reference nr.:' => 'Referenz-Nr.:',
            'Ticket'         => ['Billett', 'billet'],
            'Your choice'    => 'Ihre Wahl',
            'Valid:'         => 'Gültig:',
            'Class'          => 'Klasse',
            'Full fare'      => ['Vollpreis', 'Reduziert'],
            'Sold:'          => 'Verkauft:',
            'exclude1'       => ['Zonen', 'Ihre Wahl'],
            'exclude2'       => ['via'],
            'Only valid for' => 'Nur gültig für',
            'dep'            => 'ab',
            'arr'            => 'an',
        ],
        'fr' => [
            'Reference nr.:' => 'No référence:',
            'Ticket'         => ['Billet'],
            //            'Your choice' => '',
            'Valid:'        => 'Valable:',
            'Class'         => 'Classe',
            'Full fare'     => 'Prix entier',
            'Sold:'         => 'Vendu:',
            'exclude1'      => ['Zones', 'Votre choix'],
            'exclude2'      => ['via'],
            'Only valid for'=> 'Seulement valable pour',
            'dep'           => 'dép.',
            'arr'           => 'arr.',
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@sbb.ch') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['subject'], 'Your online purchase from SBB') !== false
            || stripos($headers['subject'], 'Ihr Online-Kauf bei der SBB') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,"//mailing.sbb.ch") or contains(@href,"//www.sbb.ch")]')->length === 0
            && $this->http->XPath->query('//node()[contains(normalize-space(),"Hello from SBB") or contains(normalize-space(),"on SBB.ch") or contains(normalize-space(),"auf SBB.ch") or contains(normalize-space(),"with SBB Mobile") or contains(.,"@sbb.ch")]')->length === 0
        ) {
            return false;
        }

        return $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }
        $email->setType('YourTicket' . ucfirst($this->lang));

        $its = $itsGarbage = [];
        $tickets = $this->http->XPath->query("//text()[ {$this->contains($this->t('Reference nr.:'))}]/ancestor::table[./preceding::text()[normalize-space(.)!=''][1][{$this->starts($this->t('Ticket'))}]][1]");

        foreach ($tickets as $ticket) {
            /** @var \AwardWallet\Schema\Parser\Common\Train $itTicket */
            $itTicket = $this->parseTicket($email, $ticket);

            if ($itTicket === null) {
                continue;
            }

            if (count($itTicket->getSegments()) > 0 && empty($itTicket->getSegments()[0]->getDepName()) && empty($itTicket->getSegments()[0]->getArrName()) && $this->http->XPath->query("descendant::tr[ not(.//tr[normalize-space()]) and {$this->eq($this->t('Saver Day Pass'))} and following-sibling::tr[{$this->eq($this->t('Valid:'))}] ]", $ticket)->length === 1) {
                // it-795969437.eml
                $email->removeItinerary($itTicket);

                continue;
            }

            if (count($itTicket->getSegments()) > 0 && !empty($itTicket->getSegments()[0]->getNoDepDate()) && !empty($itTicket->getSegments()[0]->getNoArrDate())) {
                $itsGarbage[] = $itTicket;
            } else {
                $its[] = $itTicket;
            }
        }

        $this->logger->debug('its: ' . count($its));
        $this->logger->debug('itsGarbage: ' . count($itsGarbage));

        if (count($its) > 0) {
            foreach ($itsGarbage as $it) {
                $email->removeItinerary($it);
            }
        }

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    protected function t($phrase)
    {
        if (!isset(self::$dict[$this->lang]) || !isset(self::$dict[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dict[$this->lang][$phrase];
    }

    protected function assignLang(): bool
    {
        foreach ($this->langDetectors as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if ($this->http->XPath->query('//node()[contains(normalize-space(.),"' . $phrase . '")]')->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function parseTicket(Email $email, \DOMNode $root): ?Train
    {
        $patterns = [
            'date' => '\d{1,2}\.\d{1,2}\.\d{4}', // 16.03.2018
            'time' => '\d{1,2}:\d{2}(?:\s*[AaPp][Mm])?', // 09:42
        ];

        $train = $email->add()->train();

        $xpathBold = '(self::b or self::strong or contains(translate(@style," ",""),"font-weight:bold"))';
        $xpathFragment1 = "./descendant::tr[ not(.//tr) and normalize-space(.) and ./following-sibling::tr[{$this->eq($this->t('Valid:'))}] ]";

        // Passengers
        $traveller = $this->http->FindSingleNode($xpathFragment1 . "[translate(normalize-space(),'0123456789 ','∆∆∆∆∆∆∆∆∆∆')='∆∆.∆∆.∆∆∆∆']/preceding-sibling::tr[normalize-space()]", $root, true, '/^[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]$/u');

        if ($traveller) {
            $train->general()->traveller($traveller);
        }

        // Currency
        // TotalCharge
        $payment = $this->http->FindSingleNode("descendant::text()[{$this->contains($this->t('Full fare'))}]/following::text()[normalize-space()][1]", $root, true, "/^.*\d.*$/")
            ?? $this->http->FindSingleNode("descendant::text()[{$this->contains($this->t('Class'))}]/following::text()[normalize-space()][position()<5][ ancestor::*[{$xpathBold}] ][1]", $root, true, "/^.*\d.*$/");

        if (preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.‘\'\d ]*)/u', $payment, $matches)) {
            // CHF 20.20
            $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
            $train->price()->currency($matches['currency'])->total(PriceHelper::parse($matches['amount'], $currencyCode));
        }

        // DepName
        // ArrName
        $stationsRows = $this->http->FindNodes($xpathFragment1 . "[{$this->contains($this->t('Your choice'))}]/following-sibling::tr", $root);
        $stations = preg_replace('/^\s*([\s\S]+?)\n\n[\s\S]*/', '$1', implode("\n", $stationsRows));
        $stations = preg_replace("/\n.*\b{$this->opt($this->t('exclude2'))}\b.*/i", '', $stations);

        if (preg_match("/^(.{3,})\n+(.{3,})$/", $stations, $m)) {
            $startStation = $m[1];
            $endStation = $m[2];
        } else {
            $endStation = $startStation = null;
        }

        $xpathFullBold = "normalize-space() and count(descendant::text()[normalize-space()])=count(descendant::text()[normalize-space()][ancestor::*[{$xpathBold}]])";
        $xpathNoFullBold = "normalize-space() and count(descendant::text()[normalize-space()])!=count(descendant::text()[normalize-space()][ancestor::*[{$xpathBold}]])";

        if (empty($startStation)) {
            $startStation = $this->http->FindSingleNode($xpathFragment1 . "[{$this->contains($this->t('Ticket'))}]/following-sibling::tr[normalize-space() and not({$this->starts($this->t('exclude1'))})][1]", $root)
            ?? $this->http->FindSingleNode($xpathFragment1 . "[{$xpathFullBold}][ preceding-sibling::tr[1][normalize-space()='' or {$xpathNoFullBold}] ][ following-sibling::tr[1][{$xpathFullBold}] and following-sibling::tr[2][normalize-space()='' or {$xpathNoFullBold}] ]", $root);
        }

        if (empty($endStation)) {
            $endStation = $this->http->FindSingleNode($xpathFragment1 . "[{$this->contains($this->t('Ticket'))}]/following-sibling::tr[normalize-space() and not({$this->starts($this->t('exclude1'))}) and not({$this->starts($this->t('exclude2'))})][2]", $root)
            ?? $this->http->FindSingleNode($xpathFragment1 . "[{$xpathFullBold}][ preceding-sibling::tr[1][{$xpathFullBold}] and preceding-sibling::tr[2][normalize-space()='' or {$xpathNoFullBold}] ][ following-sibling::tr[1][normalize-space()='' or {$xpathNoFullBold}] ]", $root);
        }

        $validTexts = $this->http->FindNodes("./descendant::tr[{$this->eq($this->t('Valid:'))}]/following-sibling::tr[normalize-space(.)!='']/descendant::text()[normalize-space(.)!='']", $root);
        $validText = implode("\n", $validTexts);

        // Type
        // FlightNumber
        $garbage = false;

        if (preg_match("/{$this->opt($this->t('Only valid for'))}\s*:\s*([A-z\d.,\s]+)\n/", $validText, $matches)) {
            // Only valid for: IC715;
            // Seulement valable pour: IC715 , IR2171
            // Nur gültig für: S2060 , WALK2258 , IR
            if (!empty($this->re("/(\,)/", $matches[1]))) {
                $nums = preg_split('/\s*,\s*/', $matches[1]);
            }

            if (empty($this->re("/(\,)/", $matches[1]))) {
                $nums = preg_split('/\s/', $matches[1]);
            }

            foreach ($nums as $key => $num) {
                if (preg_match('/^(.+?)(\d+)?$/', $num, $m)) {
                    $s = $train->addSegment();
                    $s->extra()->type($m[1]);

                    if (!empty($m[2])) {
                        $s->extra()->number($m[2]);
                    } else {
                        $s->extra()->noNumber();
                    }
                }
            }
        } elseif (preg_match("/{$patterns['date']}\s+{$patterns['time']}\s+\-\s+{$patterns['date']}\s+{$patterns['time']}/", $validText)) {
            $s = $train->addSegment();
            $s->extra()->noNumber();
            $garbage = true;
        }

        // Cabin
        $cabinTexts = array_filter($this->http->FindNodes("descendant::text()[{$this->contains($this->t('Class'))}]", $root, "/^(.+{$this->opt($this->t('Class'))})(?:\s*[,;(]|$)/"));

        if (count(array_unique($cabinTexts)) === 1) {
            $cabin = array_shift($cabinTexts);

            foreach ($train->getSegments() as $seg) {
                $seg->extra()->cabin($cabin);
            }
        }

        // DepDate
        // DepName
        if (preg_match_all('/(\n\s*\S.+? )?\b' . $this->opt($this->t('dep')) . '\s*(' . $patterns['date'] . ')(?:\s+' . $patterns['date'] . ')?\s+(' . $patterns['time'] . ')/i', $validText, $depMatches)) {
            // dep 16.03.2018 09:42; Genève dép. 18.03.2019 01.01.1970 09:42:00
            if (count($depMatches[0]) === count($train->getSegments())) {
                foreach ($train->getSegments() as $key => $seg) {
                    $seg->departure()->date(strtotime($depMatches[3][$key], strtotime($depMatches[2][$key])));

                    if (!empty(trim($depMatches[1][$key]))) {
                        $seg->departure()->name(trim($depMatches[1][$key]));
                    }
                }
            }
        } elseif ($garbage && count($train->getSegments()) > 0) {
            $train->getSegments()[0]->departure()->noDate();
        }

        // ArrDate
        // ArrName
        if (preg_match_all('/(\n\s*\S.+? )?\b' . $this->opt($this->t('arr')) . '\s*(' . $patterns['date'] . ')(?:\s+' . $patterns['date'] . ')?\s+(' . $patterns['time'] . ')/i', $validText, $arrMatches)) {
            // arr 16.03.2018 10:18
            if (count($arrMatches[0]) === count($train->getSegments())) {
                foreach ($train->getSegments() as $key => $seg) {
                    $seg->arrival()->date(strtotime($arrMatches[3][$key], strtotime($arrMatches[2][$key])));

                    if (!empty(trim($arrMatches[1][$key]))) {
                        $seg->arrival()->name(trim($arrMatches[1][$key]));
                    }
                }
            }
        } elseif ($garbage && count($train->getSegments()) > 0) {
            $train->getSegments()[0]->arrival()->noDate();
        }

        if (count($train->getSegments()) > 0 && empty($train->getSegments()[0]->getDepName()) && $startStation) {
            $train->getSegments()[0]->departure()->name($startStation);
        }

        if (count($train->getSegments()) > 0 && empty($train->getSegments()[count($train->getSegments()) - 1]->getArrName()) && $endStation) {
            $train->getSegments()[count($train->getSegments()) - 1]->arrival()->name($endStation);
        }

        foreach ($train->getSegments() as $seg) {
            $geoTip = $this->http->XPath->query("following::text()[{$this->contains(['Switzerland', 'Schweiz'])}]", $root)->length > 0 ? 'Switzerland' : 'Europe';

            if (!empty($seg->getDepName())) {
                $seg->departure()->geoTip($geoTip);
            }

            if (!empty($seg->getArrName())) {
                $seg->arrival()->geoTip($geoTip);
            }
        }

        // ReservationDate
        $solid = $this->http->FindSingleNode("./descendant::tr[{$this->eq($this->t('Sold:'))}]/following-sibling::tr[normalize-space(.)!=''][1]/descendant::text()[normalize-space(.)!='']", $root);

        if ($solid && preg_match('/ \d{1,2}:\d{2}:\d{2}$/', $solid) > 0) {
            $solid = substr($solid, 0, -3);
        }

        if ($solid) {
            $train->general()->date2($solid);
        }

        // tickets
        $ticketID = $this->http->FindSingleNode("./descendant::tr[{$this->eq($this->t('Ticket-ID:'))}]/following-sibling::tr[normalize-space(.)!=''][1]/descendant::text()[normalize-space(.)!='']", $root, true, '/^(\d[-\d\s]{6,}\d)$/');

        if ($ticketID) {
            $train->addTicketNumber($ticketID, false);
        }

        // confirmation
        $train->general()->confirmation($this->http->FindSingleNode("./descendant::tr[{$this->eq($this->t('Reference nr.:'))}]/following-sibling::tr[normalize-space()][1]/descendant::text()[normalize-space()]", $root, true, '/^[A-Z\d]{5,}$/'));

        return $train;
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

    private function opt($field): string
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
    }

    private function re($re, $str, $c = 1): ?string
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }
}
