<?php

namespace AwardWallet\Engine\marriott\Email;

class HotelConfirmation extends \TAccountChecker
{
    public $mailFiles = ""; // +1 bcdtravel(html)[en]

    protected $langDetectors = [
        'en' => ['Arrival:', 'Arrival :'],
    ];

    protected $lang = '';

    protected static $dict = [
        'en' => [],
    ];

    // Standard Methods

    public function detectEmailFromProvider($from)
    {
        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $condition1 = $this->http->XPath->query('//*[(name()="strong" or name()="b") and (contains(.,"Marriott") or contains(.,"MARRIOTT"))]')->length === 0;
        $condition2 = $this->http->XPath->query('//a[contains(@href,"//www.regonline.com/register")]')->length === 0;

        if ($condition1 || $condition2) {
            return false;
        }

        return $this->assignLang();
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->assignLang();
        $it = $this->parseEmail();

        return [
            'parsedData' => [
                'Itineraries' => [$it],
            ],
            'emailType' => 'HotelConfirmation_' . $this->lang,
        ];
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    public function IsEmailAggregator()
    {
        return false;
    }

    protected function t($phrase)
    {
        if (!isset(self::$dict[$this->lang]) || !isset(self::$dict[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dict[$this->lang][$phrase];
    }

    protected function parseEmail()
    {
        $it = [];
        $it['Kind'] = 'R';

        $rateLabelNodes = $this->http->XPath->query('//text()[contains(normalize-space(.),"River View Rate:")]/ancestor::*[name()="strong" or name()="b"][1]');

        if ($rateLabelNodes->length > 0) {
            $root = $rateLabelNodes->item(0);

            $patterns = [
                'date' => '/^[^:]+:\s*(\d{1,2}\/\d{1,2}\/\d{2,4})/',
            ];

            $xpathFragment1 = './ancestor::*[count(./*[(name()="strong" or name()="b") and normalize-space(.)])>1]';

            $it['HotelName'] = $this->http->FindSingleNode($xpathFragment1 . '/descendant::*[ (name()="strong" or name()="b") and normalize-space(.) and ./following::text()[contains(normalize-space(.),"River View Rate:")] ][1]', $root);

            if ($it['HotelName']) {
                $addressTexts = $this->http->FindNodes($xpathFragment1 . '/descendant::text()[ ./preceding::*[(name()="strong" or name()="b") and normalize-space(.)="' . str_replace('"', '\"', $it['HotelName']) . '"] and ./following::text()[contains(normalize-space(.),"River View Rate:")] ]', $root, '/^\s*(.+)\s*$/s');
                $addressValues = array_values(array_filter($addressTexts));
                $it['Address'] = implode(', ', $addressValues);
            }

            $it['Rate'] = $this->http->FindSingleNode('./following::text()[normalize-space(.)][1][contains(.,"per") or contains(.,"/")]', $root);

            $rateDescTexts = $this->http->FindNodes('./following::text()[normalize-space(.)][position()<20][starts-with(normalize-space(.),"-")]', $root);
            $rateDescText = implode(' ', $rateDescTexts);

            if (preg_match('/Check[-\s]+in\s*:\s*(\d{1,2}:\d{2}(?:\s*[ap]m)?|noon)/i', $rateDescText, $matches)) {
                $timeCheckIn = $matches[1];
            }

            if (preg_match('/Check[-\s]+out\s*:\s*(\d{1,2}:\d{2}(?:\s*[ap]m)?|noon)/i', $rateDescText, $matches)) {
                $timeCheckOut = $matches[1];
            }

            if ($cancelPolicy = $this->http->FindSingleNode('./following::*[ (name()="strong" or name()="b") and ./following::text()[starts-with(normalize-space(.),"Guest:")] ][starts-with(normalize-space(.),"Cancellation")][1]', $root)) {
                $it['CancellationPolicy'] = $cancelPolicy;
            }

            if ($guest = $this->http->FindSingleNode('//text()[starts-with(normalize-space(.),"Guest:")]', null, true, '/^[^:]+:\s*(.+)/')) {
                $it['GuestNames'] = [$guest];
            }

            if ($confNumber = $this->http->FindSingleNode('//text()[starts-with(normalize-space(.),"Confirmation #:")]', null, true, '/^[^:]+:\s*([A-Z\d]{5,})$/')) {
                $it['ConfirmationNumber'] = $confNumber;
            }

            if ($dateCheckIn = $this->http->FindSingleNode('//text()[starts-with(normalize-space(.),"Arrival:")]', null, true, $patterns['date'])) {
                $it['CheckInDate'] = strtotime($dateCheckIn . (isset($timeCheckIn) ? ', ' . $timeCheckIn : ''));
            }

            if ($dateCheckOut = $this->http->FindSingleNode('//text()[starts-with(normalize-space(.),"Departure:")]', null, true, $patterns['date'])) {
                $it['CheckOutDate'] = strtotime($dateCheckOut . (isset($timeCheckOut) ? ', ' . $timeCheckOut : ''));
            }
        }

        return $it;
    }

    protected function assignLang()
    {
        foreach ($this->langDetectors as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if ($this->http->XPath->query('//node()[contains(normalize-space(.),"' . $phrase . '")]')->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }
}
