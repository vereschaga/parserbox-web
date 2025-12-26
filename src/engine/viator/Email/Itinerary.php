<?php

// Careful with defects!

namespace AwardWallet\Engine\viator\Email;

class Itinerary extends \TAccountChecker
{
    public $mailFiles = "viator/it-1591091.eml, viator/it-1643898.eml";

    public static $dictionary = [
        'en' => [
            'Total Price'          => 'Total Price:',
            'Booking Reference'    => 'Booking Reference Number',
            'Product Booking'      => 'Product Booking Number:',
            'Product Booking2'     => 'Itinerary Number:',
            'Product Booking3'     => 'Booking Reference:',
            'Lead'                 => 'Lead traveler:',
            'Lead2'                => 'Lead Traveler:',
            'Travel Date'          => 'Travel Date:',
            'Dest'                 => 'Destination:',
            'Dest2'                => 'Location:',
            'Number of travelers'  => 'Number of travelers:',
            'Number of travelers2' => 'Number of Travelers:',
        ],
    ];

    public $lang = '';
    private $tot = [];

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->assignLang();

        $its = $this->parseEmail($this->tot);

        if ($this->tot != null) {
            return [
                //'parsedData' => ['Itineraries' => $its, $this->tot],
                'parsedData' => ['Itineraries' => $its,
                    'TotalCharge'              => [
                        'TotalCharge' => $this->tot['TotalCharge'],
                        'Currency'    => $this->tot['Currency'],
                    ],
                ],
                'emailType' => 'Itinerary',
            ];
        }

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => "Itinerary",
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $href = ['.viator.com/', 'www.viator.com'];

        if ($this->http->XPath->query("//a[{$this->contains($href, '@href')} or {$this->contains($href, '@originalsrc')}]")->length === 0) {
            return false;
        }

        return $this->assignLang();
    }

    public function detectEmailByHeaders(array $headers)
    {
        return array_key_exists('subject', $headers)
            && stripos($headers['subject'], 'Your Viator.com Booking Confirmation') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[.@]viator\.com$/i', $from) > 0;
    }

    private function parseEmail(&$tot): array
    {
        $it_out = [];
        $TripNo = $this->http->FindSingleNode("//table//*[self::div|self::p|self::span][contains(text(),'" . $this->t('Product Booking') . "') or contains(text(),'" . $this->t('Booking Reference') . "')]/strong");
        $SUBNODE = "(//tr[(@style='border-top:1px solid #e0dbd5' or contains(@style,'border-top-width: 1px; border-top-style: solid;')) and not(contains(.,'" . $this->t('Total Price') . "')) ])";
        $checkReserv = $this->http->FindNodes($SUBNODE . "//text()[contains(.,'" . $this->t('Product Booking') . "')]", null, "#" . $this->t('Product Booking') . "\s+(.+)#");

        if ($checkReserv == null) {
            $SUBNODE = "//span[contains(@style,'#333333') and contains(text(),'" . $this->t('Product Booking3') . "') and not(contains(.,'" . $this->t('Total Price') . "'))]/ancestor-or-self::tr[1]";
            $checkReserv = $this->http->FindNodes($SUBNODE . "//text()[contains(.,'" . $this->t('Product Booking3') . "')]", null, "#" . $this->t('Product Booking3') . "\s+(.+)#");
        }

        foreach ($checkReserv as $res => $id) {
            $it = ['Kind' => 'E'];
            $it['ConfNo'] = trim($id);

            $SUBNODE_ONE_TOUR = $SUBNODE . "[" . (int) ($res + 1) . "]";

            $it['Name'] = $this->http->FindSingleNode($SUBNODE_ONE_TOUR . "//a/text()");
            $it['DinerName'] = $this->http->FindSingleNode($SUBNODE_ONE_TOUR . "//p//text()[contains(.,'" . $this->t('Lead') . "') or contains(.,'" . $this->t('Lead2') . "')]", null, true, "#" . $this->t('Lead') . "\s+(.+)#i");
            $it['TripNumber'] = $TripNo;
            $it['StartDate'] = strtotime(trim($this->http->FindSingleNode($SUBNODE_ONE_TOUR . "//p//text()[contains(.,'" . $this->t('Travel Date') . "')]", null, true, "#" . $this->t('Travel Date') . ".+?\s+(.+)#")) . " 00:00");
            $it['Address'] = $this->http->FindSingleNode($SUBNODE_ONE_TOUR . "//p//text()[contains(.,'" . $this->t('Dest') . "') or contains(.,'" . $this->t('Dest2') . "')]", null, true, "#\:\s+(.+)#");
            $it['Guests'] = $this->http->FindSingleNode($SUBNODE_ONE_TOUR . "//p//text()[contains(.,'" . $this->t('Number of travelers') . "') or contains(.,'" . $this->t('Number of travelers2') . "')]", null, true, "#" . $this->t('Number of travelers') . "\s+(\d+)\s*#i");
            $it['Status'] = $this->http->FindSingleNode($SUBNODE_ONE_TOUR . "//span[contains(@style,'color:green')]");

            if ($it['Status'] == null) {
                $it['Status'] = $this->http->FindSingleNode($SUBNODE_ONE_TOUR . "//td[contains(translate(@style,' ',''),'text-align:right')]/b");
            }
            $node = $this->http->FindSingleNode($SUBNODE_ONE_TOUR . "//td[contains(translate(@style,' ',''),'text-align:right')]/text()[normalize-space(.)]");

            if ($node == null) {
                $node = $this->http->FindSingleNode("(" . $SUBNODE_ONE_TOUR . "//span[contains(@style,'color:green')]/ancestor::td/p/span)[1]");
            }

            if ($node != null) {
                if (preg_match("#\s*(?<CurText>[A-Z]*)\s*(?<CurSign>\D?)\s*(?<Summ>[\d\,\.]+)#", trim($node), $m)) {
                    $it['TotalCharge'] = $m['Summ'];
                }

                if (isset($m['CurText'])) {
                    $it['Currency'] = trim($m['CurText']);
                } else {
                    $it['Currency'] = currency($m['CurSign']);
                }
            }

            $it_out[] = $it;
        }
        $node = $this->http->FindSingleNode("//tr[@style='border-top:1px solid #e0dbd5' and contains(.,'Total Price:') ]");

        if ($node != null) {
            if (preg_match("#Total Price:\s*(?<CurText>[A-Z]*)\s*(?<CurSign>\D?)\s*(?<Summ>[\d\,\.]+)#", trim($node), $m)) {
                $tot['TotalCharge'] = $m['Summ'];
            }

            if (isset($m['CurText'])) {
                $tot['Currency'] = trim($m['CurText']);
            } else {
                $tot['Currency'] = currency($m['CurSign']);
            }
        }

        return $it_out;
    }

    private function assignLang(): bool
    {
        if ( !isset(self::$dictionary, $this->lang) ) {
            return false;
        }
        foreach (self::$dictionary as $lang => $phrases) {
            if ( !is_string($lang) ) {
                continue;
            }
            if (!empty($phrases['Product Booking']) && $this->http->XPath->query("//*[{$this->contains($phrases['Product Booking'])}]")->length > 0
                || !empty($phrases['Product Booking2']) && $this->http->XPath->query("//*[{$this->contains($phrases['Product Booking2'])}]")->length > 0
                || !empty($phrases['Product Booking3']) && $this->http->XPath->query("//*[{$this->contains($phrases['Product Booking3'])}]")->length > 0
            ) {
                $this->lang = $lang;
                return true;
            }
        }
        return false;
    }

    private function t(string $phrase, string $lang = '')
    {
        if ( !isset(self::$dictionary, $this->lang) ) {
            return $phrase;
        }
        if ($lang === '') {
            $lang = $this->lang;
        }
        if ( empty(self::$dictionary[$lang][$phrase]) ) {
            return $phrase;
        }
        return self::$dictionary[$lang][$phrase];
    }

    private function contains($field, string $node = ''): string
    {
        $field = (array)$field;
        if (count($field) === 0)
            return 'false()';
        return '(' . implode(' or ', array_map(function($s) use($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';
            return 'contains(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }
}
