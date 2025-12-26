var plugin = {

    hosts: {'www.golfnow.com': true, "golfnow.com": true, 'my.golfid.io': true},

    getStartingUrl: function (params) {
        return 'https://www.golfnow.com/account/information';
    },

    loadLoginForm: function (params) {
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
                        plugin.loginComplete(params);
                    else
                        plugin.logout(params);
                }
                else {
                    var iframe = $('iframe#golfid-oauth-frame');
                    if (iframe.length) {
                        provider.setNextStep('login', function () {
                            document.location.href = iframe.attr('src');
                        });
                    }
                    else
                        plugin.login(params);
                }
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
        if ($('button#btnsign').length > 0
            || $('iframe#golfid-oauth-frame').length// new form
        ) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('a#logoutHamburgerMenu').length > 0) {
            browserAPI.log("LoggedIn logout found");
            return true;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        var name = $('input[name = "FirstName"]').attr('value') + ' ' + $('input[name = "LastName"]').attr('value');
        browserAPI.log("name: " + name);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.Name) != 'undefined')
            && (account.properties.Name != '')
            && name
            && (name.toLowerCase() == account.properties.Name.toLowerCase()));
    },

    logout: function (region) {
        browserAPI.log("logout");
        provider.setNextStep('loadLoginForm', function () {
            document.location.href = 'https://www.golfnow.com/account/logout';
        });
    },

    login: function (params) {
        browserAPI.log("login");
        var form = $('form#fmlogin');
        var formNew = $("#root");
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            form.find('input#UserName').val(params.account.login);
            form.find('input#Password').val(params.account.password);
            provider.setNextStep('checkLoginErrors', function () {
                form.find("button#btnsign").click();
            });
        }
        else {
            if (formNew.length > 0) {
                browserAPI.log("submitting saved credentials");
                // reactjs
                provider.eval(
                    "var FindReact = function (dom) {" +
                    "    for (var key in dom) if (0 == key.indexOf(\"__reactEventHandlers$\")) {" +
                    "        return dom[key];" +
                    "    }" +
                    "    return null;" +
                    "};" +
                    "FindReact($('#username').get(0)).onChange({target:{value:'" + params.account.login + "'}, preventDefault:function(){}});" +
                    "FindReact($('#password').get(0)).onChange({target:{value:'" + params.account.password + "'}, preventDefault:function(){}});"
                );
                provider.setNextStep('checkLoginErrors', function () {
                    formNew.find("button[type = 'button']").click();
                });
            }
            else
                provider.setError(util.errorMessages.loginFormNotFound);
        }
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        var error = $('small.error');
        if (error.length == 0)// new form
            error = $("div[role = 'alertdialog']:visible");
        if (error.length > 0)
            provider.setError(error.text());
        else {
            provider.setNextStep('loginComplete', function () {
                document.location.href = plugin.getStartingUrl(params);
            });
        }
    },

    loginComplete: function (params) {
        browserAPI.log("loginComplete");
        provider.complete();
    }
};
