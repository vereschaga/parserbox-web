<?php

namespace AwardWallet\Engine\goldpassport\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class ReservationDetails extends \TAccountChecker
{
    public $mailFiles = "goldpassport/it-123105638.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'confNumber'          => ['Your confirmation number is'],
            'checkIn'             => ['Arrival Date'],
            'cancellationPhrases' => ['Cancell for FREE', 'Cancel for FREE'],
        ],
    ];

    private $subjects = [
        'en' => ['Reservation Confirmation for'],
    ];

    private $detectors = [
        'en' => ['Reservation Details'],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@playaresorts.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true && strpos($headers['subject'], 'Hyatt') === false) {
            return false;
        }

        foreach ($this->subjects as $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (is_string($phrase) && stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,"@Hyatt.com")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"Thank you for making a reservation at Hyatt") or contains(.,"@Hyatt.com")]')->length === 0
        ) {
            return false;
        }

        return $this->detectBody() && $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }
        $email->setType('ReservationDetails' . ucfirst($this->lang));

        $this->parseHotel($email, $parser->getSubject());

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

    private function parseHotel(Email $email, string $subject): void
    {
        $h = $email->add()->hotel();

        $traveller = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Hello'))}]", null, true, "/{$this->opt($this->t('Hello'))}\s+([[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]])(?:\s*[,;:?!]|$)/u");
        $h->general()->traveller($traveller);

        $hotelName_temp = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Thank you for making a reservation at'))}]", null, true, "/{$this->opt($this->t('Thank you for making a reservation at'))}\s*(.{3,}?)(?:\s*[.!?]|$)/");

        if ($hotelName_temp
            && ($this->http->XPath->query("//text()[{$this->contains($hotelName_temp)}]")->length > 1 || preg_match("/{$this->opt($hotelName_temp)}/", $subject) > 0)
        ) {
            $h->hotel()->name($hotelName_temp)->noAddress();
        }

        $confirmation = $this->http->FindSingleNode("//text()[{$this->starts($this->t('confNumber'))}]/following::text()[normalize-space()][1]", null, true, '/^[A-Z\d]{5,}$/');

        if ($confirmation) {
            $confirmationTitle = $this->http->FindSingleNode("//text()[{$this->starts($this->t('confNumber'))}]", null, true, "/^(.+?)(?:\s+{$this->opt($this->t('is'))})?[\s:：]*$/u");
            $h->general()->confirmation($confirmation, $confirmationTitle);
        }

        $checkIn = $this->http->FindSingleNode("//tr[not(.//tr)]/*[{$this->starts($this->t('checkIn'))}]", null, true, "/{$this->opt($this->t('checkIn'))}[:\s]*(.{6,})$/");
        $checkOut = $this->http->FindSingleNode("//tr[not(.//tr)]/*[{$this->starts($this->t('Departure Date'))}]", null, true, "/{$this->opt($this->t('Departure Date'))}[:\s]*(.{6,})$/");
        $h->booked()->checkIn2($checkIn)->checkOut2($checkOut);

        $guests = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Number of guests'))}]/following::text()[normalize-space()][1]", null, true, "/^\d{1,3}$/");
        $h->booked()->guests($guests);

        $roomType = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Room Type:'))}]", null, true, "/^{$this->opt($this->t('Room Type:'))}\s*(.{2,})$/");

        $xpathPayment = "//tr[{$this->eq($this->t('Payment Details'))}]/following-sibling::tr[normalize-space()][1]/descendant-or-self::tr[count(*[normalize-space()])=2][1]";

        $paymentCol1Texts = [];
        $paymentCol1Nodes = $this->http->XPath->query($xpathPayment . "/*[normalize-space()][1]/descendant::p");

        foreach ($paymentCol1Nodes as $pNode) {
            $paymentCol1Texts[] = $this->htmlToText($this->http->FindHTMLByXpath('.', null, $pNode));
        }
        $paymentCol1Texts = preg_split('/[ ]*\n[ ]*/', implode("\n", $paymentCol1Texts));

        $paymentCol2Texts = [];
        $paymentCol2Nodes = $this->http->XPath->query($xpathPayment . "/*[normalize-space()][2]/descendant::p");

        foreach ($paymentCol2Nodes as $pNode) {
            $paymentCol2Texts[] = $this->htmlToText($this->http->FindHTMLByXpath('.', null, $pNode));
        }
        $paymentCol2Texts = preg_split('/[ ]*\n[ ]*/', implode("\n", $paymentCol2Texts));

        $paymentFullTexts = [];

        if (count($paymentCol1Texts) === count($paymentCol2Texts)) {
            foreach ($paymentCol1Texts as $key => $pCol1Text) {
                $paymentFullTexts[] = $pCol1Text . ' ' . $paymentCol2Texts[$key];
            }
        }
        $paymentFullText = implode("\n", $paymentFullTexts);

        $roomRate = preg_match("/^[ ]*{$this->opt($this->t('Average Nightly Rate:'))}[ ]*(.*\d.*?)[ ]*$/m", $paymentFullText, $m) ? $m[1] : null;

        $room = $h->addRoom();
        $room->setType($roomType);

        if ($roomRate !== null) {
            $room->setRate($roomRate);
        }

        if (preg_match("/^[ ]*{$this->opt($this->t('Vacation Total:'))}[ ]*(.*\d.*?)[ ]*$/m", $paymentFullText, $m)
            && preg_match("/^(?<currency>[^\d)(]+?)[ ]*(?<amount>\d[,.\'\d ]*)$/", $m[1], $matches)
        ) {
            // USD 1,978
            $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
            $h->price()->currency($matches['currency'])->total(PriceHelper::parse($matches['amount'], $currencyCode));
        }

        $cancellation = $this->http->FindSingleNode("//text()[{$this->contains($this->t('cancellationPhrases'))}]");

        if (preg_match("/Cancell? (?i)for FREE (?<prior>\d{1,3} days?) prior to arrival/", $cancellation, $m)) {
            $h->booked()->deadlineRelative($m['prior'], '00:00');
        }
    }

    private function detectBody(): bool
    {
        if (!isset($this->detectors)) {
            return false;
        }

        foreach ($this->detectors as $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (!is_string($phrase)) {
                    continue;
                }

                if ($this->http->XPath->query("//*[{$this->contains($phrase)}]")->length > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['confNumber']) || empty($phrases['checkIn'])) {
                continue;
            }

            if ($this->http->XPath->query("//*[{$this->contains($phrases['confNumber'])}]")->length > 0
                && $this->http->XPath->query("//*[{$this->contains($phrases['checkIn'])}]")->length > 0
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function t(string $phrase)
    {
        if (!isset(self::$dictionary, $this->lang) || empty(self::$dictionary[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$this->lang][$phrase];
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
