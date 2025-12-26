<?php

namespace AwardWallet\Engine\viator\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class NewBookingHost extends \TAccountChecker
{
    // letter for the tour organizer, not the traveler

    public $mailFiles = "viator/it-346910285.eml, viator/it-374647522.eml, viator/it-680385571.eml";

    public $detectSubject = [
        // en
        // New Booking for Mon, Apr 03, 2023 (#BR-995200791)
        'New Booking for',
        'Nueva reserva para',
    ];
    public $lang = 'en';
    public static $dictionary = [
        'en' => [
            'visit your dashboard to manage all bookings' => ['visit your dashboard to manage all bookings', 'visit your dashboard to manage other bookings'],
            'Send the customer a message.'                => 'Send the customer a message.',
            'Management Center'                           => 'Management Center',
        ],
        'es' => [
            'visit your dashboard to manage all bookings' => 'acceda al panel para administrar todas las reservas',
            'Send the customer a message.'                => 'Envíe un mensaje al cliente.',
            'Management Center'                           => 'Centro de Gestión',
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        if ($this->http->XPath->query("//a[contains(@href, 'viator')]/@href")->length > 0) {
            foreach (self::$dictionary as $dict) {
                if (!empty($dict['visit your dashboard to manage all bookings'])
                    && $this->http->XPath->query("//*[{$this->contains($dict['visit your dashboard to manage all bookings'])}]")->length > 0
                    // && !empty($dict['Send the customer a message.'])
                    // && $this->http->XPath->query("//*[{$this->contains($dict['Send the customer a message.'])}]")->length > 0
                    && !empty($dict['Management Center'])
                    && $this->http->XPath->query("//*[{$this->contains($dict['Management Center'])}]")->length > 0
                ) {
                    $this->parseEmail($email);
                }
            }
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function parseEmail(Email $email)
    {
        $name = $this->nextText($this->t('Tour Name:'));

        if (empty($name)) {
            $name = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Booking Reference:'))}]/following::text()[normalize-space()][position() < 5][{$this->eq($this->t('Canceled'))}]/following::text()[normalize-space()][1]");
        }

        if (preg_match('/(^| )(?:Transfer|transfers|Transportation|Car Service)( |$)/', $name)
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Hotel Pick Up'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Drop Off Location'))}]")->length > 0
        ) {
            $this->parseTransfer($email);
        } else {
            $this->parseEvent($email);
        }
    }

    public function parseEvent(Email $email)
    {
        $event = $email->add()->event();

        $email->setSentToVendor(true);

        // Type
        $event->type()->event();

        // General
        $event->general()
            ->confirmation($this->nextText($this->t('Booking Reference:'), "/^\s*\#?\s*([A-Z\-\d]+)\s*$/"));
        $traveller = $this->nextText($this->t('Traveler Names:'));

        if (empty($traveller)) {
            $traveller = $this->nextText($this->t('Lead Traveler Name:'));
        }
        $event->general()
            ->travellers(array_unique(array_filter(array_map(function ($v) {
                if (preg_match("/^\s*(?:passenger|Traveler)\b/iu", $v)) {
                    return false;
                }

                return $v;
            },
                preg_split('/\s*,\s*/', $traveller)))))
        ;

        // Place
        $name = $this->nextText($this->t('Tour Name:'));

        if (empty($name)) {
            $name = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Booking Reference:'))}]/following::text()[normalize-space()][position() < 5][{$this->eq($this->t('Canceled'))}]/following::text()[normalize-space()][1]");
        }
        $event->place()
            ->name($name)
            ->address($this->nextText($this->t('Location:')))
        ;

        if ($this->http->XPath->query("//text()[{$this->eq($this->t('Booking Reference:'))}]/following::text()[normalize-space()][position() < 5][{$this->eq($this->t('Canceled'))}]")->length > 0
            || $this->http->XPath->query("//text()[{$this->contains($this->t(' has been canceled.'))}]")->length > 0
        ) {
            $event->general()
                ->status('Canceled')
                ->cancelled();
        }

        // Booked
        $event->booked()
            ->start(strtotime($this->nextText($this->t('Travel Date:'))))
            ->guests($this->nextText($this->t('Travelers:'), "/^\s*(\d+)\s*{$this->opt($this->t('Adult'))}/"))
        ;

        $time = $this->nextText($this->t('Tour Grade Code:'), "/~\s*(\d{1,2}:\d{2})\s*$/");

        if (!empty($time) && !empty($event->getStartDate())) {
            $event->booked()
                ->start(strtotime($time, $event->getStartDate()));
            $duration = $this->nextText($this->t('Tour Grade Description:'), "/{$this->opt($this->t('Duration:'))}(.+?)(?:\n|<|$)/", "\n");

            if (!empty($duration)) {
                $event
                    ->booked()->end(strtotime('+ ' . $duration, $event->getStartDate()));
            } else {
                $event
                    ->booked()->noEnd();
            }
        }

        if (!$event->getCancelled()) {
            $total = $this->getTotal($this->nextText($this->t('Net Rate:')));
            $event->price()
                ->total($total['amount'])
                ->currency($total['currency']);
        }
    }

    public function parseTransfer(Email $email)
    {
        $t = $email->add()->transfer();
        $t
            ->setHost(true);

        // General
        $t->general()
            ->confirmation($this->nextText($this->t('Booking Reference:')))
            ->travellers(array_filter(array_map(function ($v) {
                if (preg_match("/^\s*passenger/iu", $v)) {
                    return false;
                }

                return $v;
            },
                preg_split('/\s*,\s*/', $this->nextText($this->t('Traveler Names:'))))))
        ;

        $s = $t->addSegment();

        $dep = $this->nextText($this->t('Hotel Pick Up:'));
        $s->departure()
            ->name($dep);
        $arr = $this->nextText($this->t('Drop Off Location:'));
        $s->arrival()
            ->name($arr);

        $date = strtotime($this->nextText($this->t('Travel Date:')));
        $time = $this->normalizeTime($this->nextText($this->t('Arrival Time:')));

        if (!empty($date) && !empty($time) && preg_match("/airport/i", $dep)) {
            $s->departure()
                ->date(strtotime($time, $date));
            $s->arrival()
                ->noDate();
        }
        $s->extra()
            ->adults($this->nextText($this->t('Travelers:'), "/^\s*(\d+)\s*{$this->opt($this->t('Adult'))}/"))
        ;

        $total = $this->getTotal($this->nextText($this->t('Net Rate:')));
        $t->price()
            ->total($total['amount'])
            ->currency($total['currency'])
        ;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href, 'viator')]/@href")->length === 0) {
            return false;
        }

        foreach (self::$dictionary as $dict) {
            if (!empty($dict['visit your dashboard to manage all bookings'])
                && $this->http->XPath->query("//*[{$this->contains($dict['visit your dashboard to manage all bookings'])}]")->length > 0
                // && !empty($dict['Send the customer a message.'])
                // && $this->http->XPath->query("//*[{$this->contains($dict['Send the customer a message.'])}]")->length > 0
                && !empty($dict['Management Center'])
                && $this->http->XPath->query("//*[{$this->contains($dict['Management Center'])}]")->length > 0
        ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'booking@t1.viator.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers['from']) || empty($headers['subject'])) {
            return false;
        }

        if (stripos($headers['from'], 'booking@t1.viator.com') === false) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers['subject'], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dictionary[$this->lang][$s])) {
            return $s;
        }

        return self::$dictionary[$this->lang][$s];
    }

    private function nextText($field, $regexp = null, $delimiter = ' ')
    {
        $str = implode($delimiter, $this->http->FindNodes("//tr[not(.//tr)][descendant::text()[normalize-space()][1][{$this->eq($field)}]]/descendant::text()[normalize-space()][position() > 1]"));

        if (!empty($regexp)) {
            return $this->re($regexp, $str);
        }

        return $str;
    }

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ')="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'starts-with(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function normalizeTime($time)
    {
//        $this->logger->debug('$time = ' . print_r($time, true));
        $in = [
            // 10:00 A.M.
            '#^\s*(\d{1,2}:\d{2})\s*([ap])\.?(m)\.?\s*$#iu',
            // 10am
            '#^\s*(\d{1,2})\s*([ap])\.?(m)\.?\s*$#iu',
        ];
        $out = [
            '$1 $2$3',
            '$1:00 $2$3',
        ];
        $time = preg_replace($in, $out, $time);
//        $this->logger->debug('$time 2 = ' . print_r($time, true));

        if (preg_match("/^\s*\d{1,2}:\d{2}\s*([ap]m)?\s*$/i", $time)) {
            return $time;
        }

        return null;
    }

    private function getTotal($text)
    {
        $result = ['amount' => null, 'currency' => null];

        // USD $210.60
        if (preg_match("#^\s*(?<currency>[^\d,.\s][^\d]{0,5})\s*(?<amount>\d[\d\., ]*)\s*$#u", $text, $m)
            || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[^\d,.\s][^\d]{0,5})\s*$#u", $text, $m)
            // $232.83 USD
            || preg_match("#^\s*\D{1,5}(?<amount>\d[\d\., ]*)\s*(?<currency>[A-Z]{3})\s*$#u", $text, $m)
        ) {
            $m['currency'] = $this->currency($m['currency']);
            $m['amount'] = PriceHelper::parse($m['amount']);

            if (is_numeric($m['amount'])) {
                $m['amount'] = (float) $m['amount'];
            } else {
                $m['amount'] = null;
            }
            $result = ['amount' => $m['amount'], 'currency' => $m['currency']];
        }

        return $result;
    }

    private function currency($s)
    {
        if ($code = $this->re("#^\s*(?:\D{1,3}\s)?\b([A-Z]{3})\b(?:\s\D{1,3})\s*$#u", $s)) {
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
