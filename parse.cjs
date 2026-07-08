const fs = require('fs');
const html = fs.readFileSync('error.html', 'utf8');
const m = html.match(/"message":\s*"([^"\\]*(?:\\.[^"\\]*)*)"/);
if (m) {
    console.log("Error:", m[1]);
} else {
    console.log("Not found.");
}
