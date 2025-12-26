<?php

namespace AwardWallet\Engine\nextravel\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class WantsToBook extends \TAccountChecker
{
    public $mailFiles = "nextravel/it-47659892.eml, nextravel/it-47795021.eml, nextravel/it-47829530.eml, nextravel/it-48116640.eml, nextravel/it-48183369.eml";

    public $reFrom = ["@nextravel.com"];
    public $reBody = [
        'en' => ['wants to book', 'pending travel request'],
    ];
    public $reSubject = [
        '/has requested approval for a flight to/',
        '/Reminder: You have \d+ pending travel requests?/',
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
            'PER PERSON'   => ['PER PERSON', 'Per Person'],
            'Approval'     => ['Flight Approval', 'Car Rental Approval', 'Hotel Approval'],
            'Trip Purpose' => 'Trip Purpose',
        ],
    ];
    private $keywordProv = 'NexTravel';
    private $status;

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->debug('can\'t determine a language');

            return $email;
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        if ($this->checkIsJunk()) {
            $email->setIsJunk(true);
        } else {
            $this->parseEmail($email);
        }

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//img[contains(@src,'.nextravel.com')] | //a[contains(@href,'.nextravel.com')]")->length > 0) {
            foreach ($this->reBody as $lang => $reBody) {
                if ($this->http->XPath->query("//*[{$this->contains($reBody)}]")->length > 0) {
                    return $this->assignLang();
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

    public function detectEmailByHeaders(array $headers)
    {
        $fromProv = true;

        if (!isset($headers['from']) || self::detectEmailFromProvider($headers['from']) === false) {
            $fromProv = false;
        }

        if (isset($headers["subject"])) {
            foreach ($this->reSubject as $reSubject) {
                if (($fromProv || preg_match("#\b{$this->opt($this->keywordProv)}\b#i", $headers["subject"]) > 0)
                    && preg_match($reSubject, $headers["subject"]) > 0
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
        $types = 3; // flight | hotel | rental
        $cnt = $types * count(self::$dict);

        return $cnt;
    }

    private function checkIsJunk()
    {
        $conditions[] = $this->http->XPath->query("//text()[{$this->contains($this->t('Flight Approval'))}]/ancestor::table[1][./descendant::text()[{$this->eq($this->t('PER PERSON'))}]]");
        $conditions[] = $this->http->XPath->query("//text()[{$this->starts($this->t('Hey '))}]/following::text()[normalize-space()!=''][1][{$this->contains($this->t('wants to book'))}]");

        foreach ($conditions as $i => $condition) {
            if ($condition->length === 0) {
                $this->logger->debug($i . '-condition not met');

                return false;
            }
            $this->logger->debug($i . '-condition is ok');
        }

        return true;
    }

    private function parseEmail(Email $email)
    {
        $this->status = $this->http->FindSingleNode("//text()[({$this->contains($this->t('reminder that you have'))}) and ({$this->contains($this->t('pending travel requests'))})]");

        $this->parseFlights($email);
        $this->parseHotels($email);
        $this->parseRentals($email);
    }

    private function parseFlights(Email $email)
    {
        $xpathFlights = "//text()[{$this->starts($this->t('Flight'))}]/ancestor::tr[count(./preceding-sibling::tr)=0 and ./following-sibling::tr][1]";
        $nodes = $this->http->XPath->query($xpathFlights);
        $this->logger->debug("[xpathFlights] " . $xpathFlights);

        foreach ($nodes as $rootFlight) {
            $r = $email->add()->flight();
            $r->general()->noConfirmation();

            $node = implode(' ',
                $this->http->FindNodes("./ancestor::td[count(./following-sibling::td[normalize-space()!=''])=1][1]/following-sibling::td[normalize-space()!='']//text()[normalize-space()!='']",
                    $rootFlight));
            $dollar = preg_quote('$');

            if (preg_match("/^({$dollar})\s*([\d\.\,]+)\s+{$this->opt($this->t('PER PERSON'))}\s+{$this->t('Base:')}\s+{$dollar}\s*([\d\.\,]+)\s+{$this->t('Tax:')}\s+{$dollar}\s*([\d\.\,]+)\s+{$this->t('Passengers:')}\s+X(\d+)/i",
                $node, $m)) {
                $cnt = (int) $m[5];
                $r->price()
                    ->currency($m[1])
                    ->total($cnt * PriceHelper::cost($m[2]))
                    ->cost($cnt * PriceHelper::cost($m[3]))
                    ->tax($cnt * PriceHelper::cost($m[4]));
            }

            if (!empty($this->status)) {
                $r->general()->status('pending');
            }

            $ruleTime = "contains(translate(normalize-space(.),'0123456789','dddddddddd--'),'d:dd')";
            $xpath = "following-sibling::tr[{$ruleTime}]";
            $roots = $this->http->XPath->query($xpath, $rootFlight);
            $this->logger->debug("[xpathSegments] " . $xpath);

            foreach ($roots as $root) {
                $s = $r->addSegment();

                $date = $this->normalizeDate($this->http->FindSingleNode("./td[1]/descendant::text()[normalize-space()!=''][1]",
                    $root));

                $node = str_replace('–', '-',
                    $this->http->FindSingleNode("./td[3]/descendant::text()[normalize-space()!=''][1]", $root));
                $times = array_map("trim", explode("-", $node));

                if (count($times) === 2) {
                    $s->departure()->date(strtotime($times[0], $date));
                    $s->arrival()->date(strtotime($times[1], $date));
                }

                $node = $this->http->FindSingleNode("./td[3]/descendant::text()[normalize-space()!=''][2]", $root);

                if (preg_match("/^[\+\-]\s*(\d+)$/", $node, $m)) {
                    if ($s->getArrDate()) {
                        $s->arrival()->date(strtotime($node . ' days', $s->getArrDate()));
                    }
                    $node = $this->http->FindSingleNode("./td[3]/descendant::text()[normalize-space()!=''][3]", $root);
                }

                if (preg_match("/^(.+)\s+\(([A-Z]{3})\)\s+[–\-]\s+(.+)\s+\(([A-Z]{3})\)$/u", $node, $m)) {
                    $s->departure()
                        ->name($m[1])
                        ->code($m[2]);
                    $s->arrival()
                        ->name($m[3])
                        ->code($m[4]);
                }

                $node = $this->http->FindSingleNode("./td[3]/descendant::text()[normalize-space()!=''][not({$this->contains($this->t('Operated by'))})][last()]",
                    $root);
                $extra = array_map("trim", explode("•", $node));

                if (count($extra) === 4 || count($extra) === 3) {
                    $s->extra()->duration($extra[0]);

                    if (isset($extra[3]) && $extra[3] != 'Other') {
                        $s->extra()->aircraft($extra[3]);
                    }

                    if (preg_match("/^([A-Z\d][A-Z]|[A-Z][A-Z\d])\s*(\d+)$/", $extra[2], $m)) {
                        $s->airline()
                            ->name($m[1])
                            ->number($m[2]);
                    }
                }
                $node = $this->http->FindSingleNode("./td[3]/descendant::text()[normalize-space()!=''][{$this->contains($this->t('Operated by'))}]",
                    $root, false, "/{$this->opt($this->t('Operated by'))}\s*(.+)/");

                if (!empty($node)) {
                    $s->airline()->operator($node);
                }
            }
        }
    }

    private function parseRentals(Email $email)
    {
        $xpath = "//text()[{$this->starts($this->t('Car Rental'))}]/ancestor::tr[count(./preceding-sibling::tr)=0 and ./following-sibling::tr][1]";
        $nodes = $this->http->XPath->query($xpath);
        $this->logger->debug("[xpathRentals] " . $xpath);

        foreach ($nodes as $root) {
            $r = $email->add()->rental();

            $r->general()->noConfirmation();

            $node = implode(' ',
                $this->http->FindNodes("./ancestor::td[count(./following-sibling::td[normalize-space()!=''])=1][1]/following-sibling::td[normalize-space()!='']//text()[normalize-space()!='']",
                    $root));
            $dollar = preg_quote('$');
            $car = $this->http->FindSingleNode("(./ancestor::td[count(./following-sibling::td[normalize-space()!=''])=1][1]/following-sibling::td[normalize-space()!='']//text()[normalize-space()!=''])[last()]",
                $root);
            $r->car()->model($car);

            if (preg_match("/^({$dollar})\s*([\d\.\,]+)\s+{$this->t('Tax:')}\s+{$dollar}\s*([\d\.\,]+)\s+(.+)/i",
                $node, $m)) {
                $r->price()
                    ->currency($m[1])
                    ->total(PriceHelper::cost($m[2]))
                    ->tax(PriceHelper::cost($m[3]));

                $str = preg_quote($car);

                if (preg_match("/^(.+?)\s*{$str}/", $m[4], $v)) {
                    $r->car()->type($v[1]);
                }
            }

            if (!empty($this->status)) {
                $r->general()->status('pending');
            }

            $rentalCompany = $this->http->FindSingleNode(".", $root, false, "/{$this->t('Car Rental')}:\s*(.+)/");
            $r->extra()->company($rentalCompany);

            $date = $this->normalizeDate($this->http->FindSingleNode("./following-sibling::tr[normalize-space()!=''][1]/td[1]/descendant::text()[normalize-space()!=''][1]",
                $root));

            $node = $this->http->FindSingleNode("./following-sibling::tr[normalize-space()!=''][1]/td[3]/descendant::text()[normalize-space()!=''][1]",
                $root, false, "/{$this->opt($this->t('Pickup'))}\s*(.+)/");
            $r->pickup()
                ->date(strtotime($node, $date));
            $node = $this->http->FindSingleNode("./following-sibling::tr[normalize-space()!=''][1]/td[3]/descendant::text()[normalize-space()!=''][3]",
                $root);
            $r->pickup()->location($node);
            $node = $this->http->FindSingleNode("./following-sibling::tr[normalize-space()!=''][1]/td[3]/descendant::text()[normalize-space()!=''][4]",
                $root);

            if (!empty($node)) {
                $r->pickup()->phone($node);
            }
            $date = $this->normalizeDate($this->http->FindSingleNode("./following-sibling::tr[normalize-space()!=''][2]/td[1]/descendant::text()[normalize-space()!=''][1]",
                $root));
            $node = $this->http->FindSingleNode("./following-sibling::tr[normalize-space()!=''][2]/td[3]/descendant::text()[normalize-space()!=''][1]",
                $root, false, "/{$this->opt($this->t('Dropoff'))}\s*(.+)/");
            $r->dropoff()
                ->date(strtotime($node, $date));
            $node = $this->http->FindSingleNode("./following-sibling::tr[normalize-space()!=''][2]/td[3]/descendant::text()[normalize-space()!=''][3]",
                $root);
            $r->dropoff()->location($node);
            $node = $this->http->FindSingleNode("./following-sibling::tr[normalize-space()!=''][2]/td[3]/descendant::text()[normalize-space()!=''][4]",
                $root);

            if (!empty($node)) {
                $r->dropoff()->phone($node);
            }
        }
    }

    private function parseHotels(Email $email)
    {
        $xpath = "//text()[{$this->starts($this->t('Hotel'))}]/ancestor::tr[count(./preceding-sibling::tr)=0 and ./following-sibling::tr][1]";
        $nodes = $this->http->XPath->query($xpath);
        $this->logger->debug("[xpathHotels] " . $xpath);

        foreach ($nodes as $root) {
            $r = $email->add()->hotel();

            $r->general()->noConfirmation();

            $node = implode(' ',
                $this->http->FindNodes("./ancestor::td[count(./following-sibling::td[normalize-space()!=''])=1][1]/following-sibling::td[normalize-space()!='']//text()[normalize-space()!='']",
                    $root));
            $dollar = preg_quote('$');

            if (preg_match("/^({$dollar})\s*([\d\.\,]+)\s+{$this->t('Tax:')}\s+{$dollar}\s*([\d\.\,]+)\s+{$this->t('Nights:')}\s+X(\d+)\s+(.+)/i",
                $node, $m)) {
                $r->price()
                    ->currency($m[1])
                    ->total(PriceHelper::cost($m[2]))
                    ->tax(PriceHelper::cost($m[3]));

                if (preg_match("/(\d+)\s+{$this->opt($this->t('Adult'))},\s*(\d+)\s*{$this->t('Room')}\s*(.+)/", $m[5],
                    $v)) {
                    $r->booked()
                        ->guests($v[1])
                        ->rooms($v[2]);
                    $room = $r->addRoom();
                    $room->setDescription($v[3]);
                }
            }

            if (!empty($this->status)) {
                $r->general()->status('pending');
            }

            $hotelName = $this->http->FindSingleNode(".", $root, false, "/{$this->t('Hotel')}:\s*(.+)/");
            $r->hotel()->name($hotelName);
            $date = $this->normalizeDate($this->http->FindSingleNode("./following-sibling::tr[normalize-space()!=''][1]/td[1]/descendant::text()[normalize-space()!=''][1]",
                $root));

            $node = $this->http->FindSingleNode("./following-sibling::tr[normalize-space()!=''][1]/td[3]/descendant::text()[normalize-space()!=''][1]",
                $root, false, "/{$this->opt($this->t('Check-In'))}\s*(.+)/");
            $r->booked()
                ->checkIn(strtotime($node, $date));
            $node = $this->http->FindSingleNode("./following-sibling::tr[normalize-space()!=''][1]/td[3]/descendant::text()[normalize-space()!=''][2]",
                $root);
            $r->hotel()->address($node);
            $node = $this->http->FindSingleNode("./following-sibling::tr[normalize-space()!=''][1]/td[3]/descendant::text()[normalize-space()!=''][3]",
                $root);

            if (!empty($node)) {
                $r->hotel()->phone($node);
            }
            $date = $this->normalizeDate($this->http->FindSingleNode("./following-sibling::tr[normalize-space()!=''][2]/td[1]/descendant::text()[normalize-space()!=''][1]",
                $root));
            $node = $this->http->FindSingleNode("./following-sibling::tr[normalize-space()!=''][2]/td[3]/descendant::text()[normalize-space()!=''][1]",
                $root, false, "/{$this->opt($this->t('Check-Out'))}\s*(.+)/");
            $r->booked()
                ->checkOut(strtotime($node, $date));
        }
    }

    private function normalizeDate($date)
    {
        $in = [
            // 11/19/2019
            '/^(\d+)\/(\d+)\/(\d{4})$/u',
        ];
        $out = [
            '$3-$1-$2',
        ];
        $str = strtotime($this->dateStringToEnglish(preg_replace($in, $out, $date)));

        return $str;
    }

    private function dateStringToEnglish($date)
    {
        if (preg_match('/[[:alpha:]]+/iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("/$monthNameOriginal/i", $translatedMonthName, $date);
            }
        }

        return $date;
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
        foreach (self::$dict as $lang => $words) {
            if (isset($words['Trip Purpose'], $words['Approval'])) {
                if ($this->http->XPath->query("//*[{$this->contains($words['Trip Purpose'])}]")->length > 0
                    && $this->http->XPath->query("//*[{$this->contains($words['Approval'])}]")->length > 0
                ) {
                    $this->lang = $lang;

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

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }
}
