<?php

namespace AwardWallet\Engine\mirage\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ItineraryDetails extends \TAccountChecker
{
    public $mailFiles = "mirage/it-63045998.eml, mirage/it-63233637.eml, mirage/it-639214951.eml";
    public $subjects = [
        '/Itinerary\s+Details\s+[-]\s+[\d\/]+\s+[-]\s+[\d\/]+$/i',
        '/Credit\/Debit Card Authorization for .+ Confirmation\s*#/i',
    ];

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'confNumber'   => ['CONFIRMATION NUMBER', 'Confirmation Number'],
            'addressAfter' => ['Thank you for choosing MGM Resorts'],
        ],
    ];

    private $patterns = [
        'phone' => '[+(\d][-+ \d)(]{5,}[\d)]',
    ];

    public function parseHotel(Email $email): void
    {
        $xPath = "//text()[starts-with(normalize-space(), 'Reservation Confirmation')]";
        $node = $this->http->XPath->query($xPath);

        if ($node->length == 0) {
            $xPath = "//img[normalize-space(@alt) = 'Reservation Confirmation']";
            $node = $this->http->XPath->query($xPath);
        }

        foreach ($node as $root) {
            $h = $email->add()->hotel();

            $dateReserv = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Dear')]/preceding::text()[starts-with(normalize-space(), 'Date')][1]", null, true, "/Date\:\s+([\d\/]+)/");

            $h->general()
                ->traveller($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Dear')]", null, true, "/Dear\s+(\D+)\,$/"), true)
                ->date(strtotime($dateReserv))
            ;

            $confirmation = $this->http->FindSingleNode("following::text()[{$this->starts($this->t('confNumber'))}][1]", $root);

            if (preg_match("/^({$this->opt($this->t('confNumber'))})[:\s]+([-A-Z\d]{5,15})$/i", $confirmation, $m)) {
                $h->general()->confirmation($m[2], $m[1]);
            }

            $h->hotel()
                ->name($this->http->FindSingleNode("following::text()[{$this->starts($this->t('addressAfter'))}][1]/preceding::text()[normalize-space()][1]/ancestor::tr[1]", $root, true, "/^(\D+)\,/"))
                ->address($this->http->FindSingleNode("following::text()[{$this->starts($this->t('addressAfter'))}][1]/preceding::text()[normalize-space()][1]/ancestor::tr[1]", $root, true, "/^\D+\,(.+)/"))
                ->phone($this->http->FindSingleNode("following::text()[{$this->starts($this->t('addressAfter'))}][1]/ancestor::*[1]", $root, true, "/toll\s*free\s*at\s*({$this->patterns['phone']})(?:\s*[,.:;!?]|$)/i"));

            $dateInfo = $this->http->FindSingleNode("./following::td[normalize-space()][1]/descendant::span[normalize-space()][1]", $root);

            if (preg_match("/^(?<checkIn>[A-Z]+\s+\d+\,\s+\d{4})\s+.\s+(?<checkOut>[A-Z]+\s+\d+\,\s+\d{4})$/u", $dateInfo, $m)) {
                $h->booked()
                    ->checkIn($this->normalizeDate($m['checkIn']))
                    ->checkOut($this->normalizeDate($m['checkOut']));
            }

            $cancellationText = $this->http->FindSingleNode("following::text()[contains(normalize-space(),'cancellation is received by Room')][1]/ancestor::*[1]", $root, true, "/The first night.+by booked offer\./");

            if (!empty($cancellationText)) {
                $this->detectDeadLine($h, $cancellationText);
                $h->general()
                    ->cancellation($cancellationText);
            }

            $totalPrice = $this->http->FindSingleNode("following::td[starts-with(normalize-space(),'Reservation Total:')][1]/following-sibling::td[normalize-space()][1]", $root, true, '/^.*\d.*$/');

            if (preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $totalPrice, $matches)) {
                // $620.87
                $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
                $h->price()->currency($matches['currency'])->total(PriceHelper::parse($matches['amount'], $currencyCode));
            }

            $roomName =
                $this->http->FindSingleNode("following::text()[normalize-space()][3][{$this->starts($this->t('confNumber'))}]/preceding::text()[normalize-space()][1]", $root) // it-639214951.eml
                ?? $this->http->FindSingleNode("following::text()[{$this->starts($this->t('confNumber'))}][1]/following::text()[normalize-space()][1][not(following::text()[normalize-space()][1][{$this->starts($this->t('addressAfter'))}])]", $root) // it-63233637.eml
            ;

            if (!empty($roomName)) {
                $room = $h->addRoom();

                $room->setType($roomName);

                $rates = [];
                $roomRateText = $this->htmlToText($this->http->FindHTMLByXpath("(following::tr[ *[normalize-space()][1][not(.//tr)][starts-with(normalize-space(),'Room Rate and Tax')] ]/*[normalize-space()][2])[1]", null, $root));
                $roomRateRows = preg_split("/[ ]*\n+[ ]*/", $roomRateText);

                foreach ($roomRateRows as $rateRow) {
                    if (preg_match("/^\d{1,2}\/\d{1,2}\/\d{2,4}[ ]+-[ ]+(.+)$/", $rateRow, $m)) {
                        // 09/05/2020 - $219.60 Rate plus 13.38% Tax    |    02/10/2022 - COMP
                        if (preg_match("/\d/", $m[1])) {
                            $rates[] = $m[1];
                        } elseif (preg_match("/^COMP$/i", $m[1])) {
                            $rates[] = '0.00';
                        }
                    } else {
                        $rates = [];

                        break;
                    }
                }

                if (count($rates) > 0) {
                    $room->setRates($rates);
                }
            }
        }
    }

    public function parseEvent(Email $email): void
    {
        $xPath = "//text()[starts-with(normalize-space(), 'Reservation Confirmation')]";
        $node = $this->http->XPath->query($xPath);

        foreach ($node as $root) {
            $e = $email->add()->event();

            $dateReserv = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Dear')]/preceding::text()[starts-with(normalize-space(), 'Date')][1]", null, true, "/Date\:\s+([\d\/]+)/");
            $e->general()
                ->traveller($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Dear')]", null, true, "/Dear\s+(\D+)\,$/"), true)
                ->date(strtotime($dateReserv))
            ;

            $confirmation = $this->http->FindSingleNode("following::text()[{$this->starts($this->t('confNumber'))}][1]", $root);

            if (preg_match("/^({$this->opt($this->t('confNumber'))})[:\s]+([-A-Z\d]{5,15})$/i", $confirmation, $m)) {
                $e->general()->confirmation($m[2], $m[1]);
            }

            $e->place()
                ->name($this->http->FindSingleNode("following::text()[{$this->starts($this->t('addressAfter'))}][1]/preceding::text()[normalize-space()][1]", $root, true, "/^(\D+)\,/"))
                ->address($this->http->FindSingleNode("following::text()[{$this->starts($this->t('addressAfter'))}][1]/preceding::text()[normalize-space()][1]", $root, true, "/^\D+\,(.+)/"))
                ->phone($this->http->FindSingleNode("following::text()[{$this->starts($this->t('addressAfter'))}][1]/ancestor::*[1]", $root, true, "/toll\s*free\s*at\s*({$this->patterns['phone']})(?:\s*[,.:;!?]|$)/i"));

            $dateStart = $this->http->FindSingleNode("./following::td[starts-with(normalize-space(), 'Reservation Date:')]/following-sibling::td[1]", $root);
            $timeStart = $this->http->FindSingleNode("./following::td[starts-with(normalize-space(), 'Reservation Time:')]/following-sibling::td[1]", $root);

            $e->booked()
                ->guests($this->http->FindSingleNode("./following::td[starts-with(normalize-space(), 'Number in Party:')]/following-sibling::td[1]", $root, true, "/^(\d+)$/"))
                ->start(strtotime($dateStart . ', ' . $timeStart))
                ->noEnd();

            $e->type()->restaurant();
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();
        $email->setType('ItineraryDetails' . ucfirst($this->lang));

        if ($this->http->XPath->query("//text()[starts-with(normalize-space(),'Room Reservations:')] | //tr[normalize-space()='Reservation Confirmation']/following::text()[normalize-space()][3][{$this->starts($this->t('confNumber'))}]")->length > 0) {
            $this->parseHotel($email);
        }

        if ($this->http->XPath->query("//text()[starts-with(normalize-space(), 'Dining Reservations:')]")->count() > 0) {
            $this->parseEvent($email);
        }

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]mgmresorts\.com$/', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@mgmresorts.com') !== false) {
            foreach ($this->subjects as $subject) {
                if (preg_match($subject, $headers['subject'])) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->detectEmailFromProvider(rtrim($parser->getHeader('from'), '> ')) !== true
            && $this->http->XPath->query('//a[contains(@href,".mgmresorts.com/") or contains(@href,"www.mgmresorts.com")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"Thank you for choosing MGM Resorts") or contains(normalize-space(),"Thank You, MGM Resorts International")]')->length === 0
        ) {
            return false;
        }

        return $this->assignLang();
    }

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'contains(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }

    private function starts($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'starts-with(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
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

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['confNumber']) || empty($phrases['addressAfter'])) {
                continue;
            }

            if ($this->http->XPath->query("//*[{$this->contains($phrases['confNumber'])}]")->length > 0
                && $this->http->XPath->query("//*[{$this->contains($phrases['addressAfter'])}]")->length > 0
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($str)
    {
        $in = [
            //SEPT 2, 2020
            '#^([A-Z]+)\s+(\d+)\,\s+(\d{4})$#u',
        ];
        $out = [
            '$2 $1 $3',
        ];
        $str = preg_replace($in, $out, $str);

        return strtotime($str);
    }

    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h, $cancellationText): void
    {
        if (preg_match("/at least (?<hours>\d{1,3} hours?) prior to the arrival date/i", $cancellationText, $m)) {
            $h->booked()->deadlineRelative($m['hours']);
        }
    }

    private function htmlToText(?string $s, bool $brConvert = true): string
    {
        if (!is_string($s) || $s === '') {
            return '';
        }
        $s = str_replace("\r", '', $s);
        $s = preg_replace('/<!--.*?-->/s', '', $s); // comments

        if ($brConvert) {
            $s = preg_replace('/\s+/', ' ', $s);
            $s = preg_replace('/<[Bb][Rr]\b.*?\/?>/', "\n", $s); // only <br> tags
        }
        $s = preg_replace('/<[A-z][A-z\d:]*\b.*?\/?>/', '', $s); // opening tags
        $s = preg_replace('/<\/[A-z][A-z\d:]*\b[ ]*>/', '', $s); // closing tags
        $s = html_entity_decode($s);
        $s = str_replace(chr(194) . chr(160), ' ', $s); // NBSP to SPACE

        return trim($s);
    }
}
