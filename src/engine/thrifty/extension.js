var plugin = {
    hosts: {'www.thrifty.com': true},
    mobileUserAgent: 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_11_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/51.0.2704.84 Safari/537.36',
    //clearCache: true,

    cashbackLink: '', // Dynamically filled by extension controller

    startFromCashback: function(params) {
        browserAPI.log('startFromCashback');
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    getStartingUrl: function (params) {
        //return 'https://www.thrifty.com/BlueChip/SignIn.aspx';
        return 'https://www.thrifty.com/bluechip/index.aspx';
    },

    start: function (params) {
        browserAPI.log("start");
        var counter = 0;
        var start = setInterval(function () {
            browserAPI.log("waiting... " + counter);
            var isLoggedIn = plugin.isLoggedIn();
            if (isLoggedIn !== null) {
                clearInterval(start);
                if (isLoggedIn) {
                    var number = $('input[name="tfytracking.common.memberID"]').val();
                    // TODO: is not available immediately: tfytracking.common.memberID
                    if (!number && document.location.href.indexOf('/BlueChip/MembershipCard.aspx') === -1) {
                        provider.setNextStep('start', function () {
                            document.location.href = 'https://www.thrifty.com/BlueChip/MembershipCard.aspx';
                        });
                    }
                    else if (plugin.isSameAccount(params.account))
                        provider.complete();
                    else
                        plugin.logout(params);
                }
                else
                    plugin.loginLoadForm(params);
            }// if (isLoggedIn !== null)
            if (isLoggedIn === null && counter > 10) {
                clearInterval(start);
                provider.setError(util.errorMessages.unknownLoginState);
                return;
            }// if (isLoggedIn === null && counter > 10)
            counter++;
        }, 500);
    },

    isLoggedIn: function () {
        browserAPI.log("isLoggedIn");
        if ($('a:contains("Sign In")').length || $('#pagetitle_0_SignInLink').length || $('#pagetitle_0_SignInCookieLink').length) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('a:contains("Sign Out")').length || $('a#pagetitle_0_SignOutLink').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        // for debug only
        //browserAPI.log("account: " + JSON.stringify(account));
        var number = $('input[name="tfytracking.common.memberID"]').val();
        browserAPI.log("number: " + number);
        return typeof account.properties !== 'undefined'
            && typeof account.properties.AccountNumber !== 'undefined'
            && account.properties.AccountNumber !== ''
            && number === account.properties.AccountNumber;
    },

    logout: function () {
        browserAPI.log("logout");
        provider.setNextStep('profileLoadForm', function () {
            $('#pagetitle_0_SignOutLink').get(0).click();
        });
    },

    profileLoadForm: function () {
        browserAPI.log("profileLoadForm");
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl();
        });
    },

    loginLoadForm: function (params) {
        browserAPI.log("loginLoadForm");
        provider.setNextStep('login', function () {
            document.location.href = 'https://www.thrifty.com/BlueChip/SignIn.aspx';
        });
    },

    login: function (params) {
        browserAPI.log("login");
        var form = $('#BlueChipFormBody');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            form.find('input[name = "content_0$BlueChipLogin$BlueChipIDTextBox"]').val(params.account.login);
            form.find('input[name = "content_0$BlueChipLogin$PasswordTextBox"]').val(params.account.password);
            provider.setNextStep('checkLoginErrors', function () {
                form.find('input[name = "content_0$BlueChipLogin$SignInSitecoreImageButton"]').get(0).click();
            });
        }// if (form.length > 0)
        else
            provider.setError(util.errorMessages.loginFormNotFound);
    },

    checkLoginErrors: function () {
        browserAPI.log("checkLoginErrors");
        var errors = $('#content_0_BlueChipLogin_ErrorLabel:visible');
        if (errors.length > 0 && util.trim(errors.text()) !== '')
            provider.setError(errors.text());
        else
            provider.complete();
    }
};