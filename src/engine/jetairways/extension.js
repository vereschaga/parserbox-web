var plugin = {

    mobileUserAgent: 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_11_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/51.0.2704.84 Safari/537.36',
    hosts: {
        'www.intermiles.com' : true,
        'intermiles.com' : true
    },

    getStartingUrl: function (params) {
        return 'https://www.intermiles.com/my-account/activity-tracker';
    },

    loadLoginForm: function(params){
        browserAPI.log("loadLoginForm");
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
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

    isLoggedIn: function () {
        browserAPI.log("isLoggedIn");
        if ($('.jp-jpn.jp-postlogin-popup').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        if ($('#VerifyMe').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        var number = util.findRegExp( $('.jp-jpn.jp-postlogin-popup').text(), / no\s*:\s*([\d]+)/i);
        browserAPI.log("number: " + number);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.AccountNumber) != 'undefined')
            && (account.properties.AccountNumber != '')
            && number
            && (number == account.properties.AccountNumber));
    },

    logout: function () {
        browserAPI.log("Logout");
        provider.setNextStep('loadLoginForm', function () {
            var popup = $('a > .jp-header-postlogin-info.jp-postlogin-popup:visible:eq(0), a.jp-mob-login:visible:eq(0) span.jp-postlogin-popup');
            if (popup.length) {
                popup.get(0).click();
                setTimeout(function () {
                    var logout = $('a.jp-logout-lnk:eq(0)');
                    if (logout.length)
                        logout.get(0).click();
                }, 2000);
            }
        });
    },

    login: function (params) {
        browserAPI.log("login");
        var form = $('#VerifyMe:visible');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            // reactjs
            provider.eval(
                "var FindReact = function (dom) {" +
                "    for (var key in dom) if (0 == key.indexOf(\"__reactEventHandlers$\")) {" +
                "        return dom[key];" +
                "    }" +
                "    return null;" +
                "};" +
                "FindReact(document.querySelector('input[id *= \"userName\"]')).onChange({target:{name:'userName', value:'" + params.account.login + "'}});"
                + "FindReact(document.querySelector('input[id *= \"password\"]')).onBlur({target:{name:'password', id:'password', value:'" + params.account.password + "'}});"
            );
            provider.setNextStep('checkLoginErrors', function () {
                setTimeout(function () {
                    browserAPI.log("click");
                    provider.eval(
                        "FindReact(document.querySelector('input[id *= \"VerifyMe\"]')).onClick({preventDefault:function(){}});"
                    );
                    setTimeout(function () {
                        plugin.checkLoginErrors();
                    }, 10000)
                }, 500)
            });
        }
        else
            provider.setError(util.errorMessages.loginFormNotFound);
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        var errors = $('div.textState-inline-message-text-color:visible, div.jp-textState-danger-text-color:visible');
        if (errors.length > 0) {
            provider.setError(errors.eq(0).text());
        }
        else {
            browserAPI.log("href: " + document.location.href);
            if (document.location.href === 'https://www.intermiles.com/login') {
                if ($('h2:contains("Let\'s get you logged in"):visible').length) {
                    provider.complete();
                }
                return;
            }
            provider.setNextStep('complete', function () {
                document.location.href = plugin.getStartingUrl(params);
            });
        }
    },

    complete: function (params) {
        browserAPI.log("complete");
        provider.complete();
    }
};