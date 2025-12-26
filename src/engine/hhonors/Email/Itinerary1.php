<?php

namespace AwardWallet\Engine\hhonors\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class Itinerary1 extends \TAccountChecker
{
    public $mailFiles = "hhonors/it-1.eml, hhonors/it-10.eml, hhonors/it-12.eml, hhonors/it-13.eml, hhonors/it-14.eml, hhonors/it-15.eml, hhonors/it-16.eml, hhonors/it-17.eml, hhonors/it-1731820.eml, hhonors/it-18.eml, hhonors/it-19.eml, hhonors/it-2.eml, hhonors/it-20.eml, hhonors/it-2112163.eml, hhonors/it-2227187.eml, hhonors/it-2227204.eml, hhonors/it-2227263.eml, hhonors/it-3.eml, hhonors/it-4.eml, hhonors/it-5.eml, hhonors/it-6.eml";
    public $reText = "#@res\.hilton\.com|generous HHonors point bonuses#i";
    public $reHtml = "#\d+ Hilton Worldwide#i";
    public $processors = [];

    private $detectFrom = ".hilton.com";

    private $lang = 'en';
    private static $dictionary = [
        'en' => [
            'Confirmation Number:' => ['Confirmation Number:'],
            'Arrival Date:'        => 'Arrival Date:',
        ],
    ];

    public function parseEmail(Email $email)
    {
        $h = $email->add()->hotel();

        // General
        $conf = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Confirmation Number:'))}]/following::text()[normalize-space()][1]", null, false,
            "/^\s*([\w\-]+)$/u");

        if (empty($conf)) {
            $conf = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Confirmation Number:'))}]", null, false,
                "/{$this->opt($this->t('Confirmation Number:'))}\s*([\w\-]+)$/u");
        }
        $h->general()
            ->confirmation($conf)
            ->traveller($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Name:")) . "]/ancestor::td[1]/following-sibling::td[normalize-space()][1]"))
            ->cancellation($this->http->FindSingleNode("//*[" . $this->eq($this->t("Rate Rules and Cancellation Policy:")) . "]/following-sibling::table[1]"), true, true)
        ;

        $hotelName = null;
        $address = null;
        $hotelNames = array_filter($this->http->FindNodes("//*[contains(@alt, 'Hotel')]/@alt", null, "/Hotel\s+Name:\s*(.*?)(?=\s*Hotel\s+Address)/ims"));

        if (!empty($hotelNames)) {
            $hotelName = array_shift($hotelNames);
        }

        if (empty($hotelName)) {
            $hotelName = $this->http->FindSingleNode('(//font[@color])[1]');
            $address = $this->http->FindSingleNode('(//font[@color])[1]/ancestor::tr[1]/following-sibling::tr[2]/descendant::td[1]', null, true, '/(.*)\sTel/ims');
        }

        $faxNode = $this->http->XPath->query('//text()[contains(., "T: ")]/following-sibling::text()[contains(., "F:")]/ancestor::tr[1]')->item(0);

        if (empty($hotelName) && $faxNode) {
            $hotelName = $this->http->FindSingleNode('./preceding-sibling::tr[last()]', $faxNode);
        }

        $hotelNames = array_filter($this->http->FindNodes("//*[contains(@alt, 'Hotel')]/@alt", null, "/Hotel\s+Name:\s*(.*?)(?=\s*Hotel\s+Address)/ims"));

        if (!empty($hotelNames)) {
            $hotelName = array_shift($hotelNames);
        }

        if (empty($address) && $faxNode) {
            $address = $this->http->FindSingleNode('./preceding-sibling::tr[last() - 1]', $faxNode);
        }

        if (empty($address)) {
            $addresses = array_filter($this->http->FindNodes("//*[contains(@alt, 'Hotel')]/@alt", null,
                "/Hotel\s+Address:\s*(.*?)(?=\s*Hotel Phone)/ims"));

            if (!empty($addresses)) {
                $street = array_shift($addresses);
                $address = $this->http->FindSingleNode("//*[contains(text(), '" . $street . "') and contains(text(), '|')]");
            }
        }

        $phone = null;
        $fax = null;
        $phones = array_filter($this->http->FindNodes("//*[contains(@alt, 'Hotel')]/@alt", null, "/Phone:\s*(.*?)(?=\s*Link)/ims"));

        if (!empty($phones)) {
            $phone = array_shift($phones);
        }

        if ($faxNode && preg_match('/T:\s*(\S+)\s+\|\s*F:\s*(\S+)/ims', $faxNode, $matches)) {
            $phone = $phone ?? $matches[1];
            $fax = $matches[2];
        }

        if (!empty($phone)) {
            $phone = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Tel:')]", null, true, '#Tel:\s*([\d \-\+\(\) ]{5,})#ims');
            $fax = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Fax:')]", null, true, '#Fax:\s*([\d \-\+\(\) ]{5,})#ims');
        }

        $h->hotel()
            ->name($hotelName)
            ->address(preg_replace('/\s*\|[\s\|]*/', ', ', $address ?? $street))
            ->phone($phone, true, true)
            ->fax($fax, true, true)
        ;

        // Booked
        $h->booked()
            ->checkIn(
                $this->normalizeDate($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Arrival Date:")) . "]/ancestor::td[1]/following-sibling::td[normalize-space()][1]")
                . ' ' . ($this->http->FindSingleNode('//td[contains(text(), "Check-in Time:")]/following-sibling::td[1]') ?? ''))
            )
            ->checkOut((
                $this->normalizeDate($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Departure Date:")) . "]/ancestor::td[1]/following-sibling::td[normalize-space()][1]")
                    . ' ' . ($this->http->FindSingleNode('//td[contains(text(), "Check-out Time:")]/following-sibling::td[1]') ?? ''))
                )
            )
        ;

        $h->booked()
            ->guests($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Clients:")) . "]/ancestor::td[1]/following-sibling::td[normalize-space()][1]", null, true, "/^\s*(\d+)\s+" . $this->opt($this->t("Adult")) . "/"), true, true)
            ->rooms($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Rooms:")) . "]/ancestor::td[1]/following-sibling::td[normalize-space()][1]", null, true, "/^\s*(\d+)\s*$/"), true, true)
        ;

        $type = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Room Type:")) . "]/ancestor::td[1]/following-sibling::td[normalize-space()][1]");

        if (!empty($type)) {
            $h->addRoom()
                ->setType($type);
        }

        // Total
        $total = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Total for Stay:")) . "]/ancestor::td[1]/following-sibling::td[normalize-space()][1]");

        if (preg_match("#^\s*(?<curr>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $total, $m)
            || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<curr>[^\d\s]{1,5})\s*$#", $total, $m)) {
            $h->price()
                ->total($this->amount($m['amount']))
                ->currency($this->currency($m['curr']))
            ;
        }
        $total = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Total Number of Points per Stay:")) . "]/ancestor::td[1]/following-sibling::td[normalize-space()][1]");

        if (!empty($total)) {
            $h->price()
                ->spentAwards($total)
            ;
        }
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectFrom);
    }

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href, '.hilton.com') or contains(@href, '.hiltonhhonors.com')]")->length === 0) {
            return false;
        }

        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict['Arrival Date:']) && $this->http->XPath->query("//text()[{$this->contains($dict['Arrival Date:'])}]")->length > 0) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    public function smartMatch($parser)
    {
        $body = $parser->getPlainBody();

        $isRe = preg_match("#[\|\(\)\[\].\+\*\!\^]#", $this->reText) ? true : false;

        if ($isRe) {
            return preg_match($this->reText, $body);
        } else {
            $find = preg_replace("#^\#([^\#]+)\#[imxse]*$#", '\1', $this->reText);

            if (stripos($body, $find) !== false) {
                return true;
            }
        }
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->parseEmail($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public static function getEmailTypesCount()
    {
        // emails are pretty much the same by the looks of it, but there are slight differences
        return 7;
    }

    private function normalizeDate($str)
    {
        $in = [
            // 2021-02-26, 00:00
            "#^\s*(\d{4})-(\d{2})-(\d{2}), (\d{2}:\d{2})\s*$#i",
        ];
        $out = [
            "$3.$2.$1, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
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

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ',
                array_map(function ($s) use ($node) {
                    return 'normalize-space(' . $node . ')="' . $s . '"';
                }, $field)) . ')';
    }

    private function contains($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ',
                array_map(function ($s) use ($node) {
                    return 'contains(normalize-space(' . $node . '),"' . $s . '")';
                }, $field))
            . ')';
    }

    private function starts($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ',
                array_map(function ($s) use ($node) {
                    return 'starts-with(normalize-space(' . $node . '),"' . $s . '")';
                }, $field))
            . ')';
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
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

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function amount($price)
    {
        $price = str_replace([',', ' '], '', $price);

        if (is_numeric($price)) {
            return (float) $price;
        }

        return null;
    }

    private function currency($s)
    {
        if ($code = $this->re("#^\s*([A-Z]{3})\s*$#", $s)) {
            return $code;
        }
        $sym = [
            '€' => 'EUR',
            '$' => 'USD',
            '£' => 'GBP',
        ];

        foreach ($sym as $f => $r) {
            if ($s == $f) {
                return $r;
            }
        }

        return null;
    }
}
