<?php

namespace AwardWallet\Engine\grab\Email;

use AwardWallet\Schema\Parser\Email\Email;

class ThanksForRidingTip extends \TAccountChecker
{
    public $mailFiles = "grab/it-61182503.eml, grab/it-61135216.eml, grab/it-61103799.eml";

    public $lang = '';

    public static $dictionary = [
        'th' => [
            'From:'       => 'สถานที่เริ่มต้นการเดินทาง:',
            'To:'         => 'สถานที่ปลายทาง:',
            'Date:'       => 'วันที่เดินทาง:',
            'Booking ID:' => 'รหัสการจอง:',
            'Issued to'   => 'ชื่อผู้เดินทาง',
            //            'Passenger' => '', // from parser ThanksForRiding
            //            'Your Trip' => '', // from parser ThanksForRiding
        ],
        'vi' => [
            'From:'       => 'Điểm đón khách:',
            'To:'         => 'Điểm trả khách:',
            'Date:'       => 'Ngày đi:',
            'Booking ID:' => 'Mã chuyến đi:',
            'Issued to'   => 'Người dùng',
            'Passenger'   => 'Khách đi xe', // from parser ThanksForRiding
            'Your Trip'   => 'Chuyến đi của bạn', // from parser ThanksForRiding
        ],
        'id' => [
            'From:'       => 'Lokasi Penjemputan:',
            'To:'         => 'Lokasi Tujuan:',
            'Date:'       => 'Dijemput Pada:',
            'Booking ID:' => 'Kode Booking:',
            'Issued to'   => 'Diterbitkan untuk',
            'Passenger'   => 'Penumpang', // from parser ThanksForRiding
            'Your Trip'   => 'Perjalananmu', // from parser ThanksForRiding
        ],
        'en' => [
            'From:'       => 'From:',
            'To:'         => 'To:',
            'Date:'       => 'Date:',
            'Booking ID:' => 'Booking ID:',
            'Issued to'   => 'Issued to',
        ],
    ];

    private $detectors = [
        'th' => ['ขอบคุณสำหรับทิปเพื่อเป็นกำลังใจให้คนขับ'],
        'vi' => ['Cảm ơn bạn đã bắn "típ" cho bác tài!'],
        'id' => ['Tip darimu sudah disalurkan ke pengemudimu'],
        'en' => ['Your tip goes a long way for your driver'],
    ];

    private $grabGoodLabels = ['JustGrab', 'GrabBike', 'GrabCar', 'GrabTukTuk', 'GrabSUV', 'GrabRemorque'];
    private $grabBadLabels = ['GrabFood', 'GrabExpress', 'GrabMart'];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@grab.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if (stripos($parser->getSubject(), 'Your Grab E-Receipt') === false) {
            return false;
        }

        if (
            $this->http->XPath->query("//node()[{$this->contains($this->grabBadLabels)} or {$this->contains($this->grabGoodLabels)}]")->length === 0
            && $this->http->XPath->query("//a[contains(@href, 'help.grab.com%2F') or contains(@href, 'help.grab.com*2F')] | //*[contains(., '© Grab ')]")->length === 0
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

        $email->setType('ThanksForRidingJunk' . ucfirst($this->lang));

        if ($this->http->XPath->query("//text()[{$this->starts($this->t('Passenger'))}]")->length === 0
            && $this->http->XPath->query("//text()[{$this->starts($this->t('Your Trip'))}]")->length === 0
        ) {
            if ($this->http->XPath->query("//text()[{$this->starts($this->grabBadLabels)}]")->length === 1) {
                $email->setIsJunk(true);

                return $email;
            }
        }

        if ($this->http->XPath->query("//text()[{$this->starts($this->grabGoodLabels)}]")->length === 1) {
            $this->parseEmail($email);
        }

        return $email;
    }

    public function parseEmail(Email $email)
    {
        $t = $email->add()->transfer();

        $t->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->starts($this->t('Booking ID:'))}]", null, true, "/^\s*{$this->opt($this->t('Booking ID:'))}\s*([A-Z\d\-]{5,})\s*$/"));

        $traveller = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Issued to'))}]/following::text()[normalize-space()][1]");
        $t->general()
            ->traveller($traveller);

        // Segment
        $segment = $t->addSegment();

        $depName = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Booking ID:'))}]/preceding::text()[{$this->starts($this->t('From:'))}][1]/ancestor-or-self::node()[position()< 5][{$this->starts($this->t('From:'))}][not(.//text()[{$this->starts($this->t('Date:'))}])][last()]",
            null, true, "/^\s*{$this->opt($this->t('From:'))}\s*(.+)/");
        $date = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Booking ID:'))}]/preceding::text()[{$this->starts($this->t('Date:'))}][1]/ancestor-or-self::node()[position()< 5][{$this->starts($this->t('Date:'))}][last()]",
            null, true, "/^\s*{$this->opt($this->t('Date:'))}\s*(.+)/");

        $segment->departure()
            ->date(strtotime($date))
            ->name($depName);

        $arrName = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Booking ID:'))}]/preceding::text()[{$this->starts($this->t('To:'))}][1]/ancestor-or-self::node()[position()< 5][{$this->starts($this->t('To:'))}][last()]",
            null, true, "/^\s*{$this->opt($this->t('To:'))}\s*(.+)/");
        $segment->arrival()
            ->noDate()
            ->name($arrName);

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

    private function detectBody(): bool
    {
        if (!isset($this->detectors)) {
            return false;
        }

        foreach ($this->detectors as $phrases) {
            if ($this->http->XPath->query("//node()[{$this->contains($phrases)}]")->length > 0) {
                return true;
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
            if (!is_string($lang) || empty($phrases['Booking ID:']) || empty($phrases['Issued to'])) {
                continue;
            }

            if ($this->http->XPath->query("//node()[{$this->contains($phrases['Booking ID:'])}]")->length > 0
                && $this->http->XPath->query("//node()[{$this->contains($phrases['Issued to'])}]")->length > 0
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
}
