<?php

namespace AwardWallet\Engine\rapidrewards\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ReviewItinerary extends \TAccountChecker
{
    public $mailFiles = "rapidrewards/it-160401035.eml, rapidrewards/it-30142609.eml, rapidrewards/it-30218142.eml, rapidrewards/it-30326071.eml, rapidrewards/it-35080624.eml, rapidrewards/it-46114471.eml, rapidrewards/it-57163079.eml, rapidrewards/it-7068921.eml, rapidrewards/it-7068924.eml, rapidrewards/it-714533393.eml, rapidrewards/it-7578221.eml, rapidrewards/it-7616616.eml, rapidrewards/it-76287877.eml, rapidrewards/it-9105320.eml";

    public $reBody = [
        'en' => ['DEPARTS', 'ARRIVES'],
    ];

    public $reSubject = [
        'en' => 'Review complete itinerary',
        'Your change is confirmed',
    ];

    public $lang = '';

    public static $dict = [
        'en' => [
            'PASSENGER' => ['PASSENGER', 'PASSENGERS'],
        ],
    ];

    private $subject;

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email): Email
    {
        $this->subject = $parser->getSubject();
        $body = $this->http->Response['body'];
        $this->assignLang($body);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $this->parseEmail($email);

        $spent = array_filter($this->http->FindNodes("//text()[{$this->contains($this->t('You successfully redeemed'))}]", null, "#{$this->opt($this->t('You successfully redeemed'))}\s+(\d[,\d]*) Rapid Rewards® points for this trip#i"));

        if (count($spent) === 0) {
            $spent = array_filter($this->http->FindNodes("//text()[{$this->eq($this->t('Rapid Rewards® Points'))}]/following::text()[normalize-space()][position()<3][{$this->starts($this->t('Payment Amount:'))}]", null, "/^{$this->opt($this->t('Payment Amount:'))}\s*(\d[,\d]*)$/"));
        }

        if (count($spent)) {
            $spentAwards = array_sum(array_map(function ($item) {
                return PriceHelper::parse($item);
            }, $spent));
            $spentAwards = number_format($spentAwards) . ' Rapid Rewards® Points';

            if (count($email->getItineraries()) === 1) {
                $email->getItineraries()[0]->price()->spentAwards($spentAwards);
            } else {
                $email->price()->spentAwards($spentAwards);
            }
        }

        $earned = array_filter(array_unique($this->http->FindNodes("//text()[contains(., 'EST. POINTS EARNED')]/ancestor::td[1][starts-with(normalize-space(), 'PASSENGER')]/following-sibling::td[1]/descendant::text()[normalize-space()][last()][not(normalize-space()='0')]", null, "#^\s*([\d\,]+)\s*$#")));

        if (!empty($earned) && 1 === count($email->getItineraries())) {
            $email->getItineraries()[0]->setEarnedAwards(implode(' + ', $earned));
        }

        return $email;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href,'southwest.com')] | //text()[contains(., 'Southwest Airlines')]")->length > 0) {
            $body = $parser->getHTMLBody();

            return $this->assignLang($body);
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true && strpos($headers['subject'], 'Southwest') === false) {
            return false;
        }

        if (isset($this->reSubject)) {
            foreach ($this->reSubject as $reSubject) {
                if (stripos($headers["subject"], $reSubject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, "southwest.com") !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function parseEmail(Email $email): void
    {
        $patterns = [
            'travellerName' => '[[:alpha:]ï¿½][-.\'’[:alpha:]ï¿½ ]*[[:alpha:]ï¿½]', // Mr. Hao-Li Huang
            'eTicket'       => '\d{3}(?: | ?- ?)?\d{5,}(?: | ?- ?)?\d{1,3}', // 075-2345005149-02    |    0167544038003-004
        ];

        $pnrs = "//text()[contains(.,'{$this->t('Confirmation')}')  and not(contains(.,'{$this->t('date')}'))]/ancestor::td[" . $this->contains($this->t('PASSENGER')) . "][1]";
        $this->logger->debug("Xpath PNRS: {$pnrs}");
        $nodes = $this->http->XPath->query($pnrs);

        if ($nodes->length == 0) {
            $pnrs = "//text()[contains(.,'" . $this->t('Confirmation') . ' #' . "')  and not(contains(.,'{$this->t('date')}'))]/ancestor::*[not(" . $this->contains($this->t('FLIGHT')) . ")][1]";
            $this->logger->debug("Xpath PNRS: {$pnrs}");
            $nodes = $this->http->XPath->query($pnrs);
        }

        foreach ($nodes as $node) {
            $f = $email->add()->flight();

            $confs = array_filter($this->http->FindNodes(".//text()[contains(.,'" . $this->t('Confirmation') . "') and not(contains(.,'{$this->t('date')}'))]/following::text()[normalize-space(.)][1]", $node, "#^[A-z\d]{6,20}$#"));

            if (empty($confs) && preg_match('/ trip \(([A-Z\d]{6})\): Your reservation is confirmed/', $this->subject, $m) > 0) {
                $confs = [$m[1]];
            }

            foreach ($confs as $conf) {
                $f->addConfirmationNumber($conf);
            }

            if ($date = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Confirmation date:'))}]", null, false, '/:\s*(.+)/')) {
                $f->general()
                    ->date2($date);
            }

            // it-30218142.eml
            $passengerRows = $this->http->XPath->query("descendant::tr[ count(*[normalize-space()])=2 and *[normalize-space()][1]/descendant::text()[normalize-space()][1][{$this->eq($this->t('PASSENGER'))}] ]", $node);

            foreach ($passengerRows as $pRow) {
                $passengerName = $this->http->FindSingleNode("*[normalize-space()][2]/*[1]", $pRow, true, "/^({$patterns['travellerName']})$/u");
                // Paige Burns (Lap Child)
                $infant = $this->http->FindSingleNode("*[normalize-space()][2]/*[1]", $pRow, true, "/^\s*({$patterns['travellerName']})\s*\(\s*{$this->opt($this->t('Lap Child'))}\)\s*\s*$/u");

                if (!empty($infant)) {
                    $f->general()->infant($infant, true);
                } else {
                    $f->general()->traveller($passengerName, true);
                }
                $account = $this->http->FindSingleNode("self::tr[ *[normalize-space()][1]/*[2][{$this->eq($this->t('RAPID REWARDS #'))}] ]/*[normalize-space()][2]/*[2]", $pRow, true, "/^\d{5,}$/");
                $ticket = $this->http->FindSingleNode("self::tr[ *[normalize-space()][1]/*[3][{$this->eq($this->t('TICKET #'))}] ]/*[normalize-space()][2]/*[3]", $pRow, true, "/^{$patterns['eTicket']}$/");

                if ($account) {
                    $f->program()->account($account, false, $passengerName, $this->http->FindSingleNode("(//*[{$this->eq($this->t('RAPID REWARDS #'))}])[1]"));
                }

                if ($ticket) {
                    $f->issued()->ticket($ticket, false, $passengerName);
                }
            }

            if (empty($f->getTravellers())) {
                // ???

                $paxs = array_values(array_filter(array_merge(
                    $this->http->FindNodes(".//text()[{$this->eq($this->t('PASSENGER'))}]/ancestor::tr[1]//text()[normalize-space() and not({$this->contains($this->t('PASSENGER'))})]", $node, "/^\s*([[:upper:]][[:lower:]]+([[:upper:] ï¿½]+[[:upper:]][[:lower:]]+){1,5})\s*$/u"),
                    $this->http->FindNodes(".//text()[{$this->eq($this->t('PASSENGER'))}]/ancestor::tr[1]//text()[normalize-space() and not({$this->contains($this->t('PASSENGER'))})]", $node, "/^\s*([[:upper:]]\s+[[:upper:]][[:lower:]]+\s+[[:upper:]]+[[:lower:]]{2,})/u") // H Noell Everhart
                )));

                if (!empty($paxs)) {
                    $f->general()
                        ->travellers($paxs);
                }
            }

            if (!empty($f->getConfirmationNumbers()) && $this->http->XPath->query("//text()[{$this->eq($this->t('Base Fare'))}]")->length > 0) {
                if ($nodes->length === 1) {
                    // FE: it-46114471.eml
                    $xp = "starts-with(normalize-space(), 'Air - ') or starts-with(normalize-space(), 'Air – ')";
                } else {
                    $cs = $f->getConfirmationNumbers();
                    $xp = implode(' or ', array_map(function ($n) {return "(normalize-space() = 'Air - {$n[0]}')"; }, $cs));
                }

                $total = implode(' ', $this->http->FindNodes("//tr[{$xp}]/following-sibling::tr[descendant::text()[normalize-space()][1][normalize-space()='Total']]//text()[normalize-space()]", $node));

                if (preg_match("/Total\s*(?<currency>[^\d)(]{1,5}?)\s*(?<amount>\d[\d,. ]*)\b/", $total, $matches)
                    || preg_match("/Total\s*(?<amount>\d[\d,. ]*)\s*(?<currency>[^\d)(]{1,5})\b/", $total, $matches)
                ) {
                    $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
                    $f->price()->currency($matches['currency'])->total(PriceHelper::parse($matches['amount'], $currencyCode));

                    $cost = implode(' ', $this->http->FindNodes("//tr[{$xp}]/following-sibling::tr[descendant::text()[normalize-space()][1][normalize-space()='Base Fare']]/descendant::text()[normalize-space()='Base Fare']/ancestor::tr[1]//text()[normalize-space()]", $node));

                    if (preg_match('/Base Fare\s*(?:' . preg_quote($matches['currency'], '/') . ')\s*(?<amount>\d[\d,. ]*)\b/', $cost, $m)
                        || preg_match('/Base Fare\s*(?<amount>\d[\d,. ]*)\s*(?:' . preg_quote($matches['currency'], '/') . ')\b/', $cost, $m)
                    ) {
                        $f->price()->cost(PriceHelper::parse($m['amount'], $currencyCode));
                    }
                }

                $feeRows = $this->http->XPath->query("//tr[{$xp}]/following-sibling::tr[normalize-space()]/descendant::tr[not(starts-with(normalize-space(),'Base Fare')) and not(starts-with(normalize-space(),'Total'))]", $node);

                foreach ($feeRows as $fRow) {
                    $fName = $this->http->FindSingleNode('*[1]', $fRow);
                    $fCurrency = $this->http->FindSingleNode('*[2]', $fRow, true, '/^[^\d)(]+$/');
                    $fAmount = $this->http->FindSingleNode('*[3]', $fRow, true, '/^\d[,.\'\d ]*$/');

                    if ($fName && $fCurrency && $fAmount !== null && $f->getPrice() !== null
                        && ($f->getPrice()->getCurrencyCode() === $fCurrency || $f->getPrice()->getCurrencySign() === $fCurrency)
                    ) {
                        $currencyCode = preg_match('/^[A-Z]{3}$/', $fCurrency) ? $fCurrency : null;
                        $f->price()->fee($fName, PriceHelper::parse($fAmount, $currencyCode));
                    }
                }
            }

            if ($this->http->XPath->query("//text()[{$this->contains($this->t('This reservation has been canceled'))}]")->length > 0
                || stripos($this->subject, 'This reservation has been canceled') !== false
            ) {
                $f
                    ->setStatus('canceled')
                    ->setCancelled(true);
            }

            $xpath = "//text()[contains(.,'" . $this->t('DEPARTS') . "')]/ancestor::tr[contains(.,'" . $this->t('ARRIVES') . "') and contains(.,'" . $this->t('FLIGHT') . "')][1]";
            $segments = $this->http->XPath->query($xpath);

            foreach ($segments as $root) {
                $s = $f->addSegment();
                $date = strtotime($this->normalizeDate($this->http->FindSingleNode("(./ancestor::tr/preceding::tr[contains(.,'Flight')])[last()]", $root, true, "#Flight\s*(?:\d+)?:\s*(.+?)(?:Est. Travel Time|$)#")));

                $cabin = $this->http->FindSingleNode("(./ancestor::tr/preceding::tr[contains(.,'Flight')])[last()]/descendant::a", $root);

                if (!empty($cabin)) {
                    $s->extra()->cabin(trim($cabin, '®'));
                }

                /*
                $node = $this->http->FindSingleNode("(./ancestor::tr/preceding::tr[contains(.,'Flight')])[last()][count(following-sibling::tr[contains(.,'DEPARTS')]) = 1]", $root, true, "#Est. Travel Time\s*:?((?:\s*\d{1,2}\s*\w+\b){1,2})#");
                if (!empty($node)) {
                    $s->extra()->duration(trim($node));
                }
                */

                $node = $this->http->FindSingleNode(".//td[not(.//td)][contains(.,'FLIGHT')]", $root);

                if (preg_match("#FLIGHT\s*\#\s*(\d+)#i", $node, $m)) {
                    $s->airline()
                        ->number($m[1]);
                }

                $node = $this->http->FindSingleNode(".//td[not(.//td)][contains(.,'DEPARTS')]", $root);

                if (preg_match("#DEPARTS\s*([A-Z]{3})\s*(\d+:\d+\s*[AaPp][Mm])\s*(.+)#", $node, $m)) {
                    $s->departure()
                        ->code($m[1])
                        ->name($m[3])
                        ->date(strtotime($m[2], $date));
                } elseif (preg_match("#DEPARTS\s*([A-Z]{3})\s*(\d+:\d+\s*[AaPp][Mm])\s*$#", $node, $m)) {
                    $s->departure()
                        ->code($m[1])
                        ->date(strtotime($m[2], $date));
                }

                $node = $this->http->FindSingleNode(".//td[not(.//td)][contains(.,'ARRIVES')]", $root);

                if (preg_match("#ARRIVES\s*([A-Z]{3})\s*(\d+:\d+\s*[AaPp][Mm])\s*(.+)#", $node, $m)) {
                    $s->arrival()
                        ->code($m[1])
                        ->name($m[3])
                        ->date(strtotime($m[2], $date));
                } elseif (preg_match("#ARRIVES\s*([A-Z]{3})\s*(\d+:\d+\s*[AaPp][Mm])\s*$#", $node, $m)) {
                    $s->arrival()
                        ->code($m[1])
                        ->date(strtotime($m[2], $date));
                }

                if (!empty($s->getFlightNumber()) && !empty($s->getDepCode()) && !empty($s->getArrCode())) {
                    $s->airline()
                        ->name('WN');
                }
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

    private function assignLang($body): bool
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if (stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function normalizeDate($str)
    {
        $in = [//Monday, 02/27/2017
            "#^\s*\S+\s+(\d+)\/(\d+)\/(\d+)\s*$#",
        ];
        $out = [
            "$2-$1-$3",
        ];

        return preg_replace($in, $out, $str);
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'starts-with(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'contains(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
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
