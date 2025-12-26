const bridge = require('bridge');

async function main() {
    const page = await bridge.connectToPage()
    console.log('connected to page')
    page.on('console', async message => {
        console.log(message)
    })
    console.log('attached console')
    // let results = [];
    page.on('response', async response => {
        console.log('response received: ' + response.request().resourceType())
        console.log('response received: ' + JSON.stringify(response.headers()))
        console.log('response received: ' + await response.text())
        console.log('response received: ' + JSON.stringify({body: await response.text(), headers: response.headers()}))
        if (response.request().resourceType() === 'fetch') {
            bridge.exitWithResponse(JSON.stringify({body: await response.text(), headers: response.headers()}))
            // bridge.exitWithResponse(await response.text());
            // results.append({body: await response.text(), headers: response.headers()})
            // results.push({body: await response.text(), headers: response.headers()})
            console.log('response received: ' + JSON.stringify(results))
        }
    })
    // setTimeout(function() {
    //     bridge.exitWithResponse(JSON.stringify(results));
    // }, 3000)
    console.log('attached page events')
}

main()
