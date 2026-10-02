const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');

let registration;
const effects = [];
let stateIndex = 0;
const initialStates = ['', [{public_id:'01M3TWBF4969R7EYPHN9M9CYKF',service:{name:'Example'}}], false, '', '', null];
const wp = {
  blocks:{registerBlockType(name,config){registration={name,config};}},
  element:{createElement(type,props,...children){return {type,props:props||{},children};},
    useState(){return [initialStates[stateIndex++],()=>{}];},useEffect(callback){effects.push(callback);}},
  blockEditor:{useBlockProps(){return {};},},
  apiFetch(){return Promise.resolve({data:[]});}
};
vm.runInNewContext(fs.readFileSync(path.join(__dirname,'../build/editor.js'),'utf8'),{window:{wp},crypto:{randomUUID(){return 'uuid';}},setTimeout(){return 1;},clearTimeout(){},encodeURIComponent});
if (!registration || registration.name!=='sharetoku/referral-offer') throw Error('Block was not registered');
if (!registration.config.attributes.offerId || !registration.config.attributes.placementKey) throw Error('Attributes missing');
if (registration.config.save()!==null) throw Error('Block is not dynamic');

const updates = [];
const tree = registration.config.edit({attributes:{offerId:'',placementKey:''},setAttributes(value){updates.push(value);},clientId:'cid'});
effects[1]();
if (updates[0].placementKey!=='block-uuid') throw Error('Placement key was not persisted');
function nodes(node) { if (Array.isArray(node)) return node.flatMap(nodes); if (!node || typeof node!=='object') return []; return [node,...(node.children||[]).flatMap(nodes)]; }
const select = nodes(tree).find(node=>node.type==='button' && node.props['aria-pressed']===false);
if (!select) throw Error('Offer selection control missing');
select.props.onClick();
if (updates[1].offerId!=='01M3TWBF4969R7EYPHN9M9CYKF') throw Error('Offer ID was not persisted');
console.log('WordPress block checks passed');
