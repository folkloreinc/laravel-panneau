webpackJsonppanneau([128],{638:function(e,t,o){function r(e){return(r="function"===typeof Symbol&&"symbol"===typeof Symbol.iterator?function(e){return typeof e}:function(e){return e&&"function"===typeof Symbol&&e.constructor===Symbol&&e!==Symbol.prototype?"symbol":typeof e})(e)}var i,a;!function(n,l){"object"==r(t)&&"undefined"!=typeof e?e.exports=l():(i=l,void 0!==(a="function"===typeof i?i.call(t,o,t,e):i)&&(e.exports=a))}(0,function(){"use strict";return[{locale:"sh",pluralRuleFunction:function(e,t){var o=String(e).split("."),r=o[0],i=o[1]||"",a=!o[1],n=r.slice(-1),l=r.slice(-2),u=i.slice(-1),s=i.slice(-2);return t?"other":a&&1==n&&11!=l||1==u&&11!=s?"one":a&&2<=n&&n<=4&&(l<12||14<l)||2<=u&&u<=4&&(s<12||14<s)?"few":"other"},fields:{year:{displayName:"Year",relative:{0:"this year",1:"next year","-1":"last year"},relativeTime:{future:{other:"+{0} y"},past:{other:"-{0} y"}}},month:{displayName:"Month",relative:{0:"this month",1:"next month","-1":"last month"},relativeTime:{future:{other:"+{0} m"},past:{other:"-{0} m"}}},day:{displayName:"Day",relative:{0:"today",1:"tomorrow","-1":"yesterday"},relativeTime:{future:{other:"+{0} d"},past:{other:"-{0} d"}}},hour:{displayName:"Hour",relative:{0:"this hour"},relativeTime:{future:{other:"+{0} h"},past:{other:"-{0} h"}}},minute:{displayName:"Minute",relative:{0:"this minute"},relativeTime:{future:{other:"+{0} min"},past:{other:"-{0} min"}}},second:{displayName:"Second",relative:{0:"now"},relativeTime:{future:{other:"+{0} s"},past:{other:"-{0} s"}}}}}]})}});