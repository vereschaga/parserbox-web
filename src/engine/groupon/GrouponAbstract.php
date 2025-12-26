<?php

abstract class GrouponAbstract
{
    public $sleepParse = 0;
    public $mainBalance = 0;
    /**
     * @var TAccountChecker
     */
    protected $checker;

    protected $login;
    protected $password;
    protected $accountID = 'Unknown';
    protected $loginType;

    public function __construct(TAccountCheckerGroupon $checker)
    {
        $this->checker = $checker;
    }

    public function __call($method, $params)
    {
        if (method_exists(CouponHelper::class, $method)) {
            return call_user_func_array([CouponHelper::class, $method], $params);
        }
    }

    public function setCredentials($login, $password)
    {
        $this->login = $login;
        $this->password = $password;
    }

    public function setAccountID($accountID)
    {
        $this->accountID = $accountID;
    }

    public function setLoginType($type)
    {
        $this->loginType = $type;
    }

    public function GetRedirectParams($arg)
    {
        return $arg;
    }

    /**
     * Checking for the existence var.
     */
    public function existsVar(&$var)
    {
        if (!is_null($var) && $var != "") {
            return true;
        }

        return false;
    }

    /**
     * Parsing pagination.
     *
     * @return array Array urls except the first page
     */
    public function getUrlsPages()
    {
        return [];
    }

    /**
     * Mark coupon (as used or unused).
     *
     * @param array $ids array["id"] = "used" (true or false)
     *
     * @return array The result for each coupon
     */
    abstract public function MarkCoupon(array $ids);

    /**
     * Parsing coupons.
     *
     * @return array coupons
     */
    abstract public function ParseCoupons($onlyActive = false);

    public function siteUpdated($subject, $message)
    {
        $this->checker->sendNotification("groupon - {$subject}", 'all', true, $message);
    }
}
