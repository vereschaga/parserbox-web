<?php

namespace AwardWallet\Engine\ticketmaster\Email;

use AwardWallet\Schema\Parser\Common\Event;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ScoredTickets extends \TAccountCheckerExtended
{
    public $mailFiles = "ticketmaster/it-120033792.eml, ticketmaster/it-160841315.eml, ticketmaster/it-29449651.eml, ticketmaster/it-30341507.eml, ticketmaster/it-33216761.eml, ticketmaster/it-54803428.eml, ticketmaster/it-644399321.eml";

    public static $dict = [
        'en' => [
            'View Mobile Ticket' => [
                'View Mobile Ticket',
                'View Ticket',
                'View Order Details',
            ],
            //'Important event information => ''
        ],
        'fr' => [
            'View Mobile Ticket' => 'Afficher le billet mobile',
            'Order #'            => 'Commande #',
            //'Your Order Details Are Below' => '',
            'Total:'                      => 'Total:',
            'Get Directions'              => 'Directions',
            'Important event information' => "Informations importantes sur l’événement",
        ],
    ];

    private $detectFrom = ["ticketmaster.com", "livenation.com"];
    private $detectSubject = [
        'You Just Scored Tickets to ',
        'You Got Tickets To ',
        'Voici vos billets pour ',
    ];

    private $detectCompany = [
        'Ticketmaster', 'Live Nation', 'livenation.com',
    ];

    private $detectBody = [
        'en' => ['Your Order Details Are Below', 'You Got the Tickets', 'Ticketmaster Fan Support', 'Your phone\'s your ticket. Locate your tickets in your account'],
        'fr' => ['Voici vos billets'],
    ];

    private $detectLang = [
        'en' => ['View Mobile Ticket', 'Your Order', 'View Ticket'],
        'fr' => ['Afficher le billet mobile'],
    ];

    private $lang = '';

    private $xpath;

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->xpath = "/descendant::tr[({$this->eq($this->t('View Mobile Ticket'))}) and preceding-sibling::tr[normalize-space()] ]";

        $this->assignLang();

        $type = '';

        $orderNumber = $this->http->FindSingleNode($this->xpath . "/preceding::text()[{$this->starts($this->t('Order #'))}][1]", null, true, "/{$this->opt($this->t('Order #'))}\s*([-\d]{5,})\b/");

        // General
        $nodes = $this->http->XPath->query($this->xpath);

        foreach ($nodes as $root) {
            $ev = $email->add()->event();
            $ev->general()->confirmation($orderNumber, $this->t('Order #'));

            if ($this->http->XPath->query("//node()[{$this->contains($this->t('Your Order Details Are Below'))}]")->length > 0) {
                // it-29449651.eml, it-30341507.eml
                $type = '1';
                $this->parseEventDetails1($ev, $root);
            } else {
                // it-33216761.eml
                $type = '2';
                $this->parseEventDetails2($ev, $root);
            }
        }

        // Price
        $total = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Total:'))}]", null, true, "/^{$this->opt($this->t('Total'))}\:*\s*(.+)$/");

        if (!$total) {
            $total = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Total'))}]/ancestor::td[1]/following-sibling::td[normalize-space()][1]");
        }

        if (
            preg_match("#^\s*(?<currency>[^\d\s]{1,5})\s*(?<amount>\d[,.\'\d]*)\s*$#", $total, $m)
            || preg_match("#^\s*(?<amount>\d[,.\'\d]*)\s*(?<currency>[^\d\s]{1,5})\s*$#", $total, $m)
        ) {
            // $3,588.00
            if (count($email->getItineraries()) == 1) {
                $ev->price()
                    ->currency($this->normalizeCurrency($m['currency']))
                    ->total($this->normalizeAmount($m['amount']));
            } else {
                $email->price()
                    ->currency($this->normalizeCurrency($m['currency']))
                    ->total($this->normalizeAmount($m['amount']));
            }
        }

        $note = $this->http->FindSingleNode("//text()[normalize-space()='Informations importantes sur l’événement']/following::text()[normalize-space()][1]");

        if (!empty($note)) {
            $ev->setNotes($note);
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . $type . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $this->assignLang();
        $body = $this->http->Response['body'];
        $foundCompany = false;

        foreach ($this->detectCompany as $dCompany) {
            if (stripos($body, $dCompany) !== false) {
                $foundCompany = true;
            }
        }

        if ($foundCompany == false) {
            return false;
        }

        foreach ($this->detectBody as $lang => $detectBody) {
            foreach ($detectBody as $dBody) {
                if (stripos($body, $dBody) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (!isset($headers['from']) || !isset($headers["subject"])) {
            return false;
        }
        $findFrom = false;

        foreach ($this->detectFrom as $dFrom) {
            if (stripos($headers['from'], $dFrom) !== false) {
                $findFrom = true;
            }
        }

        if ($findFrom == false) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers["subject"], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->detectFrom as $dFrom) {
            if (stripos($from, $dFrom) !== false) {
                return true;
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
        return count(self::$dict);
    }

    public function assignLang()
    {
        foreach ($this->detectLang as $lang => $words) {
            foreach ($words as $word) {
                if ($this->http->XPath->query("//text()[{$this->contains($word)}]")->length > 0) {
                    $this->lang = $lang;
                }
            }
        }
    }

    public function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field));
    }

    private function parseEventDetails1(Event $ev, $root)
    {
        // Place
        $ev->place()
            ->name($this->http->FindSingleNode("preceding-sibling::tr[normalize-space()][last()]", $root))
            ->address($this->http->FindSingleNode("preceding-sibling::tr[normalize-space()][last()-3]", $root, true, "#(.+?)(?:\s*{$this->opt($this->t('Get Directions'))}|$)#i"))
            ->type(Event::TYPE_SHOW);

        // Seats
        $seatsHtml = $this->http->FindHTMLByXpath("preceding-sibling::tr[contains(normalize-space(),'Seat') or contains(normalize-space(),'seat')]", null, $root);
        $seatsText = $this->htmlToText($seatsHtml);
        $this->parseSeats($ev, $seatsText);

        // Booked
        $ev->booked()
            ->start($this->normalizeDate($this->http->FindSingleNode("preceding-sibling::tr[normalize-space()][not(contains(normalize-space(),'Seat') or contains(normalize-space(),'seat'))][last()-1]", $root)))
            ->noEnd()
        ;
    }

    private function parseEventDetails2(Event $ev, $root)
    {
        $xpath = '(*[1]//img and *[2][normalize-space()])';

        // Booked
        $dateStart = $this->http->FindSingleNode("preceding-sibling::tr[$xpath][last()]", $root);
        $separator = '&';

        if (stripos($dateStart, '—') !== false) {
            $separator = '—';
        }

        if (count(explode($separator, $dateStart)) == 1) {
            $ev->booked()
                ->start($this->normalizeDate($dateStart))
                ->noEnd();
        }

        if (count(explode($separator, $dateStart)) == 2) {
            $dateNode = explode($separator, $dateStart);
            $ev->booked()
                ->start($this->normalizeDate(trim($dateNode[0])))
                ->end($this->normalizeDate(trim($dateNode[1])));
        }

        // Place
        $ev->place()
            ->address($this->http->FindSingleNode("preceding-sibling::tr[$xpath][last()-1]", $root, true, "#(.+?)(?:\s*Get Directions|$)#i"))
            ->name($this->http->FindSingleNode("preceding-sibling::tr[$xpath][last()]/preceding::tr[not(.//tr) and string-length(normalize-space())>1][1][count(*[normalize-space()])=1]", $root))
            ->type(Event::TYPE_SHOW);

        // Seats
        $seatsHtml = $this->http->FindHTMLByXpath("preceding-sibling::tr[$xpath][last()-2]", null, $root);
        $seatsText = $this->htmlToText($seatsHtml);
        $this->parseSeats($ev, $seatsText);
    }

    /**
     * @param string $text seats text
     */
    private function parseSeats(Event $ev, $text = '')
    {
        $seatsRow = explode("\n", $text);

        foreach ($seatsRow as $row) {
            if (!preg_match_all("/^(.*seat)\s*(\d+)(?:\s*-\s*(\d+))?$/im", $row, $seatMatches, PREG_SET_ORDER)) {
                return;
            }

            foreach ($seatMatches as $m) {
                if (!empty($m[3]) && ($m[3] - $m[2]) < 20) {
                    for ($i = $m[2]; $i <= $m[3]; $i++) {
                        $ev->booked()->seat($m[1] . ' ' . $i);
                    }
                } else {
                    $ev->booked()->seat($m[1] . ' ' . $m[2]);
                }
            }
        }
    }

    private function normalizeDate($str)
    {
        $in = [
            "#^\s*\w+\s*(?: Â)?\W*\s*(\w+)\s+(\d{1,2})\,?\s+(\d{4})\s*(?: Â)?\W*\s*(\d+:\d+\s*([ap]m)?)\s*$#iu", //Sun • Dec 30 2018 • 3:30 PM
            "#^[^\d\W]{2,}\s*,\s*([^\d\W]{3,})\s+(\d{1,2})\s*,\s*(\d{4})\s*(\d{1,2}:\d{2}(?:\s*[AaPp]\.?[Mm]\.?)?).*$#", //Wednesday, August 07, 2019 2:00 PM EDT
            "#^\w+\.[\s\·]*(\d+\s*\w*\s*\d{4})\s*$#u", //Ven. · 09 août 2024
        ];
        $out = [
            "$2 $1 $3, $4",
            "$2 $1 $3, $4",
            "$1",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#u", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }

    /**
     * @param string $string Unformatted string with amount
     */
    private function normalizeAmount(string $s): string
    {
        $s = preg_replace('/\s+/', '', $s);             // 11 507.00  ->  11507.00
        $s = preg_replace('/[,.\'](\d{3})/', '$1', $s); // 2,790      ->  2790    |    4.100,00  ->  4100,00    |    1'619.40  ->  1619.40
        $s = preg_replace('/,(\d{2})$/', '.$1', $s);    // 18800,00   ->  18800.00

        return $s;
    }

    /**
     * @param string $string Unformatted string with currency
     */
    private function normalizeCurrency(string $string): string
    {
        $string = trim($string);
        $currences = [
            'GBP' => ['£'],
            'EUR' => ['€'],
            'USD' => ['$', 'US$'],
        ];

        foreach ($currences as $currencyCode => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $currencyCode;
                }
            }
        }

        return $string;
    }

    private function htmlToText($s = '', bool $brConvert = true): string
    {
        if ($brConvert) {
            $s = str_replace("\n", '', $s);
            $s = preg_replace('/<[Bb][Rr]\b[ ]*\/?>/', "\n", $s); // only <br> tags
            $s = preg_replace('/(<td(?:| [^<>]+)>)/i', "\n" . '$1', $s);
        }
        $s = preg_replace('/<[A-z]+\b.*?\/?>/', '', $s); // opening tags
        $s = preg_replace('/<\/[A-z]+\b[ ]*>/', '', $s); // closing tags
        $s = html_entity_decode($s);
        $s = str_replace(chr(194) . chr(160), ' ', $s); // NBSP to SPACE

        return trim($s);
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "starts-with(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "contains(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function t($word)
    {
        if (!isset(self::$dict[$this->lang]) || !isset(self::$dict[$this->lang][$word])) {
            return $word;
        }

        return self::$dict[$this->lang][$word];
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }
}
