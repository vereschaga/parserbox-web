<?php

namespace AwardWallet\Schema\Parser\Common;

use AwardWallet\Schema\Parser\Component\Base;

class SubAccount extends Base
{

    /**
     * @parsed Field
     */
    protected $code;

    /**
     * @parsed Field
     */
    protected $displayName;

    /**
     * @parsed DateTime
     * @attr seconds=true
     */
    protected $expirationDate;

    /**
     * @parsed Boolean
     */
    protected $neverExpires;

    /**
     * @parsed Field
     * @attr type=basic
     * @attr length=short
     */
    protected $balance;

    /**
     * @parsed Boolean
     */
    protected $noBalance;

    /**
     * @var array
     */
    protected $properties = [];

    /**
     * @var array
     */
    protected $history = [];

    /**
     * @return string
     */
    public function getCode()
    {
        return $this->code;
    }

    /**
     * @param string $code
     * @return SubAccount
     */
    public function setCode($code)
    {
        $this->setProperty($code, 'code', false, false);
        return $this;
    }

    /**
     * @return string
     */
    public function getDisplayName()
    {
        return $this->displayName;
    }

    /**
     * @param string $displayName
     * @return SubAccount
     */
    public function setDisplayName($displayName)
    {
        $this->setProperty($displayName, 'displayName', false, false);
        $this->displayName = $displayName;
        return $this;
    }

    /**
     * @return int
     */
    public function getExpirationDate()
    {
        return $this->expirationDate;
    }

    /**
     * @param int $expirationDate
     * @return SubAccount
     */
    public function setExpirationDate($expirationDate)
    {
        $this->setProperty($expirationDate, 'expirationDate', false, false);
        return $this;
    }

    public function parseExpirationDate($dateStr)
    {
        $this->parseUnixTimeProperty($dateStr, 'expirationDate', null, '%D% %Y%', true);
        return $this;
    }

    /**
     * @return bool
     */
    public function getNeverExpires()
    {
        return $this->neverExpires;
    }

    /**
     * @param bool $neverExpires
     * @return SubAccount
     */
    public function setNeverExpires($neverExpires)
    {
        $this->setProperty($neverExpires, 'neverExpires', false, false);
        return $this;
    }

    /**
     * @return mixed
     */
    public function getBalance()
    {
        return $this->balance;
    }

    /**
     * @param mixed $balance
     * @return SubAccount
     */
    public function setBalance($balance)
    {
        $this->setProperty($balance, 'balance', false, false);
        return $this;
    }

    /**
     * @return bool
     */
    public function getNoBalance()
    {
        return $this->noBalance;
    }

    /**
     * @param bool $noBalance
     * @return SubAccount
     */
    public function setNoBalance($noBalance)
    {
        $this->setProperty($noBalance, 'noBalance', false, false);
        return $this;
    }

    public function addProperty($name, $value)
    {
        $this->logDebug(sprintf('%s: setting property `%s` = `%s`', $this->_name, $this->str($name), $this->str($value)));
        $valid = true;
        $error = null;
        if (!is_string($name) || !is_scalar($value) && !is_null($value)) {
            $valid = false;
            $error = sprintf('invalid property name or value: `%s` => `%s`', $this->str($name), $this->str($value));
        }
        else {
            if (is_string($value)) {
                $value = trim($value, " \r\n\t");
            }
            $name = trim($name, " \r\n\t");
            $this->properties[$name] = $value;
        }
        if (!$valid)
            $this->invalid($error);
        return $this;
    }

    public function setProperties($properties)
    {
        if (is_array($properties)) {
            $this->properties = [];
            foreach($properties as $key => $value) {
                $this->addProperty($key, $value);
            }
        }
    }

    public function getProperties(): array
    {
        return $this->properties;
    }

    /**
     * @return array
     */
    public function getHistory()
    {
        return $this->history;
    }

    /**
     * @param array $history
     * @return $this
     */
    public function setHistoryArray(array $history)
    {
        $this->history = $history;
        return $this;
    }

    /**
     * @param array $row
     * @return $this
     */
    public function addHistoryRow(array $row)
    {
        $this->history[] = $row;
        return $this;
    }

    protected function getChildren()
    {
        return [];
    }
}