const bridge = require('bridge');

async function main() {
    const page = await bridge.connectToPage();
    console.log('connected to page')
    page.on('console', async message => {
        console.log(message)
    });
    console.log('attached console')
    page.on('response', async response => {
        /*console.log('response received: ' + response.request().resourceType())
        console.log('response received: ' + JSON.stringify(response.headers()))
        console.log('response received: ' + await response.text())
        console.log('response received: ' + JSON.stringify({body: await response.text(), headers: response.headers()}))
        if (response.request().resourceType() === 'fetch') {
            bridge.exitWithResponse(JSON.stringify({body: await response.text(), headers: response.headers()}))
            //console.log('response received: ' + JSON.stringify(results))
        }*/
        var request = response.request();
        console.log('response received: ' + request.resourceType() + ', url: ' + request.url());
        if (request.resourceType() === 'fetch' /*&& request.url().endsWith('/bff/auth/v1/user/auth/') && request.url().endsWith('/search')*/) {
            bridge.exitWithResponse(JSON.stringify({body: await response.text(), headers: response.headers()}))
        }
    });
    const element = await page.$('button[id = "primary-button"]');
    element.click();
    console.log('attached page events')
}

main();
