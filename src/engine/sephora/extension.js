var plugin = {
    hosts: {'www.sephora.com': true, 'www.sephora.it': true, 'www.sephora.es': true, 'm.sephora.com': true},
    hideOnStart: true,//todo
    cashbackLinkMobile : false,
    cashbackLink: '', // Dynamically filled by extension controller
    startFromCashback: function(params) {
        browserAPI.log('startFromCashback');
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    getStartingUrl: function (params) {
        if (params.account.login2 === 'Italy')
            return 'https://www.sephora.it/auth_secure/user/my_account/account_loyalty.jsp';
        if (params.account.login2 === 'Spain')
            return 'https://www.sephora.es/iniciar-sesion';

        return 'https://www.sephora.com/profile/me';
    },

    loadLoginStart: function(params) {
        browserAPI.log("loadLoginStart");
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    loadLoginForm: function(params) {
        browserAPI.log("loadLoginForm");
        // if (params.account.login2 === 'USA') {
        //     provider.setNextStep('login', function () {
        //         document.location.href = plugin.getStartingUrl(params);
        //     });
        // } else
            plugin.login(params);
    },

    start: function (params) {
        browserAPI.log("start");
        let counter = 0;
        let start = setInterval(function () {
            browserAPI.log("waiting... " + counter);
            let isLoggedIn = plugin.isLoggedIn(params.account);
            if (isLoggedIn !== null) {
                clearInterval(start);
                if (isLoggedIn) {
                    if (plugin.isSameAccount(params.account))
                        plugin.loginComplete(params);
                    else
                        plugin.logout(params);
                }
                else
                    plugin.loadLoginForm(params);
            }// if (isLoggedIn !== null)
            if (isLoggedIn === null && counter > 10) {
                clearInterval(start);
                provider.setError(util.errorMessages.unknownLoginState);
                return;
            }// if (isLoggedIn === null && counter > 10)
            counter++;
        }, 500);
    },

    isLoggedIn: function (account) {
        browserAPI.log("isLoggedIn");
        switch (account.login2) {
            case 'Italy':
            case 'Spain':
                if ($('form.email-form:visible').length > 0) {
                    browserAPI.log("not LoggedIn");
                    return false;
                }
                if ($('a:contains("Disconnetti"):visible, a:contains("Cerrar sesión"):visible').length > 0) {
                    browserAPI.log("LoggedIn");
                    return true;
                }
                break;
            default:
                if ($('h2:contains("To view your profile, please"):visible').length > 0) {
                    browserAPI.log("not LoggedIn");
                    return false;
                }
                if ($('button:contains("Sign Out")').length > 0) {
                    browserAPI.log("LoggedIn");
                    return true;
                }
                break;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        switch (account.login2) {
            case 'Italy':
            case 'Spain':
                const number = util.findRegExp($('div.card-number').text(), /\s+([\d\s]+)$/i);
                browserAPI.log("number: " + number);
                return ((typeof(account.properties) != 'undefined')
                    && (typeof(account.properties.BeautyInsiderNumber) != 'undefined')
                    && (account.properties.BeautyInsiderNumber !== '')
                    && number
                    && (number === account.properties.BeautyInsiderNumber));
                break;
            default:
                //const name = util.filter($('#account_drop_trigger > span').text());
                let name = '';
                plugin.ajaxRequest("https://www.sephora.com/api/users/profiles/current/full?includeTargeters=%2Fatg%2Fregistry%2FRepositoryTargeters%2FSephora%2FCCDynamicMessagingTargeter&includeApis=profile,basket,loves,shoppingList,targetersResult,targetedPromotion&cb=" + new Date().getTime(),
                    'GET', null, false, function (response) {
                    name = util.beautifulName(response.profile.firstName + ' ' + response.profile.lastName);
                        browserAPI.log("Name: " + name);
                    }, function (response) {
                        browserAPI.log('Parse Props error: ' + response);
                    });
                 browserAPI.log("account.properties.Name: " + account.properties.Name);

                return (typeof(account.properties) != 'undefined')
                    && (typeof(account.properties.Name) != 'undefined')
                    && (account.properties.Name !== '')
                    && name
                    && name.toLowerCase().toLowerCase().indexOf(account.properties.Name.toLowerCase()) !== -1;
                break;
        }
    },

    logout: function (params) {
        browserAPI.log("logout");
        switch (params.account.login2) {
            case 'Italy':
                provider.setNextStep('loadLoginStart', function () {
                    $('a:contains("Disconnetti"):visible').get(0).click();
                });
                break;
            case 'Spain':
                provider.setNextStep('loadLoginStart', function () {
                    $('a:contains("Cerrar sesión"):visible').get(0).click();
                });
                break;
            default:
                provider.setNextStep('loadLoginStart', function () {
                    $('button:contains("Sign Out")').click();
                });
                break;
        }
    },

    login: function (params) {
        browserAPI.log("login");

        var form;
        switch (params.account.login2) {
            case 'Italy':
            case 'Spain':
                form = $('form.email-form');
                if (form.length === 0) {
                    provider.setError(util.errorMessages.loginFormNotFound);
                    return;
                }
                browserAPI.log("submitting saved credentials");
                form.find('input[name = "dwfrm_crmsephoracard_email"]').val(params.account.login);

                // reactjs
                provider.eval(
                    "function triggerInput(selector, enteredValue) {\n" +
                    "      let input = document.querySelector(selector);\n" +
                    "      input.dispatchEvent(new Event('focus'));\n" +
                    "      input.dispatchEvent(new KeyboardEvent('keypress',{'key':'a'}));\n" +
                    "      let nativeInputValueSetter = Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype, 'value').set;\n" +
                    "      nativeInputValueSetter.call(input, enteredValue);\n" +
                    "      let inputEvent = new Event(\"input\", { bubbles: true });\n" +
                    "      input.dispatchEvent(inputEvent);\n" +
                    "}\n" +
                    "triggerInput('input[name = \"dwfrm_crmsephoracard_email\"]', '" + params.account.login + "');"
                );

                setTimeout(function () {
                    form.find('button[name = "dwfrm_crmsephoracard_confirm"]').click();

                    provider.setNextStep('checkLoginErrors', function () {
                        util.waitFor({
                            selector: 'input[name *= "dwfrm_login_password_"]',
                            success: function() {
                                // reactjs
                                provider.eval(
                                    "function triggerInput(selector, enteredValue) {\n" +
                                    "      let input = document.querySelector(selector);\n" +
                                    "      input.dispatchEvent(new Event('focus'));\n" +
                                    "      input.dispatchEvent(new KeyboardEvent('keypress',{'key':'a'}));\n" +
                                    "      let nativeInputValueSetter = Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype, 'value').set;\n" +
                                    "      nativeInputValueSetter.call(input, enteredValue);\n" +
                                    "      let inputEvent = new Event(\"input\", { bubbles: true });\n" +
                                    "      input.dispatchEvent(inputEvent);\n" +
                                    "}\n" +
                                    "triggerInput('input[name *= \"dwfrm_login_password_\"]', '" + params.account.password + "');"
                                );

                                $('button[name = "dwfrm_login_login"]').click();
                            },
                            fail: function() {
                                plugin.checkLoginErrors(params);
                            },
                            timeout: 5
                        });
                    });
                }, 1000);
                break;
            default:
                $('h2 button:contains("sign in")').get(0).click();
                setTimeout(function () {
                    form = $('form input[name=username]').closest('form');
                    if (form.length > 0) {
                        browserAPI.log("submitting saved credentials");
                        setTimeout(function () {
                            // reactjs
                            provider.eval(
                                "var FindReact = function (dom) {" +
                                "    for (var key in dom) if (0 == key.indexOf(\"__reactEventHandlers$\")) {" +
                                "        return dom[key];" +
                                "    }" +
                                "    return null;" +
                                "};" +
                                "FindReact(document.querySelector('input[name=username]')).onChange({target:{value:'" + params.account.login + "'}, preventDefault:function(){}});"
                                + "FindReact(document.querySelector('input[name=password]')).onChange({target:{value:'" + params.account.password + "'}, preventDefault:function(){}});"
                            );

                            provider.setNextStep('checkLoginErrors', function () {
                                setTimeout(function () {
                                    form.find('button[type="submit"]').click();
                                    setTimeout(function () {
                                        plugin.checkLoginErrors(params);
                                    }, 7000);
                                }, 1000);
                            });
                        }, 1000);
                    } else
                        provider.setError(util.errorMessages.loginFormNotFound);
                }, 1000);
                break;
        }
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        let counter = 0;
        let checkLoginErrorsEs = setInterval(function () {
            browserAPI.log("waiting... " + counter);
            let error = $('.u-textDanger[ng-show="!!errors"]');
            if (params.account.login2 === 'Italy' || params.account.login2 === 'Spain') {
                error = $('div.error-form:visible');
            }
            if (error.length > 0 && util.filter(error.text()) !== '') {
                clearInterval(checkLoginErrorsEs);
                provider.setError(util.filter(error.text()));
            }
            if (counter > 5 || plugin.isLoggedIn(params.account)) {
                clearInterval(checkLoginErrorsEs);
                plugin.loginComplete(params);
            }
            counter++;
        }, 300);
    },

    loginComplete: function (params) {
        browserAPI.log("loginComplete");
        provider.logBody("loginCompletePage");
        if (params.autologin) {
            provider.complete();
            return;
        }
        browserAPI.log("Loading account");
        var url;
        switch (params.account.login2) {
            case 'Italy':
                url = plugin.getStartingUrl(params);
                break;
            case 'Spain':
                url = plugin.getStartingUrl(params);
                break;
            default:
                url = plugin.getStartingUrl(params);
                break;
        }

        if (document.location.href !== url) {
            provider.setNextStep('parse', function () {
                document.location.href = url;
            });
            return;
        }
        plugin.parse(params);
    },

    getCookie: function (name) {
        var matches = document.cookie.match(new RegExp(
            "(?:^|; )" + name.replace(/([.$?*|{}()\[\]\\\/+^])/g, '\\$1') + "=([^;]*)"
        ));
        return matches ? decodeURIComponent(matches[1]) : undefined;
    },
    parseUSAStatus: function (status) {
        if (status === 'BI') {
            status = 'Insider';
        } else if (status === 'ROUGE') {
            status = 'Rouge';
        }
        return status;
    },

    parse: function (params) {
        browserAPI.log("parse");
        provider.updateAccountMessage();
        browserAPI.log('Current URL: ' + document.location.href);
        let userId = plugin.getCookie('DYN_USER_ID');

        if (!userId) {
            browserAPI.log('userId not found');
            return;
        }

        var data = {};
        plugin.ajaxRequest("https://www.sephora.com/api/users/profiles/current/full?includeTargeters=%2Fatg%2Fregistry%2FRepositoryTargeters%2FSephora%2FCCDynamicMessagingTargeter&includeApis=profile,basket,loves,shoppingList,targetersResult,targetedPromotion&cb=" + new Date().getTime(),
            'GET', null, false, function (response) {
                let name = util.beautifulName(response.profile.firstName + ' ' + response.profile.lastName);
                browserAPI.log("Name: " + name);
                data.Name = name;
            }, function (response) {
                browserAPI.log('Parse Props error: ' + response);
            });


        plugin.ajaxRequest('https://www.sephora.com/api/bi/profiles/' + userId + '/points?source=profile',
            'GET', null, false, function (response) {
                // Status - BI (INSIDER), VIB
                let status = plugin.parseUSAStatus(response.biStatus);
                browserAPI.log("Status: " + status);
                data.Status = status;
                // Balance - New Balance
                browserAPI.log("Balance: " + response.beautyBankPoints);
                data.Balance = util.trim(response.beautyBankPoints);
                // spend $... to reach VIB status.
                if (response.amountToNextSegment) {
                    browserAPI.log("ToNextLevel: " + response.amountToNextSegment);
                    data.ToNextLevel = '$' + util.trim(response.amountToNextSegment);
                }
                // Status valid until
                if (response.vibEndYear) {
                    browserAPI.log("StatusValidUntil: " + response.vibEndYear);
                    data.StatusValidUntil = '12/31/' + response.vibEndYear;
                }
                // Next elite level
                if (response.nextSegment) {
                    browserAPI.log("NextEliteLevel: " + response.nextSegment);
                    data.NextEliteLevel = plugin.parseUSAStatus(response.nextSegment);
                }
                // Miles/Points to retain status - Spend $350 to keep your VIB status through 2018.
                if (response.realTimeVIBMessages) {
                    response.realTimeVIBMessages.forEach(function (row) {
                        browserAPI.log("PointsRetainStatus: " + row);
                        let retainStatus = util.findRegExp(row, /Spend <span data-price>(.+?)<\/span> to keep your.+?status through/)
                        if (retainStatus)
                            data.PointsRetainStatus = retainStatus;
                    });
                }
            }, function (response) {
                browserAPI.log('Parse Props error: ' + response);
            });

        // save data
        params.data.properties = data;
        provider.saveTemp(params.data);


        params.account.properties = params.data.properties;
        // console.log(params.account.properties);// TODO
        provider.saveProperties(params.account.properties);
        provider.complete();

    },

    ajaxRequest: function (url = '', method = 'POST', data = null, withCredentials = true, callback, callbackError) {
        browserAPI.log('plugin.ajax');
        browserAPI.log(url + ' => ' + method + ' ' + (data ? JSON.stringify(data) : null));
        return $.ajax({
            url: url,
            type: method,
            beforeSend: function (request) {
                request.setRequestHeader('Accept', 'application/json, text/plain, */*');
                request.setRequestHeader('Cache-Control', 'no-cache');
                request.setRequestHeader('Pragma', 'no-cache');
                request.setRequestHeader('Content-Type', 'application/json;charset=UTF-8');
            },
            data: (data ? JSON.stringify(data) : null),
            crossDomain: true,
            dataType: 'json',
            cache: false,
            async: false,
            xhrFields: {
                withCredentials: withCredentials
            },
            success: function (response) {
                if (response) {
                    callback(response);
                } else {
                    browserAPI.log('ajax error, response null');
                    callbackError(response);
                }
            },
            error: function (xhr, status) {
                browserAPI.log('ajax error' + xhr.responseText);
                callbackError(xhr.responseText);
            }
        });
    },
};