<?php

namespace AwardWallet\Engine\virginvoyages\Email;

// TODO: delete what not use
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class BookingInformation extends \TAccountChecker
{
    public $mailFiles = "virginvoyages/it-284658200.eml, virginvoyages/it-287873397.eml, virginvoyages/it-288007965.eml, virginvoyages/it-295868524.eml, virginvoyages/it-690980567.eml, virginvoyages/it-691155231.eml, virginvoyages/it-691327378.eml, virginvoyages/it-699020743.eml, virginvoyages/it-818810839.eml, virginvoyages/it-818819905.eml";

    public $lang;
    public static $dictionary = [
        'en' => [
            'Booking information'           => ['Booking information', 'ITINERARY INFORMATION', 'VOYAGE INFORMATION', 'BOOKING INFORMATION'],
            'BOOKING #'                     => ['BOOKING #'],
            'and let the countdown to your' => 'and let the countdown to your',
            'getaway begin.'                => 'getaway begin.',
            'Booked on'                     => ['Booked on', 'BOOKED ON'],
            'Booking Reference'             => ['Booking Reference', 'BOOKING REFERENCE'],
            'Itinerary'                     => ['Itinerary', 'ITINERARY'],
            //            'DAY ' => '',
            'AT SEA'           => ['AT SEA', 'At Sea'],
            'Ship Information' => ['Ship Information', 'Ship information'],
            //            'SHIP NAME' => '',
            //            'CABIN TYPE' => '',
            //            'CABIN NUMBER' => '',
            //            'DECK' => '',
            'Total'          => ['Total', 'TOTAL', 'TOTAL:'],
            'TAXES & FEES:'  => ['TAXES & FEES:', 'GOVERNMENT TAXES & FEES:'],
            'FEES:'          => ['DONATION:', 'INSURANCE:', 'SHORE THINGS:', 'PORT FEES:'],
            'Sailor 1'       => ['Sailor 1', 'SAILOR 1'],
            'Fellow Sailors' => ['Fellow Sailors', 'FELLOW SAILORS'],
        ],
    ];

    private $detectFrom = "info@em.virginvoyages.com";
    private $detectSubject = [
        // en
        'booking is confirmed',
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '.virginvoyages.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (strpos($headers["from"], $this->detectFrom) === false) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers["subject"], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if (
            $this->http->XPath->query("//a[{$this->contains(['.virginvoyages.com'], '@href')}]")->length === 0
            && $this->http->XPath->query("//img[{$this->contains(['.virginvoyages.com'], '@src')}]")->length === 0
        ) {
            return false;
        }

        foreach (self::$dictionary as $dict) {
            if (empty($dict['Booking information']) || $this->http->XPath->query("//*[{$this->eq($dict['Booking information'])}]")->length === 0
            ) {
                continue;
            }

            if (!empty($dict['Booking Reference']) && $this->http->XPath->query("//*[{$this->eq($dict['Booking Reference'])}]")->length > 0
                || !empty($dict['BOOKING #']) && $this->http->XPath->query("//*[{$this->starts($dict['BOOKING #'])}]")->length > 0
            ) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict['Booking information']) && $this->http->XPath->query("//*[{$this->eq($dict['Booking information'])}]")->length > 0) {
                $this->lang = $lang;

                break;
            }
        }

        $this->parseEmailHtml($email);

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
        return count(self::$dictionary);
    }

    private function parseEmailHtml(Email $email)
    {
        // Travel Agency
        $otaConf = $this->nextText($this->t('Booking Reference'));

        if (empty($otaConf)) {
            $otaConf = $this->http->FindSingleNode("//text()[{$this->starts($this->t('BOOKING #'))}]", null, true, "/{$this->opt($this->t('BOOKING #'))}\s*(\d{5,})$/");
        }

        if (!empty($otaConf)) {
            $email->ota()
                ->confirmation($otaConf);
        }

        // Cruise
        $c = $email->add()->cruise();

        // General
        $c->general()
            ->noConfirmation()
            ->travellers(array_filter([
                $this->nextText($this->t('Sailor 1')),
                $this->nextText($this->t('Fellow Sailors')),
            ]), true);

        $dateRes = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Booked on'))}]", null, true, "/{$this->opt($this->t('Booked on'))}\s*(.+)/");

        if (!empty($dateRes)) {
            $c->general()
                ->date(strtotime($dateRes));
        }

        // Detail
        $desc = $this->nextText($this->t('Itinerary'));

        if (empty($desc)) {
            $desc = $this->http->FindSingleNode("//text()[{$this->contains($this->t('and let the countdown to your'))}][{$this->contains($this->t('getaway begin.'))}]",
                null, true, "/{$this->opt($this->t('and let the countdown to your'))}\s+(.+)\s+{$this->opt($this->t('getaway begin.'))}/");
        }
        $c->details()
            ->description($desc)
        ;

        if (!empty($this->http->FindSingleNode("(//*[{$this->contains($this->t('and their vacay is on lock'))}])[1]"))
            && $this->http->XPath->query("//text()[{$this->eq($this->t('SHIP NAME'))} or {$this->eq($this->t('CABIN TYPE'))} or {$this->eq($this->t('CABIN NUMBER'))}]")->length === 0
        ) {
        } else {
            $c->details()
                ->ship($this->nextText($this->t('SHIP NAME'))
                ?? $this->http->FindSingleNode("//text()[{$this->contains($this->t('DAYS UNTIL YOU SAIL ON'))}]/ancestor::p[1][.//img[contains(@src, '/cruise-ship.png')]]",
                        null, true, "/{$this->opt($this->t('DAYS UNTIL YOU SAIL ON'))}\s*(.+)/")
                ?? $this->http->FindSingleNode("//text()[contains(., ', your Sailors') and contains(., 'voyage on') and contains(., 'that sets sail on')]",
                        null, true, "/, your Sailors' .+ voyage on (.+?) that sets sail on/"))
                ->roomClass($this->nextText($this->t('CABIN TYPE')))
                ->room($this->nextText($this->t('CABIN NUMBER'), null, "/.*\d+.*/"), true, true)
                ->deck(preg_replace('/^[\-\s]+$/', '', $this->nextText($this->t('DECK'))), true)
            ;
        }

        if ($c->getShip() == 'Placeholder') {
            $date = strtotime($this->nextText($this->t('SAILING DATE')));

            if (strtotime('- 20 year', $date) - strtotime(null) > 0) {
                $email->removeItinerary($c);
                $email->setIsJunk(true);

                return null;
            }
        }

        // Price
        $total = $this->nextText($this->t('Total'));

        if (preg_match('/^(?<currency>[A-Z]{3})[^\-)(\w]*?[ ]*(?<amount>\d[,.\'\d ]*)$/', $total, $m)
            || preg_match('/^[^\-\d)(]*?[ ]*(?<amount>\d[,.\'\d ]*)\s*(?<currency>[A-Z]{3})$/', $total, $m)
            || preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.\'\d ]*)$/', $total, $m)
            || preg_match('/^(?<amount>\d[,.\'\d ]*)[ ]*(?<currency>[^\-\d)(]+?)$/', $total, $m)
        ) {
            $cost = $this->http->FindSingleNode("//text()[{$this->starts($this->t('VOYAGE FARE:'))}]",
                null, true, "/:\D*(\d[., \d]*?)\D*$/");

            if (empty($cost)) {
                $cost = $this->nextText($this->t('VOYAGE FARE:'), null, "/^\s*\D*(\d[., \d]*?)\D*$/");
            }

            if (!empty($cost)) {
                $c->price()
                    ->cost(PriceHelper::parse($cost, $m['currency']));

                $c->price()
                    ->currency($m['currency'])
                    ->total(PriceHelper::parse($m['amount'], $m['currency']));

                $discount = $this->http->FindSingleNode("//text()[{$this->starts($this->t('DISCOUNT:'))}]",
                    null, true, "/:\D*(\d[., \d]*?)\D*$/");

                if (empty($discount)) {
                    $discount = $this->nextText($this->t('DISCOUNT:'), null, "/^\s*\D*(\d[., \d]*?)\D*$/");
                }

                if (!empty($discount)) {
                    $c->price()
                        ->discount(PriceHelper::parse($discount, $m['currency']));
                }
                $tax = $this->http->FindSingleNode("//text()[{$this->starts($this->t('TAXES & FEES:'))}]",
                    null, true, "/:\D*(\d[., \d]*?)\D*$/");

                if (empty($tax)) {
                    $tax = $this->nextText($this->t('TAXES & FEES:'), null, "/^\s*\D*(\d[., \d]*?)\D*$/");
                }

                if (!empty($tax)) {
                    $c->price()
                        ->tax(PriceHelper::parse($tax, $m['currency']));
                }

                foreach ((array) $this->t('FEES:') as $feeName) {
                    $fee = $this->http->FindSingleNode("//text()[{$this->starts($feeName)}]",
                        null, true, "/:\D*(\d[., \d]*?)\D*$/");

                    if (empty($fee)) {
                        $fee = $this->nextText($feeName, null, "/^\s*\D*(\d[., \d]*?)\D*$/");
                    }

                    if (!empty($fee)) {
                        $c->price()
                            ->fee(trim($feeName, ':'), PriceHelper::parse($fee, $m['currency']));
                    }
                }
            }
        }

        // Segments

        // Times from url
        if (!empty($c->getDescription())) {
            $url = 'https://www.google.com/search?q=' . trim(urlencode($c->getDescription()));

            $http2 = clone $this->http;
            $http2->GetURL($url);

            $cruiseUrl = $http2->FindSingleNode("//h3/ancestor::a[contains(@href, '.virginvoyages.com/itinerary/')][1]/ancestor::*[not(.//h1)][last()][{$this->contains($c->getDescription())}]//h3/ancestor::a[contains(@href, '.virginvoyages.com/itinerary/')][1]/@href",
                null, true, "/(.+\.virginvoyages\.com\/itinerary\/.+)/");
            // $this->logger->debug('$cruiseUrl = '.print_r( $cruiseUrl,true));

            if (empty($cruiseUrl)) {
                $url = 'https://www.google.com/search?q=voyage+' . trim(urlencode($c->getDescription()));

                $http2 = clone $this->http;
                $http2->GetURL($url);
                $cruiseUrl = $http2->FindSingleNode("//h3/ancestor::a[contains(@href, '.virginvoyages.com/itinerary/')][1]/ancestor::*[not(.//h1)][last()][{$this->contains($c->getDescription())}]//h3/ancestor::a[contains(@href, '.virginvoyages.com/itinerary/')][1]/@href",
                    null, true, "/(.+\.virginvoyages\.com\/itinerary\/.+)/");
            }

            if (!empty($cruiseUrl)) {
                $headers = [
                    'User-Agent'      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/116.0.0.0 Safari/537.36',
                    'Accept'          => 'text/html,application/xhtml+xml,application/xml',
                    'Accept-Encoding' => 'gzip, deflate, br',
                    'Referer'         => 'https://www.virginvoyages.com/destinations/europe-cruises',
                    // csrf - можно менять любые символы, главное оставлять формат
                    'Cookie' => 'csrf=117Gc1SF-UTrweArp18gvGyxfV7PBol0KyfGXNxpeQ5mRjEXRhPyI18y9Da7uyzVCHRrAz_51BKu8cKVc5xAmw:AAABkCBNh00:uCeIFLHpf0E5XtNtdgfFbA;',
                ];

                $http2->getUrl($cruiseUrl, $headers);

                $xpath = "//*[descendant::text()[normalize-space()][1][contains(., 'Day ')]][following-sibling::*[descendant::text()[normalize-space()][1][contains(., 'Day ')]]]/ancestor::*[1]/*[not(.//*[{$this->eq('Sailing')}])]";
                // $this->logger->debug('$xpath = '.print_r( $xpath,true));
                $days = $http2->XPath->query($xpath);

                foreach ($days as $root) {
                    $text = implode("\n", $http2->FindNodes(".//text()[normalize-space()]", $root));

                    if (preg_match("/^\s*{$this->opt($this->t('Day '))}(?<day>\d+)\s+(?<name>.+)\s*\n\s*(?<arrTime>\d{1,2}:\d{2}.+?) - (?<depTime>\d{1,2}:\d{2}.+?),/u", $text, $m)) {
                        $timesFromUrl[$m['day']] = [
                            'portName' => $m['name'],
                            'depTime'  => $m['depTime'],
                        ];
                    }
                }
            }
        }

        $xpath = "//text()[{$this->eq($this->t('Itinerary'))}]/following::text()[normalize-space()][{$this->starts($this->t('DAY '))}]/ancestor::tr[count(.//text()[normalize-space()]) > 2][1][not(.//text()[{$this->eq($this->t('AT SEA'))}])]";
        // $this->logger->debug('$xpath = '.print_r( $xpath,true));
        $days = $this->http->XPath->query($xpath);

        $segments = [];

        foreach ($days as $root) {
            // DAY 1
            // May 21, 2023 06:00 PM
            // Barcelona
            $date = $this->normalizeDate($this->http->FindSingleNode(".//tr[not(.//tr)][normalize-space()][2]", $root));
            $name = $this->http->FindSingleNode(".//tr[not(.//tr)][normalize-space()][3]", $root);
            $type = $this->http->FindSingleNode(".//img/@alt", $root);
            $segments[] = [
                'date' => $date,
                'name' => $name,
                'type' => $type,
            ];
        }

        if ($days->length == 0) {
            $xpath = "//text()[{$this->eq($this->t('Itinerary'))} or {$this->eq($this->t('Booking information'))}]/following::text()[normalize-space()][{$this->starts($this->t('Day '))}]/ancestor::tr[count(.//text()[normalize-space()]) > 2][1][not(.//text()[{$this->eq($this->t('AT SEA'))}])]";
            // $this->logger->debug('$xpath = '.print_r( $xpath,true));
            $days = $this->http->XPath->query($xpath);

            foreach ($days as $root) {
                // Day 1 - Miami, Florida, United States
                // Departs 06:00 PM local time on Nov 27, 2024
                $text = implode("\n", $this->http->FindNodes(".//text()[normalize-space()]", $root));

                $date = null;
                $name = null;
                $type = null;

                if (preg_match("/^\s*{$this->opt($this->t('Day '))}(?<day>\d+)\s*-\s*(?<name>[\s\S]+?)\n\s*(?<date>(?<type>[[:alpha:]]+) \d{1,2}:\d{2}.+)/", $text, $m)) {
                    $date = $this->normalizeDate($m['date']);
                    $name = preg_replace('/\s*,\s*/', ', ', $m['name']);

                    if ($m['type'] === 'Departs') {
                        $type = 'departure';
                        $segments[] = [
                            'date' => $date,
                            'name' => $name,
                            'type' => $type,
                        ];
                    } elseif ($m['type'] === 'Arrives') {
                        $type = 'Arrival';
                        $segments[] = [
                            'date' => $date,
                            'name' => $name,
                            'type' => $type,
                        ];

                        if (!empty($timesFromUrl[$m['day']]) && stripos($m['name'], $timesFromUrl[$m['day']]['portName']) === 0) {
                            $segments[] = [
                                'date' => strtotime($timesFromUrl[$m['day']]['depTime'], $date),
                                'name' => $name,
                                'type' => 'departure',
                            ];
                        }
                    }
                } else {
                    $segments[] = [
                        'date' => $date,
                        'name' => $name,
                        'type' => $type,
                    ];
                }
            }
        }
        // $this->logger->debug('$segments = '.print_r( $segments,true));

        foreach ($segments as $i => $seg) {
            switch ($seg['type']) {
                case 'departure':
                    if ($i === 0) {
                        $s = $c->addSegment();
                        $s
                            ->setName($seg['name']);
                    } elseif (!isset($s)) {
                        $c->addSegment();
                        $this->logger->debug('incorrect sequence of segments 1');

                        break 2;
                    }

                    if ($seg['name'] === $s->getName()) {
                        $s
                            ->setName($seg['name'])
                            ->setAboard($seg['date'])
                        ;
                    } else {
                        $c->addSegment();
                        $this->logger->debug('incorrect sequence of segments 2');

                        break 2;
                    }

                    break;

                case 'Arrival':
                    $s = $c->addSegment();
                    $s
                        ->setName($seg['name'])
                        ->setAshore($seg['date'])
                    ;

                    if (isset($segments[$i + 1]) && $segments[$i + 1]['type'] === 'Arrival') {
                        $s
                            ->setName($seg['name'])
                            ->setAboard(strtotime('19:00', $seg['date']))
                        ;
                    }

                    break;

                default: $c->addSegment();
                    $this->logger->debug('incorrect sequence of segments 3');

                    break 2;
            }
        }

        return true;
    }

    private function t($s)
    {
        if (!isset($this->lang, self::$dictionary[$this->lang], self::$dictionary[$this->lang][$s])) {
            return $s;
        }

        return self::$dictionary[$this->lang][$s];
    }

    // additional methods

    private function contains($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) {
            return 'contains(' . $text . ',"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function nextText($field, $root = null, $regexp = null, $addXpath = '')
    {
        $rule = $this->eq($field);

        return $this->http->FindSingleNode(".//text()[{$rule}]/following::text()[normalize-space(.)][1]" . $addXpath, $root, true, $regexp);
    }

    private function normalizeDate(?string $date): ?int
    {
//        $this->logger->debug('date begin = ' . print_r( $date, true));
        if (empty($date)) {
            return null;
        }

        $in = [
            // Departs at 06:00 PM local time on Jul 26, 2023
            '/^\s*[[:alpha:] ]+?\s+(\d{1,2}:\d{2}(?:\s*[ap]m)?) local time on ([[:alpha:]]+)\s+(\d{1,2})\s*,\s*(\d{4})\s*$/iu',
        ];
        $out = [
            '$3 $2 $4, $1',
        ];

        $date = preg_replace($in, $out, $date);

//        $this->logger->debug('date end = ' . print_r( $date, true));

        return strtotime($date);
    }

    private function opt($field, $delimiter = '/')
    {
        $field = (array) $field;

        if (empty($field)) {
            $field = ['false'];
        }

        return '(?:' . implode("|", array_map(function ($s) use ($delimiter) {
            return str_replace(' ', '\s+', preg_quote($s, $delimiter));
        }, $field)) . ')';
    }
}
