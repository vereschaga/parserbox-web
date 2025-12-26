var plugin = {
    hideOnStart: true,
    clearCache: true,
    // keepTabOpen: true,//todo
    hosts: {
        'subcard.subway.co.uk': true,
        'www.mysubwaycard.com': true,
        '/\\w+\\.subway\\.com/': true,
        'subwayrewards.uk': true,
    },
    //alwaysSendLogs: true,//todo
    blockImages: /Chrome/.test(navigator.userAgent) && /Google Inc/.test(navigator.vendor),

    getStartingUrl: function (params) {
        switch (params.account.login2) {
            /*case 'UK':
                return 'https://subwayrewards.uk/login';
                break;
            case 'Germany':
                return 'https://subwayrewards.de/login';
                break;
            case 'Finland':
                return 'https://order.subway.com/en-UK/profile/rewards-activity';
                break;
            case 'USA':*/
            default:
                return 'https://www.subway.com/en-us/profile/rewards-activity';
                break;
        }// switch (params.account.login2)
    },

    getFocusTab: function (account, params) {
        return true;
    },

    loadLoginForm: function(params) {
        browserAPI.log("loadLoginForm");
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    start: function (params) {
        // IE not working properly
        if (!!navigator.userAgent.match(/Trident\/\d\./)) {
            provider.eval('jQuery.noConflict()');
        }
        browserAPI.log("start");
        browserAPI.log("UserAgent -> " + navigator.userAgent);
        browserAPI.log("UserAgent -> " + JSON.stringify(util.detectBrowser()));
        let counter = 0;
        let start = setInterval(function () {
            browserAPI.log("waiting... " + counter);
            let isLoggedIn = plugin.isLoggedIn(params);
            if (isLoggedIn !== null) {
                clearInterval(start);
                if (isLoggedIn) {
                    if (plugin.isSameAccount(params.account))
                        plugin.LoginComplete(params);
                    else
                        plugin.logout(params);
                }
                else
                    plugin.login(params);
            }// if (isLoggedIn !== null)
            if (isLoggedIn === null && counter > 20) {
                clearInterval(start);
                provider.logBody("lastPage");
                provider.setError(util.errorMessages.unknownLoginState);
                return;
            }// if (isLoggedIn === null && counter > 10)
            counter++;
        }, 1000);
    },

    regionSelected: function (params) {
        provider.setNextStep('start', function(){
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    isLoggedIn: function (params) {
        browserAPI.log("isLoggedIn");
        //input[@name="Email Address"] | //button[@aria-label="Signed In Open Profile"]
        if ($('input[name="Email Address"]').length)
            return false;
        if ($('button[aria-label="Signed In Open Profile"]').length)
            return true;
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        return false;
    },

    logout: function (params) {
        browserAPI.log("logout");
        provider.setNextStep('loadLoginForm', function () {
            document.location.href = 'https://www.subway.com/en-US/auth/logout';
        });
    },

    login: function (params) {
        browserAPI.log("login");
        var form;

        setTimeout(function () {
            const form = $('#localAccountForm');
            if (form.length > 0) {
                browserAPI.log("submitting saved credentials");
                form.find('input[name="Email Address"]').val(params.account.login);
                form.find('input[name="Password"]').val(params.account.password);

                provider.setNextStep('checkLoginErrors', function () {
                    $('button#next').click();
                    let counter = 0;
                    let login = setInterval(function () {
                        browserAPI.log("login waiting... " + counter);
                        let errors = $('div.error > p:visible:not(:empty)');
                        if (errors.length === 0)
                            errors = $('p.error_text:visible:not(:empty)');
                        if (errors.length === 0) {
                            errors = $('p.error-block:visible:not(:empty)');
                        }
                        if (
                            (errors.length > 0 && util.filter(errors.text()) !== '')
                            || ($('button:contains("SIGN IN")').length === 0 && counter > 5)
                        ) {
                            clearInterval(login);
                            plugin.checkLoginErrors(params);
                        }// if (errors.length > 0 && util.filter(errors.text()) !== '')
                        if (counter > 120) {
                            clearInterval(login);
                            provider.setError(util.errorMessages.captchaErrorMessage, true);
                        }// if (counter > 120)
                        counter++;
                    }, 500);

                });
            }
            else {
                provider.logBody("loginPage");
                provider.setError(util.errorMessages.loginFormNotFound);
            }
        }, 1000);
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        let errors;
        browserAPI.log("Region => " + params.account.login2);


        errors = $('div.error > p:visible:not(:empty)');
        if (errors.length === 0)
            errors = $('p.error_text:visible:not(:empty)');
        if (errors.length === 0) {
            errors = $('p.error-block:visible:not(:empty)');
        }
        // New Terms & Conditions.
        if ($('div#optInText:contains("I agree to these Terms & Conditions."):visible').length > 0) {
            provider.setError(["Subway website is asking you to update your profile, until you do so we would not be able to retrieve your account information.", util.errorCodes.providerError], true);
            return;
        }
        // Wait! We made some changes 'round here.
        if ($('div#pageTitle:contains("Wait! We made some changes"):visible').length > 0) {
            provider.setError(["Subway website is asking you to update your profile, until you do so we would not be able to retrieve your account information.", util.errorCodes.providerError], true);
            return;
        }



        browserAPI.log("Errors length => " + errors.length);

        if (errors && errors.length > 0 && util.filter(errors.text()) !== '') {
            if (errors.text().indexOf('We are sorry, your account could not be verified at this time') !== -1
                || errors.text().indexOf('Incorrect CAPTCHA response. Please try again') !== -1
                || errors.text().indexOf('Ooops. Something is not right') !== -1
            )
                provider.setError([util.filter(errors.text()), util.errorCodes.providerError], true);
            else
                provider.setError(util.filter(errors.text()), true);

            return;
        }

        plugin.LoginComplete(params);
    },

    LoginComplete: function (params) {
        browserAPI.log("loginComplete");
        if (params.autologin) {
            provider.complete();
            return;
        }
        if (params.account.login2 === 'USA' || params.account.login2 === '') {
            provider.setNextStep('loadAccount', function() {
                browserAPI.log("Redirect");
            });
            setTimeout(function() {
                browserAPI.log("Force redirect");
                plugin.loadAccount(params);
            }, 5000);
        }
        else
            plugin.loadAccount(params);
    },

    loadAccount: function (params) {
        browserAPI.log("loadAccount");
        browserAPI.log('Current URL: ' + document.location.href);
        browserAPI.log("Loading account");
        provider.logBody("loadAccountPage");


        let url = 'https://www.subway.com/en-us/profile/rewards-activity';
        if (!util.stristr(document.location.href, url)) {
            browserAPI.log('>> Opening Account page...');
            provider.setNextStep('preParse', function () {
                document.location.href = url;
            });
            return;
        }

        plugin.parse(params);
    },

    preParse: function(params) {
        browserAPI.log("preParse");
        let counter = 0;
        let preParse = setInterval(function () {
            browserAPI.log("preParse waiting... " + counter);
            if (
                $('h2.rewards-content--points').length
                || counter > 10
            ) {
                plugin.parse(params);
            }
            counter++;
        }, 500);
    },

    parse: function(params) {
        browserAPI.log("parse");
        provider.logBody("parsePage");
        provider.updateAccountMessage();
        browserAPI.log('Current URL: ' + document.location.href);
        browserAPI.log("Region => " + params.account.login2);

        switch (params.account.login2) {
            case 'UK':
            case 'Germany':
            case 'Finland':
                var data = {};
                // Balance - Points Balance
                var balance = $('a.green-btn-balance');
                if (balance.length > 0) {
                    browserAPI.log("Balance: " + balance.text());
                    data.Balance = util.trim(balance.text());
                }
                else
                    browserAPI.log("Balance is not found");
                // App - ...
                var cardNumber = $('span.acc-card:contains("App - "), span.acc-card:contains("Sovellus - ")');
                if (cardNumber.length > 0) {
                    data.Number = util.findRegExp(cardNumber.text(), /-\s*([^<]+)/ig);
                    browserAPI.log("Card number: " + data.Number);
                }
                else
                    browserAPI.log("Card number not found");
                // Name
                var name = $('div.acc-name > span');
                if (name.length > 0) {
                    data.Name = util.trim(name.text());
                    browserAPI.log("Name: " + data.Name);
                }
                else
                    browserAPI.log("Name not found");
                
                // Expiration Date
                var string = $('p:contains("use your card again before"), p:contains("use your card before")');
                if (string.length > 0) {
                    var expirationDate = util.findRegExp(string.text(), /use your card(?:\s*again|\s*) before\s+([\d-/]+)/);
                    var date;
                    if (/-/.test(expirationDate))
                        date = new Date(expirationDate.split('-').reverse().join('/') + ' UTC');
                    else
                        date = new Date(util.modifyDateFormat(expirationDate) + ' UTC');
                    if (!isNaN(date)) {
                        var unixtime = date.getTime() / 1000;
                        if (!isNaN(unixtime)) {
                            browserAPI.log("Expiration Date: " + date + " Unixtime: " + unixtime);
                            data.AccountExpirationDate = unixtime;
                        }
                    } else
                        browserAPI.log("Invalid Expiration Date");
                } else
                    browserAPI.log("Expiration Date not found");

                params.account.properties = data;
                // console.log(params.account.properties);
                provider.saveProperties(params.account.properties);
                provider.complete();
                break;
            case 'USA':
            default:
                browserAPI.log("Region => USA");
                let properties = {};
                name = util.trim($('input#dtmUserName').val());
                if (name !== '') {
                    name = util.beautifulName(name);
                    browserAPI.log("Name: " + name);
                    properties.Name = name;
                }
                else
                    browserAPI.log("Name not found");
                // Balance - Points
                balance = $('h2.rewards-content--points');

                if (balance.length) {
                    properties.Balance = util.findRegExp(balance.text(), /(.+)\s+Point/ig)
                    browserAPI.log("Balance: " + properties.Balance);
                }
                else
                    browserAPI.log("Balance not found");

                // Status
                let status = $('h2.rewards-content--recruit');

                if (status.length) {
                    properties.Status = status.text();
                    browserAPI.log("Status: " + properties.Status);
                }
                else
                    browserAPI.log("Status not found");

                // Spend until next Status
                let spendUntilNextStatus = $('h2:contains("to unlock")');

                if (spendUntilNextStatus.length) {
                    properties.SpendUntilNextStatus = util.findRegExp(spendUntilNextStatus.text(), /Spend (.+) to unlock/);
                    browserAPI.log("SpendUntilNextStatus: " + properties.SpendUntilNextStatus);
                }
                else
                    browserAPI.log("SpendUntilNextStatus not found");

                // Certificates
                const certificates = $('h1:contains("My Rewards") + div:eq(0) div.card__details');
                browserAPI.log('Total ' + certificates.length + ' rewards were found');
                let subAccounts = [];

                certificates.each(function () {
                    let displayName = util.filter($('h2[class *= "card__title"]', $(this)).text());
                    browserAPI.log('displayName: ' + displayName);
                    let exp = util.findRegExp($('p[class *= "card__description"]', $(this)).text(), /Expires\s*([^<]+)/);
                    browserAPI.log('[' + displayName + ']: ' + exp);

                    if (displayName && exp) {
                        subAccounts.push({
                            "Code"          : 'subwayUSA' + plugin.md5(displayName),
                            "DisplayName"   : displayName,
                            "Balance"       : null,
                            "ExpirationDate": new Date(exp) / 1000
                        });
                    }
                });

                /*
                $.ajax({
                    url: 'https://www.subway.com/RemoteOrder/Orders/GetLoyaltyData',
                    type: "POST",
                    data: '{"storeId":null,"siteName":"remoteorderen-US","loyaltyFlag":false}',
                    // xhr: plugin.getXMLHttp,
                    contentType: "application/json; charset=UTF-8",
                    beforeSend: function(request) {
                        request.setRequestHeader("Accept", 'application/json, text/javascript, *
                        / *');
                    },
                    dataType: "json",
                    async: false,
                    success: function (loyaltyData) {
                        loyaltyData = $(loyaltyData);
                        //console.log("---------------- data ----------------");
                        //console.log(loyaltyData);
                        //console.log("---------------- data ----------------");
                        if (typeof (loyaltyData[0].successResult.loyalty.summaries) !== 'undefined') {
                            var balance = null;
                            var certificate = null;
                            for (var summary in loyaltyData[0].successResult.loyalty.summaries) {
                                browserAPI.log("Try to find Balance/MyRewards");
                                browserAPI.log("summaries: " + JSON.stringify(loyaltyData[0].successResult.loyalty.summaries));
                                if (loyaltyData[0].successResult.loyalty.summaries.hasOwnProperty(summary)) {
                                    var rewardType = loyaltyData[0].successResult.loyalty.summaries[summary].rewardType;
                                    var available = loyaltyData[0].successResult.loyalty.summaries[summary].available;
                                    // Balance - Tokens
                                    if (rewardType == 'Points') {
                                        if (!balance)
                                            balance = parseInt(available);
                                        else
                                            balance += parseInt(available);
                                    }// if (rewardType == 'Points')
                                    // My Rewards
                                    if (rewardType == 'Certificate') {
                                        if (!certificate)
                                            certificate = parseInt(available);
                                        else
                                            certificate += parseInt(available);
                                    }// if (rewardType == 'Certificate')
                                }// if (loyaltyData[0].loyalty.summaries.hasOwnProperty(summary))
                            }// for (var summary in loyaltyData[0].loyalty.summaries)

                            if (balance !== null)
                                properties.Balance = '' + balance;
                            if (certificate !== null) {
                                browserAPI.log("MyRewards (certificate): " + certificate);
                                properties.MyRewards = "$" + certificate;
                            }
                            else if (certificate === null && typeof (loyaltyData[0].successResult.rewardsString) != 'undefined') {
                                properties.MyRewards = loyaltyData[0].successResult.rewardsString;
                                browserAPI.log("MyRewards: " + properties.MyRewards);
                            }

                            browserAPI.log("Balance: " + properties.Balance);
                            if (typeof (properties.Balance) == 'undefined'
                                && loyaltyData[0].successResult.loyalty.summaries.length == 0) {
                                properties.Balance = '0';
                                browserAPI.log("Balance: " + properties.Balance);
                                browserAPI.log("properties: " + JSON.stringify(properties));
                            }
                            // Name
                            if (
                                typeof (properties.Name) == 'undefined'
                                && typeof (loyaltyData[0].successResult.name) != 'undefined'
                            ) {
                                name = util.beautifulName(loyaltyData[0].successResult.name);
                                browserAPI.log("Name: " + name);
                                properties.Name = name;
                            }

                            // LastActivity and ExpirationDate

                            browserAPI.log('getLastActivityUSA');
                            // Expiration Date refs #18787
                            var lastActivity = plugin.getLastActivityUSA(params);
                            var lastActivityDate = new Date(lastActivity + ' UTC');
                            if (lastActivityDate.getTime()) {
                                properties.LastActivity = lastActivity;
                                browserAPI.log("LastActivity: " + properties.LastActivity);
                                var expDate = lastActivityDate;
                                expDate.setFullYear(expDate.getFullYear() + 1);
                                browserAPI.log("AccountExpirationDate: " + expDate + ', unixtime: ' + (expDate.getTime() / 1000));
                                properties.AccountExpirationDate = expDate.getTime() / 1000;
                                properties.AccountExpirationWarning = 'Subway state the following on their website: <a href="https://www.subway.com/en-US/Legal/mywayrewardstermsofuse">Any Tokens you may have earned that are below the two hundred (200) count threshold and have not been converted into a Reward will expire after twelve (12) months of inactivity, depending on your last “activity” date (“activity” is defined as any positive change to your member account balance, the earning of any Token, or any Reward redemption).</a><br><br> We determined that last time you had account activity with Subway on ' + lastActivity + ', so the expiration date was calculated by adding 12 months to this date.';
                            } else {
                                browserAPI.log("LastActivity not found");
                            }


                        }// if (typeof (loyaltyData[0].loyalty.summaries[0].available) !== 'undefined')
                        else
                            browserAPI.log("Balance/MyRewards not found");
                        // Certificates
                        if (typeof (loyaltyData[0].successResult.certificates.certificatesList) !== 'undefined') {
                            for (var certificate in loyaltyData[0].successResult.certificates.certificatesList) {
                                if (loyaltyData[0].successResult.certificates.certificatesList.hasOwnProperty(certificate)) {
                                    var balance = loyaltyData[0].successResult.certificates.certificatesList[certificate].amount;
                                    var code = loyaltyData[0].successResult.certificates.certificatesList[certificate].serialNumber;
                                    // Expiration
                                    var unixtime = new Date(loyaltyData[0].successResult.certificates.certificatesList[certificate].expirationDate) / 1000;
                                    if (!isNaN(unixtime) && balance > 0) {
                                        subAccounts.push({
                                            "Code": 'subwayUSA' + code,
                                            "DisplayName": "Reward",
                                            "Balance": balance,
                                            "ExpirationDate": unixtime
                                        });
                                    } else if (balance > 0) {
                                        subAccounts.push({
                                            "Code": 'subwayUSA' + code,
                                            "DisplayName": "Reward",
                                            "Balance": balance
                                        });
                                    }
                                    console.log(subAccounts);
                                }// if (loyaltyData[0].loyalty.summaries.hasOwnProperty(summary))
                            }// for (var summary in loyaltyData[0].loyalty.summaries)
                        }// if (typeof (loyaltyData[0].loyalty.summaries[0].available) !== 'undefined')
                        else
                            browserAPI.log("Certificates not found");
                    },// success: function (loyaltyData)
                    error: function (data) {
                        browserAPI.log("fail: isSameAccount");
                        browserAPI.log("---------------- fail data ----------------");
                        browserAPI.log(JSON.stringify(data));
                        browserAPI.log("---------------- fail data ----------------");

                        if (data.responseText === '{"Error":{"Title":null,"Message":null,"ErrorCode":null}}'
                            && $('p:contains("An error has occurred. Please retry your request."):visible').length > 0
                        ) {
                            balance = $('div.token-value:visible');
                            if (balance.length > 0) {
                                properties.Balance = util.findRegExp(balance.text(), /^(\d+)\//);
                                browserAPI.log("Balance: " + properties.Balance);
                            }
                        }
                    }
                });// $.ajax({
                */

                params.account.properties = properties;
                params.account.properties.SubAccounts = subAccounts;
                params.account.properties.CombineSubAccounts = 'false';
                // console.log(params.account.properties);
                provider.saveProperties(params.account.properties);
                provider.complete();

                break;
        }// switch (params.account.login2)
    },

    getLastActivityUSA: function () {
        browserAPI.log('getLastActivityUSA');
        var res = null;
        /*$.ajax({
            async: false,
            url: 'https://www.subway.com/RemoteOrder/Rewards/RewardsHistory',
            type: "POST",
            dataType: "html",
            success: function (data) {
                data = $(data);
                browserAPI.log(data.find('#rewardsActivity').html());
                data.find('#rewardsActivity tr.reward').each(function(){
                    var type = util.trim($(this).find('td.type'));
                    if (['Earned', 'Used', 'Earned (-200 Tokens)'].indexOf(type) !== -1) {
                        var date = util.trim($(this).find('td.date'));
                        res = util.findRegExp(date, /^(.+?\/\d{4})$/);
                        return false;
                    }
                });
            },
        });*/
        $('#rewardsActivity tr.reward').each(function(){
            var type = util.trim($(this).find('td.type').text());
            if (['Earned', 'Used', 'Earned (-200 Tokens)'].indexOf(type) !== -1) {
                var date = util.trim($(this).find('td.date').text());
                res = util.findRegExp(date, /^(.+?\/\d{4})$/);
                return false;
            }
        });
        return res;
    },

    // for Firefox, refs #19191, #note-24
    getXMLHttp: function () {
        if (typeof content !== 'undefined' && content && content.XMLHttpRequest) {
            return new content.XMLHttpRequest();
        }
        return new XMLHttpRequest();
    },

    md5: function(d){function M(d){for(var _,m="0123456789ABCDEF",f="",r=0;r<d.length;r++)_=d.charCodeAt(r),f+=m.charAt(_>>>4&15)+m.charAt(15&_);return f}function X(d){for(var _=Array(d.length>>2),m=0;m<_.length;m++)_[m]=0;for(m=0;m<8*d.length;m+=8)_[m>>5]|=(255&d.charCodeAt(m/8))<<m%32;return _}function V(d){for(var _="",m=0;m<32*d.length;m+=8)_+=String.fromCharCode(d[m>>5]>>>m%32&255);return _}function Y(d,_){d[_>>5]|=128<<_%32,d[14+(_+64>>>9<<4)]=_;for(var m=1732584193,f=-271733879,r=-1732584194,i=271733878,n=0;n<d.length;n+=16){var h=m,t=f,g=r,e=i;f=md5_ii(f=md5_ii(f=md5_ii(f=md5_ii(f=md5_hh(f=md5_hh(f=md5_hh(f=md5_hh(f=md5_gg(f=md5_gg(f=md5_gg(f=md5_gg(f=md5_ff(f=md5_ff(f=md5_ff(f=md5_ff(f,r=md5_ff(r,i=md5_ff(i,m=md5_ff(m,f,r,i,d[n+0],7,-680876936),f,r,d[n+1],12,-389564586),m,f,d[n+2],17,606105819),i,m,d[n+3],22,-1044525330),r=md5_ff(r,i=md5_ff(i,m=md5_ff(m,f,r,i,d[n+4],7,-176418897),f,r,d[n+5],12,1200080426),m,f,d[n+6],17,-1473231341),i,m,d[n+7],22,-45705983),r=md5_ff(r,i=md5_ff(i,m=md5_ff(m,f,r,i,d[n+8],7,1770035416),f,r,d[n+9],12,-1958414417),m,f,d[n+10],17,-42063),i,m,d[n+11],22,-1990404162),r=md5_ff(r,i=md5_ff(i,m=md5_ff(m,f,r,i,d[n+12],7,1804603682),f,r,d[n+13],12,-40341101),m,f,d[n+14],17,-1502002290),i,m,d[n+15],22,1236535329),r=md5_gg(r,i=md5_gg(i,m=md5_gg(m,f,r,i,d[n+1],5,-165796510),f,r,d[n+6],9,-1069501632),m,f,d[n+11],14,643717713),i,m,d[n+0],20,-373897302),r=md5_gg(r,i=md5_gg(i,m=md5_gg(m,f,r,i,d[n+5],5,-701558691),f,r,d[n+10],9,38016083),m,f,d[n+15],14,-660478335),i,m,d[n+4],20,-405537848),r=md5_gg(r,i=md5_gg(i,m=md5_gg(m,f,r,i,d[n+9],5,568446438),f,r,d[n+14],9,-1019803690),m,f,d[n+3],14,-187363961),i,m,d[n+8],20,1163531501),r=md5_gg(r,i=md5_gg(i,m=md5_gg(m,f,r,i,d[n+13],5,-1444681467),f,r,d[n+2],9,-51403784),m,f,d[n+7],14,1735328473),i,m,d[n+12],20,-1926607734),r=md5_hh(r,i=md5_hh(i,m=md5_hh(m,f,r,i,d[n+5],4,-378558),f,r,d[n+8],11,-2022574463),m,f,d[n+11],16,1839030562),i,m,d[n+14],23,-35309556),r=md5_hh(r,i=md5_hh(i,m=md5_hh(m,f,r,i,d[n+1],4,-1530992060),f,r,d[n+4],11,1272893353),m,f,d[n+7],16,-155497632),i,m,d[n+10],23,-1094730640),r=md5_hh(r,i=md5_hh(i,m=md5_hh(m,f,r,i,d[n+13],4,681279174),f,r,d[n+0],11,-358537222),m,f,d[n+3],16,-722521979),i,m,d[n+6],23,76029189),r=md5_hh(r,i=md5_hh(i,m=md5_hh(m,f,r,i,d[n+9],4,-640364487),f,r,d[n+12],11,-421815835),m,f,d[n+15],16,530742520),i,m,d[n+2],23,-995338651),r=md5_ii(r,i=md5_ii(i,m=md5_ii(m,f,r,i,d[n+0],6,-198630844),f,r,d[n+7],10,1126891415),m,f,d[n+14],15,-1416354905),i,m,d[n+5],21,-57434055),r=md5_ii(r,i=md5_ii(i,m=md5_ii(m,f,r,i,d[n+12],6,1700485571),f,r,d[n+3],10,-1894986606),m,f,d[n+10],15,-1051523),i,m,d[n+1],21,-2054922799),r=md5_ii(r,i=md5_ii(i,m=md5_ii(m,f,r,i,d[n+8],6,1873313359),f,r,d[n+15],10,-30611744),m,f,d[n+6],15,-1560198380),i,m,d[n+13],21,1309151649),r=md5_ii(r,i=md5_ii(i,m=md5_ii(m,f,r,i,d[n+4],6,-145523070),f,r,d[n+11],10,-1120210379),m,f,d[n+2],15,718787259),i,m,d[n+9],21,-343485551),m=safe_add(m,h),f=safe_add(f,t),r=safe_add(r,g),i=safe_add(i,e)}return Array(m,f,r,i)}function md5_cmn(d,_,m,f,r,i){return safe_add(bit_rol(safe_add(safe_add(_,d),safe_add(f,i)),r),m)}function md5_ff(d,_,m,f,r,i,n){return md5_cmn(_&m|~_&f,d,_,r,i,n)}function md5_gg(d,_,m,f,r,i,n){return md5_cmn(_&f|m&~f,d,_,r,i,n)}function md5_hh(d,_,m,f,r,i,n){return md5_cmn(_^m^f,d,_,r,i,n)}function md5_ii(d,_,m,f,r,i,n){return md5_cmn(m^(_|~f),d,_,r,i,n)}function safe_add(d,_){var m=(65535&d)+(65535&_);return(d>>16)+(_>>16)+(m>>16)<<16|65535&m}function bit_rol(d,_){return d<<_|d>>>32-_};var r = M(V(Y(X(d),8*d.length)));return r.toLowerCase()},

};