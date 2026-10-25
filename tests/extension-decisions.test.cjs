const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const root = path.join(__dirname, '../extension-setup/extensions-version');
const variants = fs.readdirSync(root).filter(name => fs.statSync(path.join(root,name)).isDirectory());

async function renderVerdict(variant, status, hostname='example.com') {
  const nodes = new Map();
  class Element {
    constructor() { this.style={}; this.children=[]; this.id=''; }
    appendChild(child) { this.children.push(child); if(child.id)nodes.set(child.id,child); }
    remove() { nodes.delete(this.id); this.removed=true; }
    setAttribute() {}
    addEventListener() {}
  }
  const document = {getElementById:id=>nodes.get(id), createElement:()=>new Element(), documentElement:new Element()};
  let calls=0;
  const result={status,risk_level:status==='Block'?'High':status==='Suspicious'?'Medium':'Low',score:.9,reason:'Test'};
  const location={hostname,href:'https://'+hostname+'/',protocol:'https:'};
  const context=vm.createContext({document,location,window:{location},
    console:{log(){},warn(){}},setTimeout(){return 0;},
    chrome:{runtime:{getURL:name=>'chrome-extension://test/'+name,sendMessage:(message,callback)=>{calls++;callback({ok:true,json:result});},onMessage:{addListener(){}}},
      storage:{local:{get:(keys,callback)=>callback({}),set(){}}}},
  });
  vm.runInContext(fs.readFileSync(path.join(root,variant,'content.js'),'utf8'),context);
  await new Promise(resolve=>setImmediate(resolve));
  return {nodes,calls};
}

for(const variant of variants) {
  test(`${variant}: suspicious verdict warns without blocking`,async()=>{
    const {nodes}=await renderVerdict(variant,'Suspicious');
    assert.ok(nodes.has('phishblock-warning-v1'));
    assert.ok(!nodes.has('phishblock-overlay-v1'));
  });
  test(`${variant}: high-risk block retains blocking overlay`,async()=>{
    const {nodes}=await renderVerdict(variant,'Block');
    assert.equal(nodes.get('phishblock-overlay-v1').style.display,'flex');
    assert.ok(!nodes.has('phishblock-warning-v1'));
  });
  test(`${variant}: proceed has no warning or blocking overlay`,async()=>{
    const {nodes}=await renderVerdict(variant,'Proceed');
    assert.ok(!nodes.has('phishblock-warning-v1'));
    assert.ok(!nodes.has('phishblock-overlay-v1'));
  });
  test(`${variant}: hosted content and lookalike brand substrings are checked`,async()=>{
    for(const hostname of ['docs.google.com','google.attacker.example']) {
      const {calls}=await renderVerdict(variant,'Proceed',hostname);
      assert.equal(calls,1);
    }
  });
}
