webpackJsonppanneau([173],{533:function(e,t){ace.define("ace/mode/latex_highlight_rules",["require","exports","module","ace/lib/oop","ace/mode/text_highlight_rules"],function(e,t,r){"use strict";var n=e("../lib/oop"),a=e("./text_highlight_rules").TextHighlightRules,o=function(){this.$rules={start:[{token:"comment",regex:"%.*$"},{token:["keyword","lparen","variable.parameter","rparen","lparen","storage.type","rparen"],regex:"(\\\\(?:documentclass|usepackage|input))(?:(\\[)([^\\]]*)(\\]))?({)([^}]*)(})"},{token:["keyword","lparen","variable.parameter","rparen"],regex:"(\\\\(?:label|v?ref|cite(?:[^{]*)))(?:({)([^}]*)(}))?"},{token:["storage.type","lparen","variable.parameter","rparen"],regex:"(\\\\(?:begin|end))({)(\\w*)(})"},{token:"storage.type",regex:"\\\\[a-zA-Z]+"},{token:"lparen",regex:"[[({]"},{token:"rparen",regex:"[\\])}]"},{token:"constant.character.escape",regex:"\\\\[^a-zA-Z]?"},{token:"string",regex:"\\${1,2}",next:"equation"}],equation:[{token:"comment",regex:"%.*$"},{token:"string",regex:"\\${1,2}",next:"start"},{token:"constant.character.escape",regex:"\\\\(?:[^a-zA-Z]|[a-zA-Z]+)"},{token:"error",regex:"^\\s*$",next:"start"},{defaultToken:"string"}]}};n.inherits(o,a),t.LatexHighlightRules=o}),ace.define("ace/mode/folding/latex",["require","exports","module","ace/lib/oop","ace/mode/folding/fold_mode","ace/range","ace/token_iterator"],function(e,t,r){"use strict";var n=e("../../lib/oop"),a=e("./fold_mode").FoldMode,o=e("../../range").Range,i=e("../../token_iterator").TokenIterator,l=t.FoldMode=function(){};n.inherits(l,a),function(){this.foldingStartMarker=/^\s*\\(begin)|(section|subsection|paragraph)\b|{\s*$/,this.foldingStopMarker=/^\s*\\(end)\b|^\s*}/,this.getFoldWidgetRange=function(e,t,r){var n=e.doc.getLine(r),a=this.foldingStartMarker.exec(n);if(a)return a[1]?this.latexBlock(e,r,a[0].length-1):a[2]?this.latexSection(e,r,a[0].length-1):this.openingBracketBlock(e,"{",r,a.index);var a=this.foldingStopMarker.exec(n);return a?a[1]?this.latexBlock(e,r,a[0].length-1):this.closingBracketBlock(e,"}",r,a.index+a[0].length):void 0},this.latexBlock=function(e,t,r){var n={"\\begin":1,"\\end":-1},a=new i(e,t,r),l=a.getCurrentToken();if(l&&("storage.type"==l.type||"constant.character.escape"==l.type)){var s=l.value,g=n[s],c=function(){var e=a.stepForward(),t="lparen"==e.type?a.stepForward().value:"";return-1===g&&(a.stepBackward(),t&&a.stepBackward()),t},p=[c()],h=-1===g?a.getCurrentTokenColumn():e.getLine(t).length,u=t;for(a.step=-1===g?a.stepBackward:a.stepForward;l=a.step();)if(l&&("storage.type"==l.type||"constant.character.escape"==l.type)){var d=n[l.value];if(d){var f=c();if(d===g)p.unshift(f);else if(p.shift()!==f||!p.length)break}}if(!p.length){var t=a.getCurrentTokenRow();return-1===g?new o(t,e.getLine(t).length,u,h):(a.stepBackward(),new o(u,h,t,a.getCurrentTokenColumn()))}}},this.latexSection=function(e,t,r){var n=["\\subsection","\\section","\\begin","\\end","\\paragraph"],a=new i(e,t,r),l=a.getCurrentToken();if(l&&"storage.type"==l.type){for(var s=n.indexOf(l.value),g=0,c=t;l=a.stepForward();)if("storage.type"===l.type){var p=n.indexOf(l.value);if(p>=2){if(g||(c=a.getCurrentTokenRow()-1),(g+=2==p?1:-1)<0)break}else if(p>=s)break}for(g||(c=a.getCurrentTokenRow()-1);c>t&&!/\S/.test(e.getLine(c));)c--;return new o(t,e.getLine(t).length,c,e.getLine(c).length)}}}.call(l.prototype)}),ace.define("ace/mode/latex",["require","exports","module","ace/lib/oop","ace/mode/text","ace/mode/latex_highlight_rules","ace/mode/folding/latex"],function(e,t,r){"use strict";var n=e("../lib/oop"),a=e("./text").Mode,o=e("./latex_highlight_rules").LatexHighlightRules,i=e("./folding/latex").FoldMode,l=function(){this.HighlightRules=o,this.foldingRules=new i,this.$behaviour=this.$defaultBehaviour};n.inherits(l,a),function(){this.type="text",this.lineCommentStart="%",this.$id="ace/mode/latex"}.call(l.prototype),t.Mode=l})}});