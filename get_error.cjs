const https = require('https');

https.get('https://api.bangkitakhtar.com/api/portfolio', (res) => {
  let data = '';
  res.on('data', chunk => data += chunk);
  res.on('end', () => {
    const match = data.match(/"message":\s*"([^"\\]*(?:\\.[^"\\]*)*)"/);
    if (match) {
        console.log("Found message:", match[1]);
    } else {
        console.log("Could not parse. Dumping 1000 chars of HTML:");
        console.log(data.substring(0, 1000));
    }
  });
}).on('error', err => console.error(err));
