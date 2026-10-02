const fs = require('node:fs');
const vm = require('node:vm');
const crypto = require('node:crypto');
const path = require('node:path');

const calls = [];
const handlers = {};
const container = {dataset:{sharetokuEndpoint:'https://api.example.com/api/v1/events/batch',sharetokuEventToken:'signed-token'}};
const card = {dataset:{sharetokuPlacement:'place',sharetokuSlot:'owner'},closest(selector){return selector==='.sharetoku-placement'?container:null;}};
const link = {closest(selector){return selector==='.sharetoku-card'?card:null;}};
const document = {body:{dataset:{}},addEventListener(name,fn){handlers[name]=fn;},querySelectorAll(){return [card];}};
const navigator = {sendBeacon(url, body){calls.push({url,body});return true;}};
const context = {document,navigator,crypto:crypto.webcrypto,location:{pathname:'/article'},Blob,BigInt,Set,Uint8Array,Array,Number,JSON,Date,fetch(){throw Error('unexpected fetch');}};
vm.runInNewContext(fs.readFileSync(path.join(__dirname,'../assets/sharetoku.js'),'utf8'),context);

async function main() {
  handlers.DOMContentLoaded();
  handlers.DOMContentLoaded();
  if (calls.length!==1) throw Error('Duplicate impression was sent');
  handlers.click({target:{closest(selector){return selector==='.sharetoku-link'?link:null;}}});
  if (calls.length!==2) throw Error('Click was not sent');
  const impression=JSON.parse(await calls[0].body.text());
  const click=JSON.parse(await calls[1].body.text());
  if (impression.events[0].type!=='impression'||click.events[0].type!=='click') throw Error('Wrong event types');
  if (impression.events[0].page_path!=='/article') throw Error('Page query was included');
  console.log('WordPress frontend checks passed');
}
main().catch((error)=>{console.error(error);process.exitCode=1;});
