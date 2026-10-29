'use strict';

const { execFileSync } = require('node:child_process');
const path = require('node:path');
const vm = require('node:vm');

// Parse the rendered scripts and inline handlers without executing browser
// actions, requests, timers, or any live bot code. No npm dependencies needed.
const output = execFileSync(process.env.PHP_BINARY || 'php', [
    '-n', path.join(__dirname, 'webapi-template.php'), '--scripts',
], { encoding: 'utf8' });
const scripts = JSON.parse(output);
if (scripts.length !== 54) {
    throw new Error(`Expected 54 scripts/handlers across six variants; got ${scripts.length}`);
}
scripts.forEach((script, index) => new vm.Script(script, { filename: `webapi-${index}.js` }));
console.log(`PASS: ${scripts.length} rendered JavaScript blocks and inline handlers parse`);
