<?php

namespace AwardWallet\Engine\shoreex\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class ETicketUrl extends \TAccountChecker
{
    public $mailFiles = "shoreex/it-448808319.eml"; // + screenshots in Dropbox

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'numberOfAdults'      => ['Number of Adults:', 'Number of Individuals:'],
            'meetingInstructions' => ['Meeting Instructions', 'Meeting Instructions:'],
            'contactInfo'         => ['Contact Information', 'Contact Information:'],
            'addressStart'        => ['Your tour departs from'],
            'addressEnd'          => ['which is'],
        ],
    ];

    private $subjects = [
        'en' => ['E-Ticket and Meeting Instructions'],
    ];

    // for remote html-content
    private $httpEticket;
    private $httpReceipt;

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@shoreexmail.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider(rtrim($headers['from'], '> ')) !== true
            && strpos($headers['subject'], 'Shore Excursion') === false
        ) {
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
        if ($this->detectEmailFromProvider(rtrim($parser->getHeader('from'), '> ')) !== true
            && $this->http->XPath->query('//a[contains(@href,".shoreexcursionsgroup.com/") or contains(@href,"www.shoreexcursionsgroup.com")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"please contact us at info@shoreex.com")]')->length === 0
        ) {
            return false;
        }

        return $this->findLink()->length > 0;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $roots = $this->findLink();

        if ($roots->length === 0) {
            $this->logger->debug('E-Ticket link not found!');

            return $email;
        }

        $urlEticket = null;
        $urlEticketList = [];

        foreach ($roots as $root) {
            $urlEticketList[] = $this->http->FindSingleNode('./@href', $root);
        }

        if (count(array_unique($urlEticketList)) === 1) {
            $urlEticket = array_shift($urlEticketList);
        }

        if (empty($urlEticket)) {
            $this->logger->debug('E-Ticket link is empty!');

            return $email;
        }

        $this->httpEticket = clone $this->http;
        $this->httpReceipt = clone $this->http;

        $this->httpEticket->GetURL($urlEticket);

        $urlReceipt = $this->http->FindSingleNode("//a[descendant::img[contains(@alt,'View Summary of Charges')] and normalize-space()='' or contains(@href,'shoreexcursionsgroup.com/receipt/') and contains(@href,'order=') and contains(@href,'email=')]/@href");

        if (!empty($urlReceipt)) {
            $this->httpReceipt->GetURL($urlReceipt);
        }

        $this->lang = 'en';
        $email->setType('ETicketUrl' . ucfirst($this->lang));

        $patterns = [
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
        ];

        $costAmounts = $costCurrencies = [];

        $tours = $this->httpEticket->XPath->query($xpath = "//*[{$this->eq($this->t('TourDetails'), "translate(.,'0123456789 ','')")}]/ancestor::*[ descendant::text()[{$this->eq($this->t('Tour Time:'))}] ][1]");

        if ($tours->length === 0) {
            $this->logger->debug('Tours not found: ' . $xpath);
        }

        foreach ($tours as $tRoot) {
            $tourText = implode("\n", $this->httpEticket->FindNodes("descendant::text()[normalize-space()]", $tRoot));
            $ev = $email->add()->event();
            $ev->type()->event();

            $confirmation = $this->httpEticket->FindSingleNode("preceding::text()[normalize-space()][1]", $tRoot);

            if (preg_match("/^({$this->opt($this->t('Order #'))})\s*([-A-Z\d]{5,})$/", $confirmation, $m)) {
                $ev->general()->confirmation($m[2], $m[1]);
            }

            $additionalInfo = $this->httpEticket->FindHTMLByXpath("descendant::text()[{$this->eq($this->t('Additional Required Information:'))}]/following::text()[normalize-space()][1][not({$this->contains($this->t('Pickup Location:'))} or {$this->contains($this->t('meetingInstructions'))} or {$this->contains($this->t('contactInfo'))})]", "/^\s*(.+?)\s*$/s", $tRoot);

            $travellers = [];

            $guestNamesVal = $this->httpEticket->FindSingleNode("descendant::text()[{$this->eq($this->t('Name of Each Guest:'))}]/following::text()[normalize-space()][1][not({$this->contains($this->t('meetingInstructions'))})]", $tRoot);

            if ($guestNamesVal) {
                $guestNames = preg_split("/(?:\s*,\s*|\s+and\s+)+/i", $guestNamesVal);

                foreach ($guestNames as $gName) {
                    if (preg_match("/^{$patterns['travellerName']}$/u", $gName)) {
                        $travellers[] = $gName;
                    } else {
                        $this->logger->debug('Alert! Wrong guest name: ' . $gName);
                        $travellers = [];

                        break;
                    }
                }
            }

            if (count($travellers) === 0 && !empty($additionalInfo)) {
                /*
                    Edward Stephenson, Male, 03/05/1956, USA
                    Meg Stephenson, Female, 02/08/1949, USA
                */
                $aiRows = preg_split("/(?:[,; ]*\n+[,; ]*)+/", $additionalInfo);

                foreach ($aiRows as $aiRow) {
                    if (preg_match("/^({$patterns['travellerName']})[ ]*,[ ]*(?:Male|Female)[ ]*,[ ]*\d{1,2}\/\d{1,2}\/\d{2,4}[ ]*,[ ]*[- [:alpha:]]{2,}$/iu", $aiRow, $m)) {
                        $travellers[] = $m[1];
                    } else {
                        $travellers = [];

                        break;
                    }
                }
            }

            if (count($travellers) === 0) {
                // stepan pachikov - 166 pounds, svetlana kondratieva - 144
                $guestNameAndWeight = $this->httpEticket->FindSingleNode("descendant::text()[{$this->eq($this->t('Name and Weight of Each Guest (lbs):'))}]/following::text()[normalize-space()][1]", $tRoot);
                $guestNAWRows = preg_split("/(?:[ ]*,[ ]*)+/", $guestNameAndWeight);

                foreach ($guestNAWRows as $gNAWRow) {
                    if (preg_match("/^({$patterns['travellerName']})[ ]*[-:][ ]*\d+(?:[ ]*pounds?)?$/iu", $gNAWRow, $m)) {
                        $travellers[] = $m[1];
                    } else {
                        $travellers = [];

                        break;
                    }
                }
            }

            if (count($travellers) === 0) {
                $customer = $this->httpEticket->FindSingleNode("descendant::text()[{$this->eq($this->t('Customer:'))}]/following::text()[normalize-space()][1]", $tRoot, true, "/^{$patterns['travellerName']}$/u");
                $travellers = [$customer];
            }

            $ev->general()->travellers($travellers, true);

            $destination = $this->httpEticket->FindSingleNode("descendant::text()[{$this->eq($this->t('Destination:'))}]/following::text()[normalize-space()][1][not({$this->contains($this->t('Tour Name:'))})]", $tRoot);
            $tourName = $this->httpEticket->FindSingleNode("descendant::text()[{$this->eq($this->t('Tour Name:'))}]/following::text()[normalize-space()][1][not({$this->contains($this->t('Tour Date:'))})]", $tRoot);
            $ev->place()->name($tourName);

            $date = strtotime($this->httpEticket->FindSingleNode("descendant::text()[{$this->eq($this->t('Tour Date:'))}]/following::text()[normalize-space()][1]", $tRoot, true, "/^[[:alpha:]]+\s+\d{1,2}\s*,\s*\d{4}$/u"));
            $timeVal = $this->httpEticket->FindSingleNode("descendant::text()[{$this->eq($this->t('Tour Time:'))}]/following::text()[normalize-space()][1]", $tRoot);

            if (preg_match("/^\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?/u", $timeVal, $m)) {
                // 12:45 PM LOCAL TIME
                $time = $m[0];
            } elseif (preg_match("/^(?:\w[\w ]* (?:Hours?|Minutes?) After Ship Arrival|Upon Ship Arrival)$/i", $timeVal)) {
                $time = '00:00';
            } else {
                $time = null;
            }

            if ($date && $time !== null) {
                $dateStart = strtotime($time, $date);
                $ev->booked()->start($dateStart);

                if ($dateStart) {
                    $ev->booked()->noEnd();
                }
            }

            $guestCount = $this->httpEticket->FindSingleNode("descendant::text()[{$this->eq($this->t('numberOfAdults'))}]/following::text()[normalize-space()][1]", $tRoot, true, "/^\d{1,3}$/");
            $ev->booked()->guests($guestCount);

            $kidCount = $this->httpEticket->FindSingleNode("descendant::text()[{$this->starts($this->t('Number of Children'))}]/following::text()[normalize-space()][1]", $tRoot, true, "/^\d{1,3}$/");

            if ($kidCount) {
                $ev->booked()->kids($kidCount);
            }

            $pickupLocation = $this->httpEticket->FindSingleNode("descendant::text()[{$this->eq($this->t('Pickup Location:'))}]/following::text()[normalize-space()][1][not({$this->contains($this->t('meetingInstructions'))})]", $tRoot);

            $meetingInstructions = preg_match("/\n{$this->opt($this->t('meetingInstructions'))}\n+(.{2,}?)\n+{$this->opt($this->t('contactInfo'))}\n/s", $tourText, $m) > 0 ? $m[1] : null;

            $notes = preg_replace('/\s+/', ' ', $meetingInstructions);
            $ev->general()->notes($notes);

            $address = $pickupLocation;

            if (empty($address)) {
                $address = preg_match("/^{$this->opt($this->t('addressStart'))}[ ]+([[:upper:]\d].{15,70}?)[, ]+{$this->opt($this->t('addressEnd'))}/m", $meetingInstructions, $m) > 0 ? $m[1] : null;
            }

            if (empty($address)) {
                $address = $destination;
            }

            $ev->place()->address($address);

            if ($tourName) {
                $tourCost = $this->httpReceipt->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1]/descendant::text()[normalize-space()][1][{$this->eq($tourName)}] ]/*[normalize-space()][2]", null, true, '/^.*\d.*$/');

                if (preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.‘\'\d ]*?)[ ]*(?<currencyCode>[A-Z]{3})$/u', $tourCost, $matches)) {
                    // $1,632.00 USD
                    if (empty($matches['currencyCode'])) {
                        $currency = $matches['currency'];
                        $currencyCode = preg_match('/^[A-Z]{3}$/', $currency) ? $currency : null;
                    } else {
                        $currencyCode = $currency = $matches['currencyCode'];
                    }
                    $amount = PriceHelper::parse($matches['amount'], $currencyCode);
                    $ev->price()->currency($currency)->total($amount);

                    $costCurrencies[] = $currency;
                    $costAmounts[] = $amount;
                }
            }
        }

        $totalPrice = $this->httpReceipt->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('TOTAL'))}] ]/*[normalize-space()][2]", null, true, '/^.*\d.*$/');

        if (preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.‘\'\d ]*?)[ ]*(?<currencyCode>[A-Z]{3})$/u', $totalPrice, $matches)) {
            // $2,844.00 USD
            if (empty($matches['currencyCode'])) {
                $currency = $matches['currency'];
                $currencyCode = preg_match('/^[A-Z]{3}$/', $currency) ? $currency : null;
            } else {
                $currencyCode = $currency = $matches['currencyCode'];
            }
            $email->price()->currency($currency)->total(PriceHelper::parse($matches['amount'], $currencyCode));

            if (count(array_unique($costCurrencies)) === 1 && $costCurrencies[0] === $currency) {
                $email->price()->cost(array_sum($costAmounts));
            }

            $feesNodes = $this->httpReceipt->XPath->query("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Excursion Protection Plan'))}] ]");

            if ($feesNodes->length === 1) {
                $feeRoot = $feesNodes->item(0);
                $feeName = $this->httpReceipt->FindSingleNode("*[normalize-space()][1]", $feeRoot, true, '/^(.+?)[\s:：]*$/u');
                $feeCharge = $this->httpReceipt->FindSingleNode("*[normalize-space()][2]", $feeRoot, true, '/^.*\d.*$/');

                if (!empty($matches['currencyCode']) && preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.‘\'\d ]*?)(?:' . preg_quote($matches['currencyCode'], '/') . ')?$/u', $feeCharge, $m)
                ) {
                    $email->price()->fee($feeName, PriceHelper::parse($m['amount'], $currencyCode));
                }
            }

            $discount = $this->httpReceipt->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Coupon/Discount'))}] ]/*[normalize-space()][2]", null, true, '/^.*\d.*$/');

            if (!empty($matches['currencyCode']) && preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*-[ ]*(?<amount>\d[,.‘\'\d ]*?)(?:' . preg_quote($matches['currencyCode'], '/') . ')?$/u', $discount, $m)) {
                // $-90.80 USD
                $email->price()->discount(PriceHelper::parse($m['amount'], $currencyCode));
            }
        }

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

    private function findLink(): \DOMNodeList
    {
        return $this->http->XPath->query("//a[descendant::img[contains(@alt,'View and Print Your Electronic Ticket')] and normalize-space()='' or contains(@href,'shoreexcursionsgroup.com/eticket/') and contains(@href,'order=') and contains(@href,'email=')]");
    }

    private function t(string $phrase, string $lang = '')
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return $phrase;
        }

        if ($lang === '') {
            $lang = $this->lang;
        }

        if (empty(self::$dictionary[$lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$lang][$phrase];
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
}
