(()=>{var P=Object.create;var m=Object.defineProperty;var S=Object.getOwnPropertyDescriptor;var W=Object.getOwnPropertyNames;var M=Object.getPrototypeOf,j=Object.prototype.hasOwnProperty;var v=(e,t)=>()=>(e&&(t=e(e=0)),t);var l=(e,t)=>()=>(t||e((t={exports:{}}).exports,t),t.exports);var K=(e,t,o,f)=>{if(t&&typeof t=="object"||typeof t=="function")for(let s of W(t))!j.call(e,s)&&s!==o&&m(e,s,{get:()=>t[s],enumerable:!(f=S(t,s))||f.enumerable});return e};var u=(e,t,o)=>(o=e!=null?P(M(e)):{},K(t||!e||!e.__esModule?m(o,"default",{value:e,enumerable:!0}):o,e));function r(e){return(...t)=>{if(window["@Neos:HostPluginAPI"]&&window["@Neos:HostPluginAPI"][`@${e}`])return window["@Neos:HostPluginAPI"][`@${e}`](...t);throw new Error("You are trying to read from a consumer api that hasn't been initialized yet!")}}var a=v(()=>{});var b=l((ae,y)=>{a();y.exports=r("vendor")().React});var A=l((ce,w)=>{a();w.exports=r("NeosProjectPackages")().ReactUiComponents});var x=l((ue,E)=>{a();E.exports=r("NeosProjectPackages")().NeosUiI18n});a();var g=r("manifest");var i=u(b()),n=u(A()),I=u(x());function R(e){let{label:t,className:o,identifier:f,options:s,renderHelpIcon:d,renderSecondaryInspector:N}=e,{src:c,icon:p,name:_,disabled:C}=s;return i.default.createElement("div",{style:{display:"flex"}},i.default.createElement(n.Label,{htmlFor:f},i.default.createElement(n.Button,{className:o,disabled:C,onClick:()=>{N("IFRAME",()=>i.default.createElement("iframe",{style:{height:"100%",width:"100%",border:0},name:_||"email-module",src:c.startsWith("ClientEval:")?(0,eval)(c.replace("ClientEval:","")):c}))},style:"lighter"},p&&i.default.createElement(n.Icon,{icon:p,padded:"right"}),i.default.createElement(I.default,{id:t}))),d&&d())}var O=R;g("Garagist.Mautic:EmailModuleEditor",{},e=>{e.get("inspector").get("editors").set("Garagist.Mautic/Inspector/Editors/EmailModule",{component:O,hasOwnLabel:!0})});})();
