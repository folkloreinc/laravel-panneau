webpackJsonppanneau([303],{463:function(e,a,o){function r(e){return(r="function"===typeof Symbol&&"symbol"===typeof Symbol.iterator?function(e){return typeof e}:function(e){return e&&"function"===typeof Symbol&&e.constructor===Symbol&&e!==Symbol.prototype?"symbol":typeof e})(e)}var t,l;!function(n,c){"object"==r(a)&&"undefined"!=typeof e?e.exports=c():(t=c,void 0!==(l="function"===typeof t?t.call(a,o,a,e):t)&&(e.exports=l))}(0,function(){"use strict";return[{locale:"ar",pluralRuleFunction:function(e,a){var o=String(e).split("."),r=Number(o[0])==e&&o[0].slice(-2);return a?"other":0==e?"zero":1==e?"one":2==e?"two":3<=r&&r<=10?"few":11<=r&&r<=99?"many":"other"},fields:{year:{displayName:"\u0627\u0644\u0633\u0646\u0629",relative:{0:"\u0627\u0644\u0633\u0646\u0629 \u0627\u0644\u062d\u0627\u0644\u064a\u0629",1:"\u0627\u0644\u0633\u0646\u0629 \u0627\u0644\u0642\u0627\u062f\u0645\u0629","-1":"\u0627\u0644\u0633\u0646\u0629 \u0627\u0644\u0645\u0627\u0636\u064a\u0629"},relativeTime:{future:{zero:"\u062e\u0644\u0627\u0644 {0} \u0633\u0646\u0629",one:"\u062e\u0644\u0627\u0644 \u0633\u0646\u0629 \u0648\u0627\u062d\u062f\u0629",two:"\u062e\u0644\u0627\u0644 \u0633\u0646\u062a\u064a\u0646",few:"\u062e\u0644\u0627\u0644 {0} \u0633\u0646\u0648\u0627\u062a",many:"\u062e\u0644\u0627\u0644 {0} \u0633\u0646\u0629",other:"\u062e\u0644\u0627\u0644 {0} \u0633\u0646\u0629"},past:{zero:"\u0642\u0628\u0644 {0} \u0633\u0646\u0629",one:"\u0642\u0628\u0644 \u0633\u0646\u0629 \u0648\u0627\u062d\u062f\u0629",two:"\u0642\u0628\u0644 \u0633\u0646\u062a\u064a\u0646",few:"\u0642\u0628\u0644 {0} \u0633\u0646\u0648\u0627\u062a",many:"\u0642\u0628\u0644 {0} \u0633\u0646\u0629",other:"\u0642\u0628\u0644 {0} \u0633\u0646\u0629"}}},month:{displayName:"\u0627\u0644\u0634\u0647\u0631",relative:{0:"\u0647\u0630\u0627 \u0627\u0644\u0634\u0647\u0631",1:"\u0627\u0644\u0634\u0647\u0631 \u0627\u0644\u0642\u0627\u062f\u0645","-1":"\u0627\u0644\u0634\u0647\u0631 \u0627\u0644\u0645\u0627\u0636\u064a"},relativeTime:{future:{zero:"\u062e\u0644\u0627\u0644 {0} \u0634\u0647\u0631",one:"\u062e\u0644\u0627\u0644 \u0634\u0647\u0631 \u0648\u0627\u062d\u062f",two:"\u062e\u0644\u0627\u0644 \u0634\u0647\u0631\u064a\u0646",few:"\u062e\u0644\u0627\u0644 {0} \u0623\u0634\u0647\u0631",many:"\u062e\u0644\u0627\u0644 {0} \u0634\u0647\u0631\u064b\u0627",other:"\u062e\u0644\u0627\u0644 {0} \u0634\u0647\u0631"},past:{zero:"\u0642\u0628\u0644 {0} \u0634\u0647\u0631",one:"\u0642\u0628\u0644 \u0634\u0647\u0631 \u0648\u0627\u062d\u062f",two:"\u0642\u0628\u0644 \u0634\u0647\u0631\u064a\u0646",few:"\u0642\u0628\u0644 {0} \u0623\u0634\u0647\u0631",many:"\u0642\u0628\u0644 {0} \u0634\u0647\u0631\u064b\u0627",other:"\u0642\u0628\u0644 {0} \u0634\u0647\u0631"}}},day:{displayName:"\u064a\u0648\u0645",relative:{0:"\u0627\u0644\u064a\u0648\u0645",1:"\u063a\u062f\u064b\u0627",2:"\u0628\u0639\u062f \u0627\u0644\u063a\u062f","-2":"\u0623\u0648\u0644 \u0623\u0645\u0633","-1":"\u0623\u0645\u0633"},relativeTime:{future:{zero:"\u062e\u0644\u0627\u0644 {0} \u064a\u0648\u0645",one:"\u062e\u0644\u0627\u0644 \u064a\u0648\u0645 \u0648\u0627\u062d\u062f",two:"\u062e\u0644\u0627\u0644 \u064a\u0648\u0645\u064a\u0646",few:"\u062e\u0644\u0627\u0644 {0} \u0623\u064a\u0627\u0645",many:"\u062e\u0644\u0627\u0644 {0} \u064a\u0648\u0645\u064b\u0627",other:"\u062e\u0644\u0627\u0644 {0} \u064a\u0648\u0645"},past:{zero:"\u0642\u0628\u0644 {0} \u064a\u0648\u0645",one:"\u0642\u0628\u0644 \u064a\u0648\u0645 \u0648\u0627\u062d\u062f",two:"\u0642\u0628\u0644 \u064a\u0648\u0645\u064a\u0646",few:"\u0642\u0628\u0644 {0} \u0623\u064a\u0627\u0645",many:"\u0642\u0628\u0644 {0} \u064a\u0648\u0645\u064b\u0627",other:"\u0642\u0628\u0644 {0} \u064a\u0648\u0645"}}},hour:{displayName:"\u0627\u0644\u0633\u0627\u0639\u0627\u062a",relative:{0:"\u0627\u0644\u0633\u0627\u0639\u0629 \u0627\u0644\u062d\u0627\u0644\u064a\u0629"},relativeTime:{future:{zero:"\u062e\u0644\u0627\u0644 {0} \u0633\u0627\u0639\u0629",one:"\u062e\u0644\u0627\u0644 \u0633\u0627\u0639\u0629 \u0648\u0627\u062d\u062f\u0629",two:"\u062e\u0644\u0627\u0644 \u0633\u0627\u0639\u062a\u064a\u0646",few:"\u062e\u0644\u0627\u0644 {0} \u0633\u0627\u0639\u0627\u062a",many:"\u062e\u0644\u0627\u0644 {0} \u0633\u0627\u0639\u0629",other:"\u062e\u0644\u0627\u0644 {0} \u0633\u0627\u0639\u0629"},past:{zero:"\u0642\u0628\u0644 {0} \u0633\u0627\u0639\u0629",one:"\u0642\u0628\u0644 \u0633\u0627\u0639\u0629 \u0648\u0627\u062d\u062f\u0629",two:"\u0642\u0628\u0644 \u0633\u0627\u0639\u062a\u064a\u0646",few:"\u0642\u0628\u0644 {0} \u0633\u0627\u0639\u0627\u062a",many:"\u0642\u0628\u0644 {0} \u0633\u0627\u0639\u0629",other:"\u0642\u0628\u0644 {0} \u0633\u0627\u0639\u0629"}}},minute:{displayName:"\u0627\u0644\u062f\u0642\u0627\u0626\u0642",relative:{0:"\u0647\u0630\u0647 \u0627\u0644\u062f\u0642\u064a\u0642\u0629"},relativeTime:{future:{zero:"\u062e\u0644\u0627\u0644 {0} \u062f\u0642\u064a\u0642\u0629",one:"\u062e\u0644\u0627\u0644 \u062f\u0642\u064a\u0642\u0629 \u0648\u0627\u062d\u062f\u0629",two:"\u062e\u0644\u0627\u0644 \u062f\u0642\u064a\u0642\u062a\u064a\u0646",few:"\u062e\u0644\u0627\u0644 {0} \u062f\u0642\u0627\u0626\u0642",many:"\u062e\u0644\u0627\u0644 {0} \u062f\u0642\u064a\u0642\u0629",other:"\u062e\u0644\u0627\u0644 {0} \u062f\u0642\u064a\u0642\u0629"},past:{zero:"\u0642\u0628\u0644 {0} \u062f\u0642\u064a\u0642\u0629",one:"\u0642\u0628\u0644 \u062f\u0642\u064a\u0642\u0629 \u0648\u0627\u062d\u062f\u0629",two:"\u0642\u0628\u0644 \u062f\u0642\u064a\u0642\u062a\u064a\u0646",few:"\u0642\u0628\u0644 {0} \u062f\u0642\u0627\u0626\u0642",many:"\u0642\u0628\u0644 {0} \u062f\u0642\u064a\u0642\u0629",other:"\u0642\u0628\u0644 {0} \u062f\u0642\u064a\u0642\u0629"}}},second:{displayName:"\u0627\u0644\u062b\u0648\u0627\u0646\u064a",relative:{0:"\u0627\u0644\u0622\u0646"},relativeTime:{future:{zero:"\u062e\u0644\u0627\u0644 {0} \u062b\u0627\u0646\u064a\u0629",one:"\u062e\u0644\u0627\u0644 \u062b\u0627\u0646\u064a\u0629 \u0648\u0627\u062d\u062f\u0629",two:"\u062e\u0644\u0627\u0644 \u062b\u0627\u0646\u064a\u062a\u064a\u0646",few:"\u062e\u0644\u0627\u0644 {0} \u062b\u0648\u0627\u0646\u064d",many:"\u062e\u0644\u0627\u0644 {0} \u062b\u0627\u0646\u064a\u0629",other:"\u062e\u0644\u0627\u0644 {0} \u062b\u0627\u0646\u064a\u0629"},past:{zero:"\u0642\u0628\u0644 {0} \u062b\u0627\u0646\u064a\u0629",one:"\u0642\u0628\u0644 \u062b\u0627\u0646\u064a\u0629 \u0648\u0627\u062d\u062f\u0629",two:"\u0642\u0628\u0644 \u062b\u0627\u0646\u064a\u062a\u064a\u0646",few:"\u0642\u0628\u0644 {0} \u062b\u0648\u0627\u0646\u0650",many:"\u0642\u0628\u0644 {0} \u062b\u0627\u0646\u064a\u0629",other:"\u0642\u0628\u0644 {0} \u062b\u0627\u0646\u064a\u0629"}}}}},{locale:"ar-AE",parentLocale:"ar",fields:{year:{displayName:"\u0627\u0644\u0633\u0646\u0629",relative:{0:"\u0647\u0630\u0647 \u0627\u0644\u0633\u0646\u0629",1:"\u0627\u0644\u0633\u0646\u0629 \u0627\u0644\u062a\u0627\u0644\u064a\u0629","-1":"\u0627\u0644\u0633\u0646\u0629 \u0627\u0644\u0645\u0627\u0636\u064a\u0629"},relativeTime:{future:{zero:"\u062e\u0644\u0627\u0644 {0} \u0633\u0646\u0629",one:"\u062e\u0644\u0627\u0644 \u0633\u0646\u0629 \u0648\u0627\u062d\u062f\u0629",two:"\u062e\u0644\u0627\u0644 \u0633\u0646\u062a\u064a\u0646",few:"\u062e\u0644\u0627\u0644 {0} \u0633\u0646\u0648\u0627\u062a",many:"\u062e\u0644\u0627\u0644 {0} \u0633\u0646\u0629",other:"\u062e\u0644\u0627\u0644 {0} \u0633\u0646\u0629"},past:{zero:"\u0642\u0628\u0644 {0} \u0633\u0646\u0629",one:"\u0642\u0628\u0644 \u0633\u0646\u0629 \u0648\u0627\u062d\u062f\u0629",two:"\u0642\u0628\u0644 \u0633\u0646\u062a\u064a\u0646",few:"\u0642\u0628\u0644 {0} \u0633\u0646\u0648\u0627\u062a",many:"\u0642\u0628\u0644 {0} \u0633\u0646\u0629",other:"\u0642\u0628\u0644 {0} \u0633\u0646\u0629"}}},month:{displayName:"\u0627\u0644\u0634\u0647\u0631",relative:{0:"\u0647\u0630\u0627 \u0627\u0644\u0634\u0647\u0631",1:"\u0627\u0644\u0634\u0647\u0631 \u0627\u0644\u0642\u0627\u062f\u0645","-1":"\u0627\u0644\u0634\u0647\u0631 \u0627\u0644\u0645\u0627\u0636\u064a"},relativeTime:{future:{zero:"\u062e\u0644\u0627\u0644 {0} \u0634\u0647\u0631",one:"\u062e\u0644\u0627\u0644 \u0634\u0647\u0631 \u0648\u0627\u062d\u062f",two:"\u062e\u0644\u0627\u0644 \u0634\u0647\u0631\u064a\u0646",few:"\u062e\u0644\u0627\u0644 {0} \u0623\u0634\u0647\u0631",many:"\u062e\u0644\u0627\u0644 {0} \u0634\u0647\u0631\u064b\u0627",other:"\u062e\u0644\u0627\u0644 {0} \u0634\u0647\u0631"},past:{zero:"\u0642\u0628\u0644 {0} \u0634\u0647\u0631",one:"\u0642\u0628\u0644 \u0634\u0647\u0631 \u0648\u0627\u062d\u062f",two:"\u0642\u0628\u0644 \u0634\u0647\u0631\u064a\u0646",few:"\u0642\u0628\u0644 {0} \u0623\u0634\u0647\u0631",many:"\u0642\u0628\u0644 {0} \u0634\u0647\u0631\u064b\u0627",other:"\u0642\u0628\u0644 {0} \u0634\u0647\u0631"}}},day:{displayName:"\u064a\u0648\u0645",relative:{0:"\u0627\u0644\u064a\u0648\u0645",1:"\u063a\u062f\u064b\u0627",2:"\u0628\u0639\u062f \u0627\u0644\u063a\u062f","-2":"\u0623\u0648\u0644 \u0623\u0645\u0633","-1":"\u0623\u0645\u0633"},relativeTime:{future:{zero:"\u062e\u0644\u0627\u0644 {0} \u064a\u0648\u0645",one:"\u062e\u0644\u0627\u0644 \u064a\u0648\u0645 \u0648\u0627\u062d\u062f",two:"\u062e\u0644\u0627\u0644 \u064a\u0648\u0645\u064a\u0646",few:"\u062e\u0644\u0627\u0644 {0} \u0623\u064a\u0627\u0645",many:"\u062e\u0644\u0627\u0644 {0} \u064a\u0648\u0645\u064b\u0627",other:"\u062e\u0644\u0627\u0644 {0} \u064a\u0648\u0645"},past:{zero:"\u0642\u0628\u0644 {0} \u064a\u0648\u0645",one:"\u0642\u0628\u0644 \u064a\u0648\u0645 \u0648\u0627\u062d\u062f",two:"\u0642\u0628\u0644 \u064a\u0648\u0645\u064a\u0646",few:"\u0642\u0628\u0644 {0} \u0623\u064a\u0627\u0645",many:"\u0642\u0628\u0644 {0} \u064a\u0648\u0645\u064b\u0627",other:"\u0642\u0628\u0644 {0} \u064a\u0648\u0645"}}},hour:{displayName:"\u0627\u0644\u0633\u0627\u0639\u0627\u062a",relative:{0:"\u0627\u0644\u0633\u0627\u0639\u0629 \u0627\u0644\u062d\u0627\u0644\u064a\u0629"},relativeTime:{future:{zero:"\u062e\u0644\u0627\u0644 {0} \u0633\u0627\u0639\u0629",one:"\u062e\u0644\u0627\u0644 \u0633\u0627\u0639\u0629 \u0648\u0627\u062d\u062f\u0629",two:"\u062e\u0644\u0627\u0644 \u0633\u0627\u0639\u062a\u064a\u0646",few:"\u062e\u0644\u0627\u0644 {0} \u0633\u0627\u0639\u0627\u062a",many:"\u062e\u0644\u0627\u0644 {0} \u0633\u0627\u0639\u0629",other:"\u062e\u0644\u0627\u0644 {0} \u0633\u0627\u0639\u0629"},past:{zero:"\u0642\u0628\u0644 {0} \u0633\u0627\u0639\u0629",one:"\u0642\u0628\u0644 \u0633\u0627\u0639\u0629 \u0648\u0627\u062d\u062f\u0629",two:"\u0642\u0628\u0644 \u0633\u0627\u0639\u062a\u064a\u0646",few:"\u0642\u0628\u0644 {0} \u0633\u0627\u0639\u0627\u062a",many:"\u0642\u0628\u0644 {0} \u0633\u0627\u0639\u0629",other:"\u0642\u0628\u0644 {0} \u0633\u0627\u0639\u0629"}}},minute:{displayName:"\u0627\u0644\u062f\u0642\u0627\u0626\u0642",relative:{0:"\u0647\u0630\u0647 \u0627\u0644\u062f\u0642\u064a\u0642\u0629"},relativeTime:{future:{zero:"\u062e\u0644\u0627\u0644 {0} \u062f\u0642\u064a\u0642\u0629",one:"\u062e\u0644\u0627\u0644 \u062f\u0642\u064a\u0642\u0629 \u0648\u0627\u062d\u062f\u0629",two:"\u062e\u0644\u0627\u0644 \u062f\u0642\u064a\u0642\u062a\u064a\u0646",few:"\u062e\u0644\u0627\u0644 {0} \u062f\u0642\u0627\u0626\u0642",many:"\u062e\u0644\u0627\u0644 {0} \u062f\u0642\u064a\u0642\u0629",other:"\u062e\u0644\u0627\u0644 {0} \u062f\u0642\u064a\u0642\u0629"},past:{zero:"\u0642\u0628\u0644 {0} \u062f\u0642\u064a\u0642\u0629",one:"\u0642\u0628\u0644 \u062f\u0642\u064a\u0642\u0629 \u0648\u0627\u062d\u062f\u0629",two:"\u0642\u0628\u0644 \u062f\u0642\u064a\u0642\u062a\u064a\u0646",few:"\u0642\u0628\u0644 {0} \u062f\u0642\u0627\u0626\u0642",many:"\u0642\u0628\u0644 {0} \u062f\u0642\u064a\u0642\u0629",other:"\u0642\u0628\u0644 {0} \u062f\u0642\u064a\u0642\u0629"}}},second:{displayName:"\u0627\u0644\u062b\u0648\u0627\u0646\u064a",relative:{0:"\u0627\u0644\u0622\u0646"},relativeTime:{future:{zero:"\u062e\u0644\u0627\u0644 {0} \u062b\u0627\u0646\u064a\u0629",one:"\u062e\u0644\u0627\u0644 \u062b\u0627\u0646\u064a\u0629 \u0648\u0627\u062d\u062f\u0629",two:"\u062e\u0644\u0627\u0644 \u062b\u0627\u0646\u064a\u062a\u064a\u0646",few:"\u062e\u0644\u0627\u0644 {0} \u062b\u0648\u0627\u0646\u064d",many:"\u062e\u0644\u0627\u0644 {0} \u062b\u0627\u0646\u064a\u0629",other:"\u062e\u0644\u0627\u0644 {0} \u062b\u0627\u0646\u064a\u0629"},past:{zero:"\u0642\u0628\u0644 {0} \u062b\u0627\u0646\u064a\u0629",one:"\u0642\u0628\u0644 \u062b\u0627\u0646\u064a\u0629 \u0648\u0627\u062d\u062f\u0629",two:"\u0642\u0628\u0644 \u062b\u0627\u0646\u064a\u062a\u064a\u0646",few:"\u0642\u0628\u0644 {0} \u062b\u0648\u0627\u0646\u0650",many:"\u0642\u0628\u0644 {0} \u062b\u0627\u0646\u064a\u0629",other:"\u0642\u0628\u0644 {0} \u062b\u0627\u0646\u064a\u0629"}}}}},{locale:"ar-BH",parentLocale:"ar"},{locale:"ar-DJ",parentLocale:"ar"},{locale:"ar-DZ",parentLocale:"ar"},{locale:"ar-EG",parentLocale:"ar"},{locale:"ar-EH",parentLocale:"ar"},{locale:"ar-ER",parentLocale:"ar"},{locale:"ar-IL",parentLocale:"ar"},{locale:"ar-IQ",parentLocale:"ar"},{locale:"ar-JO",parentLocale:"ar"},{locale:"ar-KM",parentLocale:"ar"},{locale:"ar-KW",parentLocale:"ar"},{locale:"ar-LB",parentLocale:"ar"},{locale:"ar-LY",parentLocale:"ar"},{locale:"ar-MA",parentLocale:"ar"},{locale:"ar-MR",parentLocale:"ar"},{locale:"ar-OM",parentLocale:"ar"},{locale:"ar-PS",parentLocale:"ar"},{locale:"ar-QA",parentLocale:"ar"},{locale:"ar-SA",parentLocale:"ar"},{locale:"ar-SD",parentLocale:"ar"},{locale:"ar-SO",parentLocale:"ar"},{locale:"ar-SS",parentLocale:"ar"},{locale:"ar-SY",parentLocale:"ar"},{locale:"ar-TD",parentLocale:"ar"},{locale:"ar-TN",parentLocale:"ar"},{locale:"ar-YE",parentLocale:"ar"}]})}});