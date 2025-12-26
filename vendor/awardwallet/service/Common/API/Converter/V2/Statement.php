<?php

namespace AwardWallet\Common\API\Converter\V2;

use AwardWallet\Common\API\Email\V2\Loyalty\HistoryField;
use AwardWallet\Common\API\Email\V2\Loyalty\HistoryRow;
use AwardWallet\Common\API\Email\V2\Loyalty\LoyaltyAccount;
use AwardWallet\Common\API\Email\V2\Loyalty\Property;
use AwardWallet\Common\API\Email\V2\Loyalty\SubAccount;
use AwardWallet\Common\Parsing\Solver\Extra\Extra;
use AwardWallet\Schema\Parser\Common\Statement as ParsedStatement;

class Statement
{

    public function convert(ParsedStatement $statement, Extra $extra): ?LoyaltyAccount
    {
        $loyalty = new LoyaltyAccount();
        $loyalty->providerCode = $extra->provider->code;

        $loyalty->balance = $statement->getBalance();
        $loyalty->balanceDate = Util::date($statement->getBalanceDate());
        $loyalty->number = $statement->getNumber();
        $loyalty->login = $statement->getLogin();
        $loyalty->login2 = $statement->getLogin2();
        $loyalty->loginMask = $statement->getLoginMask();
        $loyalty->numberMask = $statement->getNumberMask();
        $loyalty->expirationDate = Util::date($statement->getExpirationDate());
        $loyalty->isMember = $statement->getMembership();
        if (!empty($statement->getProperties())) {
            foreach ($statement->getProperties() as $key => $value) {
                if (!isset($value))
                    continue;
                if (array_key_exists($key, $extra->provider->properties)) {
                    $name = $extra->provider->properties[$key]['Name'];
                    $kind = $extra->provider->properties[$key]['Kind'];
                }
                else
                    $name = $kind = null;
                $loyalty->properties[] = new Property($key, $name, $kind, $value);
            }
        }

        $columns = $extra->provider->historyFields;
        $dateField = null;
        foreach($columns as $name => $type) {
            if ($type === 'PostingDate') {
                $dateField = $name;
                break;
            }
        }
        if ($statement->getActivity() && $columns) {
            $activity = [];
            foreach($statement->getActivity() as $item) {
                $new = [];
                foreach($item as $k => $v) {
                    if (!isset($columns[$k]))
                        continue;
                    if (isset($dateField) && $k === $dateField && is_numeric($v))
                        $v = date(Util::DATE_FORMAT, $v);
                    $new[] = new HistoryField($k, $columns[$k], $v);
                }
                if (count($new) > 0) {
                    $activity[] = new HistoryRow($new);
                }
            }
            if (count($activity) > 0) {
                $loyalty->history = $activity;
            }
        }
        if ($statement->getSubAccounts()) {
            foreach($statement->getSubAccounts() as $sub) {
                $new = new SubAccount();
                $new->code = $sub->getCode();
                $new->displayName = $sub->getDisplayName();
                $new->balance = $sub->getBalance();
                if (!empty($sub->getExpirationDate())) {
                    $new->expirationDate = Util::date($sub->getExpirationDate());
                }
                foreach($sub->getProperties() as $k => $v) {
                    $new->properties[] = new Property($k, null, null, $v);
                }
                $history = [];
                foreach($sub->getHistory() as $row) {
                    $fields = [];
                    foreach($row as $k => $v) {
                        if (isset($dateField) && $k === $dateField && is_numeric($v)) {
                            $v = date(Util::DATE_FORMAT, $v);
                        }
                        $fields[] = new HistoryField($k, $columns[$k] ?? null, $v);
                    }
                    if (count($fields) > 0) {
                        $history[] = new HistoryRow($fields);
                    }
                }
                if (count($history) > 0) {
                    $new->history = $history;
                }
                $loyalty->subAccounts[] = $new;
            }
        }
        if ($loyalty->isMember
            || $loyalty->balance !== null
            || $loyalty->login !== null
            || $loyalty->number !== null
            || !empty($loyalty->properties)
            || !empty($loyalty->history))
            return $loyalty;
        return null;
    }

}
