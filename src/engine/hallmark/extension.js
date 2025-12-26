var plugin = {

    hosts: {
        'www.hallmark.com': true,
        'www.hallmark.nl': true,
        'cdns.gigya.com': true,
        'accounts.us1.gigya.com': true,
        'cr-content.hallmark.com': true,
        'account.hallmark.com': true,
        'my.hallmark.com': true
    },

    getStartingUrl: function (params) {
        if (params.account.login2 === 'Dutch') {
            return 'https://www.hallmark.nl/mijn-account/Discounts/LoyaltyCards';
        }

        return 'https://www.hallmark.com/login/';
    },

    start: function (params) {
        browserAPI.log("start");

        let counter = 0;
        let start = setInterval(function () {
            browserAPI.log("waiting... " + counter);
            let isLoggedIn = plugin.isLoggedIn(params);
            if (isLoggedIn !== null) {
                clearInterval(start);
                if (isLoggedIn) {
                    if (plugin.isSameAccount(params.account))
                        provider.complete();
                    else
                        plugin.logout(params);
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

    isLoggedIn: function (params) {
        browserAPI.log("isLoggedIn");
        if (params.account.login2 === 'Dutch') {
            browserAPI.log("Region => Dutch");
            if ($('form[action *= "/Security/Login"]').length > 0) {
                browserAPI.log("not LoggedIn");
                return false;
            }
            if ($('#button-top-header-logout').length > 0) {
                browserAPI.log("LoggedIn");
                return true;
            }
        }// if (params.account.login2 == 'Dutch')
        else {
            browserAPI.log("Region => USA");
            if ($('form[name = "login-form"]:visible').length > 0) {
                browserAPI.log("not LoggedIn");
                return false;
            }
            if ($('button[data-tau="logout_submit"]:visible').length > 0) {
                browserAPI.log("LoggedIn");
                return true;
            }
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        if (account.login2 === 'Dutch') {
            browserAPI.log("Region => Dutch");
            var name = $('div.content > h2').text();
            browserAPI.log("name: " + name);
            return ((typeof(account.properties) != 'undefined')
                && (typeof(account.properties.Name) != 'undefined')
                && (account.properties.Name != '')
                && name
                && (name.toLowerCase() == account.properties.Name.toLowerCase()));
        }
        else {
            browserAPI.log("Region => USA");
            const number = $('p:contains("number:")').text();
            browserAPI.log("number: " + number);
            return ((typeof(account.properties) != 'undefined')
                && (typeof(account.properties.Number) != 'undefined')
                && (account.properties.Number !== '')
                && (number === account.properties.Number));
        }
    },

    logout: function (params) {
        browserAPI.log("logout");
        if (params.account.login2 === 'Dutch') {
            browserAPI.log("Region => Dutch");
            provider.setNextStep('loadLoginForm', function () {
                $('#button-top-header-logout').get(0).click();

                if ($.browser.msie || $.browser.version == '11.0')
                    plugin.loadLoginForm();
            });
        }
        else {
            browserAPI.log("Region => USA");
            provider.setNextStep('start', function () {
                $('button[data-tau="logout_submit"]').get(0).click();
            });
        }
    },

    loadLoginForm: function(params) {
        browserAPI.log("loadLoginForm");
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    login: function (params) {
        browserAPI.log("login");
        let form;
        switch (params.account.login2) {
            case 'Dutch':
                browserAPI.log("Region => Dutch");
                form = $('form[action *= "/Security/Login"]');
                if (form.length > 0) {
                    browserAPI.log("submitting saved credentials");
                    form.find('input[name = "EmailAddress"]').val(params.account.login);
                    form.find('input[name = "Password"]').val(params.account.password);
                    provider.setNextStep('checkLoginErrors', function () {
                        form.find('button[id = "btn-submit-login-form"]').get(0).click();

                        if ($.browser.msie || $.browser.version == '11.0') {
                            setTimeout(function(){
                                window.location.reload();
                                provider.complete();
                            }, 1000)
                        }
                        setTimeout(function () {
                            plugin.checkLoginErrors(params);
                        }, 7000)
                    });
                }// if (form.length > 0)
                else
                    provider.setError(util.errorMessages.loginFormNotFound);
                break;
            default:
                browserAPI.log("Region => USA");
                form = $('form[name = "login-form"]:visible');

                if (form.length === 0) {
                    provider.setError(util.errorMessages.loginFormNotFound);
                    return;
                }

                browserAPI.log("submitting saved credentials");
                form.find('input[name = "dwfrm_login_email"]').val(params.account.login);
                form.find('input[name = "dwfrm_login_password"]').val(params.account.password);
                provider.setNextStep('checkLoginErrors', function () {
                    form.find('button[data-tau="login_submit"]').get(0).click();

                    setTimeout(function () {
                        plugin.checkLoginErrors(params);
                    }, 5000)
                });

                break;
        }
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        let errors;
        switch (params.account.login2) {
            case 'Dutch':
                browserAPI.log("Region => Dutch");
                errors = $("div.validation-summary-errors ul li");
                if (errors.length > 0 && util.filter(errors.text()) != '')
                    provider.setError(util.filter(errors.text()));
                else
                    provider.complete();
                break;
            default:
                browserAPI.log("Region => USA");
                errors = $('div[data-tau="global_alerts_item"]:visible div');

                if (errors.length > 0) {
                    provider.setError(errors.text());
                    return;
                }

                provider.complete();
                break;
        }
    }

}
