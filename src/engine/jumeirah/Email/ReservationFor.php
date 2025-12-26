<?php

namespace AwardWallet\Engine\jumeirah\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Common\Hotel;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ReservationFor extends \TAccountChecker
{
    public $mailFiles = "jumeirah/it-177737600.eml";
    public $subjects = [
        'Reservation for',
    ];

    public $lang = '';

    public static $dictionary = [
        "en" => [
            'checkIn'        => ['CHECK IN'],
            'checkOut'       => ['CHECK OUT'],
            'Cancel Policy:' => ['Cancel Policy:', 'Cancellation Policy:', 'Cancel Policy', 'Cancellation Policy'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider(rtrim($headers['from'], '> ')) !== true) {
            return false;
        }

        foreach ($this->subjects as $phrase) {
            if (is_string($phrase) && array_key_exists('subject', $headers) && stripos($headers['subject'], $phrase) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//img[normalize-space(@width)="600" and starts-with(normalize-space(@alt),"Jumeirah")]')->length === 0
            && $this->http->XPath->query('//a[contains(@href,"%40jumeirah.com%7C")]')->length === 0
            && $this->http->XPath->query('//text()[starts-with(normalize-space(),"© Jumeirah")]')->length === 0
        ) {
            return false;
        }

        return $this->assignLang();
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]jumeirah\.com$/', $from) > 0;
    }

    public function ParseHotel(Email $email): void
    {
        $patterns = [
            'phone'         => '[+(\d][-+. \d)(]{5,}[\d)]', // +377 (93) 15 48 52    |    (+351) 21 342 09 07    |    713.680.2992
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
        ];

        $h = $email->add()->hotel();

        $h->general()
            ->confirmation($this->http->FindSingleNode("//text()[normalize-space()='Confirmation Number']/following::text()[normalize-space()][1]", null, true, "/^(\d+)$/"))
            ->traveller(str_replace(['Mrs.', 'Mr.'], '', $this->http->FindSingleNode("//text()[{$this->eq($this->t('Guest Name:'))}]/ancestor::tr[1]/descendant::td[2]", null, true, "/^{$patterns['travellerName']}$/u")))
            ->cancellation($this->http->FindSingleNode("//text()[{$this->eq($this->t('Cancel Policy:'))}]/following::text()[normalize-space()][1]"));

        $h->hotel()
            ->name($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'We look forward to welcoming you to ')]", null, true, "/{$this->opt($this->t('We look forward to welcoming you to '))}(.+?)\,/"));

        $hotelInfo = (!empty($h->getHotelName()) ? $this->http->FindSingleNode("//text()[{$this->eq($this->t('UNSUBSCRIBE'))}]/preceding::text()[{$this->starts($h->getHotelName())}][1]/ancestor::tr[1]") : null)
            ?? $this->htmlToText($this->http->FindHTMLByXpath("//text()[{$this->starts($this->t('Telephone Number:'))}]/ancestor::tr[1]"));

        //$this->logger->debug('$hotelInfo = ' . print_r($hotelInfo, true));

        if (!empty($h->getHotelName()) && preg_match("/^\s*{$this->opt($h->getHotelName())}\s*,\s*(?<address>.{3,75}?)\s*{$this->opt($this->t('Telephone Number:'))}/i", $hotelInfo, $m)
            || preg_match("/^\s*[^,]*Jumeirah[^,]*,\s*(?<address>.{3,75}?)\s*{$this->opt($this->t('Telephone Number:'))}/i", $hotelInfo, $m)
        ) {
            $h->hotel()->address($m['address']);
        }

        if (preg_match("/{$this->opt($this->t('Telephone Number:'))}[:\s]*(?<phone>{$patterns['phone']})\s*{$this->opt($this->t('UNSUBSCRIBE'))}/m", $hotelInfo, $m)) {
            $h->hotel()->phone($m['phone']);
        }

        $depMonth = $this->http->FindSingleNode("//text()[{$this->eq($this->t('checkIn'))}]/ancestor::tr[1]/preceding::tr[1]/td[normalize-space()][1]");
        $depDay = $this->http->FindSingleNode("//text()[{$this->eq($this->t('checkIn'))}]/ancestor::tr[1]/preceding::tr[2]/td[normalize-space()][1]");

        $arrMonth = $this->http->FindSingleNode("//text()[{$this->eq($this->t('checkIn'))}]/ancestor::tr[1]/preceding::tr[1]/td[normalize-space()][2]");
        $arrDay = $this->http->FindSingleNode("//text()[{$this->eq($this->t('checkIn'))}]/ancestor::tr[1]/preceding::tr[2]/td[normalize-space()][2]");

        $h->booked()
            ->checkIn(strtotime($depDay . ' ' . $depMonth))
            ->checkOut(strtotime($arrDay . ' ' . $arrMonth))
            ->guests($this->http->FindSingleNode("//text()[normalize-space()='Number of Adults:']/ancestor::tr[1]/td[2]", null, false, '/^\d{1,3}\b/'))
            ->kids($this->http->FindSingleNode("//text()[normalize-space()='Number of Children:']/ancestor::tr[1]/td[2]", null, false, '/^\d{1,3}\b/'), false, true)
            ->rooms($this->http->FindSingleNode("//text()[normalize-space()='Number of Rooms:']/ancestor::tr[1]/td[2]", null, false, '/^\d{1,3}\b/'));

        $roomType = $this->http->FindSingleNode("//text()[normalize-space()='Room Type:']/ancestor::tr[1]/descendant::td[2]");
        $roomRateItems = $this->http->FindNodes("//text()[normalize-space()='Nightly Room Rate:' or normalize-space()='Nightly Room Rate']/following::table[normalize-space()][1]/descendant::tr[normalize-space()]");

        if (!empty($roomType) || count($roomRateItems) > 0) {
            $room = $h->addRoom();

            if (!empty($roomType)) {
                $room->setType($roomType);
            }

            if (count($roomRateItems) > 0) {
                $room->setRate(implode('; ', $roomRateItems));
            }
        }

        $total = $this->http->FindSingleNode("//text()[normalize-space()='Total Room Cost per Stay*']/following::text()[normalize-space()][1]");

        if (preg_match("/^\s*(?<total>\d[\d\.\, ]*?) ?(?<currency>[^\d\s]{1,5})\s*[\,\*]/u", $total, $m)
            || preg_match("/^\s*(?<currency>[^\d\s]{1,5}) ?(?<total>\d[\d\.\, ]*)\s*[\,\*]/u", $total, $m)
        ) {
            $currency = $this->normalizeCurrency($m['currency']);

            $h->price()
                ->total(PriceHelper::parse(trim($m['total']), $currency))
                ->currency($currency);
        }

        $this->detectDeadLine($h);
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();
        $this->ParseHotel($email);

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

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['checkIn']) || empty($phrases['checkOut'])) {
                continue;
            }

            if ($this->http->XPath->query("//*[{$this->eq($phrases['checkIn'])}]/following-sibling::*[{$this->eq($phrases['checkOut'])}]")->length > 0) {
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

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
    }

    private function detectDeadLine(Hotel $h): void
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        if (preg_match("/Full\, non\-refundable, prepayment due at time of booking/", $cancellationText)) {
            $h->setNonRefundable(true);
        }

        if (preg_match("/Cancellations within (\d+) days of arrival charged full stay/", $cancellationText, $m)) {
            $h->booked()
                ->deadlineRelative($m[1] . ' days');
        }

        if (preg_match("/Reservation must be cancelled until (?<time>[\d\:]+\s*a?p?m)\s*(?<day>\d+\s*days?) prior to the arrival/", $cancellationText, $m)) {
            $h->booked()
                ->deadlineRelative($m['day'], $m['time']);
        }
    }

    private function normalizeCurrency(string $string)
    {
        $string = trim($string);
        $currencies = [
            'GBP' => ['£'],
            'EUR' => ['€'],
            '$'   => ['$'],
            'USD' => ['US$'],
        ];

        foreach ($currencies as $code => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $code;
                }
            }
        }

        return $string;
    }

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'normalize-space(' . $node . ')=' . $s;
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
