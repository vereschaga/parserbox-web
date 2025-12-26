<?php

namespace AwardWallet\Engine\unitedsignature\Email;

// TODO: delete what not use
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class DetailsRecentGACBooking extends \TAccountChecker
{
    public $mailFiles = "unitedsignature/it-688917220.eml, unitedsignature/it-689272753.eml";

    public $lang;
    public static $dictionary = [
        'en' => [
            //            'Confirmation' => 'Confirmation',
        ],
    ];

    private $detectFrom = "united@globalairportconcierge.com";
    private $detectSubject = [
        'Details of Your Recent GAC Booking Confirmation.',
    ];
    private $detectBody = [
        'en' => [
            'JOURNEY BOOKING DETAILS',
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return preg_match("/united@globalairportconcierge\.com$/", $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers["from"], $this->detectFrom) === false
            && strpos($headers["subject"], ' GAC ') === false
        ) {
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
            $this->http->XPath->query("//a[{$this->contains(['united.globalairportconcierge.com'], '@href')}]")->length === 0
            && $this->http->XPath->query("//*[{$this->contains(['united@globalairportconcierge.com'])}]")->length === 0
        ) {
            return false;
        }

        foreach ($this->detectBody as $detectBody) {
            if ($this->http->XPath->query("//*[{$this->contains($detectBody)}]")->length > 0) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->parseEmailEvent($email);
        $this->parseEmailFlight($email);

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

    private function parseEmailEvent(Email $email)
    {
        $xpath = "//text()[{$this->starts($this->t('Services Booked For'))}][following::text()[normalize-space()][{$this->eq($this->t('Service Type'))}]]";
        $this->logger->debug('$xpath = ' . print_r($xpath, true));
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $event = $email->add()->event();

            $event->type()->event();

            $event->general()
                ->confirmation($this->http->FindSingleNode("preceding::text()[{$this->starts($this->t('Your USS Booking Confirmation:'))}][1]",
                    $root, true, "/{$this->opt($this->t('Your USS Booking Confirmation:'))}\s*(USS\-[A-Z\d]{5,})\s*$/"))
            ;
            $traveller = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Dear '))}]",
                null, true, "/^\s*{$this->opt($this->t('Dear '))}\s*(.+)/");

            if (preg_match("/\bAccount\b/i", $traveller)) {
                $traveller = null;

                $travellers = array_unique($this->http->FindNodes("//tr[{$this->starts($this->t('Name:'))}][following-sibling::tr[{$this->starts($this->t('PNR:'))}]]",
                    null, "/^\s*{$this->opt($this->t('Name:'))}\s*(.+)/"));

                if (!empty($travellers)) {
                    $event->general()
                        ->travellers($travellers);
                }
            } else {
                $event->general()
                    ->traveller($traveller);
            }

            $type = $this->http->FindSingleNode(".", $root, true,
                "/{$this->opt($this->t('Services Booked For'))}\s*(.+?)\s*$/");

            if ($type === 'Departure') {
                $xpath = "(./preceding::*[{$this->eq($this->t('Flight No:'))}])[1]/ancestor::tr[{$this->starts($this->t('Flight No:'))}]/following-sibling::tr[{$this->starts($this->t('Origin:'))}]/ancestor::*[1]";

                $event->place()
                    ->name(implode(', ', $this->http->FindNodes("following::text()[normalize-space()][{$this->eq($this->t('Service Type'))}][1]/ancestor::tr[1]/following-sibling::*[count(*) = 2]/*[1]", $root)))
                    ->address($this->http->FindSingleNode($xpath . "/tr[*[normalize-space()][1][{$this->eq($this->t('Origin:'))}]]/*[normalize-space()][2]",
                        $root));

                $date = strtotime($this->http->FindSingleNode($xpath . "/tr[*[normalize-space()][1][{$this->eq($this->t('Departure Time:'))}]]/*[normalize-space()][2]",
                    $root));

                if (!empty($date)) {
                    $event->booked()
                        ->start(strtotime('- 3 hours', $date))
                        ->end($date)
                    ;
                }
                $event->booked()
                    ->guests($this->http->FindSingleNode($xpath . "/tr[*[normalize-space()][1][{$this->eq($this->t('Adlt. | Chld. | Inf. | Bags :'))}]]/*[normalize-space()][2]",
                        $root, true, "/^\s*(\d+)\s*\|\s*\d+\s*\|\s*\d+\s*\|/"))
                    ->kids($this->http->FindSingleNode($xpath . "/tr[*[normalize-space()][1][{$this->eq($this->t('Adlt. | Chld. | Inf. | Bags :'))}]]/*[normalize-space()][2]",
                        $root, true, "/^\s*\d+\s*\|\s*(\d+)\s*\|\s*\d+\s*\|/"))
                ;

                $total = $this->http->FindSingleNode("following::text()[normalize-space()][{$this->eq($this->t('Service Type'))}][1]/ancestor::tr[1]/following-sibling::*[last()]", $root);

                if (preg_match("/^\s*{$this->opt($this->t('Sub Total'))}\s*\((?<currency>[A-Z]{3})\):\s*(?<amount>\d[\d., \d]*)\s*$/", $total, $m)) {
                    $event->price()
                        ->total(PriceHelper::parse($m['amount'], $m['currency']))
                        ->currency($m['currency'])
                    ;
                } else {
                    $event->price()
                        ->total(null);
                }
            }
        }

        if ($nodes->length === 0) {
            $email->add()->event();
        }

        return true;
    }

    private function parseEmailFlight(Email $email)
    {
        $f = $email->add()->flight();

        $f->obtainTravelAgency();

        // General
        $f->general()
            ->noConfirmation()
            ->travellers(array_unique($this->http->FindNodes("//tr[{$this->starts($this->t('Name:'))}][following-sibling::tr[{$this->starts($this->t('PNR:'))}]]",
                null, "/^\s*{$this->opt($this->t('Name:'))}\s*(.+)/")))
        ;

        //Segments
        $xpath = "//tr[{$this->starts($this->t('Flight No:'))}]/following-sibling::tr[{$this->starts($this->t('Origin:'))}]/ancestor::*[1]";
        $this->logger->debug('$xpath = ' . print_r($xpath, true));
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            // Airline
            $flight = $this->http->FindSingleNode("tr[*[normalize-space()][1][{$this->eq($this->t('Flight No:'))}]]/*[normalize-space()][2]", $root);

            if (preg_match("/^\s*(?<al>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<fn>\d{1,5})\s*$/", $flight, $m)) {
                $s->airline()
                    ->name($m['al'])
                    ->number($m['fn']);
            }

            $s->airline()
                ->confirmation($this->http->FindSingleNode("following::text()[normalize-space()][position() < 10][{$this->eq($this->t('PNR:'))}]/following::text()[normalize-space()][1]",
                    $root, true, "/^\s*([A-Z\d]{5,7})\s*$/"));

            // Departure
            $s->departure()
                ->name($this->http->FindSingleNode("tr[*[normalize-space()][1][{$this->eq($this->t('Origin:'))}]]/*[normalize-space()][2]",
                    $root, true, "/^\s*[A-Z]{3}\s+(.+)/"))
                ->code($this->http->FindSingleNode("tr[*[normalize-space()][1][{$this->eq($this->t('Origin:'))}]]/*[normalize-space()][2]",
                    $root, true, "/^\s*([A-Z]{3})\s+.+/"))
                ->date(strtotime($this->http->FindSingleNode("tr[*[normalize-space()][1][{$this->eq($this->t('Departure Time:'))}]]/*[normalize-space()][2]",
                    $root)))
                ->terminal(trim(preg_replace("/\s*\bterminal\b\s*/iu", '', $this->http->FindSingleNode("tr[*[normalize-space()][1][{$this->eq($this->t('Departure Terminal:'))}]]/*[normalize-space()][2]",
                    $root, true, "/^\s*[A-Z]{3}\s+(.+)/"))))
            ;

            // Arrival
            $s->arrival()
                ->name($this->http->FindSingleNode("tr[*[normalize-space()][1][{$this->eq($this->t('Destination:'))}]]/*[normalize-space()][2]",
                    $root, true, "/^\s*[A-Z]{3}\s+(.+)/"))
                ->code($this->http->FindSingleNode("tr[*[normalize-space()][1][{$this->eq($this->t('Destination:'))}]]/*[normalize-space()][2]",
                    $root, true, "/^\s*([A-Z]{3})\s+.+/"))
                ->date(strtotime($this->http->FindSingleNode("tr[*[normalize-space()][1][{$this->eq($this->t('Arrival Time:'))}]]/*[normalize-space()][2]",
                    $root)))
                ->terminal(trim(preg_replace("/\s*\bterminal\b\s*/iu", '', $this->http->FindSingleNode("tr[*[normalize-space()][1][{$this->eq($this->t('Arrival Terminal:'))}]]/*[normalize-space()][2]",
                    $root, true, "/^\s*[A-Z]{3}\s+(.+)/"))))
            ;
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

    private function normalizeDate(?string $date): ?int
    {
        $this->logger->debug('date begin = ' . print_r($date, true));

        if (empty($date)) {
            return null;
        }

        $in = [
            //            // Apr 09
            //            '/^\s*(\w+)\s+(\d+)\s*$/iu',
            //            // Sun, Apr 09
            //            '/^\s*(\w+),\s*(\w+)\s+(\d+)\s*$/iu',
            //            // Tue Jul 03, 2018 at 1:43 PM
            //            '/^\s*[\w\-]+\s+(\w+)\s+(\d+),\s*(\d{4})\s+(\d{1,2}:\d{2}(?:\s*[ap]m)?)\s*$/ui',
        ];
        $out = [
            //            '$2 $1 %year%',
            //            '$1, $3 $2 ' . $year,
            //            '$2 $1 $3, $4',
        ];

        $date = preg_replace($in, $out, $date);

//        $this->logger->debug('date replace = ' . print_r( $date, true));

        $this->logger->debug('date end = ' . print_r($date, true));

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
