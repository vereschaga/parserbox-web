<?php

namespace AwardWallet\Engine\virtuoso\Email;

use AwardWallet\Schema\Parser\Email\Email;

class ReservationSummary extends \TAccountChecker
{
    public $mailFiles = "virtuoso/it-17988842.eml, virtuoso/it-274190339.eml";

    public $reFrom = "virtuoso.com";
    public $reBody = [
        'en'  => ['This email was sent by Virtuoso', 'Your Reservation Summary'],
        'en2' => ['You have received a new booking', 'Traveler Reservation Summary'],
    ];

    public $lang = '';
    public static $dict = [
        'en' => [],
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

        $roots = $this->http->XPath->query("//h1[normalize-space(.)='Traveler Reservation Summary']");

        if (1 < $roots->length) {
            foreach ($roots as $i => $root) {
                $i++;
                $this->parseEmail($email, $root, $i);
            }
        } else {
            $this->parseEmail($email);
        }

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//img[@alt='Virtuoso' or contains(@src,'virtuoso.com')] | //text()[contains(.,'Virtuoso')]")->length > 0) {
            return $this->assignLang();
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->reFrom) !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function parseEmail(Email $email, ?\DOMNode $root = null, int $i = 1)
    {
        $h = $email->add()->hotel();

        $h->ota()
            ->confirmation($this->http->FindSingleNode("(//text()[{$this->starts($this->t('Confirmation Code'))}]/following::text()[normalize-space(.)!=''][1])[{$i}]",
                $root, false, "#^\s*([\w\-]{5,})\s*$#"), $this->t('Confirmation Code'));

        $h->general()
            ->confirmation($this->http->FindSingleNode("(//text()[{$this->starts($this->t('Record Locator'))}]/following::text()[normalize-space(.)!=''][1])[$i]",
                $root, false, "#^\s*([\w\-]{5,})\s*$#"), $this->t('Record Locator'))
            ->traveller($this->http->FindSingleNode("(//text()[{$this->starts($this->t('Traveler Name'))}]/following::text()[normalize-space(.)!=''][1])[$i]"));

        $tot = $this->getTotalCurrency($this->http->FindSingleNode("(//text()[{$this->starts($this->t('TOTAL:'))}])[$i]", $root));

        if (!empty($tot['Total'])) {
            $h->price()
                ->total($tot['Total'])
                ->currency($tot['Currency']);
        }

        $tot = $this->getTotalCurrency($this->http->FindSingleNode("(//text()[{$this->eq($this->t('Taxes & Fees'))}]/ancestor::td[1]/following-sibling::td[normalize-space(.)!=''][1])[$i]", $root));

        if (!empty($tot['Total'])) {
            $h->price()
                ->tax($tot['Total']);
        }

        $node = implode("\n",
            $this->http->FindNodes("(//text()[{$this->eq($this->t('Property:'))}]/ancestor::td[1]/following-sibling::td[normalize-space(.)!=''][1])[$i]//text()[normalize-space(.)!='']", $root));

        if (preg_match("#([^\n]+)\n(.+?)\n([\+\-\d\(\) ]+(?:[a-z\d]+)?)$#s", $node, $m)) {
            $h->hotel()
                ->name($m[1])
                ->address(trim(preg_replace("#\s+#", ' ', $m[2])))
                ->phone(trim($m[3]));
        }

        $h->booked()
            ->rooms($this->http->FindSingleNode("(//text()[{$this->contains($this->t('Number of Rooms'))}]/following::text()[normalize-space(.)!=''][1])[$i]",
                $root, false, "#^\s*(\d+)\s*$#"))
            ->checkIn(strtotime($this->http->FindSingleNode("(//text()[{$this->starts($this->t('Check In:'))}]/following::text()[normalize-space(.)!=''][1])[$i]", $root)))
            ->checkOut(strtotime($this->http->FindSingleNode("(//text()[{$this->starts($this->t('Check-out:'))}]/following::text()[normalize-space(.)!=''][1])[$i]", $root)));

        $guests = $this->http->FindSingleNode("(//text()[{$this->eq($this->t('Number of Guests:'))}]/following::text()[normalize-space(.)!=''][1])[$i]", $root);

        if (preg_match("/(\d+) ?adult/i", $guests, $m)) {
            $h->booked()
                ->guests($m[1]);
        }

        if (preg_match("/(\d+) ?child/i", $guests, $m)) {
            $h->booked()
                ->kids($m[1]);
        }

        if ($cancelation = $this->http->FindSingleNode("(//text()[{$this->eq($this->t('Cancellation Policy'))}]/following::text()[normalize-space(.)!=''][1])[$i]", $root, true, '/\d+\s+day(?:\(s\))?/')) {
            $h->booked()->deadlineRelative($cancelation, '00:00');
        }
        $cancel = $this->http->FindSingleNode("(//text()[starts-with(normalize-space(.), 'Regular House Policy')][1])[$i]", $root);

        if (preg_match('/is\s+(\d+)\s*(\w+)\s+prior to arrival\s*\-\s*(\d{1,2}\s*[ap]m)/i', $cancel, $m)) {
            $h->booked()->deadlineRelative($m[1] . ' ' . $m[2], $m[3]);
        }

        $r = $h->addRoom();
        $r->setDescription($this->http->FindSingleNode("(//text()[{$this->eq($this->t('Room Description:'))}]/following::text()[normalize-space(.)!=''][1])[$i]", $root))
            ->setRate($this->http->FindSingleNode("(//text()[{$this->starts($this->t('Room #'))}]/following::table[1]/descendant::tr[1]/td[normalize-space(.)!=''][last()])[$i]", $root));

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

    private function getTotalCurrency($node)
    {
        $node = str_replace("€", "EUR", $node);
        $node = str_replace("Rp", "IDR", $node);
        $node = str_replace("₪", "ILS", $node);
        $node = str_replace("$", "USD", $node);
        $node = str_replace("£", "GBP", $node);
        $node = str_replace('CN¥', 'CNY', $node);
        $node = str_replace('¥', 'JPY', $node);
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node,
                $m) || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node,
                $m) || preg_match("#(?<c>\-*?)(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)
        ) {
            $m['t'] = preg_replace('/\s+/', '', $m['t']);            // 11 507.00	->	11507.00
            $m['t'] = preg_replace('/[,.](\d{3})/', '$1', $m['t']);    // 2,790		->	2790		or	4.100,00	->	4100,00
            $m['t'] = preg_replace('/^(.+),$/', '$1', $m['t']);    // 18800,		->	18800
            $m['t'] = preg_replace('/,(\d{2})$/', '.$1', $m['t']);    // 18800,00		->	18800.00
            $tot = $m['t'];
            $cur = $m['c'];
        }

        return ['Total' => $tot, 'Currency' => $cur];
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
