var plugin = {

    hosts: {'www.enterprise.com': true, 'enterpriseplus.enterprise.com': true},

    getStartingUrl: function (params) {
        return 'https://www.enterprise.com/';
    },

    cashbackLink : '', // Dynamically filled by extension controller
    startFromCashback : function(params) {
        browserAPI.log('startFromCashback');
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    start: function (params) {
        browserAPI.log("start");
        if (document.location.href.match(/^https?:\/\/m\.enterprise\.com\//)) {
            var fullSiteLink = $('a[href *= "View_Full_Site"]');
            if (fullSiteLink.length == 1) {
                provider.setNextStep('start', function () {
                    document.location.href = fullSiteLink.attr('href');
                });
                return;
            }// if (fullSiteLink.length == 1)
        }// if (document.location.href.match(/^https?:\/\/m\.enterprise\.com\//))

        var counter = 0;
        var start = setInterval(function () {
            browserAPI.log("[start]: waiting... " + counter);
            let isLoggedIn = plugin.isLoggedIn();
            if (isLoggedIn !== null) {
                clearInterval(start);
                $('button[data-dtm-tracking = "button|top_nav|signin_join"]').get(0).click();
                if (isLoggedIn) {
                    setTimeout(function() {
                        if (plugin.isSameAccount(params.account))
                            plugin.loginComplete(params);
                        else
                            plugin.logout(params);
                    }, 2000);
                    return;
                }
                else
                    plugin.login(params);
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
        if ($('strong#signInJoinButton').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('button[data-dtm-tracking = "button|top_nav|signin_join"]').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        var number = util.findRegExp($('div.loyalty-number:contains("#")').text(), /#(\w+)/i);
        browserAPI.log("number: " + number);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.MemberNumber) != 'undefined')
            && (account.properties.MemberNumber != '')
            && (number == account.properties.MemberNumber ));
    },

    logout: function (params) {
        browserAPI.log("logout");
        $('button.logout').get(0).click();
        setTimeout(function() {
            $('button:contains("Sign Out")').click();
            setTimeout(function() {
                plugin.login(params);
            }, 2000)
        }, 2000)
    },

    login: function (params) {
        browserAPI.log("login");
        // wait login form
        var counter = 0;
        var login = setInterval(function () {
            var form = $('div.login-field-container:visible');
            browserAPI.log("[login]: waiting... " + counter);
            if (form.length > 0) {
                clearInterval(login);
                browserAPI.log("submitting saved credentials");
                form.find('input[name = "eplus-email"]').val(params.account.login);
                form.find('input[name = "eplus-password"]').val(params.account.password);
                provider.setNextStep('checkLoginErrors', function () {
                    $('button.btn:contains("Sign In")', '.login-field-container').click();
                    setTimeout(function() {
                        plugin.checkLoginErrors(params);
                    }, 5000)
                });
            }
            if (counter > 10) {
                clearInterval(login);
                provider.setError(util.errorMessages.loginFormNotFound);
            }
            counter++;
        }, 500);
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        const error = $('div.global-error:visible, span[id *= "-error"]:visible');

        if (error.length > 0) {
            provider.setError(error.text());
            return;
        }

        plugin.loginComplete(params);
    },

    loginComplete: function (params) {
        browserAPI.log("loginComplete");
        provider.complete();
    }
};
