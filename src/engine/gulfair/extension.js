var plugin = {
    // keepTabOpen: true,
    hosts: {'falconflyer.gulfair.com': true, 'www.gulfair.com': true},
    getStartingUrl: function (params) {
        //return 'https://falconflyer.gulfair.com/#/member/profilecompletion';
        return 'https://www.gulfair.com/';
    },

    loadLoginForm: function (params) {
        browserAPI.log("loadLoginForm");
        provider.setNextStep('start', function () {
            document.location.href = 'https://www.gulfair.com/loyality-system/login?dest=/';
        });
    },

    loadLoginForm2: function (params) {
        browserAPI.log("loadLoginForm");
        provider.setNextStep('login', function () {
            document.location.href = 'https://www.gulfair.com/loyality-system/login?dest=/';
        });
    },

    loadProfile: function (params) {
        browserAPI.log("loadProfile");
        provider.setNextStep('start', function () {
            document.location.href = $('.search-login-member a[href*="https://falconflyer.gulfair.com/member/profile?lang="]').attr('href');
        });
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
                    if (document.location.href.indexOf('https://www.gulfair.com/') !== -1) {
                        plugin.loadProfile(params);
                        return;
                    }
                    if (plugin.isSameAccount(params.account))
                        provider.complete();
                    else
                        plugin.logout(params);
                }
                else
                    plugin.loadLoginForm2(params);
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
        if ($('form#sso-login-form-login-non-corporate-form:visible, .box-wraper form.form-signin:visible').length > 0
        || $(".search-login-member a[href*='/loyality-system/login']:visible").length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('.header__user .dropdown-toggle:visible').length > 0
            || $(".search-login-member a[href*='/loyality-system/logout']:visible").length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        // for debug only
        //browserAPI.log("account: " + JSON.stringify(account));
        var number = util.findRegExp( $('.digital-card__info .number').text(), /^(\d+)$/);
        browserAPI.log("number: " + number);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.Number) != 'undefined')
            && (account.properties.Number != '')
            && (number == account.properties.Number));
    },

    logout: function (params) {
        browserAPI.log("logout");
        provider.setNextStep('logout2', function () {
            document.location.href = 'https://www.gulfair.com/loyality-system/logout';
        });
    },

    logout2: function (params) {
        browserAPI.log("logout");
        provider.setNextStep('logout3', function () {
            document.location.href = 'https://falconflyer.gulfair.com/#/member/profilecompletion';
        });
    },

    logout3: function (params) {
        browserAPI.log("logout2");
        provider.setNextStep('loadLoginForm2', function () {
            var logout = $('.header__user .dropdown-toggle:visible');
            if (logout.length) {
                logout.get(0).click();
                $('.header__user a:contains("Logout")').get(0).click();
            } else {
                plugin.loadLoginForm2(params);
            }
        });
    },

    login: function (params) {
        browserAPI.log("login");
        var form = $('form#sso-login-form-login-non-corporate-form');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            form.find('input[name = "membership_number"], input#edit-membership-number').val(params.account.login);
            form.find('input[name = "password"], input#edit-password').val(params.account.password);
            provider.setNextStep('checkLoginErrors', function () {
                form.find('button#edit-submit--2').get(0).click();
            });
        }// if (form.length > 0)
        else
            provider.setError(util.errorMessages.loginFormNotFound);
    },

    checkLoginErrors: function () {
        browserAPI.log("checkLoginErrors");
        var errors = $('#messages-wrapper .single-status-message:visible');
        if (errors.length > 0 && util.filter(errors.text()) !== '')
            provider.setError(errors.text());
        else
            provider.complete();
    }

};
