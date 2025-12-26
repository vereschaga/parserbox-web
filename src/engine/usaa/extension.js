var plugin = {

    hosts: {'www.usaa.com': true},

    cashbackLink: '', // Dynamically filled by extension controller
    startFromCashback: function(params) {
        browserAPI.log('startFromCashback');
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    getStartingUrl: function (params) {
        /*
        if (provider.isMobile)
            return 'https://www.usaa.com/inet/ent_logon/Logon?skipredirect=true';

        return 'https://www.usaa.com/inet/ent_home/CpHome?action=INIT&jump=jp_default';
        */
        return 'https://www.usaa.com/my/logon';
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
                    let login = $('a:contains("Log On"):visible');
                    if (login.length) {
                        provider.setNextStep('login');
                        login.get(0).click();
                        return;
                    }
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
        if ($('#Logon, form[name = Logon]:visible, #main-logon-wrapper form').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('a[href *=logoff]').text()) {
            browserAPI.log("LoggedIn");
            return true;
        }
        return false;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        var name = util.findRegExp( $('div.toolsMessage').text(), /Welcome\s*,\s*([^<]+)/i);
        browserAPI.log("name: " + name);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.Name) != 'undefined')
            && (account.properties.Name != '')
            && (name.toLowerCase() == account.properties.Name.toLowerCase()));
    },

    logout: function () {
        browserAPI.log("Logout");
        provider.setNextStep('loadLoginForm');
        document.location.href = 'https://www.usaa.com/inet/ent_logon/Logoff?wa_ref=pri_auth_nav_logoff';
    },

    loadLoginForm: function(params) {
        browserAPI.log("loadLoginForm");
        provider.setNextStep('start');
        document.location.href = plugin.getStartingUrl(params);
    },

    login: function (params) {
        browserAPI.log("login");
        setTimeout(function() {
            let form = $('#Logon, form[name = Logon]:visible');
            if (form.length === 0) {
                form = $('#main-logon-wrapper form');
                if (form.length === 0) {
                    provider.setError(util.errorMessages.loginFormNotFound);
                    return;
                }
                // reactjs
                provider.eval(
                    "var FindReact = function (dom) {" +
                    "    for (var key in dom) if (0 == key.indexOf(\"__reactEventHandlers$\")) {" +
                    "        return dom[key];" +
                    "    }" +
                    "    return null;" +
                    "};" +
                    "FindReact(document.querySelector('input[name = \"memberId\"]')).onChange('" + params.account.login + "');"
                );
                form.find('button.submit-btn:visible').click();
                util.waitFor({
                    selector: 'input[name = "password"]:visible',
                    success: function (elem) {
                        // reactjs
                        provider.eval(
                            "function triggerInput(selector, enteredValue) {\n" +
                            "  const input = document.querySelector(selector);\n" +
                            "  const lastValue = input.value;\n" +
                            "  input.value = enteredValue;\n" +
                            "  const event = new Event(\"input\", { bubbles: true });\n" +
                            "  const tracker = input._valueTracker;\n" +
                            "  if (tracker) {\n" +
                            "    tracker.setValue(lastValue);\n" +
                            "  }\n" +
                            "  input.dispatchEvent(event);\n" +
                            "}\n" +
                            "triggerInput('input[name = \"password\"]', '" + params.account.password + "');"
                        );
                        $('button.pass-submit-btn:visible').click();

                        util.waitFor({
                            selector: 'a:contains("Use my PIN"):visible',
                            success: function (elem) {
                                elem.get(0).click();
                                setTimeout(function() {
                                    // reactjs
                                    provider.eval(
                                        "function triggerInput(selector, enteredValue) {\n" +
                                        "  const input = document.querySelector(selector);\n" +
                                        "  const lastValue = input.value;\n" +
                                        "  input.value = enteredValue;\n" +
                                        "  const event = new Event(\"input\", { bubbles: true });\n" +
                                        "  const tracker = input._valueTracker;\n" +
                                        "  if (tracker) {\n" +
                                        "    tracker.setValue(lastValue);\n" +
                                        "  }\n" +
                                        "  input.dispatchEvent(event);\n" +
                                        "}\n" +
                                        "triggerInput('input[name = \"pin\"]', '" + params.account.login2 + "');"
                                    );
                                    provider.setNextStep('checkLoginErrors');
                                    $('button.miam-btn-next:visible').click();
                                    setTimeout(function() {
                                        plugin.checkLoginErrors(params);
                                    }, 5000);
                                }, 2000);
                            },
                            fail: function () {
                                plugin.checkLoginErrors(params);
                            },
                            timeout: 5
                        });
                    },
                    fail: function () {
                        plugin.checkLoginErrors(params);
                    },
                    timeout: 15
                });
                return;
            }
            browserAPI.log("submitting saved credentials");
            // form.find('input[id = "j_usaaNum"]').val(params.account.login);
            // form.find('input[id = "j_usaaPass"]').val(params.account.password);
            if (provider.isMobile) {
                document.querySelector('input[name = "j_username"]').value = params.account.login;
                document.querySelector('input[name = "j_password"]').value = params.account.password;
            } else {
                document.querySelector('input[id = "j_usaaNum"]').value = params.account.login;
                document.querySelector('input[id = "j_usaaPass"]').value = params.account.password;
            }
            provider.setNextStep('enterPin');
            $('button.ent-logon-jump-button:visible, input.main-button:visible').click();
        }, 1000)
    },

    enterPin: function (params) {
        browserAPI.log("enterPin");
        var form = $('form[id *= id]');
        if (form.length === 0) {

            if ($('#messageLoginErrorLabel').length) {
                plugin.checkLoginErrors(params);
                return;
            }

            provider.setError(util.errorMessages.passwordFormNotFound);
            return;
        }
        form.find('input[name = "table:row1:pin1"]').val(params.account.login2);
        var button = $('button[name = submitButton]');
        button.click();
        provider.setNextStep('checkLoginErrors');
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        let errors = $('#messageLoginErrorLabel, .usaa-alert-message:visible');
        if (errors.length > 0) {
            provider.setError(errors.text());
            return;
        }

        plugin.loginComplete(params);
    },

    loginComplete: function(params) {
        browserAPI.log("loginComplete");
        provider.complete();
    }
};